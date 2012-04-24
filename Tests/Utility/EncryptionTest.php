<?php

/**
 *
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 */
class Tx_FalWebdav_Utility_EncryptionTest extends Tx_Phpunit_TestCase {
	/**
	 * @test
	 */
	public function passwordsCanBeEncryptedAndDecrypted() {
		$password = uniqid();

		$encryptedPassword = Tx_FalWebdav_Utility_Encryption::encryptPassword($password);
		$decryptedPassword = Tx_FalWebdav_Utility_Encryption::decryptPassword($encryptedPassword);

		$this->assertEquals($password, $decryptedPassword);
	}

	/**
	 * @test
	 */
	public function encryptedPasswordContainsAlgorithm() {
		$password = uniqid();

		$encryptedPassword = Tx_FalWebdav_Utility_Encryption::encryptPassword($password);

		$this->assertStringStartsWith(
			sprintf('$%s$%s$',
				Tx_FalWebdav_Utility_Encryption::getEncryptionMethod(),
				Tx_FalWebdav_Utility_Encryption::getEncryptionMode()
			),
			$encryptedPassword
		);
	}

	/**
	 * @test
	 */
	public function encryptingEmptyStringReturnsEmptyString() {
		$encryptedPassword = Tx_FalWebdav_Utility_Encryption::encryptPassword('');

		$this->assertEmpty($encryptedPassword);
	}

	/**
	 * @test
	 */
	public function decryptingEmptyStringReturnsEmptyString() {
		$this->assertEquals('', Tx_FalWebdav_Utility_Encryption::decryptPassword(''));
	}
}