<?php
/*
 SPDX-FileCopyrightText: © 2019, 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>, Soham Banerjee <sohambanerjee4abc@hotmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller to get REST API information
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

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
   * @throws HttpErrorException
   */
  public function getInfo($request, $response)
  {
    global $SysConf;
    try {
      $yaml = new Parser();
      $yamlDocArray = $yaml->parse(file_get_contents(__DIR__ ."/../documentation/openapi.yaml"));
    } catch (ParseException $exception) {
      printf("Unable to parse the YAML string: %s", $exception->getMessage());
      throw new HttpInternalServerErrorException("Unable to read openapi.yaml",
        $exception);
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

  /**
   * Get the current OpenAPI info
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getOpenApi($request, $response)
  {
    $isJsonRequest = false;
    $requestFormat = $request->getHeader("Accept");
    if (!empty($requestFormat) && !empty($requestFormat[0])) {
      $requestFormat = $requestFormat[0];
    } else {
      $requestFormat = "";
    }
    if (strcasecmp($requestFormat, "application/vnd.oai.openapi+json") === 0
        || strcasecmp($requestFormat, "application/json") === 0) {
      $isJsonRequest = true;
    }
    if ($isJsonRequest) {
      try {
        $yaml = new Parser();
        $yamlDocArray = $yaml->parse(file_get_contents(dirname(__DIR__) . "/documentation/openapi.yaml"));
      } catch (ParseException $exception) {
        printf("Unable to parse the YAML string: %s", $exception->getMessage());
        throw new HttpInternalServerErrorException("Unable to read openapi.yaml",
          $exception);
      }
      return $response
        ->withHeader("Content-Disposition", "inline; filename=\"openapi.json\"")
        ->withJson($yamlDocArray, 200);
    }
    $yaml = file_get_contents(dirname(__DIR__) . "/documentation/openapi.yaml");
    if (empty($yaml)) {
      throw new HttpInternalServerErrorException("Unable to read openapi.yaml");
    }
    $response->getBody()->write($yaml);
    return $response
      ->withHeader("Content-Type", "application/vnd.oai.openapi;charset=utf-8")
      ->withHeader("Content-Disposition", "inline; filename=\"openapi.yaml\"")
      ->withStatus(200);
  }
}
