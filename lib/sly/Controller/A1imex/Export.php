<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
*/

/**
 * Controller for Exporting 
 *
 * @author zozi
 */
class sly_Controller_A1imex_Export extends sly_Controller_A1imex{

	protected function export() {
		$download      = sly_post('download', 'boolean', false);
		$systemexports = sly_postArray('systemexports', 'string', array());
		$exportfiles  = sly_postArray('directories', 'string', array());

		$filename = sly_post('filename', 'string', 'sly_'.date('Ymd'));
		$orig     = $filename;
		$filename = strtolower($filename);
		$filename = preg_replace('#[^\.a-z0-9_-]#', '', $filename);

		$params = array();
		$params['warning'] = '';
		$params['info']    = '';

		$success = true;

		if ($filename != $orig) {
			$params['info'] .= t('im_export_filename_updated');
			$success = false;
		}

		if($success === true) {
			$exportPath = sly_A1_Helper::getDataDir().DIRECTORY_SEPARATOR;
			$filename   = sly_A1_Helper::getIteratedFilename($exportPath, $filename, '.tar.gz');

			@ini_set('memory_limit', '64M');

			if (in_array('configuration', $systemexports)) {
				$configfilename = sly_Core::config()->getProjectConfigFile();
				$exportfiles[] = $configfilename;
			}

			if (in_array('sql', $systemexports)) {
				$addonservice = sly_Service_Factory::getService('AddOn');
				$sqltempdir   = $addonservice->internalFolder('import_export');
				$sqlfilename  = $sqltempdir.DIRECTORY_SEPARATOR.$filename.'.sql';
				$exporter     = new sly_A1_Export_Database();
				$success      = $exporter->export($sqlfilename);
				if($success) {
					$exportfiles[] = $sqlfilename;
				}else {
					$params['warning'] .= t('im_export_sql_dump_could_not_be_generated');
				}
			}
		}

		if ($success === true && empty($exportfiles)) {
			$params['warning'] .= t('im_export_please_choose_files');
			$success = false;
		}

		if ($success === true) {
			foreach($exportfiles as $key => $file) {
				$exportfiles[$key] = str_replace(SLY_BASE, '.'.DIRECTORY_SEPARATOR, $file);
			}

			$exporter   = new sly_A1_Export_Files();
			$success    = $exporter->export($exportPath.$filename.'.tar.gz', $exportfiles);
			if (in_array('sql', $systemexports)) {
				unlink($sqlfilename);
			}
			if($success) {
				if ($download) {
					while (ob_get_level()) ob_end_clean();
					$filename = $filename.'.tar.gz';
					header("Content-Type: tar/gzip");
					header("Content-Disposition: attachment; filename=$filename");
					readfile($exportPath.$filename);
					unlink($exportPath.$filename);
					$this->index();
					exit;
				}
			}else {
				$params['warning'] .= t('im_export_file_could_not_be_generated').' '.t('im_export_check_rights_in_directory').' '.$exportPath;
			}
		}

		if ($success === true) {
			$params['info']          = t('im_export_file_generated_in').' '.strtr($filename.'.tar.gz', '\\', '/');
			$params['filename']      = 'sly_'.date('Ymd');
			$params['systemexports'] = array();
			$params['selectedDirs']  = array();
			$params['download']      = array(0 => true);
		}else {
			$params['filename']      = addslashes($filename);
			$params['selectedDirs']  = $selectedDirs;
			$params['systemexports'] = $systemexports;
			$params['download']      = array(intval($download) => true);
		}
		$this->exportView($params);
	}

	protected function checkPermission() {
		$user = sly_Service_Factory::getService('User')->getCurrentUser();
		return $user->hasRight('import_export[export]') || $user->isAdmin();
	}
}
?>
