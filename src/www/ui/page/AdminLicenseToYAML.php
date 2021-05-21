<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminLicenseToYAML extends DefaultPlugin
{
  const NAME = "admin_license_to_yaml";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Admin License Rules Export",
        self::MENU_LIST => "Admin::License Admin::Rules Export",
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
    $licenseYamlExport = new \Fossology\Lib\Application\LicenseCompatibilityRulesYamlExport($this->getObject('db.manager'));
    $content = $licenseYamlExport->createYaml(0);
    $fileName = "fossology-license-comp-rules-export-".date("YMj-Gis");
    $headers = array(
        'Content-type' => 'text/x-yaml, charset=UTF-8',
        'Content-Disposition' => 'attachment; filename='.$fileName.'.yaml',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0',
        'Expires' => 'Expires: Thu, 19 Nov 1981 08:52:00 GMT');

    return new Response($content, Response::HTTP_OK, $headers);
  }
}

register_plugin(new AdminLicenseToYAML());
