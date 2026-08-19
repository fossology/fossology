<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\UI\Ajax;

use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class AjaxCompatibilityReport extends DefaultPlugin
{
  const NAME = "ajax_compatibility_report";

  /** @var UploadDao */
  private $uploadDao;
  /** @var CompatibilityDao */
  private $compatibilityDao;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::PERMISSION => Auth::PERM_READ
      ));
    $this->uploadDao = $this->getObject('dao.upload');
    $this->compatibilityDao = $this->getObject('dao.compatibility');
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
    $incompatiblePairs = $this->compatibilityDao->getIncompatiblePairsForUpload($upload);
    $data = [];
    foreach ($incompatiblePairs as $pair) {
      $resStr = "Incompatible";
      if ($pair['result'] === null || $pair['result'] === '') {
        $resStr = "Unknown";
      } else if ($pair['result'] == true || $pair['result'] == 't') {
        $resStr = "Compatible";
      } else {
        $resStr = "Incompatible";
      }

      if ($resStr !== "Compatible") {
        $data[] = [
          "license1" => $pair['license1'],
          "license2" => $pair['license2'],
          "result" => $resStr,
          "description" => $pair['description'] ?: "No rule description"
        ];
      }
    }

    if (empty($data)) {
        $data[] = [
            "license1" => "N/A",
            "license2" => "N/A",
            "result" => true,
            "description" => "No incompatible licenses found in this upload."
        ];
    }

    return new JsonResponse(["data" => $data]);
  }
}

register_plugin(new AjaxCompatibilityReport());
