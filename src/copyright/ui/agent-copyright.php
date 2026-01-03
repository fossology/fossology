<?php
/*
 SPDX-FileCopyrightText: © 2010-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @class CopyrightAgentPlugin
 * @brief Create UI plugin for copyright agent
 */
class CopyrightAgentPlugin extends AgentPlugin
{
  /** @var copyrightDesc */
  private $copyrightDesc = "Performs file scanning to find text fragments that could be relevant to copyrights, emails, and URLs";

  public function __construct()
  {
    $this->Name = "agent_copyright";
    $this->Title =  _("Copyright/Email/URL/Author Analysis <img src=\"images/info_16.png\" data-toggle=\"tooltip\" title=\"".$this->copyrightDesc."\" class=\"info-bullet\"/>");
    $this->AgentName = "copyright";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  
  function AgentHasResults($uploadId=0)
{
    // Check if table exists first
    global $pdo; // Assuming PDO object is available

    try {
        $stmt = $pdo->query("SELECT to_regclass('public.copyright_ars') AS tbl");
        $result = $stmt->fetch();
        if (!$result['tbl']) {
            // Table does not exist yet
            return false;
        }
    } catch (\Exception $e) {
        // Something went wrong with DB, fail safely
        return false;
    }

    // Table exists, call original CheckARS
    return CheckARS($uploadId, $this->AgentName, "copyright scanner", "copyright_ars");
}


  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=[],
      $arguments=null, $request=null, $unpackArgs=null)
  {
    if ($request != null && !is_array($request)) {
      $unpackArgs = intval($request->get('scm', 0)) == 1 ? '-I' : '';
    } else {
      $unpackArgs = intval(@$_POST['scm']) == 1 ? '-I' : '';
    }
    if ($this->AgentHasResults($uploadId) == 1) {
      return 0;
    }

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0) {
      return $jobQueueId;
    }

    $args = $unpackArgs;
    if (!empty($unpackArgs)) {
      return $this->doAgentAdd($jobId, $uploadId, $errorMsg, array("agent_mimetype"),$uploadId,$args,$request);
    } else {
      return $this->doAgentAdd($jobId, $uploadId, $errorMsg, array("agent_adj2nest"), $uploadId, null, $request);
    }
  }

  /**
   * Check if agent already included in the dependency list
   * @param mixed  $dependencies Array of job dependencies
   * @param string $agentName    Name of the agent to be checked for
   * @return boolean true if agent already in dependency list else false
   */
  protected function isAgentIncluded($dependencies, $agentName)
  {
    foreach ($dependencies as $dependency) {
      if ($dependency == $agentName) {
        return true;
      }
      if (is_array($dependency) && $agentName == $dependency['name']) {
        return true;
      }
    }
    return false;
  }
}

register_plugin(new CopyrightAgentPlugin());
