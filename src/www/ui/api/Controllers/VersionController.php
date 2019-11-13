<?php
/***************************************************************
 Copyright (C) 2019 Siemens AG
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
 * @brief Controller to get REST API version
 */

namespace Fossology\UI\Api\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * @class VersionController
 * @brief Controller for REST API version
 */
class VersionController extends RestController
{
  /**
   * Get the current API version and authentication methods
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function getVersion($request, $response, $args)
  {
    try {
      $yaml = new Parser();
      $yamlDocArray = $yaml->parse(file_get_contents(__DIR__ ."/../documentation/openapi.yaml"));
    } catch (ParseException $exception) {
      printf("Unable to parse the YAML string: %s", $exception->getMessage());
    }
    $apiVersion = $yamlDocArray["info"]["version"];
    $security = array();
    foreach ($yamlDocArray["security"] as $secMethod) {
      $security[] = key($secMethod);
    }
    return $response->withJson(array(
      "version" => $apiVersion,
      "security" => $security
    ), 200);
  }
}
