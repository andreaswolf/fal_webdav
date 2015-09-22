<?php
namespace TYPO3\FalWebdav\Dav;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Sabre\DAV;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\FalWebdav\Utility\UrlTools;


/**
 * Utility class for doing DAV requests to the server and decoding the results into a form usable by the driver
 *
 * All identifiers within this class are relative to the DAV storage’s base path and thus have no slash at the
 * beginning (unlike FAL identifiers, which are always prepended with a slash).
 */
class WebDavFrontend {

	/**
	 * @var WebDavClient
	 */
	protected $davClient;

	/**
	 * The storage base URL, has to be already URL-encoded
	 *
	 * @var string
	 */
	protected $baseUrl;

	/**
	 * @var string
	 */
	protected $basePath;

	/**
	 * @var \TYPO3\CMS\Core\Log\Logger
	 */
	protected $logger;

	/**
	 * @var int
	 */
	protected $storageUid;


	public function __construct(WebDavClient $client, $baseUrl, $storageUid) {
		$this->logger = GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);

		$this->davClient = $client;
		$this->baseUrl = rtrim($baseUrl, '/') . '/';
		$urlParts = parse_url($this->baseUrl);
		$this->basePath = $urlParts['path'];
		$this->storageUid = $storageUid;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	protected function encodeUrl($url) {
		$urlParts = parse_url($url);
		$urlParts['path'] = $this->urlEncodePath($urlParts['path']);

		return HttpUtility::buildUrl($urlParts);
	}

	/**
	 * Wrapper around the PROPFIND method of the WebDAV client to get proper local error handling.
	 *
	 * @param string $path
	 * @return array
	 * @throws DAV\Exception\NotFound if the given URL does not hold a resource
	 *
	 * TODO define proper error handling for other cases
	 */
	public function propFind($path) {
		$url = $this->baseUrl . $path;
		$encodedUrl = $this->encodeUrl($url);

		try {
			// propfind() already decodes the XML, so we get back an array
			$propfindResultArray = $this->davClient->propfind($encodedUrl, NULL, 1);

			// the returned items are indexed by their key, so sort them here to return the correct items.
			// At least Apache does not sort them before returning
			uksort($propfindResultArray, 'strnatcasecmp');

			return $propfindResultArray;
		} catch (DAV\Exception\NotFound $exception) {
			$this->logger->warning('URL not found: ' . $url);
			// If a file is not found, we have to deal with that on a higher level, so throw the exception again
			throw $exception;
		} catch (DAV\Exception $exception) {
			// log all other exceptions
			$this->logger->error(sprintf(
				'Error while executing DAV PROPFIND request. Original message: "%s" (Exception %s, id: %u)',
				$exception->getMessage(), get_class($exception), $exception->getCode()
			));
			// TODO check how we can let this propagate to the driver
			return array();
		}
	}

	/**
	 *
	 * @param string $path
	 * @return array A list of file names in the path
	 */
	public function listFiles($path) {
		$files = $this->listItems($path, function ($currentItem) {
			if (substr($currentItem, -1) == '/') {
				return FALSE;
			}
			return TRUE;
		});

		return $files;
	}

	/**
	 * @param string $path
	 * @return array A list of folder names in the path
	 */
	public function listFolders($path) {
		$folders = $this->listItems($path, function ($currentItem) {
			if (substr($currentItem, -1) != '/') {
				return FALSE;
			}
			return TRUE;
		});

		return $folders;
	}

	protected function listItems($path, $itemFilterCallback) {
		$path = trim($path, '/');
		if (strlen($path) > 0) {
			$path .= '/';
		}
		$urlParts = parse_url($this->baseUrl . $path);
		$unencodedBasePath = $urlParts['path'];

		$result = $this->propFind($path);

		// remove first entry, as it is the folder itself
		array_shift($result);

		$files = array();
		// $filePath contains the path part of the URL, no server name and protocol!
		foreach ($result as $filePath => $fileInfo) {
			$decodedFilePath = urldecode($filePath);
			$decodedFilePath = substr($decodedFilePath, strlen($unencodedBasePath));
			// ignore folder entries
			if (!$itemFilterCallback($decodedFilePath)) {
				continue;
			}

			// TODO if depth is > 1, we will also include deeper entries here, which we should not

			$files[] = basename($decodedFilePath);
		}

		return $files;
	}

	/**
	 * Returns information about the given file.
	 *
	 * TODO define what to return
	 *
	 * @param string $path
	 * @return array
	 */
	public function getFileInfo($path) {
		// the leading slash is already included in baseURL/basePath
		$path = ltrim($path, '/');

		$result = $this->propFind($path);

		return $this->extractFileInfo($path, $result[$this->basePath . $this->urlEncodePath($path)]);
	}

	protected function extractFileInfo($path, $propFindArray) {
		$fileInfo = array(
			'mtime' => (int)strtotime($propFindArray['{DAV:}getlastmodified']),
			'ctime' => (int)strtotime($propFindArray['{DAV:}creationdate']),
			'mimetype' => (string)$propFindArray['{DAV:}getcontenttype'],
			'name' => basename($path),
			'size' => (int)$propFindArray['{DAV:}getcontentlength'],
			'identifier' => '/' . $path,
			'storage' => $this->storageUid,
			'identifier_hash' => sha1('/' . $path),
			'folder_hash' => sha1('/' . $this->getFolderPathFromIdentifier($path)),
		);

		return $fileInfo;
	}

	/**
	 * @param string $path The identifier, without a leading slash!
	 * @return string The folder path, without a trailing slash. If the file is on root level, an empty string is returned
	 */
	protected function getFolderPathFromIdentifier($path) {
		$dirPath = dirname($path);

		return $dirPath . ($dirPath !== '') ? '/' : '';
	}

	/**
	 * @param $path
	 * @return string
	 */
	protected function urlEncodePath($path) {
		// using urlencode() does not work because it encodes a space as "+" and not as "%20".
		return implode('/', array_map('rawurlencode', explode('/', $path)));
	}

}
