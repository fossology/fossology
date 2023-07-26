<?php
/*
 SPDX-FileCopyrightText: © 2022 Rohit Pandey <rohit.pandey4900@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\UI\Ajax;

use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Fossology\Lib\BusinessRules\ReuseReportProcessor;

class AjaxReuseReport extends DefaultPlugin
{
  const NAME = "ajax_reuse_report";

  /** @var UploadDao */
  private $uploadDao;
  /** @var ReuseReportProcessor */
  private $reuseReportProcessor;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::PERMISSION => Auth::PERM_READ
      ));
    $this->uploadDao = $this->getObject('dao.upload');
    $this->reuseReportProcessor = $this->getObject('businessrules.reusereportprocessor');
  }

  /**
   * @param Request $request
   * @return Response
   * @throws \Exception If upload is not accessible.
   */
  public function handle(Request $request)
  {
    $upload = intval($request->get("upload"));
    $groupId = Auth::getGroupId();
    if (!$this->uploadDao->isAccessible($upload, $groupId)) {
      throw new \Exception("Permission Denied");
    }
    $vars = $this->reuseReportProcessor->getReuseSummary($upload);
    return new JsonResponse([
      "data" =>[
        [
          "title" => _("Edited Results"),
          "value" => $vars['clearedLicense']
        ],
        [
          "title" => _("Decleared licenses"),
          "value" => $vars['declearedLicense']
        ],
        [
          "title" => _("Used licenses"),
          "value" => $vars['usedLicense']
        ],
        [
          "title" => _("Unused licenses"),
          "value" => $vars['unusedLicense']
        ],
        [
          "title" => _("Missing licenses"),
          "value" => $vars['missingLicense']
        ]
      ]
    ]);
  }
}

register_plugin(new AjaxReuseReport());
