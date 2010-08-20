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

// Für größere Exports den Speicher für PHP erhöhen.
@ini_set('memory_limit', '64M');

include_once $REX['INCLUDE_PATH'].'/addons/import_export/functions/function_import_export.inc.php';

$info     = '';
$warning  = '';
$function = sly_request('function', 'string');
$filename = sly_post('filename', 'string', 'sly_'.date('Ymd'));
$types    = sly_postArray('types', 'string', array());
$download = sly_post('download', 'boolean', false);
if ($function == 'export') {
	// Dateiname entschärfen

	$orig     = $filename;
	$filename = strtolower($filename);
	$filename = preg_replace('#[^\.a-z0-9_-]#', '', $filename);

	if ($filename != $orig) {
		$info = t('im_export_filename_updated');
		$_POST['filename'] = addslashes($filename);
	}
	else {
		$content      = '';
		$hasContent   = false;
		$exportPath   = sly_A1_Helper::getDataDir().DIRECTORY_SEPARATOR;
		$filename     = sly_A1_Helper::getIteratedFilename($exportPath, $filename, '.tar.gz');
		$export  = sly_postArray('directories', 'string');

		if (in_array('sql', $types)) {
			$addonservice = sly_Service_Factory::getService('AddOn');
			$sqltempdir   = $addonservice->internalFolder('import_export');
			$sqlfilename   = $sqltempdir.DIRECTORY_SEPARATOR.$filename.'.sql';
			$exporter   = new sly_A1_Export_Database();
			$hasContent = $exporter->export($sqlfilename);
			if($hasContent) {
				$export[] = $sqlfilename;
			}else {
				$warning .= t('im_export_sql_dump_could_not_be_generated');
			}
		}
		
		if (in_array('configuration', $types)) {
			$configfilename = sly_Core::config()->getProjectCacheFile();
			$export[] = $configfilename;
		}

		$dispatcher   = sly_Core::dispatcher();

		if($dispatcher->hasListeners('SLY_A1_EXPORT_FILENAMES')) {
			$export = $dispatcher->filter('SLY_A1_EXPORT_FILENAMES', $export, array('filename' => $filename));
		}

		foreach($export as $key => $file) {
			$export[$key] = str_replace(SLY_BASE, '.'.DIRECTORY_SEPARATOR, $file);
		}

		if (empty($export)) {
			$warning .= t('im_export_please_choose_files');
		}
		else {
			$exporter   = new sly_A1_Export_Files($export);
			$hasContent = $exporter->export($exportPath.$filename.'.tar.gz');
			if (in_array('sql', $types)) {
				unlink($sqlfilename);
			}
			if($hasContent) {
				if ($download) {
					while (ob_get_level()) ob_end_clean();
					$filename = $filename.'.tar.gz';
					header("Content-Type: tar/gzip");
					header("Content-Disposition: attachment; filename=$filename");
					readfile($exportPath.$filename);
					unlink($exportPath.$filename);
					exit;
				}
				$info = t('im_export_file_generated_in').' '.strtr($filename.'.tar.gz', '\\', '/');
			}else {
				$warning .= t('im_export_file_could_not_be_generated').' '.t('im_export_check_rights_in_directory').' '.$exportPath;
			}
		}
	}
}

// View anzeigen

include $REX['INCLUDE_PATH'].'/addons/import_export/templates/export.phtml';
