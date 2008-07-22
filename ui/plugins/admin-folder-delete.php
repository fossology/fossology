<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class admin_folder_delete extends FO_Plugin
{
  public $Name       = "admin_folder_delete";
  public $Title      = "Delete Folder";
  public $MenuList   = "Organize::Folders::Delete Folder";
  public $Version    = "1.0";
  public $Dependency = array("db");
  public $DBaccess   = PLUGIN_DB_DELETE;

  /***********************************************************
   RegisterMenus(): Register additional menus.
   ***********************************************************/
  function RegisterMenus()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); } // don't run
    }

  /*********************************************
   Delete(): Given a folder_pk, add a job.
   Returns NULL on success, string on failure.
   *********************************************/
  function Delete($folderpk,$Depends=NULL)
  {
    /* Get the folder's name */
    $FolderName = FolderGetName($folderpk);

    /* Prepare the job: job "Delete" */
    $jobpk = JobAddJob(NULL,"Delete Folder: $FolderName");
    if (empty($jobpk) || ($jobpk < 0)) { return("Failed to create job record"); }

    /* Add job: job "Delete" has jobqueue item "delagent" */
    $jqargs = "DELETE FOLDER $folderpk";
    $jobqueuepk = JobQueueAdd($jobpk,"delagent",$jqargs,"no",NULL,NULL);
    if (empty($jobqueuepk)) { return("Failed to place delete in job queue"); }
    return(NULL);
  } // Delete()

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $DB;
    $V="";
    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
	/* If this is a POST, then process the request. */
	$folder = GetParm('folder',PARM_INTEGER);
	if (!empty($folder))
	  {
	  $rc = $this->Delete($folder);
	  if (empty($rc))
	    {
	    /* Need to refresh the screen */
	    $V .= PopupAlert('Deletion added to job queue');
	    }
	  else
	    {
	    $V .= PopupAlert('Scheduling failed: $rc');
	    }
	  }

        $V .= "<form method='post'>\n"; // no url = this url
	$V .= "Select the folder to <em>delete</em>.\n";
	$V .= "<ul>\n";
	$V .= "<li>This will <em>delete</em> the folder, all subfolders, and all uploaded files stored within the folder!\n";
	$V .= "<li>Be very careful with your selection since you can delete a lot of work!\n";
	$V .= "<li>All analysis only associated with the deleted uploads will also be deleted.\n";
	$V .= "<li>THERE IS NO UNDELETE. When you select something to delete, it will be removed from the database and file repository.\n";
	$V .= "</ul>\n";
        $V .= "<P>Select the folder to delete:  \n";
        $V .= "<select name='folder'>\n";
        $V .= FolderListOption(-1,0);
        $V .= "</select><P />\n";
        $V .= "<input type='submit' value='Delete!'>\n";
        $V .= "</form>\n";
	break;
      case "Text":
	break;
      default:
	break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
  }
};
$NewPlugin = new admin_folder_delete;
$NewPlugin->Initialize();
?>
