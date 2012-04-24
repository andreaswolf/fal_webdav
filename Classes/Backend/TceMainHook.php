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

		$currentValue = &$incomingFieldArray['configuration']['data']['sDEF']['lDEF']['password']['vDEF'];
			// skip encryption if we have no password set or the password is already encrypted
		if ($currentValue == '' || substr($currentValue, 0, 1) == '$') {
			return;
		}
		$currentValue = Tx_FalWebdav_Utility_Encryption::encryptPassword($currentValue);
	}
}
