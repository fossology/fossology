<?php
/*
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminObligationToCSV extends DefaultPlugin
{
  const NAME = "admin_obligation_to_csv";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Admin Obligation CSV Export",
        self::MENU_LIST => "Admin::Obligation Admin::CSV Export",
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
    $obligationCsvExport = new \Fossology\Lib\Application\ObligationCsvExport($this->getObject('db.manager'));
    $content = $obligationCsvExport->createCsv(intval($request->get('rf')));
    $fileName = "fossology-obligations-export-".date("YMj-Gis");
    $headers = array(
        'Content-type' => 'text/csv, charset=UTF-8',
        'Content-Disposition' => 'attachment; filename='.$fileName.'.csv',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0',
        'Expires' => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');

    return new Response($content, Response::HTTP_OK, $headers);
  }
}

register_plugin(new AdminObligationToCSV());
