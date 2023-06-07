<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for uploadtree queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Data\DecisionTypes;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Container\ContainerInterface;


/**
 * @class UploadTreeController
 * @brief Controller for UploadTree model
 */
class UploadTreeController extends RestController
{

  /**
   * @var DecisionTypes $decisionTypes
   * Decision types object
   */
  private $decisionTypes;


  public function __construct($container)
  {
    parent::__construct($container);
    $this->decisionTypes = $this->container->get('decision.types');
  }

  /**
   * Get the contents of a specific file
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function viewLicenseFile($request, $response, $args)
  {
    $uploadId = intval($args['id']);
    $itemId = intval($args['itemId']);

    $uploadDao = $this->restHelper->getUploadDao();
    $returnVal = null;

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadId), "uploadtree_pk", $itemId)) {
      $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
    }

    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $view = $this->restHelper->getPlugin('view');

    $inputFile = @fopen(RepPathItem($itemId), "rb");
    if (empty($inputFile)) {
      global $Plugins;
      $reunpackPlugin = &$Plugins[plugin_find_id("ui_reunpack")];
      $state = $reunpackPlugin->CheckStatus($uploadId, "reunpack", "ununpack");
      if ($state != 0 && $state != 2) {
        $errorMess = _("Reunpack job is running: you can see it in");
      } else {
        $errorMess = _("File contents are not available in the repository.");
      }
      $info = new Info(500, $errorMess, InfoType::ERROR);
      return $response->withJson($info->getArray(), $info->getCode());
    }
    rewind($inputFile);

    $res = $view->getText($inputFile, 0, 0, -1, null, false, true);
    $response->getBody()->write($res);
    return $response->withHeader("Content-Type", "text/plain")
      ->withHeader("Cache-Control", "max-age=1296000, must-revalidate")
      ->withHeader("Etag", md5($response->getBody()));
  }

  /**
   * Set the clearing decision for a particular upload-tree
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function setClearingDecision($request, $response, $args)
  {
    $body = $this->getParsedBody($request);
    $decisionType = $body['decisionType'];
    $global = $body['globalDecision'];

    // check if the given globalDecision value is a boolean
    if ($global !== null && !is_bool($global)) {
      $returnVal = new Info(400, "GlobalDecision should be a boolean", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $uploadTreeId = intval($args['itemId']);

    $returnVal = null;
    $uploadDao = $this->restHelper->getUploadDao();

    // check if the given key exists in the known decision types
    if (!array_key_exists($decisionType, $this->decisionTypes->getMap())) {
      $returnVal = new Info(400, "Decision Type should be one of the following keys: " . implode(", ", array_keys($this->decisionTypes->getMap())), InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadTreeId), "uploadtree_pk", $uploadTreeId)) {
      $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
    }

    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    try {
      $viewLicensePlugin = $this->restHelper->getPlugin('view-license');
      $_GET['clearingTypes'] = $decisionType;
      $_GET['globalDecision'] = $global ? 1 : 0;
      $viewLicensePlugin->updateLastItem($this->restHelper->getUserId(), $this->restHelper->getGroupId(), $uploadTreeId, $uploadTreeId);
      $returnVal = new Info(200, "Successfully set decision", InfoType::INFO);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    } catch (\Exception $e) {
      $returnVal = new Info(500, $e->getMessage(), InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }
}
