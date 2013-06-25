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
use sly\ImportExport\Archive\Base;

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
		$filename = $this->getSelectedFile(false);
		$importer = $this->container['sly-importexport-importer'];
		$flash    = $this->container['sly-flash-message'];

		try {
			$service->import($filename, SLY_BASE);
			$flash->prependInfo(t('im_export_file_imported'), false);
		}
		catch (Exception $e) {
			$flash->appendWarning($e->getMessage());
		}

		return $this->redirectResponse();
	}

	public function deleteAction() {
		$flash    = $this->container['sly-flash-message'];
		$filename = $this->getSelectedFile(true);
		$basename = basename($filename);

		if (@unlink($filename)) {
			$flash->addInfo(t('im_export_file_deleted', $basename));
		}
		else {
			$flash->addWarning(t('im_export_file_could_not_be_deleted', $basename));
		}

		return $this->redirectResponse();
	}

	public function downloadAction() {
		$filename = $this->getSelectedFile(true);

		if ($filename && file_exists($filename)) {
			$response = new sly_Response_Stream($filename);
			$type     = Util::guessFileType($filename);

			if ($type === Base::TYPE_SQL) {
				$response->setContentType('text/sql', 'UTF-8');
			}
			else {
				$response->setContentType('application/zip', null);
			}

			$response->setHeader('Content-Disposition', 'attachment; filename="'.basename($filename).'"');

			return $response;
		}

		$this->container['sly-flash-message']->addWarning(t('im_export_selected_file_not_exists', basename($filename)));

		return $this->redirectResponse();
	}

	public function checkPermission($action) {
		$user = $this->getCurrentUser();
		if (!$user) return false;

		if (in_array($action, array('delete', 'import'))) {
			sly_Util_Csrf::checkToken();
		}

		if ($user->isAdmin()) return true;

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

	protected function getSelectedFile($absolute) {
		$request    = $this->getRequest();
		$service    = $this->container['sly-importexport-service'];
		$storageDir = $service->getStorageDir();
		$filename   = $request->request('file', 'string');
		$filename   = preg_replace('#[^a-z0-9,._-]#', '', $filename);
		$filename   = basename($filename);

		return $absolute ? ($storageDir.'/'.$filename) : $filename;
	}
}
