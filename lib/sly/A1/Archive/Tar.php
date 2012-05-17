<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_A1_Archive_Tar extends sly_A1_Archive_Base {
	protected $isOpen  = false;
	protected $archive = null;

	public function open() {
		if ($this->isOpen) return;

		$this->archive = new Archive_Tar($this->getFilename());
		$this->isOpen  = true;
	}

	public function close() {
		$this->isOpen = false;
	}

	public function addFile($filename) {
		$this->open();

		$cwd = getcwd();
		chdir(SLY_BASE);

		$ok = $this->archive->add(array(sly_Util_Directory::getRelative($filename))) === true;

		chdir($cwd);
		return $ok;
	}

	protected function readComment() {
		return null;
	}

	protected function writeComment($comment) {
		return false;
	}
}
