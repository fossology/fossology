<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Dao\PolicyDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface as Request;

class PolicyController extends RestController
{
  /** @var PolicyDao */
  private $policyDao;

  /** @var LicenseDao */
  private $licenseDao;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->policyDao = new PolicyDao($this->dbHelper->getDbManager(), $this->container->get('logger'));
    $this->licenseDao = $this->container->get('dao.license');
  }

  public function getAllPolicies($request, ResponseHelper $response, $args)
  {
    $policies = $this->policyDao->getAllPolicies();
    return $response->withJson($policies, 200);
  }

  public function getPolicy($request, ResponseHelper $response, $args)
  {
    $shortName = $args["shortname"];
    if (empty($shortName)) {
      throw new HttpBadRequestException("Short name missing from request.");
    }
    $license = $this->licenseDao->getLicenseByShortName($shortName, $this->restHelper->getGroupId());
    if ($license === null) {
      throw new HttpNotFoundException("No license found with short name '$shortName'.");
    }

    $policy = $this->policyDao->getPolicyByLicenseId($license->getId());
    if ($policy === null) {
      throw new HttpNotFoundException("No policy found for license '$shortName'.");
    }

    return $response->withJson($policy, 200);
  }

  public function setPolicy($request, ResponseHelper $response, $args)
  {
    if (!Auth::isClearingAdmin()) {
      throw new HttpForbiddenException("Only clearing admins can modify license policies.");
    }

    $shortName = $args["shortname"];
    if (empty($shortName)) {
      throw new HttpBadRequestException("Short name missing from request.");
    }

    $params = $this->getParsedBody($request);
    
    // Cast to int just in case it comes as a string '0', '1', '2'
    if (!isset($params['policy_rank']) || !in_array((int)$params['policy_rank'], [0, 1, 2], true)) {
      throw new HttpBadRequestException("Invalid or missing 'policy_rank'. Must be 0, 1, or 2.");
    }

    $license = $this->licenseDao->getLicenseByShortName($shortName, $this->restHelper->getGroupId());
    if ($license === null) {
      throw new HttpNotFoundException("No license found with short name '$shortName'.");
    }

    $this->policyDao->setLicensePolicy($license->getId(), (int)$params['policy_rank'], $this->restHelper->getUserId(), 'API', $request->getServerParams()['REMOTE_ADDR'] ?? null);

    $info = new Info(200, "Successfully updated policy for '$shortName'.", InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  public function deletePolicy($request, ResponseHelper $response, $args)
  {
    if (!Auth::isClearingAdmin()) {
      throw new HttpForbiddenException("Only clearing admins can modify license policies.");
    }

    $shortName = $args["shortname"];
    if (empty($shortName)) {
      throw new HttpBadRequestException("Short name missing from request.");
    }

    $license = $this->licenseDao->getLicenseByShortName($shortName, $this->restHelper->getGroupId());
    if ($license === null) {
      throw new HttpNotFoundException("No license found with short name '$shortName'.");
    }

    $deleted = $this->policyDao->deleteLicensePolicy($license->getId(), $this->restHelper->getUserId(), 'API', $request->getServerParams()['REMOTE_ADDR'] ?? null);
    if (!$deleted) {
      throw new HttpNotFoundException("No policy to delete for license '$shortName'.");
    }

    $info = new Info(200, "Successfully deleted policy for '$shortName'.", InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }
}
