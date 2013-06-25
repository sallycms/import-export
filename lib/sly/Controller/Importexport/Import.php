<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

use sly\ImportExport\Util;

/**
 * Basic Controller for Import and Export Pages
 *
 * @author zozi
 */
class sly_Controller_Importexport_Import extends sly_Controller_Importexport {
	public function indexAction() {
		$service  = $this->container['sly-importexport-service'];
		$user     = $this->getCurrentUser();
		$canEx    = $user->isAdmin() || $user->hasPermission('import_export', 'export');
		$canIm    = $user->isAdmin() || $user->hasPermission('import_export', 'import');
		$canDL    = $user->isAdmin() || $user->hasPermission('import_export', 'download');
		$archives = $service->getArchives();

		// sort archives by date
		foreach ($archives as $idx => $archive) {
			$archives[$idx] = $service->getArchiveInfo($archive);
		}

		usort($archives, function($a1, $a2) {
			$date1 = $a1['date'];
			$date2 = $a2['date'];

			return $date1 === $date2 ? 0 : ($date1 < $date2 ? -1 : 1);
		});

		$archives = array_reverse($archives);

		$this->pageHeader();
		$this->render('import.phtml', array(
			'files' => $archives,
			'canEx' => $canEx,
			'canIm' => $canIm,
			'canDL' => $canDL
		), false);
	}

	public function importAction() {
		$this->init();

		$filename = sly_request('file', 'string');
		$flash    = sly_Core::getFlashMessage();

		@set_time_limit(0);

		try {
			sly_A1_Util::cleanup();
			sly_A1_Util::import($filename);

			// import all dumps we can find

			$tmpDir = new sly_Util_Directory(sly_A1_Util::getTempDir(), false);
			$files  = $tmpDir->listPlain(true, false, false, true);

			foreach ($files as $file) {
				if (substr($file, -4) === '.sql') {
					$importer = new sly_DB_Importer();
					$importer->import($file);
				}

				unlink($file);
			}

			// try the old-fashioned way

			if (count($files) === 0) {
				$srv  = sly_Service_Factory::getAddOnService();
				$dir  = $srv->internalDirectory('sallycms/import-export');
				$file = $dir.'/'.str_replace('.zip', '.sql', $filename);

				if (file_exists($file)) {
					$importer = new sly_DB_Importer();
					$importer->import($file);
				}
			}

			$flash->prependInfo(t('im_export_file_imported'), false);
			sly_Core::dispatcher()->notify('SLY_A1_AFTER_IMPORT', $filename);
		}
		catch (Exception $e) {
			$flash->appendWarning($e->getMessage());
		}

		return $this->redirectResponse();
	}

	public function deleteAction() {
		$this->init();

		$filename = sly_request('file', 'string');
		$flash    = sly_Core::getFlashMessage();

		if (unlink($this->baseDir.$filename)) {
			$flash->addInfo(t('im_export_file_deleted'));
		}
		else {
			$flash->addWarning(t('im_export_file_could_not_be_deleted'));
		}

		return $this->redirectResponse();
	}

	public function downloadAction() {
		$this->init();

		$filename = sly_request('file', 'string');
		$filename = preg_replace('#[^\.a-z0-9_-]#', '', $filename);
		$filename = basename($filename);

		if (!empty($filename) && file_exists($this->baseDir.$filename)) {
			while (ob_get_level()) ob_end_clean();
			header('Content-Type: tar/gzip');
			header('Content-Disposition: attachment; filename='.$filename);
			readfile($this->baseDir.$filename);
			exit;
		}

		$flash = sly_Core::getFlashMessage();
		$flash->addWarning(t('im_export_selected_file_not_exists'));
		return $this->redirectResponse();
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		if (!$user) return false;

		if (class_exists('sly_Util_Csrf') && in_array($action, array('delete', 'import'))) {
			sly_Util_Csrf::checkToken();
		}

		if ($user->isAdmin()) return true;

		$hasPageAccess = $user->hasRight('pages', 'a1imex');

		if (!$hasPageAccess || !in_array($action, array('index', 'download', 'delete', 'import'))) {
			return false;
		}

		$canExport   = $user->hasRight('import_export', 'export');
		$canImport   = $user->hasRight('import_export', 'import');
		$canDownload = $user->hasRight('import_export', 'download');

		switch ($action) {
			case 'index':    return ($canImport || $canDownload || $canExport);
			case 'download': return $canDownload;
			case 'delete':   return $canExport;
			case 'import':   return $canImport;
		}
	}
}
