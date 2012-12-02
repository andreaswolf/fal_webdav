<?php
namespace TYPO3\FalWebdav\Driver;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Andreas Wolf
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
include_once 'Sabre/autoload.php';

use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * The driver class for WebDAV storages.
 */
class WebDavDriver extends \TYPO3\CMS\Core\Resource\Driver\AbstractDriver {

	/**
	 * The base URL of the WebDAV share. Always ends with a trailing slash.
	 *
	 * @var string
	 */
	protected $baseUrl = '';

	/**
	 * The base path of the WebDAV store. This is the URL without protocol, host and port (i.e., only the path on the host).
	 * Always ends with a trailing slash.
	 *
	 * @var string
	 */
	protected $basePath = '';

	/**
	 * @var \Sabre_DAV_Client
	 */
	protected $davClient;

	/**
	 * The username to use for connecting to the storage.
	 *
	 * @var string
	 */
	protected $username = '';

	/**
	 * The password to use for connecting to the storage.
	 *
	 * @var string
	 */
	protected $password = '';

	/**
	 * @var \TYPO3\CMS\Core\Cache\Frontend\AbstractFrontend
	 */
	protected $directoryListingCache;

	/**
	 * @var \TYPO3\CMS\Core\Log\Logger
	 */
	protected $logger;

	public function __construct(array $configuration = array()) {
		$this->directoryListingCache = $GLOBALS['typo3CacheManager']->getCache('tx_falwebdav_directorylisting');

		$this->logger = GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);

