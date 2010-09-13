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

if (IS_SALLY_BACKEND){
	$I18N->appendFile(SLY_INCLUDE_PATH.'/addons/import_export/lang/');
	sly_Loader::addLoadPath(dirname(__FILE__).DIRECTORY_SEPARATOR.'lib');
}