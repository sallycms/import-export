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
 * Basic Controller for Import and Export Pages
 *
 * @author zozi
 */
class sly_Controller_A1imex extends sly_Controller_Sally {
	const VIEW_PATH = 'addons/import_export/views/';

	protected function index() {
		$params['filename']      = 'sly_'.date('Ymd');
		$params['systemexports'] = array();
		$params['selectedDirs']  = array();
		$params['download']      = array(0);
		$this->exportView($params);
	}

	protected function exportView($params) {
		$dirs = array(
			'assets'  => t('im_export_explain_assets'),
			'develop' => t('im_export_explain_develop'),
			substr(SLY_MEDIAFOLDER, strlen(SLY_BASE)+1) => t('im_export_explain_mediapool')
		);

		$dispatcher     = sly_Core::dispatcher();
		$dirs           = $dispatcher->filter('SLY_A1_EXPORT_FILENAMES', $dirs);
		$params['dirs'] = $dirs;

		$this->render(self::VIEW_PATH.'export.phtml', $params);
	}

	protected function init() {
		$user     = sly_Service_Factory::getService('User')->getCurrentUser();
		$subpages = array();

		if ($user->hasRight('import_export[export]') || $user->isAdmin()){
			$subpages[] = array('', t('im_export_export'));
		}

		if ($user->hasRight('import_export[import]') || $user->isAdmin()){
			$subpages[] = array('import', t('im_export_import'));
		}

		$nav  = sly_Core::getNavigation();
		$page = $nav->find('import_export', 'addons');

		if ($page) {
			foreach ($subpages as $subpage) {
				$page->addSubpage($subpage[0], $subpage[1]);
			}
		}

		$this->render(self::VIEW_PATH.'head.phtml', compact('subpages'));
	}

	protected function export() {
		$download      = sly_post('download', 'boolean', false);
		$systemexports = sly_postArray('systemexports', 'string', array());
		$exportfiles   = sly_postArray('directories', 'string', array());

		$filename = sly_post('filename', 'string', 'sly_'.date('Ymd'));
		$orig     = $filename;
		$filename = strtolower($filename);
		$filename = preg_replace('#[^\.a-z0-9_-]#', '', $filename);
		$params   = array('warning' => '', 'info' => '');
		$success  = true;

		if ($filename != $orig) {
			$params['info'] .= t('im_export_filename_updated');
			$success = false;
		}

		if ($success === true) {
			$exportPath = sly_A1_Helper::getDataDir().DIRECTORY_SEPARATOR;
			$filename   = sly_A1_Helper::getIteratedFilename($exportPath, $filename, '.zip');

			@ini_set('memory_limit', '64M');

			if (in_array('configuration', $systemexports)) {
				$configfilename = sly_Core::config()->getProjectConfigFile();
				$exportfiles[]  = substr($configfilename, strlen(SLY_BASE)+1);
			}

			if (in_array('sql', $systemexports)) {
				$addonservice = sly_Service_Factory::getService('AddOn');
				$sqltempdir   = $addonservice->internalFolder('import_export');
				$sqlfilename  = $sqltempdir.DIRECTORY_SEPARATOR.$filename.'.sql';
				$exporter     = new sly_A1_Export_Database();
				$success      = $exporter->export($sqlfilename);

				if ($success) {
					$exportfiles[] = substr($sqlfilename, strlen(SLY_BASE)+1);
				}
				else {
					$params['warning'] .= t('im_export_sql_dump_could_not_be_generated');
				}
			}
		}

		if ($success === true && empty($exportfiles)) {
			$params['warning'] .= t('im_export_please_choose_files');
			$success = false;
		}

		if ($success === true) {
			$exporter = new sly_A1_Export_Files();
			$success  = $exporter->export($exportPath.$filename.'.zip', $exportfiles);

			if (in_array('sql', $systemexports)) {
				unlink($sqlfilename);
			}
			if ($success) {
				if ($download) {
					while (ob_get_level()) ob_end_clean();
					$filename = $filename.'.zip';
					header('Content-Type: application/zip');
					header('Content-Disposition: attachment; filename='.$filename);
					readfile($exportPath.$filename);
					unlink($exportPath.$filename);
					$this->index();
					exit;
				}
			}
			else {
				$params['warning'] .= t('im_export_file_could_not_be_generated').' '.t('im_export_check_rights_in_directory').' '.$exportPath;
			}
		}

		if ($success === true) {
			$params['info']          = t('im_export_file_generated_in').' '.strtr($filename.'.zip', '\\', '/');
			$params['filename']      = 'sly_'.date('Ymd');
			$params['systemexports'] = array();
			$params['selectedDirs']  = array();
			$params['download']      = array(0);
		}
		else {
			$params['filename']      = $filename;
			$params['selectedDirs']  = $exportfiles;
			$params['systemexports'] = $systemexports;
			$params['download']      = array(intval($download));
		}

		$this->exportView($params);
	}

	protected function checkPermission() {
		$user = sly_Service_Factory::getService('User')->getCurrentUser();
		return $user->hasRight('import_export[export]') || $user->isAdmin();
	}
}
