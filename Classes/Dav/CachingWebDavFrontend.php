<?php
namespace TYPO3\FalWebdav\Dav;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;


class CachingWebDavFrontend extends WebDavFrontend {

	/**
	 * @var FrontendInterface
	 */
	protected $cache;

	protected $cacheHits = array(
		'propFind' => 0,
		'listFiles' => 0,
		'listFolders' => 0,
		'getFileInfo' => 0,
	);

	protected $cacheMisses = array(
		'propFind' => 0,
		'listFiles' => 0,
		'listFolders' => 0,
		'getFileInfo' => 0,
	);


	public function __construct(WebDavClient $client, $baseUrl, $storageUid, FrontendInterface $cache) {
		parent::__construct($client, $baseUrl, $storageUid);

		$this->cache = $cache;
	}

	/**
	 * @return FrontendInterface
	 */
	protected function getCache() {
		if (!$this->cache) {
			/** @var CacheManager $cacheManager */
			$cacheManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Cache\\CacheManager');
			$this->cache = $cacheManager->getCache('tx_falwebdav_directorylisting');
		}

		return $this->cache;
	}

	public function propFind($path) {
		$cacheKey = $this->getCacheIdentifierForResponse($path);

		if (!$this->getCache()->has($cacheKey)) {
			++$this->cacheMisses[__FUNCTION__];
			// TODO also extract information on all files/folders
			$this->getCache()->set($cacheKey, parent::propFind($path));
		} else {
			++$this->cacheHits[__FUNCTION__];
		}

		return $this->getCache()->get($cacheKey);
	}

	public function listFiles($path) {
		$cacheKey = $this->getCacheIdentifierForFileList($path);
		if (!$this->getCache()->has($cacheKey)) {
			++$this->cacheMisses[__FUNCTION__];
			$this->getCache()->set($cacheKey, parent::listFiles($path));
		} else {
			++$this->cacheHits[__FUNCTION__];
		}

		return $this->getCache()->get($cacheKey);
	}

	public function listFolders($path) {
		$cacheKey = $this->getCacheIdentifierForFolderList($path);
		if (!$this->getCache()->has($cacheKey)) {
			++$this->cacheMisses[__FUNCTION__];
			$this->getCache()->set($cacheKey, parent::listFolders($path));
		} else {
			++$this->cacheHits[__FUNCTION__];
		}

		return $this->getCache()->get($cacheKey);
	}

	public function getFileInfo($path) {
		$cacheKey = $this->getCacheIdentifierForFileInfo($path);
		if (!$this->getCache()->has($cacheKey)) {
			++$this->cacheMisses[__FUNCTION__];
			$this->getCache()->set($cacheKey, parent::getFileInfo($path));
		} else {
			++$this->cacheHits[__FUNCTION__];
		}

		return $this->getCache()->get($cacheKey);
	}

	public function logCacheStatistics() {
		print_r(sprintf('WebDAV frontend cache stats (hits/misses): '
			.'propFind %d/%d, listFiles %d/%d, listFolders %d/%d, getFileInfo %d/%d',
			$this->cacheHits['propFind'], $this->cacheMisses['propFind'],
			$this->cacheHits['listFiles'], $this->cacheMisses['listFiles'],
			$this->cacheHits['listFolders'], $this->cacheMisses['listFolders'],
			$this->cacheHits['getFileInfo'], $this->cacheMisses['getFileInfo']
			)
		);
	}

	/**
	 * Returns the cache identifier for the raw response for a given path
	 *
	 * @param string $url
	 * @return string
	 */
	protected function getCacheIdentifierForResponse($url) {
		return 'davResponse-' . sha1($url);
	}

	/**
	 * Returns the cache identifier for the file list of a given path.
	 *
	 * @param string $path
	 * @return string
	 */
	protected function getCacheIdentifierForFileList($path) {
		return 'filelist-' . sha1($this->baseUrl . ':' . trim($path, '/') . '/');
	}

	/**
	 * Returns the cache identifier for the folder list of a given path.
	 *
	 * @param string $path
	 * @return string
	 */
	protected function getCacheIdentifierForFolderList($path) {
		return 'folderlist-' . sha1($this->baseUrl . ':' . trim($path, '/') . '/');
	}

	/**
	 * Returns the cache identifier for the file list of a given path.
	 *
	 * @param string $path
	 * @return string
	 */
	protected function getCacheIdentifierForFileInfo($path) {
		return 'fileinfo-' . sha1($this->baseUrl . ':' . trim($path, '/') . '/');
	}

	function __destruct() {
		$this->logCacheStatistics();
	}

}
