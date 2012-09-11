<?php
namespace TYPO3\FalWebdav\Utility;

/**
 * Utility methods for encrypting/decrypting data. Currently only supports Blowfish encryption in CBC mode,
 *
 * NOTE: This is just a working draft - the methods should be refined and moved to the TYPO3 core.
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 */
class EncryptionUtility {

	protected static $encryptionMethod = MCRYPT_BLOWFISH;

	protected static $encryptionMode = MCRYPT_MODE_CBC;

	public static function getEncryptionMethod() {
		return self::$encryptionMethod;
	}

	public static function getEncryptionMode() {
		return self::$encryptionMode;
	}

	/**
	 * Returns an initialization vector suitable for the chosen encryption method.
	 *
	 * @param string $encryptionMethod
	 * @param string $encryptionMode
	 * @return string
	 */
	protected static function getInitializationVector($encryptionMethod = NULL, $encryptionMode = NULL) {
		if (!$encryptionMethod) {
			$encryptionMethod = self::$encryptionMethod;
		}
		if (!$encryptionMode) {
			$encryptionMode = self::$encryptionMode;
		}

		$iv_size = mcrypt_get_iv_size($encryptionMethod, $encryptionMode);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		return $iv;
	}

	/**
	 * Creates and returns a resource pointer for the encryption method. This is required for e.g. retrieving an
	 * encryption key.
	 *
	 * @param string $encryptionMethod
	 * @param string $encryptionMode
	 * @return resource
	 */
	protected static function createEncryptionContext($encryptionMethod = NULL, $encryptionMode = NULL) {
		if (!$encryptionMethod) {
			$encryptionMethod = self::$encryptionMethod;
		}
		if (!$encryptionMode) {
			$encryptionMode = self::$encryptionMode;
		}

		$td = mcrypt_module_open($encryptionMethod, '', $encryptionMode, '');
		return $td;
	}

	/**
	 * @param $td
	 * @return string
	 */
	protected static function getEncryptionKey($td) {
		$ks = mcrypt_enc_get_key_size($td);
		$key = substr(md5($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']), 0, $ks);
		return $key;
	}

	/**
	 * Encrypts a password. The algorithm used and - if necessary - the initialization vector are stored within the
	 * password string. The encrypted password itself is stored base64 encoded to make it possible to store this password
	 * in an XML structure.
	 *
	 * @param $value
	 * @return string
	 * @see decryptPassword()
	 */
	public static function encryptPassword($value) {
		if ($value == '') {
			return '';
		}

		$td = self::createEncryptionContext();
		$iv = self::getInitializationVector();
		$key = self::getEncryptionKey($td);
		mcrypt_generic_init($td, $key, $iv);

		$encryptedPassword = mcrypt_generic($td, $value);
		mcrypt_generic_deinit($td);

		$encryptedText = sprintf('$%s$%s$%s$%s',
			self::$encryptionMethod,
			self::$encryptionMode,
			base64_encode($iv),
			base64_encode($encryptedPassword)
		);

			// close the module opened for encrypting
		mcrypt_module_close($td);

		return $encryptedText;
	}

	/**
	 * Decrypts a password. The necessary initialization vector is extracted from the password.
	 *
	 * @param string $encryptedPassword
	 * @return string
	 * @see encryptPassword()
	 */
	public static function decryptPassword($encryptedPassword) {
		if ($encryptedPassword == '') {
			return '';
		}

		if (substr_count($encryptedPassword, '$') > 0) {
			$passwordParts = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode('$', $encryptedPassword, TRUE);
				// Base64 decoding the password is done below
			$encryptedPassword = $passwordParts[3];

			$encryptionMethod = $passwordParts[0];
			$mode = $passwordParts[1];
			$td = self::createEncryptionContext($encryptionMethod, $mode);

			$iv = base64_decode($passwordParts[2]);
		} else {
			$td = self::createEncryptionContext(self::$encryptionMethod, self::$encryptionMode);
			$iv = self::getInitializationVector();
		}
		$key = self::getEncryptionKey($td);

		mcrypt_generic_init($td, $key, $iv);
		$decryptedPassword = trim(mdecrypt_generic($td, base64_decode($encryptedPassword)));
		mcrypt_generic_deinit($td);

			// close the module opened for decrypting
		mcrypt_module_close($td);

		return $decryptedPassword;
	}
}