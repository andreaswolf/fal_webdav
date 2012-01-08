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

	/**
	 * @var string
	 */
	private $baseUrl;

	public function setUp() {
		$this->baseUrl = 'http://example.org/webdav-root/';
	}

	protected function mockDavClient() {
		return $this->getMock('Sabre_DAV_Client', array(), array(), '', FALSE);
	}

	protected function prepareFixture(Sabre_DAV_Client $client = NULL) {
		if ($client === NULL) {
			$client = $this->mockDavClient();
		}

		$this->fixture = new Tx_FalWebdav_Driver_WebDavDriver(array('baseUrl' => $this->baseUrl));
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

		$this->fixture->createFile('someFile', $mockedFolder);
	}

	/**
	 * @test
	 * @return t3lib_file_Folder
	 */
	public function createFolderIssuesCorrectCreateCommandOnServer() {
		/** @var $clientMock Sabre_DAV_Client */
		$clientMock = $this->mockDavClient();
		$clientMock->expects($this->at(0))->method('request')->with($this->equalTo('MKCOL'), $this->stringEndsWith('/mainFolder/subFolder/'));
		$this->prepareFixture($clientMock);
		$mockedFolder = $this->getSimpleFolderMock('/mainFolder/');

		return $this->fixture->createFolder('subFolder', $mockedFolder);
	}

	/**
	 * @test
	 * @depends createFolderIssuesCorrectCreateCommandOnServer
	 * @param t3lib_file_Folder $folder
	 */
	public function createFolderReturnsObjectWithCorrectIdentifier(t3lib_file_Folder $folder) {
		$this->assertEquals('/mainFolder/subFolder/', $folder->getIdentifier());
	}

	/**
	 * @test
	 */
	public function copyFileToTemporaryPathCreatesLocalCopyOfFile() {
		$fileContents = uniqid();

		/** @var $clientMock Sabre_DAV_Client */
		$clientMock = $this->mockDavClient();
		$clientMock->expects($this->once())->method('request')->with($this->equalTo('GET'), $this->stringEndsWith('/mainFolder/file.txt'))
			->will($this->returnValue(array('body' => $fileContents)));
		$this->prepareFixture($clientMock);
		$mockedFile = $this->getSimpleFileMock('/mainFolder/file.txt');

		$temporaryPath = $this->fixture->copyFileToTemporaryPath($mockedFile);
		$this->assertEquals($fileContents, file_get_contents($temporaryPath));

		unlink($temporaryPath);
	}

	/**
	 * @test
	 */
	public function moveFileWithinStorageIssuesCorrectCommand() {
		$mockedFile = $this->getSimpleFileMock('/someFile');
		$mockedFolder = $this->getSimpleFolderMock('/targetFolder/');

		/** @var $clientMock Sabre_DAV_Client */
		$clientMock = $this->mockDavClient();
			// TODO make the parameter matching here more special as soon as PHPUnit supports doing so
		$clientMock->expects($this->once())->method('request')->with($this->equalTo('MOVE'), $this->stringEndsWith('/someFile'),
			NULL, $this->logicalAnd($this->contains($this->baseUrl . 'targetFolder/movedFile'), $this->contains('T')));
		$this->prepareFixture($clientMock);

		$newFileIdentifier = $this->fixture->moveFileWithinStorage($mockedFile, $mockedFolder, 'movedFile');

		$this->assertEquals('/targetFolder/movedFile', $newFileIdentifier);
	}

	/**
	 * @return array
	 */
	public function isWithin_dataProvider() {
		return array(
			'file in folder' => array(
				'/someFolder',
				'/someFolder/file',
				TRUE
			),
			'file within subfolder' => array(
				'/someFolder',
				'/someFolder/someOtherFolder/file',
				TRUE
			),
			'file in root folder' => array(
				'/',
				'/file',
				TRUE
			)
		);
	}

	/**
	 * @test
	 * @dataProvider isWithin_dataProvider
	 */
	public function isWithinCorrectlyDetectsPaths($containerPath, $contentPath, $expectedResult) {
		$mockedFolder = $this->getSimpleFolderMock($containerPath);
		$this->prepareFixture();

		$actualResult = $this->fixture->isWithin($mockedFolder, $contentPath);

		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @test
	 */
	public function setFileContentsIssuesCorrectCommandOnServer() {
		$fileContents = uniqid();
		/** @var $clientMock Sabre_DAV_Client */
		$clientMock = $this->mockDavClient();
		$clientMock->expects($this->once())->method('request')->with($this->equalTo('PUT'), $this->stringEndsWith('/someFile'), $fileContents);
		$this->prepareFixture($clientMock);
		$mockedFile = $this->getSimpleFileMock('/someFile');

		$this->fixture->setFileContents($mockedFile, $fileContents);
	}

	/**
	 * @test
	 */
	public function getPublicUrlReturnsCorrectUrlIfStorageIsPublic() {
		$this->prepareFixture();
		$this->fixture->getStorage()->expects($this->any())->method('isPublic')->will($this->returnValue(TRUE));

		$mockedFile = $this->getSimpleFileMock('/someFolder/someFile.jpg');

		$this->fixture->getPublicUrl($mockedFile);
	}

	/**
	 * @test
	 */
	public function deleteFolderIssuesCorrectCommandOnServer() {
		/** @var $clientMock Sabre_DAV_Client */
		$clientMock = $this->mockDavClient();
		$clientMock->expects($this->once())->method('request')->with($this->equalTo('DELETE'), $this->stringEndsWith('/someFolder/'),
			$this->contains('Infinity'));
		$this->prepareFixture($clientMock);
		$mockedFolder = $this->getSimpleFolderMock('/someFolder/');

		$this->fixture->deleteFolder($mockedFolder);
	}
}
