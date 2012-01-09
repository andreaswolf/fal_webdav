<?php

include_once 'Sabre/autoload.php';

class Tx_FalWebdav_Driver_WebDavDriver extends t3lib_file_Driver_AbstractDriver {

	/**
	 * The base URL of the WebDAV share. Always ends with a trailing slash.
	 *
	 * @var string
	 */
	protected $baseUrl;

	/**
	 * The base path of the WebDAV store. This is the URL without protocol, host and port (i.e., only the path on the host).
	 * Always ends with a trailing slash.
	 *
	 * @var string
	 */
	protected $basePath;

	/**
	 * @var Sabre_DAV_Client
	 */
	protected $davClient;

	public function __construct(array $configuration = array()) {
		parent::__construct($configuration);
	}

	/**
	 * Initializes this object. This is called by the storage after the driver has been attached.
	 *
	 * @return void
	 */
	public function initialize() {
	}

	public function injectDavClient(Sabre_DAV_Client $client) {
		$this->davClient = $client;
	}

	protected function processConfiguration() {
		$this->baseUrl = $this->configuration['baseUrl'];
		$urlInfo = parse_url($this->baseUrl);
		if ($urlInfo === FALSE) {
			throw new InvalidArgumentException('Invalid base URL configured for WebDAV driver: ' . $this->configuration['baseUrl'], 1325771040);
		}
		$this->basePath = rtrim($urlInfo['path'], '/') . '/';

		$settings = array(
			'baseUri' => $this->baseUrl,
			'userName' => $urlInfo['user'],
			'password' => $urlInfo['pass']
		);

		$this->davClient = new Sabre_DAV_Client($settings);
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
		return $this->davClient->request('MOVE', $oldUrl, NULL, array('Destination' => $newUrl, 'Overwrite' => 'T'));
	}



	/**
	 * Executes a PROPFIND request on the given URL and returns the result array
	 *
	 * @param string $url
	 * @return array
	 */
	protected function davPropFind($url) {
		return $this->davClient->propfind($url, array(
			'{DAV:}resourcetype',
			'{DAV:}creationdate',
			'{DAV:}getcontentlength',
			'{DAV:}getlastmodified'
		), 1);
		// TODO throw exception on error
	}

	/**
	 * Checks if a given resource exists in this DAV share.
	 *
	 * @param string $resourcePath The path to the resource, i.e. a regular identifier as used everywhere else here.
	 * @return bool
	 */
	protected function resourceExists($resourcePath) {
		if ($resourcePath == '') {
			throw new InvalidArgumentException('Resource path cannot be empty');
		}
		$url = $this->baseUrl . ltrim($resourcePath, '/');
		try {
			$this->davClient->request('HEAD', $url);
		} catch (Sabre_DAV_Exception_NotFound $exception) {
			return FALSE;
		}
		// TODO check if other status codes may also indicate that the file is present
		return TRUE;
	}


	/**
	 * Returns the complete URL to a file. This is not necessarily the publicly available URL!
	 *
	 * @param string|t3lib_file_File|t3lib_file_Folder $file The file object or its identifier
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
	 * @param t3lib_file_File $file
	 * @return string
	 */
	public function getPublicUrl(t3lib_file_File $file) {
		if ($this->storage->isPublic()) {
				// as the storage is marked as public, we can simply use the public URL here.
			return $this->getResourceUrl($file);
		}
	}

