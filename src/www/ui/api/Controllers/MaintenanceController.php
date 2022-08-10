<?php
/*
 SPDX-FileCopyrightText: © 2022 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Auth\Auth;
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
   */
  public function createMaintenance($request, $response, $args)
  {
    // Check if the request comes from the admin.
    if (!Auth::isAdmin()) {
      $returnVal =  new Info(401, "Only admins can run this job.", InfoType::ERROR);
    } else {

      $body = $this->getParsedBody($request);

      if ($body['options']) {

        //Remove all duplicate options

        if (!is_array($body['options'])) {

          $returnVal = new Info(400, "Options property should be an array.", InfoType::ERROR);

        } else if (in_array("o",$body["options"]) && empty($body["goldDate"])) {

            $returnVal = new Info(400, "Please provide a gold date.", InfoType::ERROR);

        } else if (in_array("l",$body["options"]) && empty($body["logsDate"])) {

          $returnVal = new Info(400, "Please provide a log date.", InfoType::ERROR);

        } else {

          $body['options'] = array_unique($body['options']);
          $alteredOptions = array();
          $maintain = $this->restHelper->getPlugin('maintagent');
          $existingOptions = $maintain->getOptions();

          //Check if all the given keys exist in the known options array
          foreach ($body['options'] as $key) {
            if (!array_key_exists($key, $existingOptions)) {
              $returnVal = new Info(404, "KEY '" . $key . "' NOT FOUND!", InfoType::ERROR);
              return $response->withJson($returnVal->getArray(), $returnVal->getCode());
            }
            $alteredOptions[$key] = $key;
          }

          $body['options'] = $alteredOptions;
          $mess = $maintain->handle($body);
          $returnVal = new Info(201, $mess, InfoType::INFO);
        }

      } else {
        $returnVal = new Info(400,"No options provided!", InfoType::ERROR);
      }
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }
}
