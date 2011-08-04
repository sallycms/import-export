<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_A1_Export_Files_ZipArchive extends sly_A1_Export_Files {
	public function export($filename, $files, $addons) {
		$tmpFile   = $this->getTempFileName();
		$archive   = new ZipArchive($archive, $baseDir, $file);
		$success   = $archive->open($tmpFile, ZipArchive::OVERWRITE);
		$isWindows = DIRECTORY_SEPARATOR === '\\';

		if ($success === true) {
			$archive->setArchiveComment($addons);

			chdir(SLY_BASE);

			foreach ($files as $file) {
				if (is_dir($file)) {
					$dir = new sly_Util_Directory($file, false);

					foreach ($dir->listRecursive(true, false) as $dirfile) {
						$success = $this->addFile($archive, $file, $dirfile);
						if ($success !== true) break;
					}
				}
				else {
					$success = $this->addFile($archive, $file, null);
					if ($success !== true) break;
				}
			}

			chdir('sally/backend');
			$archive->close();

			if ($success !== true) {
				throw new sly_Exception('im_export_failed_to_create_archive');
			}
		}

		return @rename($tmpFile, $filename);
	}

	protected function addFile(ZipArchive $archive, $base, $file) {
		$fullpath = str_replace('\\', '/', $file === null ? $base : "$base/$file");

		if (DIRECTORY_SEPARATOR === '\\') {
			$success = $archive->addFromString($fullpath, file_get_contents($fullpath));
		}
		else {
			$success = $archive->addFile($fullpath, $fullpath);
		}

		return $success;
	}
}
