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
	$dispatcher = $container['sly-dispatcher'];
	$service    = $container['sly-service-addon'];
	$tempDir    = $service->getTempDirectory('sallycms/import-export');
	$storage    = $service->getDynFilesystem('sallycms/import-export');

	return new sly\ImportExport\Service($dispatcher, $tempDir, $storage, $service);
});

$container['sly-importexport-exporter'] = $container->share(function($container) {
	$service = $container['sly-importexport-service'];
	$dumper  = $container['sly-importexport-dumper'];

	return new sly\ImportExport\Exporter($service, $dumper);
});

$container['sly-importexport-importer'] = $container->share(function($container) {
	$service    = $container['sly-importexport-service'];
	$dispatcher = $container['sly-dispatcher'];
	$importer   = new sly_DB_Importer($container['sly-persistence'], $dispatcher);

	return new sly\ImportExport\Importer($service, $importer, $dispatcher);
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
	if (!$user) return;

	// check permissions

	$isAdmin     = $user->isAdmin();
	$canExport   = $isAdmin || $user->hasPermission('import_export', 'export');
	$canImport   = $isAdmin || $user->hasPermission('import_export', 'import');
	$canDownload = $isAdmin || $user->hasPermission('import_export', 'download');

	if ($canExport || $canImport || $canDownload) {
		// add main page

		$page = !$canExport ? 'importexport_import' : 'importexport';
		$page = $nav->addPage('addon', $page, t('im_export_importexport'));

		// init subpages

		if ($canExport && ($canImport || $canDownload)) {
			if ($canExport) {
				$page->addSubpage('importexport', t('im_export_export'));
			}

			if ($canImport || $canDownload) {
				$page->addSubpage('importexport_import', t('im_export_import'));
			}
		}
	}
});
