<?php

/**
 * TCEmain integration for the WebDAV driver.
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 */
class Tx_FalWebdav_Backend_TceMainHook {

	public function processDatamap_preProcessFieldArray(&$incomingFieldArray, $table, $id, $tceMainObject) {
		if ($table != 'sys_file_storage') {
			return;
		}
		if ($incomingFieldArray['configuration']['data']['sDEF']['lDEF']['driver']['vDEF'] != 'WebDav') {
			return;
		}

		$url = &$incomingFieldArray['configuration']['data']['sDEF']['lDEF']['baseUrl']['vDEF'];
		$username = &$incomingFieldArray['configuration']['data']['sDEF']['lDEF']['username']['vDEF'];
		$password = &$incomingFieldArray['configuration']['data']['sDEF']['lDEF']['password']['vDEF'];

		list($cleanedUrl, $extractedUsername, $extractedPassword) = Tx_FalWebdav_Utility_UrlTools::extractUsernameAndPasswordFromUrl($url);
		if ($cleanedUrl != $url) {
			$url = $cleanedUrl;
		}
			// if we found authentication information in the URL, use it instead of the information currently stored
		if ($extractedUsername != '') {
			$username = $extractedUsername;
			$password = $extractedPassword;
		}

			// skip encryption if we have no password set or the password is already encrypted
		if ($password == '' || substr($password, 0, 1) == '$') {
			return;
		}
		$password = Tx_FalWebdav_Utility_Encryption::encryptPassword($password);
	}

}
