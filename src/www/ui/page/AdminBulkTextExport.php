<?php
/*
 SPDX-FileCopyrightText: © 2026 Kaushlendra Pratap <kaushlendra-pratap.singh@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Application\BulkTextExport;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Util\DownloadUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \brief Export bulk text license data as CSV or JSON
 */
class AdminBulkTextExport extends DefaultPlugin
{
  const NAME = "admin_bulk_text_export";

  /** @var DbManager */
  private $dbManager;

  /** @var UserDao */
  private $userDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
      self::TITLE => "Admin Bulk Text Export",
      self::MENU_LIST => "Admin::Bulk::Bulk Text Export",
      self::REQUIRES_LOGIN => true,
      self::PERMISSION => Auth::PERM_ADMIN
    ));
    $this->dbManager = $this->getObject('db.manager');
    $this->userDao = $this->getObject('dao.user');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    if ($request->get('export', false)) {
      return $this->handleExport($request);
    }

    $vars = $this->getFormVars();
    return $this->render('admin_bulk_text_export.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * Get variables for the form
   * @return array
   */
  private function getFormVars()
  {
    $userId = Auth::getUserId();
    $groupMap = $this->userDao->getAdminGroupMap($userId, $_SESSION[Auth::USER_LEVEL]);
    $vars = array(
      'users' => $this->userDao->getUsersByGroup(),
      'groups' => $groupMap
    );
    return $vars;
  }

  /**
   * Handle the export action
   * @param Request $request
   * @return Response
   */
  private function handleExport(Request $request)
  {
    $exportFormat = $request->get('export_format', 'csv');
    $filterType = $request->get('filter_type', 'all');
    $user_pk = intval($request->get('selected_user', 0));
    $group_pk = intval($request->get('selected_group', 0));
    $delimiter = $request->get('delimiter', ',');
    $enclosure = $request->get('enclosure', '"');

    if (!in_array($exportFormat, array('csv', 'json'))) {
      $exportFormat = 'csv';
    }

    if ($exportFormat === 'json') {
      $fileName = "fossology-bulk-text-export-" . date("YMj-Gis") . '.json';
      $contentType = 'application/json';
      $generateJson = true;
    } else {
      $fileName = "fossology-bulk-text-export-" . date("YMj-Gis") . '.csv';
      $contentType = 'text/csv';
      $generateJson = false;
    }

    $filterUserPk = 0;
    $filterGroupPk = 0;

    if ($filterType === 'user' && $user_pk > 0) {
      $filterUserPk = $user_pk;
    } elseif ($filterType === 'group' && $group_pk > 0) {
      $filterGroupPk = $group_pk;
    }

    $bulkTextExporter = new BulkTextExport($this->dbManager);
    if (!$generateJson) {
      $bulkTextExporter->setDelimiter($delimiter);
      $bulkTextExporter->setEnclosure($enclosure);
    }

    $content = $bulkTextExporter->exportBulkText($filterUserPk, $filterGroupPk, $generateJson);

    return DownloadUtil::getDownloadResponse($content, $fileName, $contentType);
  }
}

register_plugin(new AdminBulkTextExport());
