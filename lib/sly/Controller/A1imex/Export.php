<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
*/

/**
 * Basic Controller for Import and Export Pages
 *
 * @author zozi
 */
class sly_Controller_A1imex_Export extends sly_Controller_A1imex{

	protected function export() {
		$download      = sly_post('download', 'boolean', false);
		$systemexports = sly_postArray('systemexports', 'string', array());
		$selectedDirs  = sly_postArray('directories', 'string', array());

		$filename = sly_post('filename', 'string', 'sly_'.date('Ymd'));
		$orig     = $filename;
		$filename = strtolower($filename);
		$filename = preg_replace('#[^\.a-z0-9_-]#', '', $filename);

		if ($filename != $orig) {
			$params['info'] = t('im_export_filename_updated');
			$params['filename'] = addslashes($filename);

			$params['selectedDirs']  = $selectedDirs;
			$params['systemexports'] = $systemexports;
			$params['download']      = array(intval($download) => true);
			$this->exportView($params);
		}


	}

	protected function checkPermission() {
		global $REX;
		return $REX['USER']->hasPerm('import_export[export]') || $REX['USER']->isAdmin();
	}
}
?>
