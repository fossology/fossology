<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @namespace Fossology::DelAgent::UI::Page
 * @brief UI namespace for delagent
 */
namespace Fossology\DelAgent\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Fossology\DelAgent\UI\DeleteMessages;
use Fossology\DelAgent\UI\DeleteResponse;

/**
 * @class AdminUploadDelete
 * @brief UI plugin to delete uploaded files
 */
class AdminUploadDelete extends DefaultPlugin
{
  const NAME = "admin_upload_delete";

  /** @var UploadDao */
  private $uploadDao;

  /** @var FolderDao */
  private $folderDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Delete Uploaded File"),
        self::MENU_LIST => "Organize::Uploads::Delete Uploaded File",
        self::PERMISSION => Auth::PERM_WIRTE,
        self::REQUIRES_LOGIN => true
    ));

    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->folderDao = $container->get('dao.folder');
  }


  /**
   * @brief Delete a given upload
   * @param int $uploadpk The upload(upload_id) you want to delete
   * @return NULL on success, string on failure.
   */
  private function delete($uploadpk)
  {
    /* Prepare the job: job "Delete" */
    $user_pk = Auth::getUserId();
    $group_pk = Auth::getGroupId();
    $jobpk = JobAddJob($user_pk, $group_pk, "Delete", $uploadpk);
    if (empty($jobpk) || ($jobpk < 0)) {
      return _("Failed to create job record");
    }
    /* Add job: job "Delete" has jobqueue item "delagent" */
    $jqargs = "DELETE UPLOAD $uploadpk";
    $jobqueuepk = JobQueueAdd($jobpk, "delagent", $jqargs, null, null);
    if (empty($jobqueuepk)) {
      return _("Failed to place delete in job queue");
    }

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (!$success) {
      $error_msg = _("Is the scheduler running? Your jobs have been added to job queue.");
      $URL = Traceback_uri() . "?mod=showjobs&upload=$uploadpk ";
      $LinkText = _("View Jobs");
      return "$error_msg <a href=\"$URL\">$LinkText</a>";
    }
    return null;
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::handle()
   * @see Fossology::Lib::Plugin::DefaultPlugin::handle()
   */
  protected function handle(Request $request)
  {
    $vars = array();

    $uploadpks = $request->get('uploads');
    $folderId = $request->get('folder');

    if (!empty($uploadpks)) {
      $vars['message'] = $this->initDeletion($uploadpks, $folderId);
    }

    $vars['uploadScript'] = ActiveHTTPscript("Uploads");
    $vars['tracbackUri'] = Traceback_uri();
    $root_folder_pk = GetUserRootFolder();
    $vars['rootFolderListOptions'] = FolderListOption($root_folder_pk, 0);

    $uploadList = array();
    $folderList = FolderListUploads_perm($root_folder_pk, Auth::PERM_WRITE);
    foreach ($folderList as $L) {
      if (!empty($L['is_editable']) && $L['is_editable']) {
        $desc = $L['name'];
        if (!empty($L['upload_desc'])) {
            $desc .= " (" . $L['upload_desc'] . ")";
        }
        if (!empty($L['upload_ts'])) {
            $desc .= " :: " . substr($L['upload_ts'], 0, 19);
        }
        $uploadList[$L['upload_pk']] = $desc;
    }
    }
    $vars['uploadList'] = $uploadList;

    return $this->render('admin_upload_delete.html.twig', $this->mergeWithDefault($vars));
  }


  /**
   * @brief starts deletion and handles error messages
   * @param array $uploadpks Upload ids to be deleted
   * @param int   $folderId  Id of folder containing uploads
   * @return string Error or success message
   */
  private function initDeletion($uploadpks, $folderId)
  {
    if (sizeof($uploadpks) <= 0) {
      return _("No uploads selected");
    }

    $errorMessages = [];
    $deleteResponse = null;
    foreach ($uploadpks as $uploadPk) {
      $deleteResponse = $this->TryToDelete(intval($uploadPk), $folderId);

      if ($deleteResponse->getDeleteMessageCode() != DeleteMessages::SUCCESS) {
        $errorMessages[] = $deleteResponse;
      }
    }

    if (sizeof($uploadpks) == 1) {
      return $deleteResponse->getDeleteMessageString().$deleteResponse->getAdditionalMessage();
    }

    $displayMessage = "";
    $countErrorMessages = array_count_values(array_filter($errorMessages));
    if (in_array(DeleteMessages::SCHEDULING_FAILED, $errorMessages)) {
      $displayMessage .= "<br/>Scheduling failed for " .
              $countErrorMessages[DeleteMessages::SCHEDULING_FAILED] . " uploads<br/>";
    }

    if (in_array(DeleteMessages::NO_PERMISSION, $errorMessages)) {
      $displayMessage .= "No permission to delete " .
              $countErrorMessages[DeleteMessages::NO_PERMISSION] . " uploads<br/>";
    }

    $displayMessage .= "Deletion of " .
            (sizeof($uploadpks) - sizeof($errorMessages)) . " projects queued";
    return DisplayMessage($displayMessage);
  }

  /**
   * @brief Given a folder_pk, try to add a job after checking permissions.
   * @param $uploadpk The upload(upload_id) you want to delete
   * @param $folderId The folder(folder_id) containing the uploads
   * @return string with the message.
   */
  public function TryToDelete($uploadpk, $folderId)
  {
    if (!$this->uploadDao->isEditable($uploadpk, Auth::getGroupId())) {
      $returnMessage = DeleteMessages::NO_PERMISSION;
      return new DeleteResponse($returnMessage);
    }

    if (!empty($this->folderDao->isRemovableContent($uploadpk,2))) {
      $this->folderDao->removeContentById($uploadpk, $folderId);
      $returnMessage = DeleteMessages::SUCCESS;
      return new DeleteResponse($returnMessage);
    } else {
      $rc = $this->delete(intval($uploadpk));
    }

    if (! empty($rc)) {
      $returnMessage = DeleteMessages::SCHEDULING_FAILED;
      return new DeleteResponse($returnMessage);
    }

    /* Need to refresh the screen */
    $URL = Traceback_uri() . "?mod=showjobs&upload=$uploadpk ";
    $LinkText = _("View Jobs");
    $returnMessage = DeleteMessages::SUCCESS;
    return new DeleteResponse($returnMessage,
      " <a href=$URL>$LinkText</a>");
  }
}

register_plugin(new AdminUploadDelete());
