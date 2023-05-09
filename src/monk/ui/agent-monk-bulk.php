<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

class MonkBulkAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_monk_bulk";
    $this->Title =  _("Monk Bulk License Clearing");
    $this->AgentName = "monkbulk";

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

register_plugin(new MonkBulkAgentPlugin());
