<?php

/** @var t3lib_file_Driver_DriverRegistry $registry */
$registry = t3lib_div::makeInstance('t3lib_file_Driver_DriverRegistry');
$registry->registerDriverClass('Tx_FalWebdav_Driver_WebDavDriver', 'WebDav');

?>
