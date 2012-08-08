<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

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

define("TITLE_upload_srv_files", _("Upload from Server"));

/**
 * \class upload_srv_files extend from FO_Plugin
 * \brief upload files(directory) from server
 */
class upload_srv_files extends FO_Plugin {
  public $Name = "upload_srv_files";
  public $Title = TITLE_upload_srv_files;
  public $Version = "1.0";
  public $MenuList = "Upload::From Server";
  public $Dependency = array("agent_unpack");
  public $DBaccess = PLUGIN_DB_USERADMIN;

  /**
   * \brief Process the upload request.  Call the upload by the Name passed in or by
   * the filename if no name is supplied.
   *
   * \param $FolderPk - folder fk to load into
   * \param $SourceFiles - files to upload, file, tar, directory, etc...
   * \param $GroupNames - flag for indicating if group names were requested.
   *        passed on as -A option to cp2foss.
   * \param $Desc - optional description for the upload
   * \param $Name - optional Name for the upload
   *
   * \return NULL on success, string on failure.
   */
  function Upload($FolderPk, $SourceFiles, $GroupNames, $Desc, $Name, $HostName) {
    global $Plugins;
    global $SysConf;

    $FolderPath = FolderGetName($FolderPk);
    // $FolderPath = str_replace('\\','\\\\',$FolderPath);
    // $FolderPath = str_replace('"','\"',$FolderPath);
    $FolderPath = str_replace('`', '\`', $FolderPath);
    $FolderPath = str_replace('$', '\$', $FolderPath);
    if (!empty($Desc)) {
      // $Desc = str_replace('\\','\\\\',$Desc);
      // $Desc = str_replace('"','\"',$Desc);
      $Desc = str_replace('`', '\`', $Desc);
      $Desc = str_replace('$', '\$', $Desc);
    }
    if (!empty($Name)) {
      // $Name = str_replace('\\','\\\\',$Name);
      // $Name = str_replace('"','\"',$Name);
      $Name = str_replace('`', '\`', $Name);
      $Name = str_replace('$', '\$', $Name);
    }
    else {
      $Name = $SourceFiles;
    }
    /* Check for specified agent analysis */
    $AgentList = menu_find("Agents", $Depth);
    foreach($AgentList as $A) {
      if (empty($A)) {
        continue;
      }
      if (GetParm("Check_" . $A->URI, PARM_INTEGER) != 1 && 'agent_unpack' != $A->URI) {
        $A->URI = NULL;
      }
    }

    // $SourceFiles = str_replace('\\','\\\\',$SourceFiles);
    // $SourceFiles = str_replace('"','\"',$SourceFiles);
    $SourceFiles = str_replace('`', '\`', $SourceFiles);
    $SourceFiles = str_replace('$', '\$', $SourceFiles);
    $SourceFiles = str_replace('|', '\|', $SourceFiles);
    $SourceFiles = str_replace(' ', '\ ', $SourceFiles);
    $SourceFiles = str_replace("\t", "\\\t", $SourceFiles);
    /* Add the job to the queue */
    // create the job
    $ShortName = basename($Name);
    if (empty($ShortName)) {
      $ShortName = $Name;
    }
    $wildcardpath = strstr($SourceFiles, '*');
    /** check if the file/directory is existed (the path does not include wildcards) */
    if (empty($wildcardpath) && !file_exists($SourceFiles)) {
      $text = _("'$SourceFiles' does not exist.\n");
      return $text;
    }

    /** check if has the read permission */
    if (empty($wildcardpath) && !fopen($SourceFiles, "r")) {
      $text = _("Have no READ permission on '$SourceFiles'.\n");
      return $text;
    }

    // Create an upload record.
    $jobq = NULL;
    $Mode = (1 << 3); // code for "it came from web upload"
    $user_pk = $SysConf['auth']['UserId'];
    $uploadpk = JobAddUpload($user_pk, $ShortName, $SourceFiles, $Desc, $Mode, $FolderPk);

    /* Prepare the job: job "wget" */
    $jobpk = JobAddJob($user_pk, "wget", $uploadpk);
    if (empty($jobpk) || ($jobpk < 0)) {
      $text = _("Failed to insert job record");
      return ($text);
    }

    $jq_args = "$uploadpk - $SourceFiles";

    $jobqueuepk = JobQueueAdd($jobpk, "wget_agent", $jq_args, "no", NULL, $HostName );
    if (empty($jobqueuepk)) {
      $text = _("Failed to insert task 'wget' into job queue");
      return ($text);
    }

    /* schedule agents */
    $unpackplugin = &$Plugins[plugin_find_id("agent_unpack") ];
    $ununpack_jq_pk = $unpackplugin->AgentAdd($jobpk, $uploadpk, $ErrorMsg, array("wget_agent"));
    if ($ununpack_jq_pk < 0) return $ErrorMsg;
    
    $adj2nestplugin = &$Plugins[plugin_find_id("agent_adj2nest") ];
    $adj2nest_jq_pk = $adj2nestplugin->AgentAdd($jobpk, $uploadpk, $ErrorMsg, array());
    if ($adj2nest_jq_pk < 0) return $ErrorMsg;

    AgentCheckBoxDo($jobpk, $uploadpk);

    $Url = Traceback_uri() . "?mod=showjobs&upload=$uploadpk";
    $msg = "The upload for $SourceFiles has been scheduled. ";
    $keep = "It is <a href='$Url'>upload #" . $uploadpk . "</a>.\n";
    print displayMessage($msg,$keep);
    return (NULL);
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
        $SourceFiles = GetParm('sourcefiles', PARM_STRING);
        $GroupNames = GetParm('groupnames', PARM_INTEGER);
        $FolderPk = GetParm('folder', PARM_INTEGER);
        $HostName = GetParm('host', PARM_STRING);
        $Desc = GetParm('description', PARM_STRING); // may be null
        $Name = GetParm('name', PARM_STRING); // may be null
        if (!empty($SourceFiles) && !empty($FolderPk)) {
          $rc = $this->Upload($FolderPk, $SourceFiles, $GroupNames, $Desc, $Name, $HostName);
          if (empty($rc)) {
            // clear form fileds
            $SourceFiles = NULL;
            $GroupNames  = NULL;
            $FolderPk    = NULL;
            $Desc        = NULL;
            $Name        = NULL;
          }
          else {
            $text = _("Upload failed for");
            $V.= displayMessage("$text $SourceFiles: $rc");
          }
        }
        /* Display instructions */
        $V.= _("This option permits uploading a file, set of files, or a directory from the web server to FOSSology.\n");
        $V.= _("This option is designed for developers who have large source code directories that they wish to analyze (and the directories are already mounted on the web server's system).\n");
        $V.= _("This option only uploads files located on the FOSSology web server.\n");
        $V.= _("If your file is located elsewhere, then use one of the other upload options.\n");
        /* Display the form */
        $V.= "<form method='post'>\n"; // no url = this url
        $V.= "<ol>\n";
        $text = _("Select the folder for storing the upload:");
        $V.= "<li>$text\n";
        $V.= "<select name='folder'>\n";
        //$V .= FolderListOption($FolderPk,0);
        $V.= FolderListOption(-1, 0);
        $V.= "</select>\n";
        $text = _("Select the directory or file(s) on the server to upload:");
        $V.= "<p><li>$text<br />\n";
        $hostlist = HostListOption();
        if ($hostlist) { // if only one host, do not display it
          $V.= "<select name='host'>\n";
          $V.= $hostlist;
          $V.= "</select>\n";
        }
        $V.= "<input type='text' name='sourcefiles' size='60' value='" . htmlentities($SourceFiles, ENT_QUOTES) . "'/><br />\n";
        $text = _("NOTE");
        $text1 = _(": Contents under a directory will be recursively included.");
        $V.= "<strong>$text</strong>$text1\n";
        $V.= _("If you specify a regular expression for the filename, then multiple filenames will be selected.\n");
        $text = _("(Optional) Enter a description for this Upload:");
        $V.= "<p><li>$text<br />\n";
        $V.= "<INPUT type='text' name='description' size=60 value='" . htmlentities($Desc, ENT_QUOTES) . "'/>\n";
        $text = _("(Optional) Enter a viewable name for this Upload:");
        $V.= "<p><li>$text<br />\n";
        $V.= "<INPUT type='text' name='name' size=60 value='" . htmlentities($Name, ENT_QUOTES) . "' /><br />\n";
        $text = _("NOTE");
        $text1 = _(": If no name is provided, then the uploaded file name will be used.");
        $V.= "<b>$text</b>$text1<P />\n";
        if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE) {
          $text = _("Select optional analysis");
          $V.= "<li>$text<br />\n";
          $Skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
          $V.= AgentCheckBoxMake(-1, $Skip);
        }
        $V.= "</ol>\n";
        $text = _("Upload");
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
$NewPlugin = new upload_srv_files;
?>
