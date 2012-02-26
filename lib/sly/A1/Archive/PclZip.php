<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_A1_Archive_PclZip extends sly_A1_Archive_Base {
	protected $isOpen  = false;
	protected $archive = null;

	public function open() {
		if ($this->isOpen) return;

		$this->archive = new PclZip($this->getFilename());
		$this->isOpen  = true;
	}

	public function close() {
		$this->isOpen = false;
	}

	public function addFile($filename) {
		$this->open();
		return $this->archive->add($filename, PCLZIP_OPT_REMOVE_PATH, SLY_BASE) === 0;
	}

	public function extract() {
		$this->open(false);
		$this->archive->extract();
		return $this->archive->errorCode() === PCLZIP_ERR_NO_ERROR;
	}

	protected function readComment() {
		$this->open();

		$props = $this->archive->properties();
		return isset($props['comment']) && is_string($props['comment']) && mb_strlen($props['comment']) > 0 ? $props['comment'] : null;
	}

	protected function writeComment($comment) {
		$this->open();
		return $this->archive->add(array(), PCLZIP_OPT_COMMENT, $comment) === 0;
	}
}
