<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

////////////////////////////////////////////////////////////////////////////////
// declare services

$container['sly-importexport-service'] = $container->share(function($container) {
	$db         = $container['sly-persistence'];
	$dispatcher = $container['sly-dispatcher'];
	$service    = $container['sly-service-addon'];
	$tempDir    = $service->getTempDirectory('sallycms/import-export');
	$storageDir = SLY_DATAFOLDER.'/import-export';

	if (!is_dir($storageDir)) {
		sly_Util_Directory::createHttpProtected($storageDir, true);
	}

	return new sly\ImportExport\Service($db, $dispatcher, $tempDir, $storageDir, $service);
});

$container['sly-importexport-dumper'] = $container->share(function($container) {
	$db      = $container['sly-persistence'];
	$ignores = $container['sly-config']->get('sly_import_export/ignored_table_prefixes', array());

	return new sly\ImportExport\Dumper($db, $ignores);
});

////////////////////////////////////////////////////////////////////////////////
// init system

$container['sly-i18n']->appendFile(__DIR__.'/lang');

$container['sly-dispatcher']->addListener('SLY_BACKEND_NAVIGATION_INIT', function($nav, $params) {
	$user = $params['user'];

	if ($user && ($user->isAdmin() || $user->hasPermission('pages', 'import_export'))) {
		$nav->addPage('addon', 'importexport', t('im_export_importexport'));
	}
});
