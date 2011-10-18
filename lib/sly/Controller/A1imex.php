<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * Basic Controller for Import and Export Pages
 *
 * @author zozi
 */
class sly_Controller_A1imex extends sly_Controller_Backend {
	protected $baseDir;

	public function __construct() {
		parent::__construct();
		$this->baseDir = sly_A1_Util::getDataDir().DIRECTORY_SEPARATOR;
	}

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

		print $this->render('export.phtml', $params);
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

		print $this->render('head.phtml', compact('subpages'));
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
			$filename     = sly_A1_Util::getIteratedFilename($filename, '.zip');
			$addonservice = sly_Service_Factory::getAddOnService();
			$addonList    = implode("\n", $addonservice->getAvailableAddons());

			if (in_array('configuration', $systemexports)) {
				$configfilename = sly_Core::config()->getProjectConfigFile();
				$exportfiles[]  = substr($configfilename, strlen(SLY_BASE)+1);
			}

			if (in_array('sql', $systemexports)) {
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
			if (class_exists('ZipArchive')) {
				$exporter = new sly_A1_Export_Files_ZipArchive();
			}
			else {
				$exporter = new sly_A1_Export_Files_PclZip();
			}

			$filename = $filename.'.zip';
			$success  = $exporter->export($this->baseDir.$filename, $exportfiles, $addonList);

			if (in_array('sql', $systemexports)) {
				unlink($sqlfilename);
			}
			if ($success) {
				if ($download) {
					while (ob_get_level()) ob_end_clean();
					header('Content-Type: application/zip');
					header('Content-Disposition: attachment; filename='.$filename);
					readfile($this->baseDir.$filename);
					unlink($this->baseDir.$filename);
					exit;
				}
				chmod($this->baseDir.$filename, sly_Core::getFilePerm());
			}
			else {
				$params['warning'] .= t('im_export_file_could_not_be_generated').' '.t('im_export_you_have_no_write_permission_in', $exportPath);
			}
		}

		if ($success === true) {
			$params['info']          = t('im_export_file_generated_in').' '.strtr($filename, '\\', '/');
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
		$user = sly_Util_User::getCurrentUser();
		return $user->isAdmin() || $user->hasRight('import_export[export]');
	}

	protected function getViewFolder() {
		return SLY_ADDONFOLDER.'/import_export/views/';
	}
}
