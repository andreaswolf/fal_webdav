<?php
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
 * Testcase for the WebDAV driver
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 * @package TYPO3
 * @subpackage fal_webdav
 */
class Tx_FalWebdav_Driver_WebDavDriverTest extends t3lib_file_BaseTestCase {

	/**
	 * @var Tx_FalWebdav_Driver_WebDavDriver
	 */
	private $fixture;

	protected function mockDavClient() {
		return $this->getMock('Sabre_DAV_Client', array(), array(), '', FALSE);
	}

	protected function prepareFixture(Sabre_DAV_Client $client = NULL) {
		if ($client === NULL) {
			$client = $this->mockDavClient();
		}

		$this->fixture = new Tx_FalWebdav_Driver_WebDavDriver();
		$this->fixture->injectDavClient($client);
		$this->fixture->setStorage($this->getMock('t3lib_file_Storage', array(), array(), '', FALSE));
		$this->fixture->initialize();
	}

	/**
	 * @test
	 */
	public function createFileIssuesCorrectCommandOnServer() {
		/** @var $clientMock Sabre_DAV_Client */
		$clientMock = $this->mockDavClient();
		$clientMock->expects($this->once())->method('request')->with($this->equalTo('PUT'), $this->stringEndsWith('/someFile'));
		$this->prepareFixture($clientMock);
		$mockedFolder = $this->getSimpleFolderMock('/');

		$this->fixture->createFile($mockedFolder, 'someFile');
	}

	/**
	 * @test
	 * @return t3lib_file_Folder
	 */
	public function createFolderIssuesCorrectCommandOnServer() {
		/** @var $clientMock Sabre_DAV_Client */
		$clientMock = $this->mockDavClient();
		$clientMock->expects($this->once())->method('request')->with($this->equalTo('MKCOL'), $this->stringEndsWith('/mainFolder/subFolder/'));
		$this->prepareFixture($clientMock);
		$mockedFolder = $this->getSimpleFolderMock('/mainFolder/');

		return $this->fixture->createFolder('subFolder', $mockedFolder);
	}

	/**
	 * @test
	 * @depends createFolderIssuesCorrectCommandOnServer
	 * @param t3lib_file_Folder $folder
	 */
	public function createFolderReturnsObjectWithCorrectIdentifier(t3lib_file_Folder $folder) {
		$this->assertEquals('/mainFolder/subFolder/', $folder->getIdentifier());
	}
}