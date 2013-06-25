/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

jQuery(function($) {
	var form = $('#export');

	$('#dump', form).on('change', function(e) {
		$('#users, #diff_friendly', form).prop('disabled', !e.currentTarget.checked);
	}).trigger('change');

	$('.a1-timeago').timeago();
});