		parent::__construct($configuration);
	}

	/**
	 * Initializes this object. This is called by the storage after the driver has been attached.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->capabilities = \TYPO3\CMS\Core\Resource\ResourceStorage::CAPABILITY_BROWSABLE
			+ \TYPO3\CMS\Core\Resource\ResourceStorage::CAPABILITY_PUBLIC
			+ \TYPO3\CMS\Core\Resource\ResourceStorage::CAPABILITY_WRITABLE;
	}

	/**
	 * Inject method for the DAV client. Mostly useful for unit tests.
	 *
	 * @param \Sabre_DAV_Client $client
	 */
	public function injectDavClient(\Sabre_DAV_Client $client) {
		$this->davClient = $client;
	}

	/**
	 * Processes the configuration coming from the storage record and prepares the SabreDAV object.
	 *
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	public function processConfiguration() {
		foreach ($this->configuration as $key => $value) {
			$this->configuration[$key] = trim($value);
		}

		$baseUrl = $this->configuration['baseUrl'];

		$urlInfo = parse_url($baseUrl);
		if ($urlInfo === FALSE) {
			throw new \InvalidArgumentException('Invalid base URL configured for WebDAV driver: ' . $this->configuration['baseUrl'], 1325771040);
		}
		$this->basePath = rtrim($urlInfo['path'], '/') . '/';

		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fal_webdav']);
		$configuration['enableZeroByteFilesIndexing'] = (boolean)$extConf['enableZeroByteFilesIndexing'];

		// Use authentication only if enabled
		$settings = array();
		if ($this->configuration['useAuthentication']) {
			$this->username = $urlInfo['user'] ? $urlInfo['user'] : $this->configuration['username'];
			$this->password = $urlInfo['pass'] ? $urlInfo['pass'] : \TYPO3\FalWebdav\Utility\EncryptionUtility::decryptPassword($this->configuration['password']);
			$settings = array(
				'userName' => $this->username,
				'password' => $this->password
			);
		}

		// create cleaned URL without credentials
		unset($urlInfo['user']);
		unset($urlInfo['pass']);
		$this->baseUrl = rtrim(\TYPO3\CMS\Core\Utility\HttpUtility::buildUrl($urlInfo), '/') . '/';
		$settings['baseUri'] = $this->baseUrl;

		$this->davClient = new \Sabre_DAV_Client($settings);
	}

	/**
	 * Checks if a configuration is valid for this driver.
	 *
	 * Throws an exception if a configuration will not work.
	 *
	 * @param array $configuration
	 * @return void
	 */
	public static function verifyConfiguration(array $configuration) {
		// TODO: Implement verifyConfiguration() method.
	}

	/**
	 * Executes a MOVE request from $oldPath to $newPath.
	 *
	 * @param string $oldPath
	 * @param string $newPath
	 * @return array The result as returned by SabreDAV
	 */
	public function executeMoveRequest($oldPath, $newPath) {
		$oldUrl = $this->baseUrl . ltrim($oldPath, '/');
		$newUrl = $this->baseUrl . ltrim($newPath, '/');

			// force overwriting the file (header Overwrite: T) because the Storage already handled possible conflicts
			// for us
		return $this->executeDavRequest('MOVE', $oldUrl, NULL, array('Destination' => $newUrl, 'Overwrite' => 'T'));
	}

	/**
	 * Executes a request on the DAV driver.
	 *
	 * @param string $method
	 * @param string $url
	 * @param string $body
	 * @param array $headers
	 * @return array
	 */
	protected function executeDavRequest($method, $url, $body = NULL, array $headers = array()) {
		try {
			return $this->davClient->request($method, $url, $body, $headers);
		} catch (\Sabre_DAV_Exception_NotFound $exception) {
			// If a file is not found, we have to deal with that on a higher level, so throw the exception again
			throw $exception;
		} catch (\Sabre_DAV_Exception $exception) {
			// log all other exceptions
			$this->logger->error(sprintf(
				'Error while executing DAV request. Original message: "%s" (Exception %s, id: %u)',
				$exception->getMessage(), get_class($exception), $exception->getCode()
			));
			$this->storage->markAsTemporaryOffline();
			return array();
		}
	}



	/**
	 * Executes a PROPFIND request on the given URL and returns the result array
	 *
	 * @param string $url
	 * @return array
	 */
	protected function davPropFind($url) {
		try {
			return $this->davClient->propfind($url, array(
				'{DAV:}resourcetype',
				'{DAV:}creationdate',
				'{DAV:}getcontentlength',
				'{DAV:}getlastmodified'
			), 1);
		} catch (\Sabre_DAV_Exception_NotFound $exception) {
			// If a file is not found, we have to deal with that on a higher level, so throw the exception again
			throw $exception;
		} catch (\Sabre_DAV_Exception $exception) {
			// log all other exceptions
			$this->logger->error(sprintf(
				'Error while executing DAV PROPFIND request. Original message: "%s" (Exception %s, id: %u)',
				$exception->getMessage(), get_class($exception), $exception->getCode()
			));
			$this->storage->markAsTemporaryOffline();
			return array();
		}
	}

	/**
	 * Checks if a given resource exists in this DAV share.
	 *
	 * @param string $resourcePath The path to the resource, i.e. a regular identifier as used everywhere else here.
	 * @return bool
	 * @throws \InvalidArgumentException
	 */
	public function resourceExists($resourcePath) {
		if ($resourcePath == '') {
			throw new \InvalidArgumentException('Resource path cannot be empty');
		}
		$url = $this->baseUrl . ltrim($resourcePath, '/');
		try {
			$this->executeDavRequest('HEAD', $url);
		} catch (\Sabre_DAV_Exception_NotFound $exception) {
			return FALSE;
		}
		// TODO check if other status codes may also indicate that the file is present
		return TRUE;
	}


	/**
	 * Returns the complete URL to a file. This is not necessarily the publicly available URL!
	 *
	 * @param string|\TYPO3\CMS\Core\Resource\FileInterface|\TYPO3\CMS\Core\Resource\Folder $file The file object or its identifier
	 * @return string
	 */
	protected function getResourceUrl($file) {
		if (is_object($file)) {
			return $this->baseUrl . ltrim($file->getIdentifier(), '/');
		} else {
			return $this->baseUrl . ltrim($file, '/');
		}
	}

	/**
	 * Returns the public URL to a file.
	 *
	 * @param \TYPO3\CMS\Core\Resource\ResourceInterface $resource
	 * @param bool  $relativeToCurrentScript    Determines whether the URL returned should be relative to the current script, in case it is relative at all (only for the LocalDriver)
	 * @return string
	 */
	public function getPublicUrl(\TYPO3\CMS\Core\Resource\ResourceInterface $resource, $relativeToCurrentScript = FALSE) {
		if ($this->storage->isPublic()) {
				// as the storage is marked as public, we can simply use the public URL here.
			return $this->getResourceUrl($resource);
		}
	}

	/**
	 * Creates a (cryptographic) hash for a file.
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @param string $hashAlgorithm The hash algorithm to use
	 * @return string
	 * TODO switch parameter order?
	 */
	public function hash(\TYPO3\CMS\Core\Resource\FileInterface $file, $hashAlgorithm) {
		// TODO add unit test
		$fileCopy = $this->copyFileToTemporaryPath($file);

		switch ($hashAlgorithm) {
			case 'sha1':
				return sha1_file($fileCopy);
				break;
		}

		unlink($fileCopy);
	}

	/**
	 * Creates a new file and returns the matching file object for it.
	 *
	 * @param string $fileName
	 * @param \TYPO3\CMS\Core\Resource\Folder $parentFolder
	 * @return \TYPO3\CMS\Core\Resource\FileInterface
	 */
	public function createFile($fileName, \TYPO3\CMS\Core\Resource\Folder $parentFolder) {
		$fileIdentifier = $parentFolder->getIdentifier() . $fileName;
		$fileUrl = $this->baseUrl . ltrim($fileIdentifier, '/');

		$this->executeDavRequest('PUT', $fileUrl, '');

		$this->removeCacheForPath($parentFolder->getIdentifier());

		return $this->getFile($fileIdentifier);
	}

	/**
	 * Returns the contents of a file. Beware that this requires to load the complete file into memory and also may
	 * require fetching the file from an external location. So this might be an expensive operation (both in terms of
	 * processing resources and money) for large files.
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @return string The file contents
	 */
	public function getFileContents(\TYPO3\CMS\Core\Resource\FileInterface $file) {
		$fileUrl = $this->baseUrl . ltrim($file->getIdentifier(), '/');

		$result = $this->executeDavRequest('GET', $fileUrl);

		return $result['body'];
	}

	/**
	 * Sets the contents of a file to the specified value.
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @param string $contents
	 * @return bool TRUE if setting the contents succeeded
	 * @throws \RuntimeException if the operation failed
	 */
	public function setFileContents(\TYPO3\CMS\Core\Resource\FileInterface $file, $contents) {
		// Apache returns a "204 no content" status after a successful put operation

		$fileUrl = $this->getResourceUrl($file);
		$result = $this->executeDavRequest('PUT', $fileUrl, $contents);

		$this->removeCacheForPath(dirname($file->getIdentifier()));

		// TODO check result
	}

	/**
	 * Adds a file from the local server hard disk to a given path in TYPO3s virtual file system.
	 *
	 * This assumes that the local file exists, so no further check is done here!
	 *
	 * @param string $localFilePath
	 * @param \TYPO3\CMS\Core\Resource\Folder $targetFolder
	 * @param string $fileName The name to add the file under
	 * @param \TYPO3\CMS\Core\Resource\AbstractFile $updateFileObject File object to update (instead of creating a new object). With this parameter, this function can be used to "populate" a dummy file object with a real file underneath.
	 * @return \TYPO3\CMS\Core\Resource\FileInterface
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
	 * @throws \RuntimeException
	 */
	public function addFile($localFilePath, \TYPO3\CMS\Core\Resource\Folder $targetFolder, $fileName, \TYPO3\CMS\Core\Resource\AbstractFile $updateFileObject = NULL) {
		$fileIdentifier = $targetFolder->getIdentifier() . $fileName;
		$fileUrl = $this->baseUrl . ltrim($fileIdentifier);

		$fileHandle = fopen($localFilePath, 'r');
		if (!is_resource($fileHandle)) {
			throw new \RuntimeException('Could not open handle for ' . $localFilePath, 1325959310);
		}
		$result = $this->executeDavRequest('PUT', $fileUrl, $fileHandle);

		// TODO check result

		$this->removeCacheForPath($targetFolder->getIdentifier());

		return $this->getFile($fileIdentifier);
	}

	/**
	 * Checks if a file exists.
	 *
	 * @param string $identifier
	 * @return bool
	 */
	public function fileExists($identifier) {
		return substr($identifier, -1) !== '/' && $this->resourceExists($identifier);
	}

	/**
	 * Checks if a file inside a storage folder exists.
	 *
	 * @param string $fileName
	 * @param \TYPO3\CMS\Core\Resource\Folder $folder
	 * @return boolean
	 */
	public function fileExistsInFolder($fileName, \TYPO3\CMS\Core\Resource\Folder $folder) {
		// TODO add unit test
		$fileIdentifier = $folder->getIdentifier() . $fileName;

		return $this->fileExists($fileIdentifier);
	}

	/**
	 * Returns a (local copy of) a file for processing it. When changing the file, you have to take care of replacing the
	 * current version yourself!
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @param bool $writable Set this to FALSE if you only need the file for read operations. This might speed up things, e.g. by using a cached local version. Never modify the file if you have set this flag!
	 * @return string The path to the file on the local disk
	 */
	public function getFileForLocalProcessing(\TYPO3\CMS\Core\Resource\FileInterface $file, $writable = TRUE) {
		return $this->copyFileToTemporaryPath($file);
	}

	/**
	 * Returns the permissions of a file as an array (keys r, w) of boolean flags
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @return array
	 */
	public function getFilePermissions(\TYPO3\CMS\Core\Resource\FileInterface $file) {
		return array('r' => TRUE, 'w' => TRUE);
	}

	/**
	 * Returns the permissions of a folder as an array (keys r, w) of boolean flags
	 *
	 * @param \TYPO3\CMS\Core\Resource\Folder $folder
	 * @return array
	 */
	public function getFolderPermissions(\TYPO3\CMS\Core\Resource\Folder $folder) {
		return array('r' => TRUE, 'w' => TRUE);
	}

	/**
	 * Renames a file
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @param string $newName
	 * @return string The new identifier of the file
	 */
	public function renameFile(\TYPO3\CMS\Core\Resource\FileInterface $file, $newName) {
		// TODO add unit test
		// Renaming works by invoking the MOVE method on the source URL and providing the new destination in the
		// "Destination:" HTTP header.
		$sourcePath = $file->getIdentifier();
		$targetPath = dirname($file->getIdentifier()) . '/' . $newName;

		$this->executeMoveRequest($sourcePath, $targetPath);

		$this->removeCacheForPath(dirname($file->getIdentifier()));

		return $targetPath;
	}

	/**
	 * Replaces the contents (and file-specific metadata) of a file object with a local file.
	 *
	 * @param \TYPO3\CMS\Core\Resource\AbstractFile $file
	 * @param string $localFilePath
	 * @return bool
	 * @throws \RuntimeException
	 */
	public function replaceFile(\TYPO3\CMS\Core\Resource\AbstractFile $file, $localFilePath) {
		$fileUrl = $this->getResourceUrl($file);
		$fileHandle = fopen($localFilePath, 'r');
		if (!is_resource($fileHandle)) {
			throw new \RuntimeException('Could not open handle for ' . $localFilePath, 1325959311);
		}

		$this->removeCacheForPath(dirname($file->getIdentifier()));

		$this->executeDavRequest('PUT', $fileUrl, $fileHandle);
	}

	/**
	 * Returns information about a file for a given file identifier.
	 *
	 * @param string $identifier The (relative) path to the file.
	 * @return array
	 */
	public function getFileInfoByIdentifier($identifier) {
		$fileUrl = $this->baseUrl . ltrim($identifier, '/');

		try {
			$properties = $this->executeDavRequest('PROPFIND', $fileUrl);
			$properties = $this->davClient->parseMultiStatus($properties['body']);
			$properties = $properties[$this->basePath . ltrim($identifier, '/')][200];

			// TODO make this more robust (check if properties are available etc.)
			$fileInfo = array(
				'mtime' => strtotime($properties['{DAV:}getlastmodified']),
				'ctime' => strtotime($properties['{DAV:}creationdate']),
				'mimetype' => $properties['{DAV:}getcontenttype'],
				'name' => basename($identifier),
				'size' => $properties['{DAV:}getcontentlength'],
				'identifier' => $identifier,
				'storage' => $this->storage->getUid()
			);
		} catch (\Sabre_DAV_Exception $exception) {
			$fileInfo = array(
				'name' => basename($identifier),
				'identifier' => $identifier,
				'storage' => $this->storage->getUid()
			);
		}

		return $fileInfo;
	}

	/**
	 * Returns a list of files inside the specified path
	 *
	 * @param string $path
	 * @param integer $start The position to start the listing; if not set, start from the beginning
	 * @param integer $numberOfItems The number of items to list; if not set, return all items
	 * @param array $filenameFilterCallbacks Callback methods used for filtering the file list.
	 * @param array $fileData Two-dimensional, identifier-indexed array of file index records from the database
	 * @return array
	 */
	// TODO add unit tests
	public function getFileList($path, $start = 0, $numberOfItems = 0, array $filenameFilterCallbacks = array(), $fileData = array()) {
		return $this->getDirectoryItemList($path, $start, $numberOfItems, $filenameFilterCallbacks, 'getFileList_itemCallback');
	}

	/**
	 * Returns a list of all folders in a given path
	 *
	 * @param string $path
	 * @param integer $start The position to start the listing; if not set, start from the beginning
	 * @param integer $numberOfItems The number of items to list; if not set, return all items
	 * @param array $foldernameFilterCallbacks Callback methods used for filtering the file list.
	 * @return array
	 */
	public function getFolderList($path, $start = 0, $numberOfItems = 0, array $foldernameFilterCallbacks = array()) {
		return $this->getDirectoryItemList($path, $start, $numberOfItems, $foldernameFilterCallbacks, 'getFolderList_itemCallback');
	}

	/**
	 * Returns a folder within the given folder. Use this method instead of doing your own string manipulation magic
	 * on the identifiers because non-hierarchical storages might fail otherwise.
	 *
	 * @param $name
	 * @param \TYPO3\CMS\Core\Resource\Folder $parentFolder
	 * @return \TYPO3\CMS\Core\Resource\Folder
	 */
	public function getFolderInFolder($name, \TYPO3\CMS\Core\Resource\Folder $parentFolder) {
		$folderIdentifier = $parentFolder->getIdentifier() . $name . '/';
		return $this->getFolder($folderIdentifier);
	}

	/**
	 * Generic handler method for directory listings - gluing together the listing items is done
	 *
	 * @param string $path
	 * @param integer $start
	 * @param integer $numberOfItems
	 * @param array $filterMethods
	 * @param callable $itemHandlerMethod
	 * @return array
	 */
	// TODO implement pre-loaded array rows
	protected function getDirectoryItemList($path, $start, $numberOfItems, $filterMethods, $itemHandlerMethod) {
		$path = ltrim($path, '/');
		$url = $this->baseUrl . $path;
			// the full (web) path to the current folder on the web server
		$basePath = $this->basePath . ltrim($path, '/');

			// Try to fetch the raw server response for the given path from our cache. We cache the raw response -
			// although it might be a bit larger than the processed result - because we mainly do the caching to avoid
			// the costly server calls - and we might save the most time and load when having the next pages already at
			// hand for a file browser or the like.
		$cacheKey = $this->getCacheIdentifierForPath($path);
		if (!$properties = $this->directoryListingCache->get($cacheKey)) {
			$properties = $this->davPropFind($url);

			// the returned items are indexed by their key, so sort them here to return the correct items.
			// At least Apache does not sort them before returning
			uksort($properties, 'strnatcasecmp');

			// TODO set cache lifetime
			$this->directoryListingCache->set($cacheKey, $properties);
		}

		// if we have only one entry, this is the folder we are currently in, so there are no items -> return an empty array
		if (count($properties) == 1) {
			return array();
		}

		$propertyIterator = new \ArrayIterator($properties);

		// TODO handle errors

		if ($path !== '' && $path != '/') {
			$path = '/' . trim($path, '/') . '/';
		}

		$c = $numberOfItems > 0 ? $numberOfItems : $propertyIterator->count();
		$propertyIterator->seek($start);

		$items = array();
		while ($propertyIterator->valid() && $c > 0) {
			$item = $propertyIterator->current();
				// the full (web) path to the current item on the server
			$filePath = $propertyIterator->key();
			$itemName = substr($filePath, strlen($basePath));
			$propertyIterator->next();

			if ($this->applyFilterMethodsToDirectoryItem($filterMethods, $itemName, $filePath, $basePath, array('item' => $item)) === FALSE) {
				continue;
			}

			list($key, $entry) = $this->$itemHandlerMethod($item, $filePath, $basePath, $path);

			if (empty($entry)) {
				continue;
			}

			$items[$key] = $entry;

			--$c;
		}

		return $items;
	}

	/**
	 * Returns the cache identifier for a given path.
	 *
	 * @param string $path
	 * @return string
	 */
	protected function getCacheIdentifierForPath($path) {
		return sha1($this->storage->getUid() . ':' . trim($path, '/') . '/');
	}

	/**
	 * Flushes the cache for a given path inside this storage.
	 *
	 * @param $path
	 * @return void
	 */
	protected function removeCacheForPath($path) {
		$this->directoryListingCache->remove($this->getCacheIdentifierForPath($path));
	}

	/**
	 * Callback method that extracts file information from a single entry inside a DAV PROPFIND response. Called by getDirectoryItemList.
	 *
	 * @param array $item The information about the item as fetched from the server
	 * @param string $filePath The full path to the item
	 * @param string $basePath The path of the queried folder
	 * @param string $path The queried path (inside the WebDAV storage)
	 * @return array
	 */
	protected function getFileList_itemCallback(array $item, $filePath, $basePath, $path) {
		if ($item['{DAV:}resourcetype']->is('{DAV:}collection')) {
			return array('', array());
		}
		$fileName = substr($filePath, strlen($basePath));

			// check if the zero bytes should not be indexed
		if ($this->configuration['enableZeroByteFilesIndexing'] === FALSE && $item['{DAV:}getcontentlength'] == 0) {
			return array('', array());
		}

			// TODO add more information
		return array($fileName, array(
			'name' => $fileName,
			'identifier' => $path . $fileName,
			'creationDate' => strtotime($item['{DAV:}creationdate']),
			'storage' => $this->storage->getUid()
		));
	}

	/**
	 * Callback method that extracts folder information from a single entry inside a DAV PROPFIND response. Called by getDirectoryItemList.
	 *
	 * @param array $item The information about the item as fetched from the server
	 * @param string $filePath The full path to the item
	 * @param string $basePath The path of the queried folder
	 * @param string $path The queried path (inside the WebDAV storage)
	 * @return array
	 */
	protected function getFolderList_itemCallback(array $item, $filePath, $basePath, $path) {
		if (!$item['{DAV:}resourcetype']->is('{DAV:}collection')) {
			return array('', array());
		}
		$folderName = trim(substr($filePath, strlen($basePath)), '/');

		if ($folderName == '') {
			return array('', array());
		}

			// TODO add more information
		return array($folderName, array(
			'name' => $folderName,
			'identifier' => $path . trim($folderName, '/') . '/',
			'creationDate' => strtotime($item['{DAV:}creationdate']),
			'storage' => $this->storage->getUid()
		));
	}

	/**
	 * Copies a file to a temporary path and returns that path. You have to take care of removing the temporary file yourself!
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @return string The temporary path
	 */
	public function copyFileToTemporaryPath(\TYPO3\CMS\Core\Resource\FileInterface $file) {
		$temporaryPath = \TYPO3\CMS\Core\Utility\GeneralUtility::tempnam('vfs-tempfile-');
		$fileUrl = $this->getResourceUrl($file);

		$result = $this->executeDavRequest('GET', $fileUrl);
		file_put_contents($temporaryPath, $result['body']);

		return $temporaryPath;
	}

	/**
	 * Moves a file *within* the current storage.
	 * Note that this is only about an intra-storage move action, where a file is just
	 * moved to another folder in the same storage.
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @param \TYPO3\CMS\Core\Resource\Folder $targetFolder
	 * @param string $fileName
	 * @return string The new identifier of the file
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException
	 */
	public function moveFileWithinStorage(\TYPO3\CMS\Core\Resource\FileInterface $file, \TYPO3\CMS\Core\Resource\Folder $targetFolder, $fileName) {
		$newPath = $targetFolder->getIdentifier() . $fileName;

		try {
			$result = $this->executeMoveRequest($file->getIdentifier(), $newPath);
		} catch (\Sabre_DAV_Exception $e) {
			// TODO insert correct exception here
			throw new \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException('Moving file ' . $file->getIdentifier()
				. ' to ' . $newPath . ' failed.', 1325848030);
		}
		// TODO check if there are some return codes that signalize an error, but do not throw an exception
		// status codes: 204: file was overwritten; 201: file was created;

		return $targetFolder->getIdentifier() . $fileName;
	}

	/**
	 * Copies a file *within* the current storage.
	 * Note that this is only about an intra-storage copy action, where a file is just
	 * copied to another folder in the same storage.
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @param \TYPO3\CMS\Core\Resource\Folder $targetFolder
	 * @param string $fileName
	 * @return \TYPO3\CMS\Core\Resource\FileInterface The new (copied) file object.
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException
	 */
	public function copyFileWithinStorage(\TYPO3\CMS\Core\Resource\FileInterface $file, \TYPO3\CMS\Core\Resource\Folder $targetFolder, $fileName) {
		$oldFileUrl = $this->getResourceUrl($file);
		$newFileUrl = $this->getResourceUrl($targetFolder) . $fileName;
		$newFileIdentifier = $targetFolder->getIdentifier() . $fileName;

		try {
				// force overwriting the file (header Overwrite: T) because the Storage already handled possible conflicts
				// for us
			$result = $this->executeDavRequest('COPY', $oldFileUrl, NULL, array('Destination' => $newFileUrl, 'Overwrite' => 'T'));
		} catch (\Sabre_DAV_Exception $e) {
			// TODO insert correct exception here
			throw new \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException('Copying file ' . $file->getIdentifier() . ' to '
				. $newFileIdentifier . ' failed.', 1325848030);
		}
		// TODO check if there are some return codes that signalize an error, but do not throw an exception
		// status codes: 204: file was overwritten; 201: file was created;

		return $this->getFile($newFileIdentifier);
	}

	/**
	 * Folder equivalent to moveFileWithinStorage().
	 *
	 * @param \TYPO3\CMS\Core\Resource\Folder $folderToMove
	 * @param \TYPO3\CMS\Core\Resource\Folder $targetFolder
	 * @param string $newFolderName
	 * @return array Mapping of old file identifiers to new ones
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException
	 */
	public function moveFolderWithinStorage(\TYPO3\CMS\Core\Resource\Folder $folderToMove, \TYPO3\CMS\Core\Resource\Folder $targetFolder,
	                                        $newFolderName) {
		$newFolderIdentifier = $targetFolder->getIdentifier() . $newFolderName . '/';

		try {
			$result = $this->executeMoveRequest($folderToMove->getIdentifier(), $newFolderIdentifier);
		} catch (\Sabre_DAV_Exception $e) {
			// TODO insert correct exception here
			throw new \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException('Moving folder ' . $folderToMove->getIdentifier()
				. ' to ' . $newFolderIdentifier . ' failed: ' . $e->getMessage(), 1326135944);
		}
		// TODO check if there are some return codes that signalize an error, but do not throw an exception
		// status codes: 204: file was overwritten; 201: file was created;

		// TODO extract mapping of old to new identifiers from server response
	}

	/**
	 * Folder equivalent to copyFileWithinStorage().
	 *
	 * @param \TYPO3\CMS\Core\Resource\Folder $folderToMove
	 * @param \TYPO3\CMS\Core\Resource\Folder $targetFolder
	 * @param string $newFolderName
	 * @return bool
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException
	 */
	public function copyFolderWithinStorage(\TYPO3\CMS\Core\Resource\Folder $folderToMove, \TYPO3\CMS\Core\Resource\Folder $targetFolder,
	                                        $newFolderName) {
		$oldFolderUrl = $this->getResourceUrl($folderToMove);
		$newFolderUrl = $this->getResourceUrl($targetFolder) . $newFolderName . '/';
		$newFolderIdentifier = $targetFolder->getIdentifier() . $newFolderName . '/';

		try {
			$result = $this->executeDavRequest('COPY', $oldFolderUrl, NULL, array('Destination' => $newFolderUrl, 'Overwrite' => 'T'));
		} catch (\Sabre_DAV_Exception $e) {
			// TODO insert correct exception here
			throw new \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException('Moving folder ' . $folderToMove->getIdentifier()
				. ' to ' . $newFolderIdentifier . ' failed.', 1326135944);
		}
		// TODO check if there are some return codes that signalize an error, but do not throw an exception
		// status codes: 204: file was overwritten; 201: file was created;

		return $newFolderIdentifier;
	}

	/**
	 * Removes a file from this storage. This does not check if the file is still used or if it is a bad idea to delete
	 * it for some other reason - this has to be taken care of in the upper layers (e.g. the Storage)!
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface $file
	 * @return boolean TRUE if the operation succeeded
	 */
	public function deleteFile(\TYPO3\CMS\Core\Resource\FileInterface $file) {
		// TODO add unit tests
		$fileUrl = $this->baseUrl . ltrim($file->getIdentifier(), '/');

		$result = $this->executeDavRequest('DELETE', $fileUrl);

		// 204 is derived from the answer Apache gives - there might be other status codes that indicate success
		return ($result['statusCode'] == 204);
	}

	/**
	 * Adds a file at the specified location. This should only be used internally.
	 *
	 * @param string $localFilePath
	 * @param \TYPO3\CMS\Core\Resource\Folder $targetFolder
	 * @param string $targetFileName
	 * @return string The new identifier of the file
	 */
	public function addFileRaw($localFilePath, \TYPO3\CMS\Core\Resource\Folder $targetFolder, $targetFileName) {
		return $this->addFile($localFilePath, $targetFolder, $targetFileName)->getIdentifier();
	}

	/**
	 * Deletes a file without access and usage checks. This should only be used internally.
	 *
	 * This accepts an identifier instead of an object because we might want to delete files that have no object
	 * associated with (or we don't want to create an object for) them - e.g. when moving a file to another storage.
	 *
	 * @param string $identifier
	 * @return bool TRUE if removing the file succeeded
	 */
	public function deleteFileRaw($identifier) {
		return $this->deleteFile($this->getFile($identifier));
	}

	/**
	 * Returns the root level folder of the storage.
	 *
	 * @return \TYPO3\CMS\Core\Resource\Folder
	 */
	public function getRootLevelFolder() {
		return $this->getFolder('/');
	}

	/**
	 * Returns the default folder new files should be put into.
	 *
	 * @return \TYPO3\CMS\Core\Resource\Folder
	 */
	public function getDefaultFolder() {
		return $this->getFolder('/');
	}

	/**
	 * Creates a folder.
	 *
	 * @param string $newFolderName
	 * @param \TYPO3\CMS\Core\Resource\Folder $parentFolder
	 * @return \TYPO3\CMS\Core\Resource\Folder The new (created) folder object
	 */
	public function createFolder($newFolderName, \TYPO3\CMS\Core\Resource\Folder $parentFolder) {
			// We add a slash to the path as some actions require a trailing slash on some servers.
			// Apache's mod_dav e.g. does not do it for this action, but it does not do harm either, so we add it anyways
		$folderPath = $parentFolder->getIdentifier() . $newFolderName . '/';
		$folderUrl = $this->baseUrl . ltrim($folderPath, '/');

		$this->executeDavRequest('MKCOL', $folderUrl);

		$this->removeCacheForPath($parentFolder->getIdentifier());

		/** @var $factory \TYPO3\CMS\Core\Resource\ResourceFactory */
		$factory = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Resource\ResourceFactory');
		return $factory->createFolderObject($this->storage, $folderPath, $newFolderName);
	}

	/**
	 * Checks if a folder exists
	 *
	 * @param string $identifier
	 * @return bool
	 */
	public function folderExists($identifier) {
		// TODO add unit test
		// TODO check if this test suffices to find out if the resource really is a folder - it might not do with some implementations
		$identifier = '/' . trim($identifier, '/') . '/';
		return $this->resourceExists($identifier);
	}

	/**
	 * Checks if a file inside a storage folder exists.
	 *
	 * @param string $folderName
	 * @param \TYPO3\CMS\Core\Resource\Folder $folder
	 * @return bool
	 */
	public function folderExistsInFolder($folderName, \TYPO3\CMS\Core\Resource\Folder $folder) {
		$folderIdentifier = $folder->getIdentifier() . $folderName . '/';
		return $this->resourceExists($folderIdentifier);
	}

	/**
	 * Checks if a given identifier is within a container, e.g. if a file or folder is within another folder.
	 * This can be used to check for webmounts.
	 *
	 * @param \TYPO3\CMS\Core\Resource\Folder $container
	 * @param string $content
	 * @return bool
	 */
	public function isWithin(\TYPO3\CMS\Core\Resource\Folder $container, $content) {
		// TODO extend this to also support objects as $content
		$folderPath = $container->getIdentifier();
		$content = '/' . ltrim($content, '/');

		return \TYPO3\CMS\Core\Utility\GeneralUtility::isFirstPartOfStr($content, $folderPath);
	}

	/**
	 * Removes a folder from this storage.
	 *
	 * @param \TYPO3\CMS\Core\Resource\Folder $folder
	 * @param bool $deleteRecursively
	 * @return boolean
	 */
	public function deleteFolder(\TYPO3\CMS\Core\Resource\Folder $folder, $deleteRecursively = FALSE) {
		$folderUrl = $this->getResourceUrl($folder);

		$this->removeCacheForPath(dirname($folder->getIdentifier()));

			// We don't need to specify a depth header when deleting (see sect. 9.6.1 of RFC #4718)
		$this->executeDavRequest('DELETE', $folderUrl, '', array());
	}

	/**
	 * Renames a folder in this storage.
	 *
	 * @param \TYPO3\CMS\Core\Resource\Folder $folder
	 * @param string $newName The new folder name
	 * @return string The new identifier of the folder if the operation succeeds
	 * @throws \RuntimeException if renaming the folder failed
	 * @throws \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException
	 */
	public function renameFolder(\TYPO3\CMS\Core\Resource\Folder $folder, $newName) {
		$sourcePath = $folder->getIdentifier();
		$targetPath = dirname($folder->getIdentifier()) . '/' . $newName . '/';

		try {
			$result = $this->executeMoveRequest($sourcePath, $targetPath);
		} catch (\Sabre_DAV_Exception $e) {
			// TODO insert correct exception here
			throw new \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException('Renaming ' . $sourcePath . ' to '
				. $targetPath . ' failed.', 1325848030);
		}

		$this->removeCacheForPath(dirname($folder->getIdentifier()));

		return $targetPath;
	}

	/**
	 * Checks if a folder contains files and (if supported) other folders.
	 *
	 * @param \TYPO3\CMS\Core\Resource\Folder $folder
	 * @return bool TRUE if there are no files and folders within $folder
	 */
	public function isFolderEmpty(\TYPO3\CMS\Core\Resource\Folder $folder) {
		$folderUrl = $this->getResourceUrl($folder);

		$folderContents = $this->davPropFind($folderUrl);

		return (count($folderContents) == 1);
	}
}
