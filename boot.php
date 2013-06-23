<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

$container['sly-i18n']->appendFile(__DIR__.'/lang');

$container['sly-dispatcher']->addListener('SLY_BACKEND_NAVIGATION_INIT', function($nav, $params) {
	$user = $params['user'];

	if ($user && ($user->isAdmin() || $user->hasPermission('pages', 'sly_import_export'))) {
		$nav->addPage('addon', 'importexport', t('im_export_importexport'));
	}
});
