<?php
/*
 SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\UI\MenuHook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \file ajax_upload_agents.php
 * \brief  This plugin is used to list all agents that can
 * be scheduled for a given upload.
 * This is NOT intended to be a user-UI plugin.
 * This is intended as an active plugin to provide support
 * data to the UI.
 */

class AjaxUploadAgents extends DefaultPlugin
{
  const NAME = "upload_agent_options";

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("List Agents for an Upload as Options"),
        self::PERMISSION => Auth::PERM_READ
    ));
  }

  /**
   * @brief This function checks if the current job was not already scheduled,
   * or did already fail (You can reschedule failed jobs)
   * @param $agentName   Name of the agent as specified in the agents table
   * @param $uploadId   Upload identifier
   * @return true if the agent is not currently scheduled for this upload, else false
   */
  function jobNotYetScheduled($agentName, $uploadId)
  {
    $sql = "select count(*) from job inner join jobqueue on job_pk=jq_job_fk "
            . "where job_upload_fk=$1 and jq_endtext is null and jq_type=$2";
    $queued = $GLOBALS['container']->get('db.manager')->getSingleRow($sql,array($uploadId,$agentName));
    return $queued['count']==0;
  }

  protected function handle(Request $request)
  {
    $uploadId = intval($request->get("upload"));
    if (empty($uploadId)) {
      throw new Exception('missing upload id');
    }

    $parmAgentList = MenuHook::getAgentPluginNames("ParmAgents");
    $plainAgentList = MenuHook::getAgentPluginNames("Agents");
    $agentList = array_merge($plainAgentList, $parmAgentList);
    $skipAgents = array("agent_unpack", "wget_agent");
    $out = "";
    $relevantAgents = array();
    foreach ($agentList as $agent) {
      if (array_search($agent, $skipAgents) !== false) {
        continue;
      }
      $plugin = plugin_find($agent);
      if (($plugin->AgentHasResults($uploadId) != 1) &&
        $this->jobNotYetScheduled($plugin->AgentName, $uploadId)) {
        $out .= "<option value='" . $agent . "'>";
        $position = strpos($plugin->Title, '<');
        if ($position !== false) {
          $out .= htmlentities(substr($plugin->Title, 0, $position));
        } else {
          $out .= htmlentities($plugin->Title);
        }
        $out .= "</option>\n";
        $relevantAgents[$agent] = $plugin->Title;
      }
    }

    $out = '<select multiple size="10" id="agents" name="agents[]">' .$out. '</select>';
    return new Response($out, Response::HTTP_OK, array('Content-Type'=>'text/plain'));
  }
}

register_plugin(new AjaxUploadAgents());
