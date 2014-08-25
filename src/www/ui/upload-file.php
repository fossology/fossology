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
   * \param $public_perm public permission on the upload
   * \return NULL on success, error string on failure.
   */
  function Upload($folder_pk, $TempFile, $Desc, $Name, $public_perm) 
  {
    global $MODDIR;
    global $SysConf;
    global $SYSCONFDIR;

    define("UPLOAD_ERR_EMPTY",5);
    define("UPLOAD_ERR_INVALID_FOLDER_PK",100);
    define("UPLOAD_ERR_RESEND",200);
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
      UPLOAD_ERR_INVALID_FOLDER_PK => _("Invalid Folder."),
      UPLOAD_ERR_RESEND => _("This seems to be a resent file.")
    );
    
    if($_SESSION['uploadformbuild']!=$_REQUEST['uploadformbuild']){
      $UploadFile['error'] = UPLOAD_ERR_RESEND;
      return $upload_errors[$UploadFile['error']];
    }
    
    $UploadFile = $_FILES['getfile'];
    if($UploadFile['size'] == 0 && $UploadFile['error'] == 0)
      $UploadFile['error'] = UPLOAD_ERR_EMPTY;
    if (empty($folder_pk))
      $UploadFile['error'] = UPLOAD_ERR_INVALID_FOLDER_PK;
    if ($UploadFile['error'] != UPLOAD_ERR_OK)
      return $upload_errors[$UploadFile['error']];

    $originName = @$UploadFile['name'];
    if (empty($Name)) $Name = basename($originName);
    $ShortName = basename($Name);
    if (empty($ShortName)) $ShortName = $Name;  // for odd case where $Name is '/'

    /* Create an upload record. */
    $Mode = (1 << 3); // code for "it came from web upload"
    $user_pk = $SysConf['auth']['UserId'];
    $uploadpk = JobAddUpload($user_pk, $ShortName, $originName, $Desc, $Mode, $folder_pk, $public_perm);
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
    if ($this->OutputType != "HTML") {
      return;
    }
    global $container;
    $renderer = $container->get('renderer');
    /* If this is a POST, then process the request. */
    $folder_pk = GetParm('folder', PARM_INTEGER);
    $renderer->vars['description'] = GetParm('description', PARM_TEXT); // may be null
    $Name = GetParm('name', PARM_TEXT); // may be null
    $public = GetParm('public', PARM_TEXT); // may be null
    if (empty($public))
      $public_perm = PERM_NONE;
    else
      $public_perm = PERM_READ;

    if (file_exists(@$_FILES['getfile']['tmp_name']) && !empty($folder_pk)) {
      $rc = $this->Upload($folder_pk, @$_FILES['getfile']['tmp_name'], $Desc, $Name, $public_perm);
      if (empty($rc)) {
        // reset form fields
        $renderer->vars['description'] = NULL;
        $Name = NULL;
      }
      else {
        $text = _("Upload failed for file");
        $V.= displayMessage("$text {$_FILES['getfile']['name']}: $rc");
      }
    }

    /* Display instructions */
    $renderer->vars['description'] = $renderer->vars['description'];
    $renderer->vars['agentCheckBoxMake'] = '';
    if (@$_SESSION['UserLevel'] >= PLUGIN_DB_WRITE) {
      $Skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
      $renderer->vars['agentCheckBoxMake'] = AgentCheckBoxMake(-1, $Skip);
    }        
    $V = $renderer->renderTemplate("upload_file");        
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ("$V");
  }
}
$NewPlugin = new upload_file;