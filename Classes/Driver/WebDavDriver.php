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
	 * The base path of the WebDAV store. This is the URL without protocol, host and port (i.e., only the path on the host)
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
		$this->baseUrl = 'http://localhost/typo3-fal/webdav-root/';
		$urlInfo = parse_url($this->baseUrl);
		$this->basePath = $urlInfo['path'];

		$settings = array(
			'baseUri' => $this->baseUrl
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
		$url = $this->baseUrl . ltrim('/', $resourcePath);
		$result = $this->davClient->request('HEAD', $url);
		print_r($result);

		// TODO check if other status codes may also indicate that the file is present
		return ($result['statusCode'] == 200);
	}


	/**
	 * Returns the complete URL to a file. This is not neccessarily the publicly available URL!
	 *
	 * @param string|t3lib_file_File $file The file object or its identifier
	 * @return string
	 */
	protected function getFileUrl($file) {
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
		// TODO: Implement getPublicUrl() method.
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
		$filePath = $parentFolder->getIdentifier() . $fileName;
		$fileUrl = $this->baseUrl . ltrim($filePath, '/');

		$this->davClient->request('PUT', $fileUrl, '');
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
		// TODO: Implement getFileContents() method.
	}

	/**
	 * Sets the contents of a file to the specified value.
	 *
	 * @param t3lib_file_File $file
	 * @param string $contents
	 * @return t3lib_file_File
	 */
	public function setFileContents(t3lib_file_File $file, $contents) {
		// TODO: Implement setFileContents() method.
	}

	/**
	 * Adds a file from the local server hard disk to a given path in TYPO3s virtual file system.
	 *
	 * This assumes that the local file exists, so no further check is done here!
	 *
	 * @param string $localFilePath
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $fileName The fileName. If this is not set, the local fileName is used
	 * @return t3lib_file_File
	 */
	public function addFile($localFilePath, t3lib_file_Folder $targetFolder = NULL, $fileName = NULL) {
		// TODO: Implement addFile() method.
		// TODO check if we can use streams in conjunction with cURL
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
	 * @param string $filth
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
		$sourceUrl = $this->baseUrl . ltrim($file->getIdentifier(), '/');
		$targetPath = $this->basePath . ltrim(dirname($file->getIdentifier()), '/') . '/' . $newName;

		$this->davClient->request('MOVE', $sourceUrl, NULL, array('Destination' => $targetPath));
	}

	/**
	 * Replaces the contents (and file-specific metadata) of a file object with a local file.
	 *
	 * @param t3lib_file_File $file
	 * @param string $localFilePath
	 * @return bool
	 */
	public function replaceFile(t3lib_file_File $file, $localFilePath) {
		// TODO: Implement replaceFile() method.
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
	 * @return array
	 */
	// TODO add unit tests
	// TODO implement pattern matching
	// TODO implement limits
	public function getFileList($path, $pattern = '') {
			// TODO refactor this into an own method
		$url = $this->baseUrl . ltrim($path, '/');
		$basePath = $this->basePath . ltrim($path, '/');
		$properties = $this->davPropFind($url);

		// TODO handle errors

			// TODO refactor this into an own method
		foreach ($properties as $filePath => $item) {
				// no folder => file TODO is this really true?
			if (!$item['{DAV:}resourcetype']->is('{DAV:}collection')) {
				$filename = substr($filePath, strlen($basePath));

					// TODO add more items
				$files[$filename] = array(
					'name' => $filename,
					'identifier' => $path . $filename,
					'creationDate' => strtotime($properties['{DAV:}creationdate']),
					'storage' => $this->storage->getUid()
				);
			}
		}
		return $files;
		//return $this->getDirectoryItemList($path, 'getFileList_itemCallback');
	}

	protected function getFileInformationFromPropertiesArray(array $properties) {
		// TODO implement and call from getFileList()
	}

	/**
	 * Returns a list of all folders in a given path
	 *
	 * @param string $path
	 * @param string $pattern
	 * @return array
	 */
	// TODO implement pattern matching
	public function getFolderList($path, $pattern = '') {
			// TODO refactor this into an own method
		$url = $this->baseUrl . ltrim($path, '/');
		$basePath = $this->basePath . ltrim($path, '/');
		$properties = $this->davPropFind($url);

		// TODO handle errors

		// TODO refactor this into an own method
		$folders = array();
		foreach ($properties as $filePath => $item) {
			if ($item['{DAV:}resourcetype']->is('{DAV:}collection')) {
				$filename = trim(substr($filePath, strlen($basePath)), '/');

				if ($filename == '') {
					continue;
				}

					// TODO add more items
				$folders[$filename] = array(
					'name' => $filename,
					'identifier' => $path . trim($filename, '/') . '/',
					'creationDate' => strtotime($properties['{DAV:}creationdate']),
					'storage' => $this->storage->getUid()
				);
			}
		}
		return $folders;
	}

	/**
	 * Copies a file to a temporary path and returns that path. You have to take care of removing the temporary file yourself!
	 *
	 * @param t3lib_file_File $file
	 * @return string The temporary path
	 */
	public function copyFileToTemporaryPath(t3lib_file_File $file) {
		$temporaryPath = t3lib_div::tempnam('vfs-tempfile-');
		$fileUrl = $this->getFileUrl($file);

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
	public function moveFileWithinStorage(t3lib_file_File $file, t3lib_file_Folder $targetFolder, $fileName = NULL) {
		// TODO: Implement moveFileWithinStorage() method.
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
	public function copyFileWithinStorage(t3lib_file_File $file, t3lib_file_Folder $targetFolder, $fileName = NULL) {
		// TODO: Implement copyFileWithinStorage() method.
	}

	/**
	 * Folder equivalent to moveFileWithinStorage().
	 *
	 * @param t3lib_file_Folder $folderToMove
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $newFolderName
	 * @return bool
	 */
	public function moveFolderWithinStorage(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetFolder,
	                                        $newFolderName = NULL) {
		// TODO: Implement moveFolderWithinStorage() method.
	}

	/**
	 * Folder equivalent to copyFileWithinStorage().
	 *
	 * @param t3lib_file_Folder $folderToMove
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $newFileName
	 * @return bool
	 */
	public function copyFolderWithinStorage(t3lib_file_Folder $folderToMove, t3lib_file_Folder $targetFolder,
	                                        $newFileName = NULL) {
		// TODO: Implement copyFolderWithinStorage() method.
	}

	/**
	 * Removes a file from this storage. This does not check if the file is still used or if it is a bad idea to delete
	 * it for some other reason - this has to be taken care of in the upper layers (e.g. the Storage)!
	 *
	 * @param t3lib_file_File $file
	 * @return void
	 */
	public function deleteFile(t3lib_file_File $file) {
		// TODO: Implement deleteFile() method.
	}

	/**
	 * Adds a file at the specified location. This should only be used internally.
	 *
	 * @param string $localFilePath
	 * @param t3lib_file_Folder $targetFolder
	 * @param string $targetFileName
	 * @return string The new identifier of the file
	 */
	public function addFileRaw($localFilePath, t3lib_file_Folder $targetFolder, $targetFileName = NULL) {
		// TODO: Implement addFileRaw() method.
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
		// TODO: Implement deleteFileRaw() method.
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

		// TODO check if parent folder exists

		$this->davClient->request('MKCOL', $folderUrl, '');

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
		// TODO: Implement deleteFolder() method.
	}

	/**
	 * Renames a folder in this storage.
	 *
	 * @param t3lib_file_Folder $folder
	 * @param string $newName The target path (including the file name!)
	 * @return string The new identifier of the folder if the operation succeeds
	 * @throws RuntimeException if renaming the folder failed
	 */
	public function renameFolder(t3lib_file_Folder $folder, $newName) {
		// TODO: Implement renameFolder() method.
	}

	/**
	 * Checks if a folder contains files and (if supported) other folders.
	 *
	 * @param t3lib_file_Folder $folder
	 * @return bool TRUE if there are no files and folders within $folder
	 */
	public function isFolderEmpty(t3lib_file_Folder $folder) {
		// TODO: Implement isFolderEmpty() method.
	}

}