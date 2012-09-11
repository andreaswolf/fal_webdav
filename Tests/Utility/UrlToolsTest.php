<?php
namespace TYPO3\FalWebdav\Tests\Utility;

/**
 * Testcase for the url tools class
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 */
class UrlToolsTest extends \Tx_Phpunit_TestCase {

	public function urlDataProvider() {
		return array(
			'regular URL with username and password' => array(
				'http://someuser:somepass@localhost/test.php',
				array('http://localhost/test.php', 'someuser', 'somepass')
			),
			'URL with just user' => array(
				'http://someuser@localhost/test.php',
				array('http://localhost/test.php', 'someuser', '')
			),
			'HTTPS URL with username and password' => array(
				'https://someuser:somepass@localhost/test.php',
				array('https://localhost/test.php', 'someuser', 'somepass')
			),
			'URL without authentication' => array(
				'http://localhost/test.php',
				array('http://localhost/test.php', '', '')
			)
		);
	}

	/**
	 * @test
	 * @dataProvider urlDataProvider
	 */
	public function usernameAndPasswordAreProperlyExtractedFromUrl($url, $expectedOutput) {
		$output = \TYPO3\FalWebdav\Utility\UrlTools::extractUsernameAndPasswordFromUrl($url);

		$this->assertEquals($expectedOutput, $output);
	}
}
