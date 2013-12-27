<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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
/**
 * \file admin_upload_delete.php
 * \brief delete a upload
 */

define("TITLE_admin_upload_delete", _("Delete Uploaded File"));

/**
 * \class admin_upload_delete extend from FO_Plugin
 * \brief delete a upload, certainly, you need the permission 
 */
class admin_upload_delete extends FO_Plugin {
  var $Name = "admin_upload_delete";
  var $Title = TITLE_admin_upload_delete;
  var $MenuList = "Organize::Uploads::Delete Uploaded File";
  var $Version = "1.0";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_WRITE;

  /**
   * \brief Register additional menus.
   */
  function RegisterMenus() {
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    } // don't run

  }

  /**
   * \brief Given a folder_pk, add a job.
   * \param $uploadpk - the upload(upload_id) you want to delete
   * \param $Depends - Depends is not used for now
   *
   * \return NULL on success, string on failure.
   */
  function Delete($uploadpk, $Depends = NULL) 
  {
    global $SysConf;

    /* Prepare the job: job "Delete" */
    $user_pk = $SysConf['auth']['UserId'];
    $jobpk = JobAddJob($user_pk, "Delete", $uploadpk);
    if (empty($jobpk) || ($jobpk < 0)) {
      $text = _("Failed to create job record");
      return ($text);
    }
    /* Add job: job "Delete" has jobqueue item "delagent" */
    $jqargs = "DELETE UPLOAD $uploadpk";
    $jobqueuepk = JobQueueAdd($jobpk, "delagent", $jqargs, NULL, NULL);
    if (empty($jobqueuepk)) {
      $text = _("Failed to place delete in job queue");
      return ($text);
    }

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (!$success) 
    {
      $error_msg = _("Is the scheduler running? Your jobs have been added to job queue.");
      $URL = Traceback_uri() . "?mod=showjobs&upload=$uploadpk ";
      $LinkText = _("View Jobs");
      $msg = "$error_msg <a href=$URL>$LinkText</a>";
      return $msg; 
    }
    return (NULL);
  } // Delete()

  /**
   * \brief Generate the text for this plugin.
   */
  function Output() 
  {
    global $PERM_NAMES;

    if ($this->State != PLUGIN_STATE_READY)  return;

    $V = "";
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $uploadpk = GetParm('upload', PARM_INTEGER);
        if (!empty($uploadpk)) {
          $rc = $this->Delete($uploadpk);
          if (empty($rc)) {
            /* Need to refresh the screen */
            $URL = Traceback_uri() . "?mod=showjobs&upload=$uploadpk ";
            $LinkText = _("View Jobs");
            $text=_("Deletion added to job queue.");
            $msg = "$text <a href=$URL>$LinkText</a>";
            $V.= displayMessage($msg);
          }
          else {
            $text=_("Deletion Scheduling failed: ");
            $V.= DisplayMessage($text.$rc);
          }
        }
        /* Create the AJAX (Active HTTP) javascript for doing the reply
         and showing the response. */
        $V.= ActiveHTTPscript("Uploads");
        $V.= "<script language='javascript'>\n";
        $V.= "function Uploads_Reply()\n";
        $V.= "  {\n";
        $V.= "  if ((Uploads.readyState==4) && (Uploads.status==200))\n";
        $V.= "    {\n";
        /* Remove all options */
        //$V.= "    document.formy.upload.innerHTML = Uploads.responseText;\n";
        $V.= "    document.getElementById('uploaddiv').innerHTML = '<BR><select name=\'upload\' size=\'10\'>' + Uploads.responseText + '</select><P />';\n";
        /* Add new options */
        $V.= "    }\n";
        $V.= "  }\n";
        $V.= "</script>\n";
        /* Build HTML form */
        $V.= "<form name='formy' method='post'>\n"; // no url = this url
        $text = _("Select the uploaded file to");
        $text1 = _("delete");
        $V.= "$text <em>$text1</em>\n";
        $V.= "<ul>\n";
        $text = _("This will");
        $text1 = _("delete");
        $text2 = _("the upload file!");
        $V.= "<li>$text <em>$text1</em> $text2\n";
        $text = _("Be very careful with your selection since you can delete a lot of work!\n");
        $V.= "<li>$text";
        $text = _("All analysis only associated with the deleted upload file will also be deleted.\n");
        $V.= "<li>$text";
        $text = _("THERE IS NO UNDELETE. When you select something to delete, it will be removed from the database and file repository.\n");
        $V.= "<li>$text";
        $V.= "</ul>\n";
        $text = _("Select the uploaded file to delete:");
        $V.= "<P>$text<P>\n";
        $V.= "<ol>\n";
        $text = _("Select the folder containing the file to delete: ");
        $V.= "<li>$text";
        $V.= "<select name='folder' ";
        $V.= "onLoad='Uploads_Get((\"" . Traceback_uri() . "?mod=upload_options&folder=-1' ";
        $V.= "onChange='Uploads_Get(\"" . Traceback_uri() . "?mod=upload_options&folder=\" + this.value)'>\n";

        $root_folder_pk = GetUserRootFolder();
        $V.= FolderListOption($root_folder_pk, 0);
        $V.= "</select><P />\n";
        $text = _("Select the uploaded project to delete:");
        $V.= "<li>$text";
        $V.= "<div id='uploaddiv'>\n";
        $V.= "<BR><select name='upload' size='10'>\n";
        $List = FolderListUploads_perm($root_folder_pk, PERM_WRITE);
        foreach($List as $L) {
          $V.= "<option value='" . $L['upload_pk'] . "'>";
          $V.= htmlentities($L['name']);
          if (!empty($L['upload_desc'])) {
            $V.= " (" . htmlentities($L['upload_desc']) . ")";
          }
          if (!empty($L['upload_ts'])) {
            $V.= " :: " . substr($L['upload_ts'], 0, 19);
          }
          $V.= "</option>\n";
        }
        $V.= "</select><P />\n";
        $V.= "</div>\n";
        $V.= "</ol>\n";
        $text = _("Delete");
        $V.= "<input type='submit' value='$text!'>\n";
        $V.= "</form>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ("$V");
    return;
  }
};
$NewPlugin = new admin_upload_delete;
?>
