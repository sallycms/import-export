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
	protected $addonService;

	/**
	 * Constructor
	 *
	 * @param sly_DB_PDO_Persistence   $db
	 * @param sly_Event_IDispatcher    $dispatcher
	 * @param string                   $tempDir
	 * @param sly_Filesystem_Interface $storage
	 * @param sly_Service_AddOn        $service
	 */
	public function __construct(sly_Event_IDispatcher $dispatcher, $tempDir, sly_Filesystem_Interface $storage, sly_Service_AddOn $service) {
		$this->dispatcher   = $dispatcher;
		$this->tempDir      = $tempDir;
		$this->storage      = $storage;
		$this->addonService = $service;
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

		$result['missing']    = $this->getMissingAddOns($result['addons']);
		$result['compatible'] = Util::isCompatible($result['version']);

		return $result;
	}

	public function getMissingAddOns($addons) {
		if (!is_array($addons) || empty($addons)) {
			return array();
		}

		$current = $this->collectAddOns();

		return array_diff($addons, $current);
	}

	public function collectAddOns() {
		$addons = array();

		foreach ($this->addonService->getAvailableAddons() as $addon) {
			$ignore = $this->addonService->getComposerKey($addon, 'imex-ignore', false);

			if ($ignore !== true && $ignore !== 'true') {
				$addons[] = $addon;
			}
		}

		return $addons;
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

	public function getArchiveURI($filename) {
		$fss = new sly_Filesystem_Service($this->storage);

		return $fss->getURI($filename);
	}

	public function getArchive($filename, $type = null) {
		$fileURI = $this->getArchiveURI($filename);

		return Util::getArchive($fileURI, $type);
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

		return $metadata;
	}

	public function setArchiveMetadata($filename, $metadata) {
		$metadataFilename = $this->getArchiveMetadataFilename($filename);
		$archive          = $this->getArchive($filename);
		$metadata         = json_encode($metadata);

		$archive->setComment($metadata);
		$this->storage->write($metadataFilename, $metadata);
	}

	/**
	 * Generate metadata for a new archive.
	 *
	 * @param  string  $comment
	 * @param  boolean $includeState
	 * @return array   metadata
	 */
	public function generateMetadata($comment, $includeState) {
		$data = array(
			'version' => sly_Core::getVersion('X.Y.*'),
			'date'    => date('r'),
			'comment' => $comment
		);

		if ($includeState) {
			$data['addons'] = $this->collectAddOns();
		}

		return $data;
	}

	protected function getArchiveMetadataFilename($filename) {
		return $filename.'.meta';
	}
}
