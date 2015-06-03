<?php
namespace TYPO3\FalWebdav\Dav;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

include_once __DIR__ . '/../../Resources/Composer/vendor/autoload.php';

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
	 * @param resource $curlHandle
	 *
	 * @return array
	 */
	protected function curlExec($curlHandle) {
		if ($this->verifyCertificates === FALSE) {
			curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, FALSE);
		}

		return parent::curlExec($curlHandle);
	}

	function propFind($url, array $properties = NULL, $depth = 0) {
		if ($properties === NULL) {
			$properties = array(
				'{DAV:}resourcetype',
				'{DAV:}creationdate',
				'{DAV:}getcontentlength',
				'{DAV:}getlastmodified'
			);
		}
		return parent::propFind($url, $properties, $depth); // TODO: Change the autogenerated stub
	}


}

?>