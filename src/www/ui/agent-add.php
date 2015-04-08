<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
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
  protected function handle(Request $request) {
    $folderId = intval($request->get('folder'));
    if (empty($folderId)) {
      $folderId = FolderGetTop();
    }
    $uploadId = intval($request->get('upload'));
    $agents = $request->get('agents') ?: '';

    if (!empty($uploadId) && !empty($agents) && is_array($agents))
    {
      $rc = $this->agentsAdd($uploadId,$agents);
      if (empty($rc))
      {
        $status = GetRunnableJobList();
        $scheduler_msg = empty($status) ? _("Is the scheduler running? ") : '';
        $url = Traceback_uri() . "?mod=showjobs&upload=$uploadId";
        $text = _("Your jobs have been added to job queue.");
        $linkText = _("View Jobs");
        $msg = "$scheduler_msg"."$text <a href=\"$url\">$linkText</a>";
        $vars['message'] = $msg;
      }
      else
      {
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
   
    return $this->render('agent_adder.html.twig', $this->mergeWithDefault($vars));
  }
  
  /**
   * @brief Add an upload to multiple agents.
   * @param int $uploadId
   * @param string[] $agentlist - list of agents
   * @return NULL on success, error message string on failure
   */
  private function agentsAdd($uploadId, $agentlist)
  {
    if (!is_array($agentlist)) {
      return "bad parameters";
    }
    if (!$uploadId) {
      return "agent-add.php AgentsAdd(): No upload_pk specified";
    }
    
    /** @var Upload $upload */
    $upload = $GLOBALS['container']->get('dao.upload')->getUpload($uploadId);
    if ($upload===null)
    {
      return _("Upload") . " " . $uploadId . " " .  _("not found");
    }

    $agents = array();
    $agentList = listAgents();
    foreach($agentList as $agentName => &$agentPlugin) {
      if (in_array($agentName, $agentlist))
      {
        $agents[$agentName] = &$agentPlugin;
      }
    }
    if (count($agents)==0)
    {
      return _("no valid agent specified");
    }

    $jobId = JobAddJob(Auth::getUserId(), Auth::getGroupId(), $upload->getFilename(), $uploadId);
    return AgentSchedule($jobId, $uploadId, $agents);
  }
  
}

register_plugin(new AgentAdder());