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

use sly_Core;
use sly_Util_Versions;
use sly\ImportExport\Archive\Base;

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
			throw new Exception(t('im_export_incompatible_dump', $dumpVersion, sly_Core::getVersion('X.Y.Z')));
		}

		return $compatible;
	}

	public static function getArchive($filename, $type = 'zip') {
		if ($type === 'sql' || substr($filename, -4) === '.sql') {
			$archive = new Archive\Plain($filename);
		}
		elseif (class_exists('ZipArchive', false)) {
			$archive = new Archive\ZipArchive($filename);
		}
		else {
			$archive = new Archive\PclZip($filename);
		}

		return $archive;
	}

	public static function guessFileType($filename) {
		if (substr($filename, -4) == '.zip') return Base::TYPE_ZIP;
		if (substr($filename, -4) == '.sql') return Base::TYPE_SQL;

		throw new Exception(t('im_export_no_import_file_chosen'));
	}
}
