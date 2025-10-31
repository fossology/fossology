<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Util\DownloadUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminCustomTextToJSON extends DefaultPlugin
{
  const NAME = "admin_custom_text_to_json";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Admin Custom Text JSON Export",
        self::MENU_LIST => "Admin::Text Management::JSON Export All",
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
    $fileName = "fossology-custom-text-export-" . date("YMj-Gis") . '.json';

    if (!$confirmed) {
      $downloadUrl = "?mod=" . self::NAME . "&cp_pk=" . $request->get('cp_pk') . "&confirmed=true";
      return DownloadUtil::getDownloadConfirmationResponse($downloadUrl, $fileName, $referer);
    }

    $customTextCsvExport = new \Fossology\Lib\Application\CustomTextCsvExport($this->getObject('db.manager'));
    $content = $customTextCsvExport->createCsv(intval($request->get('cp_pk')), true);
    return DownloadUtil::getDownloadResponse($content, $fileName, 'application/json');
  }
}

register_plugin(new AdminCustomTextToJSON());
