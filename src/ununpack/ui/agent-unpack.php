<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens

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
***********************************************************/
/**
 * @dir
 * @brief UI components for ununpack agent
 * @file
 * @brief UI plugin for ununpack agent
 */
use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class UnpackAgentPlugin
 * @brief UI for ununpack agent to schedule a job
 */
class UnpackAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_unpack";
    $this->Title = _("Schedule an Unpack");
    $this->AgentName = "ununpack";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, "ununpack", "Archive unpacker", "ununpack_ars");
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=array(), $arguments=null)
  {

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0)
    {
       return $jobQueueId;
    }

    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $uploadId, $arguments);
  }
}

register_plugin(new UnpackAgentPlugin());
