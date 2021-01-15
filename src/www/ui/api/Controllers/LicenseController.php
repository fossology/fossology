<?php

/***************************************************************
 Copyright (C) 2021 HH Partners

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
 * @brief Controller for licenses
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Dao\LicenseDao;
use Fossology\UI\Api\Models\License;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @class LicenseController
 * @brief Controller for licenses
 */
class LicenseController extends RestController
{
  /**
   * @var LicenseDao $licenseDao
   * License Dao object
   */
  private $licenseDao;

  /**
   * @param ContainerInterface $container
   */
  public function __construct($container)
  {
    parent::__construct($container);
    $this->licenseDao = $this->container->get('dao.license');
  }

  /**
   * Get the license information based on the provided parameters
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   * @return Response
   */
  public function getLicense($request, $response, $args)
  {
    $shortName = $request->getHeaderLine("shortName");

    if (empty($shortName)) {
      $error = new Info(
        400,
        "'shortName' parameter missing from query.",
        InfoType::ERROR
      );
      return $response->withJson($error->getArray(), $error->getCode());
    }

    $license = $this->licenseDao->getLicenseByShortName($shortName);

    if ($license === NULL) {
      $error = new Info(
        404,
        "No license found with short name '{$shortName}'.",
        InfoType::ERROR
      );
      return $response->withJson($error->getArray(), $error->getCode());
    }

    $returnVal = new License(
      $license->getId(),
      $license->getShortName(),
      $license->getFullName(),
      $license->getText(),
      $license->getRisk()
    );

    return $response->withJson($returnVal->getArray(), 200);
  }
}
