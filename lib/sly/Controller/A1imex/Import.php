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
class sly_Controller_A1imex_Import extends sly_Controller_A1imex {
	protected function index() {
		$this->importView();
	}

	protected function importView($params = array()) {
		$params['files'] = sly_A1_Util::getArchives($this->baseDir);
		print $this->render('import.phtml', $params);
	}

	protected function import() {
		$params   = array('warning' => '', 'info' => '');
		$filename = sly_request('file', 'string');

		try {
			sly_A1_Util::import($filename);
			$params['info'] .= t('im_export_file_imported').'<br />';
			$state = true;
		}
		catch (Exception $e) {
			$params['warning'] .= $e->getMessage();
			$state = false;
		}

		if ($state) {
			$addonservice = sly_Service_Factory::getAddOnService();
			$sqltempdir   = $addonservice->internalFolder('import_export');
			$error        = false;

			$addonListFilename = $sqltempdir.DIRECTORY_SEPARATOR.'addons.php';

			if (file_exists($addonListFilename)) {
				$handle = fopen($addonListFilename, 'r');
				flock($handle, LOCK_SH);

				include $addonListFilename;

				// release lock again
				flock($handle, LOCK_UN);
				fclose($handle);

				$availableAddons = $addonservice->getAvailableAddons();
				$missingAddons   = array_diff($addons, array_intersect($addons, $availableAddons));

				if (isset($addons) && count($missingAddons)) {
					$params['warning'] .= t('im_export_missing_addons_for_db_import').': '.implode(', ', $missingAddons);
					$error = true;
				}

				unlink($addonListFilename);
			}

			$sqlfilename = explode('.', $filename);
			$sqlfilename = $sqltempdir.DIRECTORY_SEPARATOR.$sqlfilename[0].'.sql';

			if (!$error && file_exists($sqlfilename)) {
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
		$user = sly_Util_User::getCurrentUser();
		return $user->isAdmin() || $user->hasRight('import_export[import]');
	}
}
