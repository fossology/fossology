<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

class KotobaBulkAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_kotoba_bulk";
    $this->Title =  _("Kotoba Bulk License Clearing");
    $this->AgentName = "kotobabulk";

    parent::__construct();
  }

  function preInstall()
  {
    // no AgentCheckBox
  }

  function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=[],
     $arguments=null, $request=null, $unpackArgs=null)
  {
    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies,
        $arguments, null, $request);
  }
}

register_plugin(new KotobaBulkAgentPlugin());
