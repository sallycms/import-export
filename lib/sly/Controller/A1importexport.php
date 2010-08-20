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
 * Controller for Import and Export Pages
 *
 * @author zozi
 */
class sly_Controller_A1importexport extends sly_Controller_Sally {

	const VIEW_PATH = 'addons/import_export/views/';

	protected function index() {
		$this->head();
	}

	protected function export() {

	}

	private function head() {
		global $REX;

		$subpages = array();
		if($REX['USER']->hasPerm('import_export[export]') || $REX['USER']->isAdmin()){
			$subpages[] = array('import', t('im_export_export'));
		}
		if($REX['USER']->hasPerm('import_export[import]') || $REX['USER']->isAdmin()){
			$subpages[] = array('import', t('im_export_import'));
		}
		$this->render(self::VIEW_PATH.'head.phtml', array('subpages' => $subpages));
	}

	protected function checkPermission() {
		global $REX;
		$special = '';
		if($this->action != 'index') $special = $this->action;
		return $REX['USER']->hasPerm('import_export['.$special.']') || $REX['USER']->isAdmin();
	}

}
?>
