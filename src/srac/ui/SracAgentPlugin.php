<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac\UI;

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class SracAgentPlugin
 * @brief UI AgentPlugin for SRAC safety-relevance sidecar correlation.
 */
class SracAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_srac";
    $this->Title = _("SRAC correlation");
    $this->AgentName = "srac";

    parent::__construct();
  }

  function preInstall()
  {
    // No automatic checkbox during general analysis; explicitly scheduled or selected
  }

  /**
   * @param array $uploads Array of upload ids
   * @return string
   */
  public function uploadsAdd($uploads)
  {
    if (count($uploads) == 0) {
      return '';
    }
    return '--uploadsAdd=' . implode(',', array_keys($uploads));
  }
}

if (function_exists('register_plugin')) {
  register_plugin(new SracAgentPlugin());
}
