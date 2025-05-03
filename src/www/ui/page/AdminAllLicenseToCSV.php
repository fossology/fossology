<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Util\DownloadUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAllLicenseToCSV extends DefaultPlugin
{
  const NAME = "admin_all_license_to_csv";

  function __construct()
  {
    parent::__construct(self::NAME, array(
      self::TITLE => "Admin All Groups License CSV Export",
      self::MENU_LIST => "Admin::License Admin::CSV Export All",
      self::REQUIRES_LOGIN => true,
      self::PERMISSION => Auth::PERM_ADMIN
    ));
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $confirmed = $request->get('confirmed', false);
    $referer = $request->headers->get('referer');
    $fileName = "fossology-license-export-" . date("YMj-Gis") . '.csv';

    if (!$confirmed) {
      $downloadUrl = "?mod=" . self::NAME . "&rf=" . $request->get('rf') . "&confirmed=true";
      return DownloadUtil::getDownloadConfirmationResponse($downloadUrl, $fileName, $referer);
    }

    $licenseCsvExport = new \Fossology\Lib\Application\LicenseCsvExport($this->getObject('db.manager'));
    $content = $licenseCsvExport->createCsv(intval($request->get('rf')), true);
    return DownloadUtil::getDownloadResponse($content, $fileName);
  }
}

register_plugin(new AdminAllLicenseToCSV());