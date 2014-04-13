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

abstract class Util {
	public static function isCompatible($dumpVersion, $throw = false) {
		if (mb_strlen($dumpVersion) === 0) return true;

		$compatible = sly_Util_Versions::isCompatible($dumpVersion);

		if (!$compatible && $throw) {
			throw new Exception(t('im_export_incompatible_dump', $dumpVersion, sly_Core::getVersion('X.Y.Z')));
		}

		return $compatible;
	}

	public static function getArchive($filename, $type = null) {
		$type = ($type !== null) ?  $type : self::guessFileType($filename);

		if ($type === Archive\Base::TYPE_SQL) {
			$archive = new Archive\Plain($filename);
		}
		elseif ($type === Archive\Base::TYPE_ZIP) {
			$archive = new Archive\PclZip($filename);
		}

		return $archive;
	}

	public static function guessFileType($filename) {
		$ext = substr($filename, -4);

		if ($ext === '.zip') {
			return Archive\Base::TYPE_ZIP;
		}
		if ($ext === '.sql') {
			return Archive\Base::TYPE_SQL;
		}

		throw new Exception(t('im_export_no_import_file_chosen'));
	}
}
