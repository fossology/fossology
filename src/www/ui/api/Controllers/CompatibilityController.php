<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Dao\UploadDao;
use Slim\Http\Request;
use Slim\Http\Response;

class CompatibilityController extends RestController
{
  /** @var CompatibilityDao */
  private $compatibilityDao;

  /** @var UploadDao */
  private $uploadDao;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->compatibilityDao = $this->container->get('dao.compatibility');
    $this->uploadDao = $this->container->get('dao.upload');
  }

  /**
   * Get compatibility report for an upload
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   * @return Response
   */
  public function getResults(Request $request, Response $response, $args)
  {
    $uploadId = intval($args['id']);
    $this->checkUploadAccess($uploadId);

    $incompatiblePairs = $this->compatibilityDao->getIncompatiblePairsForUpload($uploadId);

    $formattedPairs = [];
    $isCompatible = true;
    foreach ($incompatiblePairs as $pair) {
      if ($pair['result'] === null || $pair['result'] === '' || $pair['result'] == false || $pair['result'] == 'f') {
        $isCompatible = false;
        $formattedPairs[] = [
          "license1" => $pair['license1'],
          "license2" => $pair['license2'],
          "result" => ($pair['result'] === null || $pair['result'] === '') ? "Unknown" : false,
          "ruleDescription" => $pair['description'] ?: "No rule description"
        ];
      }
    }

    $result = [
      "compatible" => $isCompatible,
      "incompatiblePairs" => $formattedPairs
    ];

    return $response->withJson($result, 200);
  }

  /**
   * Check if user has access to the upload.
   *
   * @param int $uploadId
   * @throws \Exception
   */
  private function checkUploadAccess($uploadId)
  {
    $upload = $this->uploadDao->getUpload($uploadId);
    if (empty($upload)) {
      throw new \Exception("Upload not found or permission denied", 404);
    }
  }
}
