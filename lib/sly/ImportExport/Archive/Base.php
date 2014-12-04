<?php
/*
 * Copyright (c) 2014, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\ImportExport\Archive;

use sly_Util_Directory;

abstract class Base {
	const TYPE_ZIP = 1;
	const TYPE_SQL = 2;

	protected $filename;
	protected $version = null; // Sally version/branch
	protected $comment = null; // additional comment (not the archive's internal comment!)
	protected $date    = null; // export date

	public function __construct($filename = null) {
		$this->filename = $filename;
	}

	public function getMetadata() {
		$comment = $this->readComment();

		if (!is_string($comment)) {
			return false;
		}

		return json_decode($comment, true);
	}

	public function setMetadata($data) {
		return $this->writeComment(json_encode($data));
	}

	public function getFilename() {
		return $this->filename;
	}

	public function getVersion() {
		return $this->version;
	}

	public function getComment() {
		return $this->comment;
	}

	public function getExportDate() {
		return $this->date;
	}

	public function setVersion($version) {
		return $this->version = $version;
	}

	public function setComment($comment) {
		return $this->comment = $comment;
	}

	public function addDirectoryRecursive($directory) {
		$this->open();

		$dir = new sly_Util_Directory($directory, false);

		foreach ($dir->listRecursive(true, true) as $file) {
			$this->addFile($file);
		}
	}

	abstract public function addFile($filename);
	abstract public function open();
	abstract public function close();

	abstract protected function readComment();
	abstract protected function writeComment($comment);
}
