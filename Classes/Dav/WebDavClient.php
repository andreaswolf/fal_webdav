<?php
namespace TYPO3\FalWebdav\Dav;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Nicole Cordes
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

include_once __DIR__ . '/../../Resources/Php/SabreDAV/vendor/autoload.php';

use Sabre\DAV\Client;

/**
 * Helper class to circumvent limitations in SabreDAV's support for cURL's certificate verification options.
 */
class WebDavClient extends Client {

	/**
	 * Trigger to enable/disable peer certificate verification
	 *
	 * @var boolean
	 */
	protected $verifyCertificates = TRUE;

	/**
	 * @param boolean $peerVerification
	 */
	public function setCertificateVerification($peerVerification) {
		$this->verifyCertificates = $peerVerification;
	}

	/**
	 * Wrapper for all cUrl functions.
	 *
	 * @param string $url
	 * @param array $settings
	 *
	 * @return array
	 */
	protected function curlRequest($url, $settings) {

		$curl = curl_init($url);
		curl_setopt_array($curl, $settings);

		if ($this->verifyCertificates === FALSE) {
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		}

		return array(
			curl_exec($curl),
			curl_getinfo($curl),
			curl_errno($curl),
			curl_error($curl)
		);

	}
}

?>