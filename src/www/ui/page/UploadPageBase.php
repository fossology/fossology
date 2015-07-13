<?php
/***********************************************************
 * Copyright (C) 2015 Siemens AG
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
         
namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Plugin\AgentPlugin;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\UI\MenuHook;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;

abstract class UploadPageBase extends DefaultPlugin
{
  const NAME = "upload_file";
  const FOLDER_PARAMETER_NAME = 'folder';

  const DESCRIPTION_INPUT_NAME = 'descriptionInputName';
  const DESCRIPTION_VALUE = 'descriptionValue';
  const UPLOAD_FORM_BUILD_PARAMETER_NAME = 'uploadformbuild';
  const PUBLIC_ALL = 'public';
  const PUBLIC_GROUPS = 'protected';

  /** @var FolderDao */
  private $folderDao;
  /** @var UploadDao */
  private $uploadDao;
  /** @var Logger */
  private $logger;
  
  public function __construct($name, $parameters = array())
  {
    parent::__construct($name, $parameters);

    $this->folderDao = $this->getObject('dao.folder');
    $this->uploadDao = $this->getObject('dao.upload');
    $this->logger = $this->getObject('logger');
  }
  abstract protected function handleUpload(Request $request);
  abstract protected function handleView(Request $request, $vars);
  
  protected function handle(Request $request)
  {
    // Handle request
    $this->folderDao->ensureTopLevelFolder();
    
    $message = "";
    $description = "";
    if ($request->isMethod(Request::METHOD_POST))
    {
      list($success, $message, $description) = $this->handleUpload($request);
    }
    $vars['message'] = $message;
    $vars['descriptionInputValue'] = $description ?: "";
    $vars['descriptionInputName'] = self::DESCRIPTION_INPUT_NAME;
    $vars['folderParameterName'] = self::FOLDER_PARAMETER_NAME;
    $vars['upload_max_filesize'] = ini_get('upload_max_filesize');
    $vars['agentCheckBoxMake'] = '';

    $rootFolder = $this->folderDao->getRootFolder(Auth::getUserId());
    $folderStructure = $this->folderDao->getFolderStructure($rootFolder->getId());

    $vars['folderStructure'] = $folderStructure;
    $vars['baseUrl'] = $request->getBaseUrl();
    $vars['moduleName'] = $this->getName();
    $vars[self::FOLDER_PARAMETER_NAME] = $request->get(self::FOLDER_PARAMETER_NAME);
    
    $parmAgentList = MenuHook::getAgentPluginNames("ParmAgents");
    $vars['parmAgentContents'] = array();
    $vars['parmAgentFoots'] = array();
    foreach($parmAgentList as $parmAgent) {
      $agent = plugin_find($parmAgent);
      $vars['parmAgentContents'][] = $agent->renderContent($vars);
      $vars['parmAgentFoots'][] = $agent->renderFoot($vars);
    }
    
    $session = $request->getSession();
    $session->set(self::UPLOAD_FORM_BUILD_PARAMETER_NAME, time().':'.$_SERVER['REMOTE_ADDR']);
    $vars['uploadFormBuild'] = $session->get(self::UPLOAD_FORM_BUILD_PARAMETER_NAME);
    $vars['uploadFormBuildParameterName'] = self::UPLOAD_FORM_BUILD_PARAMETER_NAME;

    if (@$_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_WRITE)
    {
      $skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
      $vars['agentCheckBoxMake'] = AgentCheckBoxMake(-1, $skip);
    }
    return $this->handleView($request, $vars);
  }
  
  protected function postUploadAddJobs(Request $request, $fileName, $uploadId, $jobId = null, $wgetDependency = false)
  {
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();

    if ($jobId === null) {
      $jobId = JobAddJob($userId, $groupId, $fileName, $uploadId);
    }
    $dummy = "";
    $adj2nestDependency = array();
    if ($wgetDependency)
    {
      $unpackplugin = \plugin_find("agent_unpack");
      $ununpackJqId = $unpackplugin->AgentAdd($jobId, $uploadId, $dummy, array("wget_agent"));
      $adj2nestDependency = array('name'=>'agent_unpack',AgentPlugin::PRE_JOB_QUEUE=>$ununpackJqId);
    }
    $adj2nestplugin = \plugin_find('agent_adj2nest');
    $adj2nestplugin->AgentAdd($jobId, $uploadId, $dummy, $adj2nestDependency);

    $checkedAgents = checkedAgents();
    AgentSchedule($jobId, $uploadId, $checkedAgents);

    $errorMsg = '';
    $parmAgentList = MenuHook::getAgentPluginNames("ParmAgents");
    $plainAgentList = MenuHook::getAgentPluginNames("Agents");
    $agentList = array_merge($plainAgentList, $parmAgentList);
    foreach($parmAgentList as $parmAgent) {
      $agent = plugin_find($parmAgent);
      $agent->scheduleAgent($jobId, $uploadId, $errorMsg, $request, $agentList);
    }
    
    $status = GetRunnableJobList();
    $message = empty($status) ? _("Is the scheduler running? ") : "";
    $jobUrl = Traceback_uri() . "?mod=showjobs&upload=$uploadId";
    $message .= _("The file") . " " . $fileName . " " . _("has been uploaded. It is") . ' <a href=' . $jobUrl . '>upload #' . $uploadId . "</a>.\n";
    if ($request->get('public')==self::PUBLIC_GROUPS)
    {
      $this->uploadDao->makeAccessibleToAllGroupsOf($uploadId, $userId);
    }
    return $message;
  }
}