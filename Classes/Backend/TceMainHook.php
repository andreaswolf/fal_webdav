<?php
namespace TYPO3\FalWebdav\Backend;

/**
 * TCEmain integration for the WebDAV driver.
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 */
class TceMainHook {
	/**
	 * @param array $incomingFieldArray
	 * @param string $table
	 * @param integer|string $id
	 * @param \TYPO3\CMS\Core\DataHandler\DataHandler $tceMainObject
	 * @return mixed
	 */
	public function processDatamap_preProcessFieldArray(&$incomingFieldArray, $table, $id, \TYPO3\CMS\Core\DataHandler\DataHandler $tceMainObject) {
		if ($table !== 'sys_file_storage') {
			return;
		}
		if ($incomingFieldArray['driver'] !== 'WebDav') {
			return;
		}

		$url = &$incomingFieldArray['configuration']['data']['sDEF']['lDEF']['baseUrl']['vDEF'];
		$username = &$incomingFieldArray['configuration']['data']['sDEF']['lDEF']['username']['vDEF'];
		$password = &$incomingFieldArray['configuration']['data']['sDEF']['lDEF']['password']['vDEF'];

		list($cleanedUrl, $extractedUsername, $extractedPassword) = \TYPO3\FalWebdav\Utility\UrlTools::extractUsernameAndPasswordFromUrl($url);
		if ($cleanedUrl != $url) {
			$url = $cleanedUrl;
		}
			// if we found authentication information in the URL, use it instead of the information currently stored
		if ($extractedUsername !== '') {
			$username = $extractedUsername;
			$password = $extractedPassword;
		}

			// skip encryption if we have no password set or the password is already encrypted
		if ($password === '' || substr($password, 0, 1) === '$') {
			return;
		}

		$password = \TYPO3\FalWebdav\Utility\Encryption::encryptPassword($password);
	}

}
