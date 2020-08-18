<?php
/***********************************************************
 * Copyright (C) 2019
 * Author: Vivek Kumar<vvksindia@gmail.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\AgentPlugin;

include_once(dirname(__DIR__) . "/agent/version.php");

class SpashtAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "spasht";
    $this->Title = _("Spasht Analysis");
    $this->AgentName = "spasht";

    parent::__construct();
  }

  /**
   * Do not register the plugin to UI as spasht requires additional parameters
   * to run
   *
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    return false;
  }

  /**
   * Agent can be rescheduled for a new package. No need to check
   * AgentHasResults()
   *
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=array(), $arguments=null)
  {
    $dependencies[] = "agent_adj2nest";

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0) {
      return $jobQueueId;
    }

    $args = is_array($arguments) ? '' : $arguments;
    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $uploadId, $args);
  }


  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "spasht agent", "spasht_ars");
  }
}

register_plugin(new SpashtAgentPlugin());
