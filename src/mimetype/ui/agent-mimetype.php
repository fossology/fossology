<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @file
 * @brief UI plugin for mimetype agent
 * @class MimetypeAgentPlugin
 * @brief UI plugin for mimetype agent
 */
class MimetypeAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_mimetype";
    $this->Title =  _("MIME-type Analysis (Determine mimetype of every file.  Not needed for licenses or buckets)");
    $this->AgentName = "mimetype";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "mimetype agent", "mimetype_ars");
  }
}

register_plugin(new MimetypeAgentPlugin());
