<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;

define("TITLE_ADMIN_FOLDER_DELETE", _("Delete Folder"));

/**
 * @class admin_folder_delete
 * @brief UI plugin to delete folders
 */
class admin_folder_delete extends FO_Plugin
{

  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name = "admin_folder_delete";
    $this->Title = TITLE_ADMIN_FOLDER_DELETE;
    $this->MenuList = "Organize::Folders::Delete Folder";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
    $this->folderDao = $GLOBALS['container']->get('dao.folder');
  }

  /**
   * @brief Creates a job to detele the folder
   * @param int $folderpk the folder_pk to remove
   * @param int $userId   the user deleting the folder
   * @return NULL on success, string on failure.
   */
  function Delete($folderpk, $userId)
  {
    $splitFolder = explode(" ",$folderpk);
    if (! $this->folderDao->isFolderAccessible($splitFolder[1], $userId)) {
      $text = _("No access to delete this folder");
      return ($text);
    }
    /* Can't remove top folder */
    if ($splitFolder[1] == FolderGetTop()) {
      $text = _("Can Not Delete Root Folder");
      return ($text);
    }
    /* Get the folder's name */
    $FolderName = FolderGetName($splitFolder[1]);
    /* Prepare the job: job "Delete" */
    $groupId = Auth::getGroupId();
    $jobpk = JobAddJob($userId, $groupId, "Delete Folder: $FolderName");
    if (empty($jobpk) || ($jobpk < 0)) {
      $text = _("Failed to create job record");
      return ($text);
    }
    /* Add job: job "Delete" has jobqueue item "delagent" */
    $jqargs = "DELETE FOLDER $folderpk";
    $jobqueuepk = JobQueueAdd($jobpk, "delagent", $jqargs, NULL, NULL);
    if (empty($jobqueuepk)) {
      $text = _("Failed to place delete in job queue");
      return ($text);
    }

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (! $success) {
      return $error_msg . "\n" . $output;
    }

    return (null);
  } // Delete()

  /**
   * @copydoc FO_Plugin::Output()
   * @see FO_Plugin::Output()
   */
  public function Output()
  {
    /* If this is a POST, then process the request. */
    $folder = GetParm('folder', PARM_RAW);
    $splitFolder = explode(" ",$folder);
    if (!empty($folder)) {
      $userId = Auth::getUserId();
      $sql = "SELECT folder_name FROM folder join users on (users.user_pk = folder.user_fk or users.user_perm = 10) where folder_pk = $1 and users.user_pk = $2;";
      $Folder = $this->dbManager->getSingleRow($sql,array($splitFolder[1],$userId),__METHOD__."GetRowWithFolderName");
      if (!empty($Folder['folder_name'])) {
        $rc = $this->Delete($folder, $userId);
        if (empty($rc)) {
          /* Need to refresh the screen */
          $text = _("Deletion of folder ");
          $text1 = _(" added to job queue");
          $this->vars['message'] = $text . $Folder['folder_name'] . $text1;
        } else {
          $text = _("Deletion of ");
          $text1 = _(" failed: ");
          $this->vars['message'] =  $text . $Folder['folder_name'] . $text1 . $rc;
        }
      } else {
        $text = _("Cannot delete this folder :: Permission denied");
        $this->vars['message'] = $text;
      }
    }

    $V= "<form method='post'>\n"; // no url = this url
    $text  =  _("Select the folder to");
    $text1 = _("delete");
    $V.= "$text <em>$text1</em>.\n";
    $V.= "<ul>\n";
    $text = _("This will");
    $text1 = _("delete");
    $text2 = _("the folder, all subfolders, and all uploaded files stored within the folder!");
    $V.= "<li>$text <em>$text1</em> $text2\n";
    $text = _("Be very careful with your selection since you can delete a lot of work!");
    $V.= "<li>$text\n";
    $text = _("All analysis only associated with the deleted uploads will also be deleted.");
    $V.= "<li>$text\n";
    $text = _("THERE IS NO UNDELETE. When you select something to delete, it will be removed from the database and file repository.");
    $V.= "<li>$text\n";
    $V.= "</ul>\n";
    $text = _("Select the folder to delete:  ");
    $V.= "<P>$text\n";
    $V.= "<select name='folder' class='ui-render-select2'>\n";
    $text = _("select folder");
    $V.= "<option value='' disabled selected>[$text]</option>\n";
    $V.= FolderListOption(-1, 0, 1, -1, true);
    $V.= "</select><P />\n";
    $text = _("Delete");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";
    return $V;
  }
}

$NewPlugin = new admin_folder_delete();
