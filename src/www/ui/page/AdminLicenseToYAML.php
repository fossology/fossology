<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Application\LicenseCompatibilityRulesYamlExport;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Util\DownloadUtil;
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
    $confirmed = $request->get('confirmed', false);
    $referer = $request->headers->get('referer');
    $fileName = "fossology-license-comp-rules-export-" . date("YMj-Gis") . '.yaml';

    if (!$confirmed) {
      $downloadUrl = "?mod=" . self::NAME . "&confirmed=true";
      return DownloadUtil::getDownloadConfirmationResponse($downloadUrl, $fileName, $referer);
    }

    /** @var DbManager $dbManager */
    $dbManager = $this->getObject('db.manager');
    /** @var CompatibilityDao $compatibilityDao */
    $compatibilityDao = $this->getObject('dao.compatibility');
    $licenseYamlExport = new LicenseCompatibilityRulesYamlExport($dbManager,
        $compatibilityDao);
    $content = $licenseYamlExport->createYaml(0);
    return DownloadUtil::getDownloadResponse($content, $fileName, 'text/x-yaml');
  }
}

register_plugin(new AdminLicenseToYAML());