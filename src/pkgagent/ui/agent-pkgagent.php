<?php
/*
 SPDX-FileCopyrightText: © 2009-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @dir
 * @brief UI plugin of pkgagent
 * @file
 * @brief UI plugin of pkgagent
 * @class PkgAgentPlugin
 * @brief UI plugin of pkgagent
 */
class PkgAgentPlugin extends AgentPlugin
{
  /** @var pkgagentDesc */
  private $pkgagentDesc = "Parse package headers. for example if files are rpm package listed, display their package information";

  public function __construct() {
    $this->Name = "agent_pkgagent";
    $this->Title = _("Package Analysis <img src=\"images/info_16.png\" data-toggle=\"tooltip\" title=\"".$this->pkgagentDesc."\" class=\"info-bullet\"/>");
    $this->AgentName = "pkgagent";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "package meta data scanner", "pkgagent_ars");
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    $dbManager = $GLOBALS['container']->get('db.manager');
    $latestPkgAgent = $dbManager->getSingleRow("SELECT agent_enabled FROM agent WHERE agent_name=$1 ORDER BY agent_ts LIMIT 1",array('pkgagent'));
    if (!empty($latestPkgAgent) && !$dbManager->booleanFromDb($latestPkgAgent['agent_enabled']))
    {
      return 0;
    }
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }

}

register_plugin(new PkgAgentPlugin());
