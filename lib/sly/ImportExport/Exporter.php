<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\ImportExport;

use sly_Core;
use sly_Service_AddOn;
use sly_Util_File;

class Exporter {
	protected $service;
	protected $dumper;
	protected $addonService;
	protected $includeDump;
	protected $includeState;
	protected $includeUsers;
	protected $diffFriendly;
	protected $files;
	protected $directories;
	protected $comment;
	protected $name;

	public function __construct(Service $service, Dumper $dumper, sly_Service_AddOn $addonService) {
		$this->service      = $service;
		$this->dumper       = $dumper;
		$this->addonService = $addonService;

		$this->includeDump  = false;
		$this->includeState = false;
		$this->includeUsers = false;
		$this->diffFriendly = false;
		$this->files        = array();
		$this->directories  = array();
		$this->comment      = '';
		$this->name         = '';
	}

	public function includeDump($includeDump) {
		$this->includeDump = !!$includeDump;
		return $this;
	}

	public function includeAddOnState($includeState) {
		$this->includeState = !!$includeState;
		return $this;
	}

	public function includeUsers($includeUsers) {
		$this->includeUsers = !!$includeUsers;
		return $this;
	}

	public function setDiffFriendly($diffFriendly) {
		$this->diffFriendly = !!$diffFriendly;
		return $this;
	}

	public function setComment($comment) {
		$this->comment = $comment;
		return $this;
	}

	public function setName($name) {
		$this->name = preg_replace('#[^a-z0-9.,_-]#', '', strtolower($name));
		return $this;
	}

	public function getName() {
		return $this->name;
	}

	public function addFile($filename) {
		$this->files[] = $filename;
		return $this;
	}

	public function addDirectory($directory) {
		$this->directories[] = $directory;
		return $this;
	}

	public function export($target = null) {
		$files = $this->files;
		$dirs  = $this->directories;

		try {
			// the plain SQL export cannot contain files (except for the dump itself)

			if ($this->diffFriendly) {
				$this->directories = array();
				$this->files       = array();
			}

			// dump database

			if ($this->includeDump) {
				$this->dumpDatabase();
			}

			// nothing to do?

			if (empty($this->directories) && empty($this->files)) {
				throw new Exception(t('im_export_please_choose_files'));
			}

			// open archive

			$tmpFile = $this->getTempFilename();
			$archive = Util::getArchive($tmpFile);

			try {
				$archive->open();
			}
			catch (Exception $e) {
				throw new Exception(t('im_export_file_could_not_be_generated').' '.t('im_export_you_have_no_write_permission_in', dirname($target)));
			}

			// set metadata

			if ($this->includeState) {
				$archive->setAddOns($this->collectAddOns());
			}

			$archive->setComment($this->comment); // for later
			$archive->setVersion(sly_Core::getVersion('X.Y.*'));
			$archive->writeInfo();

			// add the files and directories

			foreach ($this->directories as $dir) {
				$dir = SLY_BASE.DIRECTORY_SEPARATOR.$dir;

				if (is_dir($dir)) {
					$archive->addDirectoryRecursive($dir);
				}
				elseif (is_file($dir)) {
					$archive->addFile($dir);
				}
			}

			foreach ($this->files as $file) {
				$archive->addFile($file);
			}

			// cleanup

			$archive->close();

			if (isset($dumpFile)) {
				unlink($dumpFile);
			}

			// move the final archive to the permanent location

			$target = $this->getTargetFilename($target);

			rename($tmpFile, $target);
			chmod($target, sly_Core::getFilePerm());
		}
		catch (\Exception $e) {
			$this->files       = $files;
			$this->directories = $dirs;

			throw $e;
		}

		$this->files       = $files;
		$this->directories = $dirs;

		return $target;
	}

	protected function dumpDatabase() {
		$dumpFile = $this->service->getTempDir().DIRECTORY_SEPARATOR.'database.sql';

		$this->dumper->export($dumpFile, $this->diffFriendly, $this->includeUsers);
		$this->addFile($dumpFile);
	}

	protected function getTempFilename() {
		$ext = $this->diffFriendly ? 'sql' : 'zip';

		return sprintf('%s/export-%s.tmp.%s', $this->service->getTempDir(), uniqid(), $ext);
	}

	protected function getTargetFilename($target) {
		if ($target === null) {
			$ext    = $this->diffFriendly ? 'sql' : 'zip';
			$dir    = $this->service->getStorageDir();
			$file   = sly_Util_File::iterateLocalFilename($dir.'/'.$this->name, '.'.$ext).'.'.$ext;
			$target = $dir.DIRECTORY_SEPARATOR.$file;
		}

		return $target;
	}

	protected function collectAddOns() {
		$addons = array();

		foreach ($this->addonService->getAvailableAddons() as $addon) {
			$ignore = $this->addonService->getComposerKey($addon, 'imex-ignore', false);

			if ($ignore !== true && $ignore !== 'true') {
				$addons[] = $addon;
			}
		}

		return $addons;
	}
}
