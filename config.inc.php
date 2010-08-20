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

if (!defined('IS_SALLY_BACKEND')) return;

$I18N->appendFile(SLY_INCLUDE_PATH.'/addons/import_export/lang/');

// Autoloading initialisieren

function _sly_a1_autoload($className)
{
	$class   = $className['subject'];
	$classes = array(
		'sly_A1_PEAR'            => 'class.pear.php',
		'sly_A1_Archive_Tar'     => 'class.tar.php',
		'sly_A1_Helper'          => 'class.helper.php',
		'sly_A1_Import_Database' => 'class.import.database.php',
		'sly_A1_Export_Database' => 'class.export.database.php',
		'sly_A1_Import_Files'    => 'class.import.files.php',
		'sly_A1_Export_Files'    => 'class.export.files.php'
	);

	if (isset($classes[$class])) {
		require_once dirname(__FILE__).'/classes/'.$classes[$class];
		return '';
	}
}

sly_Loader::addLoadPath(dirname(__FILE__).DIRECTORY_SEPARATOR.'lib');
rex_register_extension('__AUTOLOAD', '_sly_a1_autoload');
