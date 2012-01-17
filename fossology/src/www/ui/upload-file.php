<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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

define("TITLE_upload_file", _("Upload a New File"));

/**
 * \class upload_file extends from FO_Plugin
 * \brief Upload a file from the users computer using the UI.
 */
class upload_file extends FO_Plugin {

  public $Name = "upload_file";
  public $Title = TITLE_upload_file;
  public $Version = "1.0";
  public $MenuList = "Upload::From File";
  //public $Dependency = array("agent_unpack", "showjobs"); // TODO to display, temporarily comment out 
  public $DBaccess = PLUGIN_DB_UPLOAD;

  /**
   * \brief Process the upload request.
   *
   * \return NULL on success, string on failure.
   */
  function Upload($Folder, $TempFile, $Desc, $Name) 
  {
    global $MODDIR;

    /* See if the URL looks valid */
    if (empty($Folder)) {
      $text = _("Invalid folder");
      return ($text);
    }
    if (empty($Name)) {
      $Name = basename(@$_FILES['getfile']['name']);
    }
    $originName = @$_FILES['getfile']['name'];
    $ShortName = basename($Name);
    if (empty($ShortName)) {
      $ShortName = $Name;
    }
    print_r($_FILES);
    if (0 == @$_FILES['getfile']['size']) {
      $text = _("Failed to upload, the file size is 0.\n");
      return ($text);
    }
    /* Create an upload record. */
    $Mode = (1 << 3); // code for "it came from web upload"
    $uploadpk = JobAddUpload($ShortName, $originName, $Desc, $Mode, $Folder);
    if (empty($uploadpk)) {
      $text = _("Failed to insert upload record");
      return ($text);
    }
    /* move the temp file */
    $UploadedFile = "$TempFile" . "-uploaded";
    if (!move_uploaded_file($TempFile, "$UploadedFile")) {
      $text = _("Could not save uploaded file");
      return ($text);
    }
    if (!chmod($UploadedFile, 0660)) {
      $text = _("ERROR! could not update permissions on downloaded file");
      return ($text);
    }

    /* Run wget_agent locally to import the file. */
    $Prog = "$MODDIR/wget_agent/agent/wget_agent -g fossy -k $uploadpk '$UploadedFile'";
    $wgetLast = exec($Prog,$wgetOut,$wgetRtn);
    unlink($UploadedFile);

    global $Plugins;
    $Unpack = &$Plugins[plugin_find_id("agent_unpack") ];

    $jobqueuepk = NULL;
    $Unpack->AgentAdd($uploadpk, array($jobqueuepk));
    AgentCheckBoxDo($uploadpk);

    if($wgetRtn == 0) {
      $text = _("The file");
      $text1 = _("has been uploaded. It is");
      $Url = Traceback_uri() . "?mod=showjobs&history=1&upload=$uploadpk";
      $Msg = "$text $Name $text1 ";
      $keep = '<a href=' . $Url . '>upload #' . $uploadpk . "</a>.\n";
      print displayMessage($Msg,$keep);
      return (NULL);
    }
    else {
      return($wgetOut[0]);
    }
    return(NULL);
  } // Upload()

  /**
   * \brief Generate the text for this plugin.
   */
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
            // reset form fields
            $GetURL = NULL;
            $Desc = NULL;
            $Name = NULL;
          }
          else {
            $text = _("Upload failed for file");
            $V.= displayMessage("$text {$_FILES['getfile']['name']}: $rc");
          }
        }

        /* Set default values */
        if (empty($GetURL)) {
          $GetURL = 'http://';
        }
        /* Display instructions */
        $V.= _("This option permits uploading a single file (which may be iso, tar, rpm, jar, zip, bz2, msi, cab, etc.) from your computer to FOSSology.\n");
        $V.= _("Your FOSSology server has imposed a maximum file size of");
        $V.= " ".  ini_get('post_max_size') . " ";
        $V.= _("bytes.");
        /* Display the form */
        $V.= "<form enctype='multipart/form-data' method='post'>\n"; // no url = this url
        $V.= "<ol>\n";
        $text = _("Select the folder for storing the uploaded file:");
        $V.= "<li>$text\n";
        $V.= "<select name='folder'>\n";
        $V.= FolderListOption(-1, 0);
        $V.= "</select><P />\n";
        $text = _("Select the file to upload:");
        $V.= "<li>$text<br />\n";
        $V.= "<input name='getfile' size='60' type='file' /><br />\n";
        $text = _("(Optional) Enter a description of this file:");
        $V.= "<li>$text<br />\n";
        $V.= "<INPUT type='text' name='description' size=60 value='" . htmlentities($Desc) . "'/><P />\n";
        $text = _("(Optional) Enter a viewable name for this file:");
        $V.= "<li>$text<br />\n";
        $V.= "<INPUT type='text' name='name' size=60 value='" . htmlentities($Name) . "'/><br />\n";
        $text1 = _("If no name is provided, then the uploaded file name will be used.");
        $V.= "$text1<P />\n";
        if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE) {
          $text = _("Select optional analysis");
          $V.= "<li>$text<br />\n";
          $V.= AgentCheckBoxMake(-1, "agent_unpack");
        }
        $V.= "</ol>\n";
        $text = _("After you press Upload, please be patient while your file is transferring.");
        $V.= "$text<br>\n";
        $text = _("Upload");
        $V.= "<p>&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' value='$text'>\n";
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
