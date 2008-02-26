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

class admin_upload_delete extends Plugin
{
  public $Name       = "admin_upload_delete";
  public $Title      = "Delete Uploaded File";
  public $MenuList   = "Organize::Uploads::Delete Uploaded File";
  public $Version    = "1.0";
  public $Dependency = array("db");

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
  function Delete($uploadpk,$Depends=NULL)
  {
    /* Prepare the job: job "Delete" */
    $jobpk = JobAddJob($uploadpk,"Delete");
    if (empty($jobpk)) { return("Failed to create job record"); }

    /* Add job: job "Delete" has jobqueue item "delagent" */
    $jqargs = "DELETE UPLOAD $uploadpk";
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
	$V .= "<H1>$this->Title</H1>\n";
	/* If this is a POST, then process the request. */
	$uploadpk = GetParm('upload',PARM_INTEGER);
	if (!empty($uploadpk))
	  {
	  $rc = $this->Delete($uploadpk);
	  if (empty($rc))
	    {
	    /* Need to refresh the screen */
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('Deletion added to job queue')\n";
	    $V .= "</script>\n";
	    }
	  else
	    {
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('Scheduling failed: $rc')\n";
	    $V .= "</script>\n";
	    }
	  }

        $V .= "<form method='post'>\n"; // no url = this url
	$V .= "Select the uploaded file to <em>delete</em>.\n";
	$V .= "<ul>\n";
	$V .= "<li>This will <em>delete</em> the upload file!\n";
	$V .= "<li>Be very careful with your selection since you can delete a lot of work!\n";
	$V .= "<li>All analysis only associated with the deleted upload file will also be deleted.\n";
	$V .= "<li>THERE IS NO UNDELETE. When you select something to delete, it will be removed from the database and file repository.\n";
	$V .= "</ul>\n";
        $V .= "<P>Select the uploaded file to delete:  \n";
        $V .= "<BR><select name='upload' size='10'>\n";
	$List = FolderListUploads();
	foreach($List as $L)
	  {
	  $V .= "<option value='" . $L['upload_pk'] . "'>";
	  $V .= htmlentities($L['folder']) . " :: " . htmlentities($L['name']);
	  if (!empty($L['upload_desc']))
	    {
	    $V .= " (" . htmlentities($L['upload_desc']) . ")";
	    }
	  $V .= "</option>\n";
	  }
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
$NewPlugin = new admin_upload_delete;
$NewPlugin->Initialize();
?>
