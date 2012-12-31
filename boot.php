<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

if (sly_Core::isBackend()) {
	$base = dirname(__FILE__);

	sly_Core::getI18N()->appendFile($base.'/lang');
	sly_Loader::addLoadPath($base.'/lib/vendor');
	sly_Loader::addLoadPath($base.'/lib');
	sly_Core::dispatcher()->register('SLY_ADDONS_LOADED', array('sly_A1_Util', 'backendNavigation'));
}
