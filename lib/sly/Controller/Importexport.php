<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * Basic Controller for Import and Export Pages
 *
 * @author zozi
 */
class sly_Controller_Importexport extends sly_Controller_Backend implements sly_Controller_Interface {
	protected function init() {
		$this->render('head.phtml', array(), false);
	}

	public function indexAction() {
		$this->init();
		$this->exportView();
	}

	protected function exportView($params = array()) {
		$dirs = array(
			'assets'  => t('im_export_explain_assets'),
			'develop' => t('im_export_explain_develop')
		);

		$dispatcher = sly_Core::dispatcher();
		$dirs       = $dispatcher->filter('SLY_A1_EXPORT_FILENAMES', $dirs);

		$params['dirs'] = $dirs;
		$this->render('export.phtml', $params, false);
	}

	public function exportAction() {
		$this->init();

		$user           = $this->getCurrentUser();
		$canDownload    = $user->isAdmin() || $user->hasPermission('import_export', 'download');
		$canAccessUsers = $user->isAdmin() || $user->hasPermission('pages', 'user');

		$request       = $this->getRequest();
		$download      = $canDownload    ? $request->post('download', 'boolean', false) : false;
		$addUsers      = $canAccessUsers ? $request->post('users',    'boolean', false) : false;
		$systemExports = $request->postArray('systemexports', 'string', array());
		$directories   = $request->postArray('directories', 'string', array());
		$addAddOns     = $request->post('addons', 'boolean', false);
		$diffFriendly  = $request->post('diff_friendly', 'boolean', false);
		$comment       = $request->post('comment', 'string');
		$filename      = $request->post('filename', 'string', 'sly_'.date('Ymd'));

		$flash    = $this->container['sly-flash-message'];
		$service  = $this->container['sly-importexport-service'];
		$exporter = $this->container['sly-importexport-exporter'];

		try {
			// setup the exporter

			$exporter
				->includeDump(in_array('sql', $systemExports))
				->includeAddOnState($addAddOns)
				->includeUsers($addUsers)
				->setDiffFriendly($diffFriendly)
				->setComment($comment)
				->setName($filename)
			;

			foreach ($directories as $dir) {
				$exporter->addDirectory($dir);
			}

			$service->cleanup();

			// stream the file if requested

			if ($download) {
				return $this->getStreamResponse($service, $exporter, $diffFriendly);
			}

			$exportFile = $exporter->export();

			// fresh form data

			$flash->addInfo(t('im_export_file_generated_in').' '.basename($exportFile));

			return $this->redirectResponse();
		}
		catch (Exception $e) {
			$flash->addWarning($e->getMessage());
		}

		$this->exportView();
	}

	protected function getStreamResponse(Service $service, Exporter $exporter, $diffFriendly) {
		// force to dump in a temp file only

		$tmpFile = $service->getTempDir().DIRECTORY_SEPARATOR.'download-'.uniqid().'.bin';
		$exporter->export($tmpFile);

		// prepare response

		$response  = new sly_Response_Stream($tmpFile);
		$extension = $diffFriendly ? '.sql' : '.zip';

		if ($diffFriendly) {
			$response->setContentType('text/sql', 'UTF-8');
		}
		else {
			$response->setContentType('application/zip', null);
		}

		$response->setHeader('Content-Disposition', 'attachment; filename="'.$exporter->getName().$extension.'"');

		// done

		return $response;
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		if (!$user) return false;

		if ($action === 'export') {
			sly_Util_Csrf::checkToken();
		}

		if ($user->isAdmin()) return true;

		// We *dont* check if someone can export data, but whether *anything* is
		// granted. Inside init() we will redirect accordingly.

		$hasPageAccess = $user->hasPermission('pages', 'a1imex');
		$canExport     = $user->hasPermission('import_export', 'export');
		$canImport     = $user->hasPermission('import_export', 'import');
		$canDownload   = $user->hasPermission('import_export', 'download');

		return $hasPageAccess && ($canExport || $canImport || $canDownload);
	}

	protected function getViewFolder() {
		return dirname(__FILE__).'/../../../views/';
	}
}