	/**
	 * Creates a (cryptographic) hash for a file.
	 *
	 * @param t3lib_file_File $file
	 * @param string $hashAlgorithm The hash algorithm to use
	 * @return string
	 * TODO switch parameter order?
	 */
	public function hash(t3lib_file_File $file, $hashAlgorithm) {
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
	 * @param t3lib_file_Folder $parentFolder
	 * @return t3lib_file_File
	 */
	public function createFile($fileName, t3lib_file_Folder $parentFolder) {
		$fileIdentifier = $parentFolder->getIdentifier() . $fileName;
		$fileUrl = $this->baseUrl . ltrim($fileIdentifier, '/');

		$this->davClient->request('PUT', $fileUrl, '');

		return $this->getFile($fileIdentifier);
	}

	/**
	 * Returns the contents of a file. Beware that this requires to load the complete file into memory and also may
	 * require fetching the file from an external location. So this might be an expensive operation (both in terms of
	 * processing resources and money) for large files.
	 *
	 * @param t3lib_file_File $file
	 * @return string The file contents
	 */
	public function getFileContents(t3lib_file_File $file) {
		$fileUrl = $this->baseUrl . ltrim($file->getIdentifier(), '/');

		$result = $this->davClient->request('GET', $fileUrl);

		return $result['body'];
	}

	/**
	 * Sets the contents of a file to the specified value.
	 *
	 * @param t3lib_file_File $file
	 * @param string $contents
	 * @return bool TRUE if setting the contents succeeded
	 * @throws RuntimeException if the operation failed
	 */
	public function setFileContents(t3lib_file_File $file, $contents) {
		// Apache returns a "204 no content" status after a successful put operation

		$fileUrl = $this->getResourceUrl($file);
		$result = $this->davClient->request('PUT', $fileUrl, $contents);

		// TODO check result
	}

	/**
	 * Adds a file from the local server hard disk to a given path in TYPO3s virtual file system.
	 *
	 * This assumes that the local file exists, so no further check is done here!
	 *
	 * @param string $localFilePath
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName The name to add the file under
	 * @return t3lib_file_File
	 */
	public function addFile($localFilePath, t3lib_file_Folder $targetFolder, $fileName) {
		$fileIdentifier = $targetFolder->getIdentifier() . $fileName;
		$fileUrl = $this->baseUrl . ltrim($fileIdentifier);

		$fileHandle = fopen($localFilePath, 'r');
		if (!is_resource($fileHandle)) {
			throw new RuntimeException('Could not open handle for ' . $localFilePath, 1325959310);
		}
		$result = $this->davClient->request('PUT', $fileUrl, $fileHandle);

		return $this->getFile($fileIdentifier);
	}

	/**
	 * Checks if a file exists.
	 *
	 * @param string $identifier
	 * @return bool
	 */
	public function fileExists($identifier) {
		return $this->resourceExists($identifier);
	}

	/**
	 * Checks if a file inside a storage folder exists.
	 *
	 * @param string $fileName
	 * @param t3lib_file_Folder $folder
	 * @return boolean
	 */
	public function fileExistsInFolder($fileName, t3lib_file_Folder $folder) {
		// TODO add unit test
		$fileIdentifier = $folder->getIdentifier() . $fileName;

		return $this->resourceExists($fileIdentifier);
	}

	/**
	 * Returns a (local copy of) a file for processing it. When changing the file, you have to take care of replacing the
	 * current version yourself!
	 *
	 * @param t3lib_file_File $file
	 * @param bool $writable Set this to FALSE if you only need the file for read operations. This might speed up things, e.g. by using a cached local version. Never modify the file if you have set this flag!
	 * @return string The path to the file on the local disk
	 */
	public function getFileForLocalProcessing(t3lib_file_File $file, $writable = TRUE) {
		return $this->copyFileToTemporaryPath($file);
	}

	/**
	 * Returns the permissions of a file as an array (keys r, w) of boolean flags
	 *
	 * @param t3lib_file_File $file
	 * @return array
	 */
	public function getFilePermissions(t3lib_file_File $file) {
		// TODO: Implement getFilePermissions() method.
	}

	/**
	 * Returns the permissions of a folder as an array (keys r, w) of boolean flags
	 *
	 * @param t3lib_file_Folder $folder
	 * @return array
	 */
	public function getFolderPermissions(t3lib_file_Folder $folder) {
		// TODO: Implement getFolderPermissions() method.
	}

	/**
	 * Renames a file
	 *
	 * @param t3lib_file_File $file
	 * @param string $newName
	 * @return string The new identifier of the file
	 */
	public function renameFile(t3lib_file_File $file, $newName) {
		// TODO add unit test
		// Renaming works by invoking the MOVE method on the source URL and providing the new destination in the
		// "Destination:" HTTP header.
		$sourcePath = $file->getIdentifier();
		$targetPath = dirname($file->getIdentifier()) . '/' . $newName;

		$this->executeMoveRequest($sourcePath, $targetPath);

		return $targetPath;
	}

	/**
	 * Replaces the contents (and file-specific metadata) of a file object with a local file.
	 *
	 * @param t3lib_file_File $file
	 * @param string $localFilePath
	 * @return bool
	 */
	public function replaceFile(t3lib_file_File $file, $localFilePath) {
		$fileUrl = $this->getResourceUrl($file);
		$fileHandle = fopen($localFilePath, 'r');
		if (!is_resource($fileHandle)) {
			throw new RuntimeException('Could not open handle for ' . $localFilePath, 1325959311);
		}

		$this->davClient->request('PUT', $fileUrl, $fileHandle);
	}

	/**
	 * Returns information about a file for a given file identifier.
	 *
	 * @param string $identifier The (relative) path to the file.
	 * @return array
	 */
	public function getFileInfoByIdentifier($identifier) {
		$fileUrl = $this->baseUrl . ltrim($identifier, '/');

		$properties = $this->davClient->request('PROPFIND', $fileUrl);
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

		return $fileInfo;
	}

	/**
	 * Returns a list of files inside the specified path
	 *
	 * @param string $path
	 * @param string $pattern
	 * @param integer $start The position to start the listing; if not set, start from the beginning
	 * @param integer $numberOfItems The number of items to list; if not set, return all items
	 * @return array
	 */
	// TODO add unit tests
	// TODO implement pattern matching
	public function getFileList($path, $pattern = '', $start = 0, $numberOfItems = 0) {
		return $this->getDirectoryItemList($path, $pattern, $start, $numberOfItems, 'getFileList_itemCallback');
	}

	/**
	 * Returns a list of all folders in a given path
	 *
	 * @param string $path
	 * @param string $pattern
	 * @param integer $start The position to start the listing; if not set, start from the beginning
	 * @param integer $numberOfItems The number of items to list; if not set, return all items
	 * @return array
	 */
	public function getFolderList($path, $pattern = '', $start = 0, $numberOfItems = 0) {
		return $this->getDirectoryItemList($path, $pattern, $start, $numberOfItems, 'getFolderList_itemCallback');
	}

	/**
	 * Generic handler method for directory listings - gluing together the listing items is done
	 *
	 * @param string $path
	 * @param string $pattern
	 * @param integer $start
	 * @param integer $numberOfItems
	 * @param callback $itemHandlerMethod
	 * @return array
	 */
	// TODO implement pattern matching
	protected function getDirectoryItemList($path, $pattern, $start, $numberOfItems, $itemHandlerMethod) {
		$url = $this->baseUrl . ltrim($path, '/');
		$basePath = $this->basePath . ltrim($path, '/');
		$properties = $this->davPropFind($url);

			// the returned items are indexed by their key, so sort them here to return the correct items
			// at least Apache does not sort them before returning
		uksort($properties, 'strnatcasecmp');
		$propertyIterator = new ArrayIterator($properties);

		// TODO handle errors

		if ($path !== '' && $path != '/') {
			$path = '/' . trim($path, '/') . '/';
		}

			// if we have only one entry, this is the folder we are currently in, so there are no items -> return an empty array
		if (count($properties) == 1) {
			return array();
		}

		$c = $numberOfItems > 0 ? $numberOfItems : $propertyIterator->count();
		$propertyIterator->seek($start);

		$items = array();
		while ($propertyIterator->valid() && $c > 0) {
			--$c;

			$item = $propertyIterator->current();
			$filePath = $propertyIterator->key();
			$propertyIterator->next();

			list($key, $entry) = $this->$itemHandlerMethod($item, $filePath, $basePath, $path);

			if (empty($entry)) {
				++$c;
				continue;
			}
			$items[$key] = $entry;
		}

		return $items;
	}

	/**
	 * Callback method that extracts file information from a single entry inside a DAV PROPFIND response. Called by getDirectoryItemList.
	 *
	 * @param array $item
	 * @param string $filePath
	 * @param string $basePath
	 * @param string $path
	 * @return array
	 */
	protected function getFileList_itemCallback(array $item, $filePath, $basePath, $path) {
		if ($item['{DAV:}resourcetype']->is('{DAV:}collection')) {
			return array('', array());
		}
		$fileName = substr($filePath, strlen($basePath));

			// TODO add more items
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
	 * @param array $item
	 * @param string $filePath
	 * @param string $basePath
	 * @param string $path
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

			// TODO add more items
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
	 * @param t3lib_file_File $file
	 * @return string The temporary path
	 */
	public function copyFileToTemporaryPath(t3lib_file_File $file) {
		$temporaryPath = t3lib_div::tempnam('vfs-tempfile-');
		$fileUrl = $this->getResourceUrl($file);

		$result = $this->davClient->request('GET', $fileUrl);
		file_put_contents($temporaryPath, $result['body']);

		return $temporaryPath;
	}

	/**
	 * Moves a file *within* the current storage.
	 * Note that this is only about an intra-storage move action, where a file is just
	 * moved to another folder in the same storage.
	 *
	 * @param t3lib_file_File $file
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName
	 * @return string The new identifier of the file
	 */
	public function moveFileWithinStorage(t3lib_file_File $file, t3lib_file_Folder $targetFolder, $fileName) {
		$newPath = $targetFolder->getIdentifier() . $fileName;

		try {
			$result = $this->executeMoveRequest($file->getIdentifier(), $newPath);
		} catch (Sabre_DAV_Exception $e) {
			// TODO insert correct exception here
			throw new t3lib_file_exception_AbstractFileOperationException('Moving file ' . $file->getIdentifier()
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
	 * @param t3lib_file_File $file
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName
	 * @return t3lib_file_File The new (copied) file object.
	 */
	public function copyFileWithinStorage(t3lib_file_File $file, t3lib_file_Folder $targetFolder, $fileName) {
		$oldFileUrl = $this->getResourceUrl($file);
		$newFileUrl = $this->getResourceUrl($targetFolder) . $fileName;
		$newFileIdentifier = $targetFolder->getIdentifier() . $fileName;

		try {
				// force overwriting the file (header Overwrite: T) because the Storage already handled possible conflicts
				// for us
			$result = $this->davClient->request('COPY', $oldFileUrl, NULL, array('Destination' => $newFileUrl, 'Overwrite' => 'T'));
		} catch (Sabre_DAV_Exception $e) {
			// TODO insert correct exception here
			throw new t3lib_file_exception_AbstractFileOperationException('Copying file ' . $file->getIdentifier() . ' to '
				. $newFileIdentifier . ' failed.', 1325848030);
		}
		// TODO check if there are some return codes that signalize an error, but do not throw an exception
		// status codes: 204: file was overwritten; 201: file was created;

		return $this->getFile($newFileIdentifier);
	}

	/**
	 * Folder equivalent to moveFileWithinStorage().
	 *
	 * @param t3lib_file_Folder $folderToMove
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $newFolderName
	 * @return array Mapping of old file identifiers to new ones
	 */
	public function moveFolderWithinStorage(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetFolder,
	                                        $newFolderName = NULL) {
		$newFolderIdentifier = $targetFolder->getIdentifier() . $newFolderName . '/';

		try {
			$result = $this->executeMoveRequest($folderToMove->getIdentifier(), $newFolderIdentifier);
		} catch (Sabre_DAV_Exception $e) {
			// TODO insert correct exception here
			throw new t3lib_file_exception_AbstractFileOperationException('Moving folder ' . $folderToMove->getIdentifier()
				. ' to ' . $newFolderIdentifier . ' failed: ' . $e->getMessage(), 1326135944);
		}
		// TODO check if there are some return codes that signalize an error, but do not throw an exception
		// status codes: 204: file was overwritten; 201: file was created;

		// TODO extract mapping of old to new identifiers from server response
	}

	/**
	 * Folder equivalent to copyFileWithinStorage().
	 *
	 * @param t3lib_file_Folder $folderToMove
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $newFolderName
	 * @return bool
	 */
	public function copyFolderWithinStorage(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetFolder,
	                                        $newFolderName = NULL) {
		$oldFolderUrl = $this->getResourceUrl($folderToMove);
		$newFolderUrl = $this->getResourceUrl($targetFolder) . $newFolderName . '/';
		$newFolderIdentifier = $targetFolder->getIdentifier() . $newFolderName . '/';

		try {
			$result = $this->davClient->request('COPY', $oldFolderUrl, NULL, array('Destination' => $newFolderUrl, 'Overwrite' => 'T'));
		} catch (Sabre_DAV_Exception $e) {
			// TODO insert correct exception here
			throw new t3lib_file_exception_AbstractFileOperationException('Moving folder ' . $folderToMove->getIdentifier()
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
	 * @param t3lib_file_File $file
	 * @return boolean TRUE if the operation succeeded
	 */
	public function deleteFile(t3lib_file_File $file) {
		// TODO add unit tests
		$fileUrl = $this->baseUrl . ltrim($file->getIdentifier(), '/');

		$result = $this->davClient->request('DELETE', $fileUrl);

		// 204 is derived from the answer Apache gives - there might be other status codes that indicate success
		return ($result['statusCode'] == 204);
	}

	/**
	 * Adds a file at the specified location. This should only be used internally.
	 *
	 * @param string $localFilePath
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $targetFileName
	 * @return string The new identifier of the file
	 */
	public function addFileRaw($localFilePath, t3lib_file_Folder $targetFolder, $targetFileName) {
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
	 * @return t3lib_file_Folder
	 */
	public function getRootLevelFolder() {
		return $this->getFolder('/');
	}

	/**
	 * Returns the default folder new files should be put into.
	 *
	 * @return t3lib_file_Folder
	 */
	public function getDefaultFolder() {
		return $this->getFolder('/');
	}

	/**
	 * Creates a folder.
	 *
	 * @param string $newFolderName
	 * @param t3lib_file_Folder $parentFolder
	 * @return t3lib_file_Folder The new (created) folder object
	 */
	public function createFolder($newFolderName, t3lib_file_Folder $parentFolder) {
			// We add a slash to the path as some actions require a trailing slash on some servers.
			// Apache's mod_dav e.g. does not do it for this action, but it does not do harm either, so we add it anyways
		$folderPath = $parentFolder->getIdentifier() . $newFolderName . '/';
		$folderUrl = $this->baseUrl . ltrim($folderPath, '/');

		$this->davClient->request('MKCOL', $folderUrl);

		/** @var $factory t3lib_file_Factory */
		$factory = t3lib_div::makeInstance('t3lib_file_Factory');
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
	 * @param t3lib_file_Folder $folder
	 * @return bool
	 */
	public function folderExistsInFolder($folderName, t3lib_file_Folder $folder) {
		$folderIdentifier = $folder->getIdentifier() . $folderName . '/';
		return $this->resourceExists($folderIdentifier);
	}

	/**
	 * Checks if a given identifier is within a container, e.g. if a file or folder is within another folder.
	 * This can be used to check for webmounts.
	 *
	 * @param t3lib_file_Folder $container
	 * @param string $content
	 * @return bool
	 */
	public function isWithin(t3lib_file_Folder $container, $content) {
		// TODO extend this to also support objects as $content
		$folderPath = $container->getIdentifier();
		$content = '/' . ltrim($content, '/');

		return t3lib_div::isFirstPartOfStr($content, $folderPath);
	}

	/**
	 * Removes a folder from this storage.
	 *
	 * @param t3lib_file_Folder $folder
	 * @param bool $deleteRecursively
	 * @return boolean
	 */
	public function deleteFolder(t3lib_file_Folder $folder, $deleteRecursively = FALSE) {
		$folderUrl = $this->getResourceUrl($folder);

			// We don't need to specify a depth header when deleting (see sect. 9.6.1 of RFC #4718)
		$this->davClient->request('DELETE', $folderUrl, '', array());
	}

	/**
	 * Renames a folder in this storage.
	 *
	 * @param t3lib_file_Folder $folder
	 * @param string $newName The new folder name
	 * @return string The new identifier of the folder if the operation succeeds
	 * @throws RuntimeException if renaming the folder failed
	 */
	public function renameFolder(t3lib_file_Folder $folder, $newName) {
		$sourcePath = $folder->getIdentifier();
		$targetPath = dirname($folder->getIdentifier()) . '/' . $newName . '/';

		try {
			$result = $this->executeMoveRequest($sourcePath, $targetPath);
		} catch (Sabre_DAV_Exception $e) {
			// TODO insert correct exception here
			throw new t3lib_file_exception_AbstractFileOperationException('Renaming ' . $sourcePath . ' to '
				. $targetPath . ' failed.', 1325848030);
		}

		return $targetPath;
	}

	/**
	 * Checks if a folder contains files and (if supported) other folders.
	 *
	 * @param t3lib_file_Folder $folder
	 * @return bool TRUE if there are no files and folders within $folder
	 */
	public function isFolderEmpty(t3lib_file_Folder $folder) {
		$folderUrl = $this->getResourceUrl($folder);

		$folderContents = $this->davPropFind($folderUrl);

		return (count($folderContents) == 1);
	}

}