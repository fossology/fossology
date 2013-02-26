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
  public $Dependency = array("agent_unpack", "showjobs"); 
  public $DBaccess = PLUGIN_DB_WRITE;

  /**
   * \brief Process the upload request.
   *
   * \param $folder_pk
   * \param $TempFile path to temporary (upload) file
   * \param $Desc optional upload description.
   * \param $Name original name of the file on the client machine.
   * \return NULL on success, error string on failure.
   */
  function Upload($folder_pk, $TempFile, $Desc, $Name) 
  {
    global $MODDIR;
    global $SysConf;
    global $SYSCONFDIR;

    define("UPLOAD_ERR_EMPTY",5);
    define("UPLOAD_ERR_INVALID_FOLDER_PK",100);
    $upload_errors = array(
      UPLOAD_ERR_OK         => _("No errors."), 
      UPLOAD_ERR_INI_SIZE   => _("Larger than upload_max_filesize ") . ini_get('upload_max_filesize'),
      UPLOAD_ERR_FORM_SIZE  => _("Larger than form MAX_FILE_SIZE."),
      UPLOAD_ERR_PARTIAL    => _("Partial upload."),
      UPLOAD_ERR_NO_FILE    => _("No file."),
      UPLOAD_ERR_NO_TMP_DIR => _("No temporary directory."),
      UPLOAD_ERR_CANT_WRITE => _("Can't write to disk."),
      UPLOAD_ERR_EXTENSION  => _("File upload stopped by extension."),
      UPLOAD_ERR_EMPTY      => _("File is empty or you don't have permission to read the file."),
      UPLOAD_ERR_INVALID_FOLDER_PK => _("Invalid Folder.")
    );

    $UploadFile = $_FILES['getfile'];
    $UploadError = @$UploadFile['error'];

    /* Additional error checks */
    if($UploadFile['size'] == 0 && $UploadFile['error'] == 0) $UploadFile['error'] = UPLOAD_ERR_EMPTY;
    if (empty($folder_pk)) $UploadFile['error'] = UPLOAD_ERR_INVALID_FOLDER_PK;
    if ($UploadFile['error'] != UPLOAD_ERR_OK) return $upload_errors[$UploadFile['error']];

    $originName = @$UploadFile['name'];
    if (empty($Name)) $Name = basename($originName);
    $ShortName = basename($Name);
    if (empty($ShortName)) $ShortName = $Name;  // for odd case where $Name is '/'

    /* Create an upload record. */
    $Mode = (1 << 3); // code for "it came from web upload"
    $user_pk = $SysConf['auth']['UserId'];
    $uploadpk = JobAddUpload($user_pk, $ShortName, $originName, $Desc, $Mode, $folder_pk);
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
    $Prog = "$MODDIR/wget_agent/agent/wget_agent -C -g fossy -k $uploadpk '$UploadedFile' -c '$SYSCONFDIR'";
    $wgetOut = array();
    $wgetLast = exec($Prog,$wgetOut,$wgetRtn);
    unlink($UploadedFile);

    /* Create Job */
    $job_pk = JobAddJob($user_pk, $ShortName, $uploadpk);
    global $Plugins;
    $adj2nestplugin = &$Plugins[plugin_find_id("agent_adj2nest") ];

    $Dependencies = array();
    $adj2nestplugin->AgentAdd($job_pk, $uploadpk, $ErrorMsg, $Dependencies);
    AgentCheckBoxDo($job_pk, $uploadpk);

    if($wgetRtn == 0) 
    {
      $Msg = "";
      /** check if the scheudler is running */
      $status = GetRunnableJobList();
      if (empty($status))
      {
        $Msg .= _("Is the scheduler running? ");
      }
      $text = _("The file");
      $text1 = _("has been uploaded. It is");
      $Url = Traceback_uri() . "?mod=showjobs&upload=$uploadpk";
      $Msg .= "$text $Name $text1 ";
      $keep = '<a href=' . $Url . '>upload #' . $uploadpk . "</a>.\n";
      print displayMessage($Msg,$keep);
      return (NULL);
    }
    else 
    {
      $ErrMsg = GetArrayVal(0, $wgetOut);
      if (empty($ErrMsg)) $ErrMsg = _("File upload failed.  Error:") . $wgetRtn;
      return($ErrMsg);
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
        $folder_pk = GetParm('folder', PARM_INTEGER);
        $Desc = GetParm('description', PARM_TEXT); // may be null
        $Name = GetParm('name', PARM_TEXT); // may be null
        if (file_exists(@$_FILES['getfile']['tmp_name']) && !empty($folder_pk)) {
          $rc = $this->Upload($folder_pk, @$_FILES['getfile']['tmp_name'], $Desc, $Name);
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
        if (@$_SESSION['UserLevel'] >= PLUGIN_DB_WRITE) {
          $text = _("Select optional analysis");
          $V.= "<li>$text<br />\n";
          $Skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
          $V.= AgentCheckBoxMake(-1, $Skip);
        }
        $V.= "</ol>\n";
        $text = _("After you press Upload, please be patient while your file is transferring.");
        $V.= "$text<br>\n";
        $text = _("Upload");
        $V.= "<p>&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' value='$text'>\n";
        $V.= "</form>\n";
        
        $text = _("Starting in FOSSology v 2.2 only your group and your designated group will have access to your uploaded files.  To specify a designated group go into Admin > Users > Account Settings.  To specify which users are in your group or your designated group, go into Admin > Groups > Manage Group Users.");
        $V .= "<p>$text";
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
