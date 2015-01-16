<?php
/***********************************************************
 Copyright (C) 2008-2014 Hewlett-Packard Development Company, L.P.

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
  function __construct()
  {
    $this->Name = "upload_srv_files";
    $this->Title = TITLE_upload_srv_files;
    $this->MenuList = "Upload::From Server";
    $this->Dependency = array("agent_unpack");
    $this->DBaccess = PLUGIN_DB_ADMIN;
    parent::__construct();
  }

  /** 
   * \brief chck if one file/dir has one permission
   *
   * \param $path - file path
   * \param $server - host name
   * \param $permission - permission x/r/w
   *
   * \return 1: yes; 0: no
   */
  function remote_file_permission($path, $server = 'localhost', $persmission = 'r')
  {
    /** local file */
    if ($server === 'localhost' || empty($server))
    { 
      $temp_path = str_replace('\ ', ' ', $path); // replace '\ ' with ' '
      return @fopen($temp_path, $persmission);
    } else return 1;  // don't do the file permission check if the file is not on the web server
  }

  /** 
   * \brief chck if one file/dir exist or not
   *
   * \param $path - file path
   * \param $server - host name
   *
   * \return 1: exist; 0: not
   */
  function remote_file_exists($path, $server = 'localhost')
  {
    /** local file */
    if ($server === 'localhost' || empty($server)) 
    {
      $temp_path = str_replace('\ ', ' ', $path); // replace '\ ' with ' '
      return file_exists($temp_path);
    } else return 1;  // don't do the file exist check if the file is not on the web server
  }

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
   * \param $public_perm public permission on the upload
   *
   * \return NULL on success, string on failure.
   */
  function Upload($FolderPk, $SourceFiles, $GroupNames, $Desc, $Name, $HostName, $public_perm) {
    global $Plugins;
    global $SysConf;

    $FolderPath = FolderGetName($FolderPk);
    $SourceFiles = trim($SourceFiles);

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
    if (empty($wildcardpath) && !$this->remote_file_exists($SourceFiles, $HostName)) {
      $text = _("'$SourceFiles' does not exist.\n");
      return $text;
    }

    /** check if has the read permission */
    if (empty($wildcardpath) && !$this->remote_file_permission($SourceFiles, $HostName, "r")) {
      $text = _("Have no READ permission on '$SourceFiles'.\n");
      return $text;
    }

    // Create an upload record.
    $jobq = NULL;
    $Mode = (1 << 3); // code for "it came from web upload"
    $user_pk = $SysConf['auth']['UserId'];
    $group_pk = $SysConf['auth']['GroupId'];
    $uploadpk = JobAddUpload($user_pk, $ShortName, $SourceFiles, $Desc, $Mode, $FolderPk, $public_perm);

    /* Prepare the job: job "wget" */
    $jobpk = JobAddJob($user_pk, $group_pk, "wget", $uploadpk);
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

    $msg = "";
    /** check if the scheudler is running */
    $status = GetRunnableJobList();
    if (empty($status))
    {
      $msg .= _("Is the scheduler running? ");
    }
    $Url = Traceback_uri() . "?mod=showjobs&upload=$uploadpk";
    $msg .= "The file $SourceFiles has been uploaded. ";
    $keep = "It is <a href='$Url'>upload #" . $uploadpk . "</a>.\n";
    $this->vars['message'] = $msg.$keep;
    return (NULL);
  } // Upload()

  /**
   * \brief Generate the text for this plugin.
   */
  public function Output() {
    $SourceFiles = GetParm('sourcefiles', PARM_STRING);
    $GroupNames = GetParm('groupnames', PARM_INTEGER);
    $FolderPk = GetParm('folder', PARM_INTEGER);
    $HostName = GetParm('host', PARM_STRING);
    $Desc = GetParm('description', PARM_STRING); // may be null
    $Name = GetParm('name', PARM_STRING); // may be null
    $public = GetParm('public', PARM_TEXT); // may be null
    $public_perm = empty($public) ? PERM_NONE : PERM_READ;
    $V = "";
    if (!empty($SourceFiles) && !empty($FolderPk)) {
      if (empty($HostName)) $HostName = "localhost";
      $rc = $this->Upload($FolderPk, $SourceFiles, $GroupNames, $Desc, $Name, $HostName, $public_perm);
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
        $this->vars['message'] = "$text $SourceFiles: $rc";
      }
    }
    /* Display instructions */
    $text22 = _("Starting in FOSSology v 2.2 only your group and any other group you assign will have access to your uploaded files.  To manage your own group go into Admin > Groups > Manage Group Users.  To manage permissions for this one upload, go to Admin > Upload Permissions");
    $V .= "<p><b>$text22</b><p>";

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
    $V.= _("'*' is supported to select multiple files (e.g. *.txt).\n");
    $text = _("(Optional) Enter a description for this Upload:");
    $V.= "<p><li>$text<br />\n";
    $V.= "<INPUT type='text' name='description' size=60 value='" . htmlentities($Desc, ENT_QUOTES) . "'/>\n";
    $text = _("(Optional) Enter a viewable name for this Upload:");
    $V.= "<p><li>$text<br />\n";
    $V.= "<INPUT type='text' name='name' size=60 value='" . htmlentities($Name, ENT_QUOTES) . "' /><br />\n";
    $text = _("NOTE");
    $text1 = _(": If no name is provided, then the uploaded file name will be used.");
    $V.= "<b>$text</b>$text1<P />\n";

    $text1 = _("(Optional) Make Public");
    $V.= "<li>";
    $V.= "<input type='checkbox' name='public' value='public' > $text1 <p>\n";

    if (@$_SESSION['UserLevel'] >= PLUGIN_DB_WRITE) {
      $text = _("Select optional analysis");
      $V.= "<li>$text<br />\n";
      $Skip = array("agent_unpack", "agent_adj2nest", "wget_agent");
      $V.= AgentCheckBoxMake(-1, $Skip);
    }
    $V.= "</ol>\n";
    $text = _("Upload");
    $V.= "<input type='submit' value='$text!'>\n";
    $V.= "</form>\n";
    $V .= "<p><b>$text22</b><p>";
    return $V;
  }
}
$NewPlugin = new upload_srv_files;
