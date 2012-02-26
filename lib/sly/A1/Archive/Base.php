<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class sly_A1_Archive_Base {
	protected $filename;
	protected $components = null; // list of required addOns and plugins
	protected $version    = null; // Sally version/branch
	protected $comment    = null; // additional comment (not the archive's internal comment!)
	protected $date       = null; // export date

	public function __construct($filename = null) {
		$this->filename = $filename;
	}

	public function readInfo() {
		$comment = $this->readComment();
		if (!is_string($comment)) return false;

		$data = json_decode($comment, true);

		// old school addon list: "addon1\naddon2\naddon3"
		if (mb_strlen($comment) > 0 && $data === null) {
			$this->components = array_filter(explode("\n", $comment));
		}
		else {
			$this->components = isset($data['components']) ? $data['components']      : null;
			$this->version    = isset($data['version'])    ? $data['version']         : null;
			$this->comment    = isset($data['comment'])    ? $data['comment']         : null;
			$this->date       = isset($data['date'])       ? strtotime($data['date']) : null;
		}

		return true;
	}

	public function writeInfo() {
		$data = array('date' => date('r'));

		if ($this->components !== false && $this->components !== null) $data['components'] = $this->components;
		if ($this->version !== false && $this->version !== null) $data['version'] = $this->version;
		if ($this->comment !== false && $this->comment !== null) $data['comment'] = $this->comment;

		return $this->writeComment(json_encode($data));
	}

	public function getFilename() {
		return $this->filename;
	}

	public function getComponents() {
		return $this->components;
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

	public function setComponents($components) {
		return $this->components = $components;
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
