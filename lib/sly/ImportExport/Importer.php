<?php
/*
 * Copyright (c) 2014, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\ImportExport;

use sly_DB_Importer;
use sly_Util_Directory;
use sly_Event_IDispatcher;

class Importer {
	protected $service;
	protected $importer;
	protected $dispatcher;

	public function __construct(Service $service, sly_DB_Importer $importer, sly_Event_IDispatcher $dispatcher) {
		$this->service      = $service;
		$this->importer     = $importer;
		$this->dispatcher   = $dispatcher;
	}

	public function import($filename, $targetDir) {
		$filename   = basename($filename);
		$archiveURI = $this->service->getArchiveURI($filename);
		$archiveURI = $this->dispatcher->filter('SLY_IMPORTEXPORT_BEFORE_IMPORT', $archiveURI);

		if (!$this->service->getStorage()->has($filename)) {
			throw new Exception(t('im_export_selected_file_not_exists'));
		}

		if (!is_dir($targetDir)) {
			throw new Exception('Target directory "'.$targetDir.'" does not exist.');
		}

		@set_time_limit(0);

		// remove old files in the temp directory
		$this->service->cleanup();

		// extract the archive
		$this->extract($filename, $targetDir);

		// import all dumps we can find
		$dumpsFound = $this->importDumps();

		$this->dispatcher->notify('SLY_IMPORTEXPORT_AFTER_IMPORT', $archiveURI, array('dumps_imported' => $dumpsFound));
	}

	protected function extract($filename, $targetDir) {
		$info    = $this->service->getArchiveInfo($filename);
		$archive = $this->service->getArchive($filename);

		// check file

		if (!empty($info['missing'])) {
			throw new Exception(t('im_export_missing_addons_for_db_import').': '.implode(', ', $missing));
		}

		// throw an exception if version does not match
		Util::isCompatible($info['version'], true);

		// extract

		$cwd = getcwd();
		chdir($targetDir);

		$success = $archive->extract();
		$archive->close();

		chdir($cwd);

		if (!$success) {
			throw new Exception(t('im_export_problem_when_extracting'));
		}
	}

	protected function importDumps() {
		$tmpDir = $this->service->getTempDir();
		$tmpDir = new sly_Util_Directory($tmpDir, false);
		$files  = $tmpDir->listPlain(true, false, false, true);

		foreach ($files as $file) {
			if (substr($file, -4) === '.sql') {
				$this->importer->import($file);
			}

			unlink($file);
		}

		return !empty($files);
	}
}
