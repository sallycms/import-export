<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_A1_Archive_ZipArchive extends sly_A1_Archive_Base {
	protected $isOpen  = false;
	protected $archive = null;

	public function __destruct() {
		$this->close();
	}

	public function open($writeMode = true) {
		if ($this->isOpen) return;

		$archive = new ZipArchive();
		$state   = $archive->open($this->getFilename(), $writeMode ? ZipArchive::OVERWRITE : null);

		if ($state !== true) {
			throw new sly_Exception('Could not open archive file, code '.$state);
		}

		$this->archive = $archive;
		$this->isOpen  = true;
	}

	public function close() {
		if (!$this->isOpen) return;
		$this->archive->close();
		$this->isOpen = false;
	}

	public function addFile($filename) {
		$this->open();

		$relname = sly_Util_Directory::getRelative($filename);

		if (DIRECTORY_SEPARATOR === '\\') {
			$success = $this->archive->addFromString(str_replace('\\', '/', $relname), file_get_contents($filename));
		}
		else {
			$success = $this->archive->addFile($filename, $relname);
		}

		return $success;
	}

	public function extract() {
		$this->open(false);
		return $this->archive->extractTo('./');
	}

	protected function readComment() {
		$this->open(false);
		return $this->archive->getArchiveComment();
	}

	protected function writeComment($comment) {
		$this->open();
		return $this->archive->setArchiveComment($comment);
	}
}
