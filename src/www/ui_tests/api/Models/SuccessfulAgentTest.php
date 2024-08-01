<?php
/*
 * SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>
 *
 * SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for SuccessfulAgent model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\SuccessfulAgent;
use PHPUnit\Framework\TestCase;

class SuccessfulAgentTest extends TestCase
{
  /**
   * Provides test data and instances of the SuccessfulAgent class.
   * @return array An associative array containing test data and SuccessfulAgent objects.
   */
  private function getSuccessfulAgentInfo($version = ApiVersion::V2)
  {
    $data = null;
    if ($version == ApiVersion::V2) {
      $data =  [
        'agentId' => $this->getAgentId(),
        'agentRev' => $this->getAgentRev(),
        'agentName' => $this->getAgentName()
      ];
    } else {
      $data = [
        'agent_id' => $this->getAgentId(),
        'agent_rev' => $this->getAgentRev(),
        'agent_name' => $this->getAgentName()
      ];
    }
    return [
      'info' => $data,
      'obj' => new SuccessfulAgent($version, $data)
    ];

  }

}
