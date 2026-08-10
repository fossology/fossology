<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for agent related REST endpoints
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @class AgentController
 * @brief Controller for agent related REST endpoints
 */
class AgentController extends RestController
{
  /**
   * Renew the current Monk agent, equivalent to the legacy "Manage Monk
   * Revision" admin page (admin_monk_revision).
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function renewMonkAgent($request, $response, $args)
  {
    // Check if the request comes from the admin.
    $this->throwNotAdminException();

    $agentDao = $this->container->get('dao.agent');
    $monk = $agentDao->getCurrentAgentRef('monk');
    if (empty($monk->getAgentId())) {
      throw new HttpNotFoundException("Monk agent has not run yet, nothing to renew.");
    }

    $agentDao->renewCurrentAgent('monk');

    $returnVal = new Info(200, "Monk agent revision has been renewed.", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }
}
