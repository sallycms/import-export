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

abstract class Util {
	public static function compareArchiveDate(array $a1, array $a2) {
		$date1 = $a1['date'];
		$date2 = $a2['date'];

		return $date1 === $date2 ? 0 : ($date1 < $date2 ? -1 : 1);
	}

	public static function isCompatible($dumpVersion, $throw = false) {
		if (mb_strlen($dumpVersion) === 0) return true;

		$compatible = sly_Util_Versions::isCompatible($dumpVersion);

		if (!$compatible && $throw) {
			throw new sly_Exception(t('im_export_incompatible_dump', $dumpVersion, sly_Core::getVersion('X.Y.Z')));
		}

		return $compatible;
	}

	public static function getArchive($filename, $type = 'zip') {
		if ($type === 'sql' || substr($filename, -4) === '.sql') {
			$archive = new sly_A1_Archive_Plain($filename);
		}
		elseif (class_exists('ZipArchive', false)) {
			$archive = new sly_A1_Archive_ZipArchive($filename);
		}
		else {
			$archive = new sly_A1_Archive_PclZip($filename);
		}

		return $archive;
	}
}
