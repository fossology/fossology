<?php
/*
 SPDX-FileCopyrightText: © 2022 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Valid status inputs
 */


/**
 * @class ReportController
 * @brief Controller for Maintenance model
 */
class MaintenanceController extends RestController
{
  /**
   * Create maintenance
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function createMaintenance($request, $response, $args)
  {
    // Check if the request comes from the admin.
    $this->throwNotAdminException();

    $body = $this->getParsedBody($request);
    if (empty($body['options'])) {
      throw new HttpBadRequestException("No options provided!");
    }

    //Remove all duplicate options
    if (!is_array($body['options'])) {
      throw new HttpBadRequestException("Options property should be an array.");
    }
    if (in_array("o",$body["options"]) && empty($body["goldDate"])) {
      throw new HttpBadRequestException("Please provide a gold date.");
    }
    if (in_array("l",$body["options"]) && empty($body["logsDate"])) {
      throw new HttpBadRequestException("Please provide a log date.");
    }

    $body['options'] = array_unique($body['options']);
    $alteredOptions = array();
    /** @var \maintagent $maintain */
    $maintain = $this->restHelper->getPlugin('maintagent');
    $existingOptions = $maintain->getOptions();

    //Check if all the given keys exist in the known options array
    foreach ($body['options'] as $key) {
      if (!array_key_exists($key, $existingOptions)) {
        throw new HttpNotFoundException("KEY '" . $key . "' NOT FOUND!");
      }
      $alteredOptions[$key] = $key;
    }

    $body['options'] = $alteredOptions;
    $mess = $maintain->handle($body);
    $returnVal = new Info(201, $mess, InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }
}
