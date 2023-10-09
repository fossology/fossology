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

use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
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
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getConfInfo($request, $response, $args)
  {
    $uploadPk = $args["id"];
    $this->uploadAccessible($uploadPk);

    $response_view = $this->restHelper->getUploadDao()->getReportInfo($uploadPk);
    $returnVal = new Conf($response_view);
    return $response->withJson($returnVal->getArray(), 200);
  }

  /**
   * Update config data for the admin
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function updateConfData($request, $response, $args)
  {
    $uploadPk = $args["id"];
    $body = $this->getParsedBody($request);
    $confObj = new Conf();

    $this->uploadAccessible($uploadPk);

    if (empty($body) || !array_key_exists("key", $body) ||
        !array_key_exists("value", $body)) {
      throw new HttpBadRequestException("Invalid request.");
    } elseif (!$confObj->doesKeyExist($body['key'])) {
      throw new HttpBadRequestException("Invalid key " . $body["key"] .
        " sent.");
    }

    $key = $body['key'];
    $value = $body['value'];
    $result = $this->restHelper->getUploadDao()->updateReportInfo($uploadPk,
      $confObj->getKeyColumnName($key), $value);

    if ($result) {
      $info = new Info(200, "Successfully updated " . $key, InfoType::INFO);
    } else {
      throw new HttpInternalServerErrorException("Failed to update " . $key);
    }
    return $response->withJson($info->getarray(), $info->getCode());
  }
}
