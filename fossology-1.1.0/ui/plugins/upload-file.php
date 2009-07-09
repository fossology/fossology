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
if (!isset($GlobalReady)) {
  exit;
}
class upload_file extends FO_Plugin {
  public $Name = "upload_file";
  public $Title = "Upload a New File";
  public $Version = "1.0";
  public $MenuList = "Upload::From File";
  public $Dependency = array("db", "agent_unpack", "showjobs");
  public $DBaccess = PLUGIN_DB_UPLOAD;

  /*********************************************
  Upload(): Process the upload request.
  Returns NULL on success, string on failure.
  *********************************************/
  function Upload($Folder, $TempFile, $Desc, $Name) {
    /* See if the URL looks valid */
    if (empty($Folder)) {
      return ("Invalid folder");
    }
    if (empty($Name)) {
      $Name = basename(@$_FILES['getfile']['name']);
    }
    $ShortName = basename($Name);
    if (empty($ShortName)) {
      $ShortName = $Name;
    }
    /* Create an upload record. */
    $Mode = (1 << 3); // code for "it came from web upload"
    $uploadpk = JobAddUpload($ShortName, $Name, $Desc, $Mode, $Folder);
    if (empty($uploadpk)) {
      return ("Failed to insert upload record");
    }
    /* move the temp file */
    //echo "<pre>uploadfile: renaming uploaded file\n</pre>";
    if (!move_uploaded_file($TempFile, "$TempFile-uploaded")) {
      return ("Could not save uploaded file");
    }
    $UploadedFile = "$TempFile" . "-uploaded";
    //echo "<pre>uploadfile: \$UploadedFile is:$UploadedFile\n</pre>";
    if (!chmod($UploadedFile, 0660)) {
      return ("ERROR! could not update permissions on downloaded file");
    }
    //echo "<pre>uploadfile: File Chmod'ed\n</pre>";
    //echo "<pre>uploadfile: scheduling wget\n</pre>";

    /* Run wget_agent locally to import the file. */
    global $LIBEXECDIR;
    $Prog = "$LIBEXECDIR/agents/wget_agent -g fossy -k $uploadpk '$UploadedFile'";
    $last = system($Prog,$rtn);
    unlink($UploadedFile);

    global $Plugins;
    //print "<pre>UPF: Plugins are:\n"; print_r($Plugins) . "\n</pre>";
    $Unpack = &$Plugins[plugin_find_id("agent_unpack") ];

    $Unpack->AgentAdd($uploadpk, array($jobqueuepk));
    //rint "<pre>UPF: after looking for agent_unpack, Unpack is:$Unpack\n</pre>";
    AgentCheckBoxDo($uploadpk);

    if (CheckEnotification()) {
      $sched = scheduleEmailNotification($uploadpk);
      if ($sched !== NULL) {
        return($sched);
      }
    }
    $Url = Traceback_uri() . "?mod=showjobs&history=1&upload=$uploadpk";
    print "The file has been uploaded. ";
    print "It is <a href='$Url'>upload #" . $uploadpk . "</a>.\n";
    print "<hr>\n";
    return (NULL);
  } // Upload()
  /*********************************************
  Output(): Generate the text for this plugin.
  *********************************************/
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
      break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $Folder = GetParm('folder', PARM_INTEGER);
        $Desc = GetParm('description', PARM_TEXT); // may be null
        $Name = GetParm('name', PARM_TEXT); // may be null
        if (file_exists(@$_FILES['getfile']['tmp_name']) && !empty($Folder)) {
          $rc = $this->Upload($Folder, @$_FILES['getfile']['tmp_name'], $Desc, $Name);
          if (empty($rc)) {
            /* Need to refresh the screen */
            $V.= PopupAlert('Upload added to job queue');
            $GetURL = NULL;
            $Desc = NULL;
            $Name = NULL;
          } else {
            $V.= PopupAlert("Upload failed: $rc");
          }
        }

        /* Set default values */
        if (empty($GetURL)) {
          $GetURL = 'http://';
        }
        /* Display instructions */
        $V.= "This option permits uploading a file from your computer to FOSSology.\n";
        $V.= "The file to upload should be located on your computer.\n";
        $V.= "Many browsers, including Microsoft's Internet Explorer, have trouble uploading ";
        $V.= "file larger than 650 Megabytes (a standard-size CD-ROM image).\n";
        $V.= "If your file is larger than 650 Megabytes, then choose one of the other upload options.";

        /* Display the form */
        $V.= "<form enctype='multipart/form-data' method='post'>\n"; // no url = this url
        $V.= "<ol>\n";
        $V.= "<li>Select the folder for storing the uploaded file:\n";
        $V.= "<select name='folder'>\n";
        $V.= FolderListOption(-1, 0);
        $V.= "</select><P />\n";
        $V.= "<li>Select the file to upload:<br />\n";
        $V.= "<input name='getfile' size='60' type='file' /><br />\n";
        $V.= "<b>NOTE</b>: If the file is larger than 650 Megs (one CD-ROM), then this method will not work with some browsers (e.g., Internet Explorer). Only attach files smaller than 650 Megs.<P />\n";
        $V.= "<li>(Optional) Enter a description of this file:<br />\n";
        $V.= "<INPUT type='text' name='description' size=60 value='" . htmlentities($Desc) . "'/><P />\n";
        $V.= "<li>(Optional) Enter a viewable name for this file:<br />\n";
        $V.= "<INPUT type='text' name='name' size=60 value='" . htmlentities($Name) . "'/><br />\n";
        $V.= "<b>NOTE</b>: If no name is provided, then the uploaded file name will be used.<P />\n";
        if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE) {
          $V.= "<li>Select optional analysis<br />\n";
          $V.= AgentCheckBoxMake(-1, "agent_unpack");
        }
        $V.= "</ol>\n";
        $V.= "It may take time to transmit the file from your computer to this server. Please be patient.<br>\n";
        $V.= "<input type='submit' value='Upload!'>\n";
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
$NewPlugin = new upload_file;
?>
