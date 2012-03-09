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

		if ($isAdmin || $user->hasRight('import_export[export]')) {
			$subpages[] = array($is06 ? 'a1imex' : '', t('im_export_export'));
		}

		if ($isAdmin || $user->hasRight('import_export[import]')) {
			$subpages[] = array($is06 ? 'a1imex_import' : 'import', t('im_export_import'));
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

		$dispatcher     = sly_Core::dispatcher();
		$dirs           = $dispatcher->filter('SLY_A1_EXPORT_FILENAMES', $dirs);
		$params['dirs'] = $dirs;

		print $this->render('export.phtml', $params);
	}

	public function exportAction() {
		$this->init();
		sly_A1_Util::cleanup();

		$download      = sly_post('download', 'boolean', false);
		$systemExports = sly_postArray('systemexports', 'string', array());
		$directories   = sly_postArray('directories', 'string', array());
		$extraFiles    = array();
		$addComponents = sly_post('components', 'boolean');
		$comment       = sly_post('comment', 'string');

		$filename = sly_post('filename', 'string', 'sly_'.date('Ymd'));
		$orig     = $filename;
		$filename = strtolower($filename);
		$filename = preg_replace('#[^\.a-z0-9_-]#', '', $filename);
		$params   = array('warning' => '', 'info' => '');
		$success  = true;

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
				$success  = $exporter->export($dumpFile);

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

			$filename = sly_A1_Util::getIteratedFilename($filename, '.zip').'.zip';
			$fullname = sly_A1_Util::getDataDir().DIRECTORY_SEPARATOR.$filename;
			$tmpFile  = $this->getTempFileName('zip');
			$archive  = sly_A1_Util::getArchive($tmpFile);

			try {
				$archive->open();
			}
			catch (Exception $e) {
				throw new Exception(t('im_export_file_could_not_be_generated').' '.t('im_export_you_have_no_write_permission_in', dirname($filename)));
			}

			// set metadata

			if ($addComponents) {
				$archive->setComponents($this->collectComponents());
			}

			$archive->setComment($comment); // for later
			$archive->setVersion(sly_Core::getVersion('X.Y'));
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

			$params['info'] = t('im_export_file_generated_in').' '.strtr($filename, '\\', '/');
		}
		catch (Exception $e) {
			if ($e->getCode() === 1) {
				$params['info']     = $e->getMessage();
				$params['filename'] = $filename;
			}
			else {
				$params['warning'] = $e->getMessage();
			}
		}

		$this->exportView($params);
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('import_export[export]'));
	}

	protected function getViewFolder() {
		return SLY_ADDONFOLDER.'/import_export/views/';
	}

	protected function getTempFileName($ext = 'tmp') {
		return sly_A1_Util::getTempDir().DIRECTORY_SEPARATOR.'a'.uniqid().'.'.$ext;
	}

	protected function collectComponents() {
		$addonService  = sly_Service_Factory::getAddOnService();
		$pluginService = sly_Service_Factory::getPluginService();
		$components    = array();

		foreach ($addonService->getAvailableAddons() as $addon) {
			$components[] = $addon;

			foreach ($pluginService->getAvailablePlugins($addon) as $plugin) {
				$components[] = array($addon, $plugin);
			}
		}

		return $components;
	}
}
