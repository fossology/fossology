<?php
/***************************************************************
 Copyright (C) 2019,2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @file
 * @brief Controller to get REST API information
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Helper\ResponseHelper;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * @class InfoController
 * @brief Controller for REST API version
 */
class InfoController extends RestController
{
  /**
   * Get the current API info
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @return ResponseHelper
   */
  public function getInfo($request, $response)
  {
    global $SysConf;
    try {
      $yaml = new Parser();
      $yamlDocArray = $yaml->parse(file_get_contents(__DIR__ ."/../documentation/openapi.yaml"));
    } catch (ParseException $exception) {
      printf("Unable to parse the YAML string: %s", $exception->getMessage());
      return $response->withStatus(500, "Unable to read openapi.yaml");
    }
    $apiTitle = $yamlDocArray["info"]["title"];
    $apiDescription = $yamlDocArray["info"]["description"];
    $apiVersion = $yamlDocArray["info"]["version"];
    $apiContact = $yamlDocArray["info"]["contact"]["email"];
    $apiLicense = $yamlDocArray["info"]["license"];
    $security = array();
    foreach ($yamlDocArray["security"] as $secMethod) {
      $security[] = key($secMethod);
    }
    $fossInfo = [
      "version"    => null,
      "branchName" => null,
      "commitHash" => null,
      "commitDate" => null,
      "buildDate"  => null
    ];
    if (array_key_exists('BUILD', $SysConf)) {
      $fossInfo["version"]    = $SysConf['BUILD']['VERSION'];
      $fossInfo["branchName"] = $SysConf['BUILD']['BRANCH'];
      $fossInfo["commitHash"] = $SysConf['BUILD']['COMMIT_HASH'];
      if (strcasecmp($SysConf['BUILD']['COMMIT_DATE'], "unknown") != 0) {
        $fossInfo["commitDate"] = date(DATE_ATOM,
          strtotime($SysConf['BUILD']['COMMIT_DATE']));
      }
      if (strcasecmp($SysConf['BUILD']['BUILD_DATE'], "unknown") != 0) {
        $fossInfo["buildDate"] = date(DATE_ATOM,
          strtotime($SysConf['BUILD']['BUILD_DATE']));
      }
    }
    return $response->withJson(array(
      "name" => $apiTitle,
      "description" => $apiDescription,
      "version" => $apiVersion,
      "security" => $security,
      "contact" => $apiContact,
      "license" => [
        "name" => $apiLicense["name"],
        "url" => $apiLicense["url"]
      ],
      "fossology" => $fossInfo
    ), 200);
  }

  /**
   * Get the API health status
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args  Set to -1 in index.php if DB connection failed
   * @return ResponseHelper
   */
  public function getHealth($request, $response, $args)
  {
    $dbStatus = ($args === -1) ? "ERROR" : "OK";
    $schedStatus = "OK";
    if (! fo_communicate_with_scheduler("status", $output, $error_msg)
      && strstr($error_msg, "Connection refused") !== false) {
      $schedStatus = "ERROR";
    }
    $status = "OK";
    $statusCode = 200;
    if ($schedStatus !== "OK") {
      $status = "WARN";
    }
    if ($dbStatus !== "OK") {
      $status = "ERROR";
      $statusCode = 503;
    }
    return $response->withJson(array(
      "status" => $status,
      "scheduler" => [
        "status" => $schedStatus
      ],
      "db" => [
        "status" => $dbStatus
      ]
    ), $statusCode);
  }
}
