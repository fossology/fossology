<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Agent's UI plugin to handle requests
 */

namespace Fossology\DecisionImporter\UI;

use Exception;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decision importer agent's plugin to show menu and handle UI requests.
 */
class AgentDecisionImporterPlugin extends DefaultPlugin
{
  const NAME = 'ui_fodecisionimporter';                 ///< Name of the plugin

  /** @var UploadDao */
  private $uploadDao;
  /** @var FolderDao */
  private $folderDao;
  /** @var UserDao $userDao */
  private $userDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
      self::TITLE => _("Decision Dump Importer"),
      self::PERMISSION => Auth::PERM_ADMIN,
      self::REQUIRES_LOGIN => TRUE
    ));
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->folderDao = $GLOBALS['container']->get('dao.folder');
    $this->userDao = $GLOBALS['container']->get('dao.user');
  }

  function preInstall()
  {
    menu_insert("Browse-Pfile::Import&nbsp;FOSSology&nbsp;Dump", 0, self::NAME, $this->getTitle());
    menu_insert("Main::Upload::Import FOSSology Dump", 0, self::NAME, $this->getTitle());
  }

  /**
   * Handle request from UI and schedule the agent.
   * @param Request $request
   * @return Response
   * @throws Exception
   */
  protected function handle(Request $request): Response
  {
    if ($this->handleRequest($request) !== false) {
      /** @var showjobs $showJobsPlugin */
      $showJobsPlugin = plugin_find('showjobs');
      $showJobsPlugin->OutputOpen();
      return $showJobsPlugin->getResponse();
    } else {
      return $this->showUiToChoose();
    }
  }

  /**
   * Handle Symfony request to schedule the agent.
   * @param Request $request
   * @return bool|array False if request does not contain required parameters (to show UI).
   * @throws Exception
   */
  public function handleRequest(Request $request)
  {
    /** @var UploadedFile $uploadedFile */
    $uploadedFile = $request->files->get("report");
    $uploadId = intval($request->get("uploadselect"));
    if (empty($uploadId) ||
      $uploadedFile == null ||
      empty($uploadedFile->getSize())) {
      return false;
    } else {
      return $this->runImport($uploadId, $uploadedFile, $request);
    }
  }

  /**
   * Show to UI to upload report JSON
   * @return Response
   */
  protected function showUiToChoose(): Response
  {
    $vars = array();
    $groupId = Auth::getGroupId();

    $rootFolder = $this->folderDao->getRootFolder(Auth::getUserId());
    $folder_pk = GetParm('folder', PARM_INTEGER);
    if (empty($folder_pk)) {
      $folder_pk = $rootFolder->getId();
    }
    $vars['folderId'] = $folder_pk;

    $folderUploads = $this->folderDao->getFolderUploads($folder_pk, $groupId);
    $uploadsById = array();
    foreach ($folderUploads as $uploadProgress) {
      if ($uploadProgress->getGroupId() != $groupId) {
        continue;
      }
      if (!$this->uploadDao->isEditable($uploadProgress->getId(), $groupId)) {
        continue;
      }
      $display = $uploadProgress->getFilename() . _(" from ") . Convert2BrowserTime(date("Y-m-d H:i:s", $uploadProgress->getTimestamp()));
      $uploadsById[$uploadProgress->getId()] = $display;
    }
    $vars['uploadList'] = $uploadsById;

    $vars['userid'] = Auth::getUserId();
    $allUsers = $this->userDao->getAllUsers();
    $usersById = [];
    $usersById[$vars['userid']] = "-- ME --";         // Select current user by default
    foreach ($allUsers as $user) {
      if ($user['user_pk'] != $vars['userid']) {
        $usersById[$user['user_pk']] = htmlentities($user['user_name']);
      }
    }
    $vars['userList'] = $usersById;

    $uploadId = GetParm('upload', PARM_INTEGER);
    if (empty($uploadId)) {
      reset($uploadsById);
      $uploadId = key($uploadsById);
    }
    $vars['uploadId'] = $uploadId;

    $folderStructure = $this->folderDao->getFolderStructure($rootFolder->getId());
    $vars['folderStructure'] = $folderStructure;
    $vars['baseUri'] = Traceback_uri() . "?mod=" . self::NAME . "&folder=";

    return $this->render('AgentDecisionImporterPlugin.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * Translate the request to CLI arguments and schedule the agent.
   * @param int $uploadId
   * @param UploadedFile $report
   * @param Request $request
   * @return array
   * @throws Exception
   */
  protected function runImport(int $uploadId, UploadedFile $report, Request $request): array
  {
    /** @var FoDecisionImporter $decisionImportAgent */
    $decisionImportAgent = plugin_find('agent_fodecisionimporter');

    $jqCmdArgs = $decisionImportAgent->addReport($report);
    $jqCmdArgs .= $decisionImportAgent->setAdditionalJqCmdArgs($request);

    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $dbManager = $this->getObject('db.manager');
    $sql = 'SELECT jq_pk,job_pk FROM jobqueue, job '
      . 'WHERE jq_job_fk=job_pk AND jq_type=$1 AND job_group_fk=$4 AND job_user_fk=$3 AND jq_args=$2 AND jq_endtime IS NULL';
    $params = array($decisionImportAgent->AgentName, $uploadId, $userId, $groupId);
    $statementName = __METHOD__;
    if ($jqCmdArgs) {
      $sql .= ' AND jq_cmd_args=$5';
      $params[] = $jqCmdArgs;
      $statementName .= '.args';
    } else {
      $sql .= ' AND jq_cmd_args IS NULL';
    }

    $scheduled = $dbManager->getSingleRow($sql, $params, $statementName);
    if (!empty($scheduled)) {
      return array($scheduled['job_pk'], $scheduled['jq_pk']);
    }

    $upload = $this->getUpload($uploadId, $groupId);
    $jobId = JobAddJob($userId, $groupId, $upload->getFilename(), $uploadId);
    $error = "";
    $jobQueueId = $decisionImportAgent->AgentAdd($jobId, $uploadId, $error, array(), $jqCmdArgs);
    if ($jobQueueId < 0) {
      throw new Exception(_("Cannot schedule") . ": " . $error);
    }
    return array($jobId, $jobQueueId);
  }

  /**
   * Get an upload from ID if accessible.
   * @param int $uploadId Upload to get
   * @param int $groupId Group to get upload from
   * @return Upload       Upload if accessible
   * @throws Exception    If upload not accessible or not found.
   */
  protected function getUpload(int $uploadId, int $groupId): Upload
  {
    if ($uploadId <= 0) {
      throw new Exception(_("parameter error: $uploadId"));
    }
    if (!$this->uploadDao->isAccessible($uploadId, $groupId)) {
      throw new Exception(_("permission denied"));
    }
    $upload = $this->uploadDao->getUpload($uploadId);
    if ($upload === null) {
      throw new Exception(_('cannot find uploadId'));
    }
    return $upload;
  }
}

register_plugin(new AgentDecisionImporterPlugin());
