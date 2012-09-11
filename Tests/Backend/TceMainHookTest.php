<?php
namespace TYPO3\FalWebdav\Tests\Backend;

/*                                                                        *
 * This script belongs to the TYPO3 project.                              *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License as published by the Free   *
 * Software Foundation, either version 2 of the License, or (at your      *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        *
 * You should have received a copy of the GNU General Public License      *
 * along with the script.                                                 *
 * If not, see http://www.gnu.org/licenses/gpl.html                       *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Testcase for the WebDAV driver configuration TCEmain hook.
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage fal_webdav
 */
class TceMainHookTest extends \Tx_Phpunit_TestCase {

	/**
	 * @var \TYPO3\FalWebdav\Backend\TceMainHook
	 */
	protected $fixture;

	public function setUp() {
		$this->fixture = new \TYPO3\FalWebdav\Backend\TceMainHook();
	}

	protected function prepareFieldArrayFixture(array $fieldValues) {
		$fieldArray = array(
			'configuration' => array(
				'data' => array(
					'sDEF' => array(
						'lDEF' => array(
								// preset the driver as the TCEmain hook checks this
							'driver' => array(
								'vDEF' => 'WebDav'
							)
						)
					)
				)
			)
		);

		foreach ($fieldValues as $field => $value) {
			$fieldArray['configuration']['data']['sDEF']['lDEF'][$field] = array(
				'vDEF' => $value
			);
		}
		return $fieldArray;
	}

	/**
	 * @test
	 */
	public function usernameAndPasswordFromUrlOverrideSetValuesInConfigurationFields() {
		$fieldArray = $this->prepareFieldArrayFixture(array(
			'baseUrl' => 'http://newUser:newPass@localhost/some/storage/',
			'username' => 'oldUser',
			'password' => 'oldPassword'
		));

		$this->fixture->processDatamap_preProcessFieldArray($fieldArray, 'sys_file_storage', -1, new \StdClass());

		$this->assertEquals('newUser', $fieldArray['configuration']['data']['sDEF']['lDEF']['username']['vDEF']);
		$this->assertEquals('newPass', \TYPO3\FalWebdav\Utility\Encryption::decryptPassword($fieldArray['configuration']['data']['sDEF']['lDEF']['password']['vDEF']));
		$this->assertEquals('http://localhost/some/storage/', $fieldArray['configuration']['data']['sDEF']['lDEF']['baseUrl']['vDEF']);
	}

	/**
	 * @test
	 */
	public function usernameAndPasswordInConfigurationFieldsAreLeftUnchangedIfNoAuthenticationInfoIsGivenInUrl() {
		$fieldArray = $this->prepareFieldArrayFixture(array(
			'baseUrl' => 'http://localhost/some/storage/',
			'username' => 'oldUser',
			'password' => 'oldPassword'
		));

		$this->fixture->processDatamap_preProcessFieldArray($fieldArray, 'sys_file_storage', -1, new \StdClass());

		$this->assertEquals('oldUser', $fieldArray['configuration']['data']['sDEF']['lDEF']['username']['vDEF']);
		$this->assertEquals('oldPassword', \TYPO3\FalWebdav\Utility\Encryption::decryptPassword($fieldArray['configuration']['data']['sDEF']['lDEF']['password']['vDEF']));
	}
}
