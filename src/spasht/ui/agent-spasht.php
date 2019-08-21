<?php
# Copyright 2019
# Author: Vivek Kumar<vvksindia@gmail.com>
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.


use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\AgentPlugin;

class SpashtAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_spasht";
    $this->Title = _("Spasht Analysis");
    $this->AgentName = "spasht";

    parent::__construct();
  }

  /**
   * Register the plugin to UI
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
      menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }


   /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "spasht scanner", "spasht_ars");
  }
}

register_plugin(new SpashtAgentPlugin());
