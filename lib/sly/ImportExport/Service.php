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

use sly_Filesystem_Interface;
use sly_Filesystem_Service;
use sly_Event_IDispatcher;
use sly_Service_AddOn;
use sly_Util_Directory;
use sly_Util_String;
use sly_Core;

class Service {
	protected $db;
	protected $dispatcher;
	protected $tempDir;
	protected $storage;

	/**
	 * Constructor
	 *
	 * @param sly_DB_PDO_Persistence   $db
	 * @param sly_Event_IDispatcher    $dispatcher
	 * @param string                   $tempDir
	 * @param sly_Filesystem_Interface $storage
	 * @param sly_Service_AddOn        $service
	 */
	public function __construct(sly_Event_IDispatcher $dispatcher, $tempDir, sly_Filesystem_Interface $storage) {
		$this->dispatcher   = $dispatcher;
		$this->tempDir      = $tempDir;
		$this->storage      = $storage;
	}

	public function getArchives() {
		$files = $this->storage->keys();

		$filtered = array();

		foreach ($files as $file) {
			if (sly_Util_String::endsWith($file, '.sql') || sly_Util_String::endsWith($file, '.zip')) {
				$filtered[] = $file;
			}
		}

		return $filtered;
	}

	/**
	 *
	 * @return \sly_Filesystem_Interface
	 */
	public function getStorage() {
		return $this->storage;
	}

	/**
	 *
	 * @return string
	 */
	public function getTempDir() {
		return $this->tempDir;
	}

	public function cleanup() {
		$dirObj = new sly_Util_Directory($this->getTempDir(), true);
		$dirObj->deleteFiles();
	}

	/**
	 * get archive and archive file info
	 *
	 * @param  string  $filename
	 * @return array   file infos
	 */
	public function getArchiveInfo($filename) {
		$filename = basename($filename);
		$result   = array(
			'filename'   => $filename,
			'name'       => substr($filename, 0, strpos($filename, '.')),
			'size'       => $this->storage->size($filename),
			'date'       => $this->storage->mtime($filename),
			'addons'     => array(),
			'comment'    => '',
			'version'    => '',
			'type'       => Util::guessFileType($filename)
		);

		// Entspricht der Dateiname einem bekannten Muster?
		if (preg_match('#^(sly_\d{8})_(.*?)_(\d+)$#i', $result['name'], $matches)) {
			$result['name']        = $matches[1].'_'.$matches[3];
			$result['description'] = str_replace('_', ' ', $matches[2]);
		}
		elseif (preg_match('#^(sly_\d{8})_(.*?)$#i', $result['name'], $matches)) {
			$result['name']        = $matches[1];
			$result['description'] = str_replace('_', ' ', $matches[2]);
		}

		// check for archive metadata
		$metadata = $this->getArchiveMetadata($filename);
		$result   = array_merge($result, $metadata);

		$result['compatible'] = Util::isCompatible($result['version']);

		return $result;
	}

	public function deleteArchive($filename) {
		$metadataFileName = $this->getArchiveMetadataFileName($filename);

		if ($this->storage->has($filename)) {
			$this->storage->delete($filename);
		}

		if ($this->storage->has($metadataFileName)) {
			$this->storage->delete($metadataFileName);
		}
	}

	public function getArchiveURI($filename, $temporary = false) {
		if ($temporary) {
			return $this->getTempDir().DIRECTORY_SEPARATOR.$filename;
		}

		$fss = new sly_Filesystem_Service($this->storage);

		return $fss->getURI($filename);
	}

	public function getArchive($filename, $temporary = false) {
		$fileURI = $this->getArchiveURI($filename, $temporary);

		$type = Util::guessFileType($filename);

		if ($type === Archive\Base::TYPE_SQL) {
			$archive = new Archive\Plain($fileURI);
		}
		elseif ($type === Archive\Base::TYPE_ZIP) {
			$archive = new Archive\PclZip($fileURI, $this->getTempDir());
		}

		return $archive;
	}

	public function archiveExists($filename) {
		return $this->storage->has($key);
	}

	/**
	 * Returns metadata array of an archive
	 *
	 * @param  string $filename  of the archive
	 * @return array             medatada of the archive
	 */
	public function getArchiveMetadata($filename) {
		$metadataFilename = $this->getArchiveMetadataFilename($filename);
		$metadata         =  array();

		if ($this->storage->has($metadataFilename)) {
			$metadata = $this->storage->read($metadataFilename);
			$metadata = json_decode($metadata, true);
		} else {
			$archive  = $this->getArchive($filename);
			$metadata = $archive->getMetadata();
		}

		if (isset($metadata['date'])) {
			$metadata['date'] = strtotime($metadata['date']);
		}

		return $metadata ? $metadata : array();
	}

	public function setArchiveMetadata($filename, $metadata) {
		$metadataFilename = $this->getArchiveMetadataFilename($filename);
		$archive          = $this->getArchive($filename);

		$archive->setMetadata($metadata);
		$this->storage->write($metadataFilename, json_encode($metadata));
	}

	/**
	 * Generate metadata for a new archive.
	 *
	 * @param  string  $comment
	 * @param  boolean $includeState
	 * @return array   metadata
	 */
	public function generateMetadata($comment) {
		$data = array(
			'version' => sly_Core::getVersion('X.Y.*'),
			'date'    => date('r'),
			'comment' => $comment
		);

		return $data;
	}

	protected function getArchiveMetadataFilename($filename) {
		return $filename.'.meta';
	}
}
