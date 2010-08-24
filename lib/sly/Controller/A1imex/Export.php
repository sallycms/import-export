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

	protected function index() {
		$this->head();
		$params['filename']      = 'sly_'.date('Ymd');
		$params['systemexports'] = array();
		$params['extra']         = array();
		$this->render(self::VIEW_PATH.'export.phtml', $params);
	}

	protected function export() {

		$params = array();
		$filename      = sly_post('filename', 'string', 'sly_'.date('Ymd'));
		$systemexports = sly_postArray('systemexports', 'string', array());
		$extra         = sly_postArray('directories', 'string', array());

		
		$params = array('filename' => $filename,
						'systemexports' => $systemexports,
						'extra' => $extra);
		$this->render(self::VIEW_PATH.'export.phtml', $params);
	}

	protected function checkPermission() {
		global $REX;
		return $REX['USER']->hasPerm('import_export[export]') || $REX['USER']->isAdmin();
	}
}
?>
