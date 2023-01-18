<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

class IpraAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_ipra";
    $this->Title = _("IPRA Analysis, scanning for text fragments potentially relevant for patent issues");
    $this->AgentName = "ipra";

    parent::__construct();
  }

  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "ipra scanner", "ipra_ars");
  }
}

register_plugin(new IpraAgentPlugin());
