<?php
/*
 * Copyright (C) 2009 REDAXO
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License Version 2 as published by the
 * Free Software Foundation.
 */

/**
 * @package redaxo4
 */

$info     = '';
$warning  = '';
$function = rex_request('function', 'string');
$filename = rex_request('file', 'string');
$baseDir  = sly_A1_Helper::getDataDir().'/';

if (!empty($filename)) {
	$filename = str_replace('/', '', $filename);
	$fileInfo = sly_A1_Helper::getFileInfo($baseDir.$filename);

	if (!$fileInfo['exists']) {
		$warning  = t('im_export_selected_file_not_exists');
		$filename = '';
		$function = '';
	}
	elseif ($function == 'fileimport' && $fileInfo['type'] != 'tar') {
		$filename = '';
		$function = '';
	}
}

$importer = null;

// Funktionen abarbeiten

if ($function == 'delete') {
	if (unlink($baseDir.$filename)) $info = $I18N->msg('im_export_file_deleted');
	else $warning = t('im_export_file_could_not_be_deleted');
}
elseif ($function == 'fileimport') {
	$importer = new sly_A1_Import_Files();
	$retval = $importer->import($baseDir.$filename);
	
	if ($retval['state']) {
		$info = $retval['message'];
		$addonservice = sly_Service_Factory::getService('AddOn');
		$sqltempdir   = $addonservice->internalFolder('import_export');
		$sqlfilename   = $sqltempdir.DIRECTORY_SEPARATOR.$fileInfo['filename'].(!empty($fileInfo['description']) ? '_'.$fileInfo['description']: '').'.sql';
		if(file_exists($sqlfilename)) {
			$importer = new sly_A1_Import_Database();
			$sqlretval = $importer->import($sqlfilename);
			if ($sqlretval['state']) {
				$info .= $sqlretval['message'];
			}else {
				$warning .= $sqlretval['message'];
			}
			unlink($sqlfilename);
		}
	}
	else $warning .= $retval['message'];

}

// View anzeigen

include $REX['INCLUDE_PATH'].'/addons/import_export/templates/import.phtml';
