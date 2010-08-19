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
class sly_A1_Import_Files
{
	public function __construct()
	{
		// pass...
	}

	public function import($filename)
	{
		global $REX, $I18N;

		$baseDir = SLY_BASE;
		$return['state'] = false;

		if (!file_exists($filename)) {
			$return['message'] = 'Datei nicht gefunden.<br />';
			return $return;
		}

		if (empty($filename) || substr($filename, -7, 7) != '.tar.gz') {
			$return['message'] = $I18N->msg('im_export_no_import_file_chosen').'<br />';
			return $return;
		}

		// Da keine dynamischen Dateien innerhalb von redaxo/ erzeugt werden
		// *sollten*, können wir das Verzeichnis auch ruhig leeren, wenn es mit
		// im Backup enthalten ist.

		$tar      = new sly_A1_Archive_Tar($filename);

		chdir($baseDir);

		// Extensions auslösen

		$tar = rex_register_extension_point('SLY_A1_BEFORE_FILE_IMPORT', $tar);

		// Tar auspacken

		if (!$tar->extract()) {
			$msg = $I18N->msg('im_export_problem_when_extracting').'<br />';
		}
		else {
			$msg = $I18N->msg('im_export_file_imported').'<br />';
		}

		// Extensions auslösen

		$tar = rex_register_extension_point('SLY_A1_AFTER_FILE_IMPORT', $tar);
		chdir('sally');

		$return['state']   = true;
		$return['message'] = $msg;
		return $return;
	}
}
