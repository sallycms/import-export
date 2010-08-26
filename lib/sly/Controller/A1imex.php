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
class sly_Controller_A1imex extends sly_Controller_Sally {

	const VIEW_PATH = 'addons/import_export/views/';

	protected function index() {
		$params['filename']      = 'sly_'.date('Ymd');
		$params['systemexports'] = array();
		$params['selectedDirs']  = array();
		$params['download']      = array(0 => true);
		$this->exportView($params);
	}

	protected function exportView($params) {

		$dirs = array(
			realpath(SLY_BASE.DIRECTORY_SEPARATOR.'assets')  => t('im_export_explain_assets'),
			realpath(SLY_BASE.DIRECTORY_SEPARATOR.'develop') => t('im_export_explain_develop'),
			SLY_MEDIAFOLDER              => t('im_export_explain_mediapool')
		);
		$dispatcher   = sly_Core::dispatcher();
		if($dispatcher->hasListeners('SLY_A1_EXPORT_FILENAMES')) {
			$dirs = $dispatcher->filter('SLY_A1_EXPORT_FILENAMES', $dirs);
		}
		$params['dirs'] = $dirs;

		$this->render(self::VIEW_PATH.'export.phtml', $params);
	}

	protected function init() {
		global $REX;

		$subpages = array();
		if($REX['USER']->hasPerm('import_export[export]') || $REX['USER']->isAdmin()){
			$subpages[] = array('export', t('im_export_export'));
		}
		if($REX['USER']->hasPerm('import_export[import]') || $REX['USER']->isAdmin()){
			$subpages[] = array('import', t('im_export_import'));
		}
		$this->render(self::VIEW_PATH.'head.phtml', array('subpages' => $subpages));
	}

	protected function checkPermission() {
		global $REX;
		return $REX['USER']->hasPerm('import_export[export]') || $REX['USER']->isAdmin();
	}
}
?>
