<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

include_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('fal_webdav') . 'Resources/Php/SabreDAV/lib/Sabre/autoload.php';

$newPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('fal_webdav') . 'Resources/Php/SabreDAV/lib/';
set_include_path($newPath . PATH_SEPARATOR . get_include_path());

/** @var \TYPO3\CMS\Core\Resource\Driver\DriverRegistry $registry */
$registry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Resource\Driver\DriverRegistry');
$registry->registerDriverClass('TYPO3\FalWebdav\Driver\WebDavDriver', 'WebDav', 'WebDAV', 'FILE:EXT:fal_webdav/Configuration/FlexForm/WebDavDriverFlexForm.xml');

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['fal_webdav'] = 'Tx_FalWebdav_Backend_TceMainHook';


	// Cache configuration, see http://wiki.typo3.org/Caching_Framework
if (!is_array($TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['tx_falwebdav_directorylisting'])) {
    $TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['tx_falwebdav_directorylisting'] = array();
}
