<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

use sly\Assets\Util;
use sly\ImportExport\Service;
use sly\ImportExport\Exporter;

/**
 * Basic Controller for Import and Export Pages
 *
 * @author zozi
 */
class sly_Controller_Importexport extends sly_Controller_Backend implements sly_Controller_Interface {
	public function indexAction() {
		$this->exportView();
	}

	public function exportAction() {
		$user           = $this->getCurrentUser();
		$canDownload    = $user->isAdmin() || $user->hasPermission('import_export', 'download');
		$canAccessUsers = $user->isAdmin() || $user->hasPermission('pages', 'user');

		$request       = $this->getRequest();
		$download      = $canDownload    ? $request->post('download', 'boolean', false) : false;
		$addUsers      = $canAccessUsers ? $request->post('users',    'boolean', false) : false;
		$includeDump   = $request->post('dump', 'boolean', false);
		$diffFriendly  = $request->post('diff_friendly', 'boolean', false);
		$comment       = $request->post('comment', 'string');
		$filename      = $request->post('filename', 'string', 'sly_'.date('Ymd'));
		$directories   = $request->postArray('directories', 'string', array());

		$flash    = $this->container['sly-flash-message'];
		$service  = $this->container['sly-importexport-service'];
		$exporter = $this->container['sly-importexport-exporter'];

		try {
			// setup the exporter

			$exporter
				->includeDump($includeDump)
				->includeUsers($addUsers)
				->setDiffFriendly($diffFriendly)
				->setComment($comment)
				->setName($filename)
			;

			foreach ($directories as $dir) {
				$exporter->addDirectory($dir);
			}

			// kill all old temp files

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
		$user = $this->getCurrentUser();
		if (!$user) return false;

		if ($action === 'export') {
			sly_Util_Csrf::checkToken();
		}

		return $user->isAdmin() || $user->hasPermission('import_export', 'export');
	}

	protected function pageHeader() {
		$locale = substr($this->container['sly-i18n']->getLocale(), 0, 2);
		$layout = $this->container['sly-layout'];
		$me     = 'sallycms/import-export';

		$layout->addCSSFile(Util::addOnUri($me, 'css/backend.less'));
		$layout->addJavaScriptFile(Util::addOnUri($me, 'js/backend.js'));
		$layout->addJavaScriptFile(Util::addOnUri($me, 'js/jquery.timeago.js'));
		$layout->addJavaScriptFile(Util::addOnUri($me, 'js/jquery.timeago.'.$locale.'.js'));

		$layout->pageHeader(t('im_export_importexport'));
	}

	protected function exportView() {
		$user = $this->getCurrentUser();
		$dirs = array(
			'assets'  => t('im_export_explain_assets'),
			'develop' => t('im_export_explain_develop')
		);

		$dirs = $this->container['sly-dispatcher']->filter('SLY_IMPORTEXPORT_EXPORT_DIRECTORIES', $dirs, array(
			'user' => $user
		));

		// check permissions
		$canDownload    = $user->isAdmin() || $user->hasPermission('import_export', 'download');
		$canAccessUsers = $user->isAdmin() || $user->hasPermission('pages', 'user');

		$this->pageHeader();
		$this->render('export.phtml', array(
			'dirs'           => $dirs,
			'canDownload'    => $canDownload,
			'canAccessUsers' => $canAccessUsers
		), false);
	}

	protected function getViewFolder() {
		return __DIR__.'/../../../views/';
	}
}
