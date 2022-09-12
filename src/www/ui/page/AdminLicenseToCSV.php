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

class AdminLicenseToCSV extends DefaultPlugin
{
  const NAME = "admin_license_to_csv";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Admin License CSV Export",
        self::MENU_LIST => "Admin::License Admin::CSV Export",
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
    $licenseCsvExport = new \Fossology\Lib\Application\LicenseCsvExport($this->getObject('db.manager'));
    $content = $licenseCsvExport->createCsv(intval($request->get('rf')));
    $fileName = "fossology-license-export-".date("YMj-Gis");
    $headers = array(
        'Content-type' => 'text/csv, charset=UTF-8',
        'Content-Disposition' => 'attachment; filename='.$fileName.'.csv',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0',
        'Expires' => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');

    return new Response($content, Response::HTTP_OK, $headers);
  }
}

register_plugin(new AdminLicenseToCSV());
