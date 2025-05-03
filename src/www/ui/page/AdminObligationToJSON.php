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

class AdminObligationToJSON extends DefaultPlugin
{
  const NAME = "admin_obligation_to_json";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Admin Obligation JSON Export",
        self::MENU_LIST => "Admin::Obligation Admin::JSON Export",
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
    $fileName = "fossology-obligations-export-" . date("YMj-Gis") . '.json';

    if (!$confirmed) {
      $downloadUrl = "?mod=" . self::NAME . "&rf=" . $request->get('rf') . "&confirmed=true";
      return DownloadUtil::getDownloadConfirmationResponse($downloadUrl, $fileName, $referer);
    }

    $obligationCsvExport = new \Fossology\Lib\Application\ObligationCsvExport($this->getObject('db.manager'));
    $content = $obligationCsvExport->createCsv(intval($request->get('rf')), true);
    return DownloadUtil::getDownloadResponse($content, $fileName, 'text/json');
  }
}

register_plugin(new AdminObligationToJSON());