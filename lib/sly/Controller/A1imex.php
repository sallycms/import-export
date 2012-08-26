<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
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
class sly_Controller_A1imex extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $baseDir;

	public function __construct() {
		parent::__construct();
		$this->baseDir = sly_A1_Util::getDataDir().DIRECTORY_SEPARATOR;
	}

	protected function init() {
		$user     = sly_Util_User::getCurrentUser();
		$subpages = array();
		$isAdmin  = $user->isAdmin();
		$is06     = version_compare(sly_Core::getVersion(), '0.6', '>=');

		// check permissions

		$canExport   = $isAdmin || $user->hasRight('import_export', 'export');
		$canImport   = $isAdmin || $user->hasRight('import_export', 'import');
		$canDownload = $isAdmin || $user->hasRight('import_export', 'download');
		$curPage     = sly_Core::getCurrentPage();

		// redirect the user to the corrent subpage, if needed

		if (!$canExport && $curPage === 'a1imex') {
			$this->redirect('', 'a1imex_import');
		}

		if (!$canImport && !$canDownload && $curPage === 'a1imex_import') {
			$this->redirect('', 'a1imex');
		}

		// init subpages

		if ($canExport && ($canImport || $canDownload)) {
			if ($canExport) {
				$subpages[] = array($is06 ? 'a1imex' : '', t('im_export_export'));
			}

			if ($canImport || $canDownload) {
				$subpages[] = array($is06 ? 'a1imex_import' : 'import', t('im_export_import'));
			}
		}

		// update navigation

		$nav  = sly_Core::getNavigation();
		$page = $nav->find('a1imex');

		if ($page) {
			foreach ($subpages as $subpage) {
				$page->addSubpage($subpage[0], $subpage[1]);
			}
		}

		// In case we have only one choice of subpages and that subpage is the import
		// page, change the lin of the main navigation point to 'a1imex_import'.

		if ($page && count($subpages) <= 1 && $curPage === 'a1imex_import') {
			$page->setName('a1imex_import');
			$page->setPageParam('a1imex_import');
		}

		$this->render('head.phtml', compact('subpages'), false);
	}

	public function indexAction() {
		$this->init();
		$this->exportView();
	}

	protected function exportView($params = array()) {
		$dirs = array(
			'assets'  => t('im_export_explain_assets'),
			'develop' => t('im_export_explain_develop'),
			substr(SLY_MEDIAFOLDER, strlen(SLY_BASE)+1) => t('im_export_explain_mediapool')
		);

		$dispatcher = sly_Core::dispatcher();
		$dirs       = $dispatcher->filter('SLY_A1_EXPORT_FILENAMES', $dirs);

		$params['dirs'] = $dirs;
		$this->render('export.phtml', $params, false);
	}

	public function exportAction() {
		$this->init();
		sly_A1_Util::cleanup();

		$user           = sly_Util_User::getCurrentUser();
		$canDownload    = $user->isAdmin() || $user->hasRight('import_export', 'download');
		$canAccessUsers = $user->isAdmin() || $user->hasRight('pages', 'user');
		$download       = $canDownload ? sly_post('download', 'boolean', false) : false;
		$systemExports  = sly_postArray('systemexports', 'string', array());
		$directories    = sly_postArray('directories', 'string', array());
		$extraFiles     = array();
		$addAddOns      = sly_post('addons', 'boolean', false);
		$addUsers       = $canAccessUsers ? sly_post('users', 'boolean', false) : false;
		$diffFriendly   = sly_post('diff_friendly', 'boolean', false);
		$comment        = sly_post('comment', 'string');

		// the plain SQL export cannot contain files
		if ($diffFriendly) {
			$systemExports = array('sql');
			$directories   = array();
		}

		$filename = sly_post('filename', 'string', 'sly_'.date('Ymd'));
		$orig     = $filename;
		$filename = strtolower($filename);
		$filename = preg_replace('#[^\.a-z0-9_-]#', '', $filename);
		$params   = array();
		$flash    = sly_Core::getFlashMessage();

		try {
			// did we alter the filename?

			if ($filename != $orig) {
				throw new Exception(t('im_export_filename_updated'), 1);
			}

			// collect additional export files

			if (in_array('configuration', $systemExports)) {
				$extraFiles[] = sly_Core::config()->getProjectConfigFile();
			}

			if (in_array('sql', $systemExports)) {
				$dumpFile = $this->getTempFileName('sql');
				$exporter = new sly_A1_Export_Database();
				$success  = $exporter->export($dumpFile, $diffFriendly, $addUsers);

				if (!$success) {
					throw new Exception(t('im_export_sql_dump_could_not_be_generated'));
				}

				$extraFiles[] = $dumpFile;
			}

			// nothing to do?

			if (empty($directories) && empty($extraFiles)) {
				throw new Exception(t('im_export_please_choose_files'));
			}

			// create appropriate archive wrapper

			$ext      = $diffFriendly ? 'sql' : 'zip';
			$filename = sly_A1_Util::getIteratedFilename($filename, '.'.$ext).'.'.$ext;
			$fullname = sly_A1_Util::getDataDir().DIRECTORY_SEPARATOR.$filename;
			$tmpFile  = $this->getTempFileName($ext);
			$archive  = sly_A1_Util::getArchive($tmpFile, $ext);

			try {
				$archive->open();
			}
			catch (Exception $e) {
				throw new Exception(t('im_export_file_could_not_be_generated').' '.t('im_export_you_have_no_write_permission_in', dirname($filename)));
			}

			// set metadata

			if ($addAddOns) {
				$archive->setAddOns($this->collectAddOns());
			}

			$archive->setComment($comment); // for later
			$archive->setVersion(sly_Core::getVersion('X.Y.*'));
			$archive->writeInfo();

			// do the actual work

			foreach ($directories as $dir) {
				$dir = SLY_BASE.DIRECTORY_SEPARATOR.$dir;

				if (is_dir($dir)) {
					$archive->addDirectoryRecursive($dir);
				}
				elseif (is_file($dir)) {
					$archive->addFile($dir);
				}
			}

			foreach ($extraFiles as $file) {
				$archive->addFile($file);
			}

			// cleanup

			$archive->close();

			if (isset($dumpFile)) {
				unlink($dumpFile);
			}

			// stream the file if requested

			if ($download) {
				while (ob_get_level()) ob_end_clean();
				header('Content-Type: application/zip');
				header('Content-Disposition: attachment; filename='.$filename);
				readfile($tmpFile);
				unlink($tmpFile);
				exit;
			}

			// move the final archive to the permanent location

			rename($tmpFile, $fullname);
			chmod($fullname, sly_Core::getFilePerm());

			// fresh form data

			$flash->addInfo(t('im_export_file_generated_in').' '.strtr($filename, '\\', '/'));
			return $this->redirectResponse();
		}
		catch (Exception $e) {
			if ($e->getCode() === 1) {
				$flash->addInfo($e->getMessage());
				$params['filename'] = $filename;
			}
			else {
				$flash->addWarning($e->getMessage());
			}
		}

		$this->exportView($params);
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();

		if (!$user) return false;
		if ($user->isAdmin()) return true;

		// We *dont* check if someone can export data, but whether *anything* is
		// granted. Inside init() we will redirect accordingly.

		$hasPageAccess = $user->hasRight('pages', 'a1imex');
		$canExport     = $user->hasRight('import_export', 'export');
		$canImport     = $user->hasRight('import_export', 'import');
		$canDownload   = $user->hasRight('import_export', 'download');

		return $hasPageAccess && ($canExport || $canImport || $canDownload);
	}

	protected function getViewFolder() {
		return dirname(__FILE__).'/../../../views/';
	}

	protected function getTempFileName($ext = 'tmp') {
		return sly_A1_Util::getTempDir().DIRECTORY_SEPARATOR.'a'.uniqid().'.'.$ext;
	}

	protected function collectAddOns() {
		$service = sly_Service_Factory::getAddOnService();
		$addons  = array();

		foreach ($service->getAvailableAddons() as $addon) {
			$ignore = $service->getComposerKey($addon, 'imex-ignore', false);

			if ($ignore !== true && $ignore !== 'true') {
				$addons[] = $addon;
			}
		}

		return $addons;
	}
}
