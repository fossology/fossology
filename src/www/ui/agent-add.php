<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\UI\MenuHook;
use Symfony\Component\HttpFoundation\Request;

class AgentAdder extends DefaultPlugin
{
  const NAME = "agent_add";

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Schedule an Analysis"),
        self::MENU_LIST => "Jobs::Schedule Agents",
        self::PERMISSION => Auth::PERM_WRITE
    ));
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    $folderId = intval($request->get('folder'));
    if (empty($folderId)) {
      $folderId = FolderGetTop();
    }
    $uploadId = intval($request->get('upload'));
    $agents = $request->get('agents') ?: '';
    $vars = [];

    if (! empty($uploadId) && ! empty($agents) && is_array($agents)) {
      $rc = $this->agentsAdd($uploadId, $agents, $request);
      if (is_numeric($rc)) {
        $status = GetRunnableJobList();
        $scheduler_msg = empty($status) ? _("Is the scheduler running? ") : '';
        $url = Traceback_uri() . "?mod=showjobs&upload=$uploadId";
        $text = _("Your jobs have been added to job queue.");
        $linkText = _("View Jobs");
        $msg = "$scheduler_msg" . "$text <a href=\"$url\">$linkText</a>";
        $vars['message'] = $msg;
      } else {
        $text = _("Scheduling of Agent(s) failed: ");
        $vars['message'] = $text.$rc;
      }
    }

    $vars['uploadScript'] = ActiveHTTPscript("Uploads");
    $vars['agentScript'] = ActiveHTTPscript("Agents");
    $vars['folderId'] = $folderId;
    $vars['folderListOptions'] = FolderListOption(-1,0,1,$folderId);
    $vars['folderListUploads'] = FolderListUploads_perm($folderId, Auth::PERM_WRITE);
    $vars['baseUri'] = Traceback_uri();
    $vars['uploadId'] = $uploadId;

    $parmAgentList = MenuHook::getAgentPluginNames("ParmAgents");
    $parmAgentFoots = '';
    $paramAgentIncludes = '';
    $out = '';
    foreach ($parmAgentList as $parmAgent) {
      $agent = plugin_find($parmAgent);
      $out .= "<br/><b>".ucfirst($agent->AgentName).":</b><br/>";
      $out .= $agent->renderContent($vars);
      $parmAgentFoots .= $agent->renderFoot($vars);
      $paramAgentIncludes .= $agent->getScriptIncludes($vars);
    }
    $vars['out'] = $out;
    $vars['outFoot'] = '<script language="javascript"> '.$parmAgentFoots.'</script>';
    $vars['paramIncludes'] = $paramAgentIncludes;

    return $this->render('agent_adder.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * @brief Add an upload to multiple agents.
   * @param int $uploadId
   * @param string[] $agentsToStart - list of agents
   * @return integer Job ID on success, error message string on failure
   */
  private function agentsAdd($uploadId, $agentsToStart, Request $request)
  {
    $mimetypeIgnore = intval($request->get('scm') == 1) ? '-I' : '';
    if (! is_array($agentsToStart)) {
      return "bad parameters";
    }
    if (! $uploadId) {
      return "agent-add.php AgentsAdd(): No upload_pk specified";
    }

    /* @var $upload Upload */
    $upload = $GLOBALS['container']->get('dao.upload')->getUpload($uploadId);
    if ($upload===null) {
      return _("Upload") . " " . $uploadId . " " .  _("not found");
    }
    $agents = array();
    $parmAgentList = MenuHook::getAgentPluginNames("ParmAgents");
    $plainAgentList = MenuHook::getAgentPluginNames("Agents");
    $agentList = array_merge($plainAgentList, $parmAgentList);
    foreach ($agentList as $agentName) {
      if (in_array($agentName, $agentsToStart)) {
        $agents[$agentName] = plugin_find($agentName);
      }
    }

    $jobId = JobAddJob(Auth::getUserId(), Auth::getGroupId(), $upload->getFilename(), $uploadId);
    $errorMsg = '';
    foreach ($parmAgentList as $parmAgent) {
      $agent = plugin_find($parmAgent);
      $agent->scheduleAgent($jobId, $uploadId, $errorMsg, $request);
    }

    $deciderRules = $request->get('deciderRules', []);
    if (count($deciderRules) == 1 && in_array('kotobaAgent', $deciderRules)) {
      if (isset($agents['agent_decider'])) {
        unset($agents['agent_decider']);
      }
    }

    foreach ($agents as &$agent) {
      if (!empty($mimetypeIgnore)) {
        $rv = $agent->AgentAdd($jobId, $uploadId, $errorMsg,
            array("agent_mimetype"), $mimetypeIgnore, $request);
      } else {
        $rv = $agent->AgentAdd($jobId, $uploadId, $errorMsg, array(), null,
            $request);
      }
      if ($rv == -1) {
        return $errorMsg;
      }
    }
    return $jobId;
  }

  /**
   * @brief Add an upload to multiple agents (wrapper for agentsAdd()).
   * @param int $uploadId
   * @param string[] $agentsToStart - list of agents
   * @return integer Job ID on success, error message string on failure
   * @sa agentsAdd()
   */
  public function scheduleAgents($uploadId, $agentsToStart, Request $request)
  {
    return $this->agentsAdd($uploadId, $agentsToStart, $request);
  }
}

register_plugin(new AgentAdder());
