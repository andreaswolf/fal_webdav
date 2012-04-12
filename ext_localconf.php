<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

include_once t3lib_extMgm::extPath('fal_webdav') . 'Resources/Php/SabreDAV/lib/Sabre/autoload.php';

$newPath = t3lib_extMgm::extPath('fal_webdav') . 'Resources/Php/SabreDAV/lib/';
set_include_path($newPath . PATH_SEPARATOR . get_include_path());

/** @var t3lib_file_Driver_DriverRegistry $registry */
$registry = t3lib_div::makeInstance('t3lib_file_Driver_DriverRegistry');
$registry->registerDriverClass('Tx_FalWebdav_Driver_WebDavDriver', 'WebDav', 'WebDAV', 'FILE:EXT:fal_webdav/Configuration/FlexForm/WebDavDriverFlexForm.xml');

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'EXT:fal_webdav/Classes/Backend/TceMainHook.php:&Tx_FalWebdav_Backend_TceMainHook';
