<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_A1_Archive_Plain extends sly_A1_Archive_Base {
	protected $isOpen = false;
	protected $fp     = null;

	public function __destruct() {
		$this->close();
	}

	public function open($writeMode = true) {
		if ($this->isOpen) return;

		$fp = @fopen($this->getFilename(), ($writeMode ? 'w' : 'r').'b');

		if (!$fp) {
			throw new sly_Exception('Could not open archive file.');
		}

		$this->fp     = $fp;
		$this->isOpen = true;
	}

	public function close() {
		if (!$this->isOpen) return;

		fclose($this->fp);

		$this->fp     = null;
		$this->isOpen = false;
	}

	public function addFile($filename) {
		$this->open();
		return fwrite($this->fp, file_get_contents($filename)) > 0;
	}

	public function extract() {
		$dir = sly_A1_Util::getTempDir();
		$sql = $this->getFilename();

		return copy($sql, $dir.'/'.basename($sql));
	}

	protected function readComment() {
		$this->open(false);

		fseek($this->fp, 0);
		$line = fgets($this->fp, 8192);

		if (mb_strpos($line, '-- importexport-comment:') === false) {
			return null;
		}

		return trim(mb_substr($line, 24));
	}

	protected function writeComment($comment) {
		$this->open();
		return fwrite($this->fp, '-- importexport-comment:'.$comment."\n") > 0;
	}
}
