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
class sly_Controller_A1imex_Import extends sly_Controller_A1imex {
	protected $baseDir;

	public function __construct() {
		parent::__construct();
		$this->baseDir = SLY_DATAFOLDER.DIRECTORY_SEPARATOR.'import_export'.DIRECTORY_SEPARATOR;
	}

	protected function index() {
		$this->importView();
	}

	protected function importView($params = array()) {
		$params['files'] = sly_A1_Helper::getFileArchives($this->baseDir);
		$this->render(self::VIEW_PATH.'import.phtml', $params);
	}

	protected function import() {
		$params   = array('warning' => '', 'info' => '');
		$filename = sly_request('file', 'string');
		$fileInfo = sly_A1_Helper::getFileInfo($this->baseDir.$filename);

		try {
			$importer = new sly_A1_Import_Files();
			$importer->import($this->baseDir.$filename);
			$params['info'] .= t('im_export_file_imported').'<br />';
			$state = true;
		}
		catch (Exception $e) {
			$params['warning'] .= $e->getMessage();
			$state = false;
		}

		if ($state) {
			$addonservice = sly_Service_Factory::getService('AddOn');
			$sqltempdir   = $addonservice->internalFolder('import_export');
			$sqlfilename  = explode('.', $filename);
			$sqlfilename  = $sqltempdir.DIRECTORY_SEPARATOR.$sqlfilename[0].'.sql';

			if (file_exists($sqlfilename)) {
				$importer  = new sly_DB_Importer();
				$sqlretval = $importer->import($sqlfilename);

				if ($sqlretval['state']) {
					$params['info'] .= $sqlretval['message'];
				}
				else {
					$params['warning'] .= $sqlretval['message'];
				}

				unlink($sqlfilename);
			}
		}

		$this->importView($params);
	}

	protected function delete() {
		$filename = sly_request('file', 'string');
		$params   = array();

		if (unlink($this->baseDir.$filename)) {
			$params['info'] = t('im_export_file_deleted');
		}
		else {
			$params['warning'] = t('im_export_file_could_not_be_deleted');
		}

		$this->importView($params);
	}

	protected function download() {
		$filename = sly_request('file', 'string');

		if (!empty($filename) && file_exists($this->baseDir.$filename)) {
			while (ob_get_level()) ob_end_clean();
			header('Content-Type: tar/gzip');
			header('Content-Disposition: attachment; filename='.$filename);
			readfile($this->baseDir.$filename);
			exit;
		}

		$params = array('warning' => t('im_export_selected_file_not_exists'));
		$this->importView($params);
	}

	protected function checkPermission() {
		$user = sly_Service_Factory::getService('User')->getCurrentUser();
		return $user->hasRight('import_export[import]') || $user->isAdmin();
	}
}
