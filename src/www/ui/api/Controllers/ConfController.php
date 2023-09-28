<?php
/*
 Author: Soham Banerjee <sohambanerjee4abc@hotmail.com>
 SPDX-FileCopyrightText: © 2023 Soham Banerjee <sohambanerjee4abc@hotmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for copyright queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Conf;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;

class ConfController extends RestController
{
  /**
   * Get all conf info for a particular upload
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function getConfInfo($request, $response, $args)
  {
    $uploadPk = $args["id"];
    $returnVal = null;
    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadPk)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    }
    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $response_view = $this->restHelper->getUploadDao()->getReportInfo($uploadPk);
    $returnVal = new Conf($response_view);
    return $response->withJson($returnVal->getArray(), 200);
  }

  /**
   * Update config data for the admin
   *
   * @param  ServerRequestInterface $request
   * @param  ResponseHelper         $response
   * @param  array                  $args
   * @return ResponseHelper
   */
  public function updateConfData($request, $response, $args)
  {
    $uploadPk = $args["id"];
    $body = $this->getParsedBody($request);
    $confObj = new Conf();
    $error = null;

    if (empty($body) || !array_key_exists("key", $body) ||
        !array_key_exists("value", $body)) {
      $error = new Info(400, "Invalid request.", InfoType::ERROR);
    } elseif (!$confObj->doesKeyExist($body['key'])) {
      $error = new Info(400, "Invalid key " . $body["key"] . " sent.", InfoType::ERROR);
    }
    if ($error !== null) {
      return $response->withJson($error->getArray(), $error->getCode());
    }

    $key = $body['key'];
    $value = $body['value'];
    $result = $this->restHelper->getUploadDao()->updateReportInfo($uploadPk,
      $confObj->getKeyColumnName($key), $value);

    if ($result) {
      $info = new Info(200, "Successfully updated " . $key, InfoType::INFO);
    } else {
      $info = new Info(500, "Failed to update " . $key, InfoType::ERROR);
    }
    return $response->withJson($info->getarray(), $info->getCode());
  }
}
