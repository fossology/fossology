<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

class IpraAgentPlugin extends AgentPlugin
{
  /** @var IPRADesc */
  private $IPRADesc = "Performs file scanning to find text fragments that could be relevant for patent issues. Note: More keywords related to patents can be included using the configuration file.";

  public function __construct()
  {
    $this->Name = "agent_ipra";
    $this->Title = _("IPRA Analysis <img src=\"images/info_16.png\" data-toggle=\"tooltip\" title=\"".$this->IPRADesc."\" class=\"info-bullet\"/>");
    $this->AgentName = "ipra";

    parent::__construct();
  }

  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "ipra scanner", "ipra_ars");
  }
}

register_plugin(new IpraAgentPlugin());
