<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_upload_vcs", _("Upload from Version Control System"));

/**
 * \class upload_vcs extend from FO_Plugin
 * \brief upload from Version Control System
 */
class upload_vcs extends FO_Plugin {
  function __construct()
  {
    $this->Name = "upload_vcs";
    $this->Title = TITLE_upload_vcs;
    $this->MenuList = "Upload::From Version Control System";
    $this->DBaccess = PLUGIN_DB_WRITE;

    parent::__construct();
  }

  /**
   * \brief Process the upload request.
   * \param $Folder
   * \param $VCSType
   * \param $GetURL
   * \param $Desc
   * \param $Name
   * \param $Username
   * \param $Passwd 
   * \param $public_perm public permission on the upload
   * Returns NULL on success, string on failure.
   */
  function Upload($Folder, $VCSType, $GetURL, $Desc, $Name, $Username, $Passwd, $public_perm) 
  {
    global $SysConf;

    /* See if the URL looks valid */
    if (empty($Folder)) 
    {
      $text = _("Invalid folder");
      return ($text);
    }

    $GetURL = trim($GetURL);
    if (empty($GetURL)) 
    {
      $text = _("Invalid URL");
      return ($text);
    }
    if (preg_match("@^((http)|(https)|(ftp))://([[:alnum:]]+)@i", $GetURL) != 1) 
    {
      $text = _("Invalid URL");
      return ("$text: " . htmlentities($GetURL));
    }
    if (preg_match("@[[:space:]]@", $GetURL) != 0) 
    {
      $text = _("Invalid URL (no spaces permitted)");
      return ("$text: " . htmlentities($GetURL));
    }
    if (empty($Name)) $Name = basename($GetURL);
    $ShortName = basename($Name);
    if (empty($ShortName))  $ShortName = $Name;

    /* Create an upload record. */
    $Mode = (1 << 2); // code for "it came from wget"
    $user_pk = $SysConf['auth']['UserId'];
    $uploadpk = JobAddUpload($user_pk, $ShortName, $GetURL, $Desc, $Mode, $Folder, $public_perm);
    if (empty($uploadpk)) {
      $text = _("Failed to insert upload record");
      return ($text);
    }

    /* Create the job: job "wget" */
    $jobpk = JobAddJob($user_pk, "wget", $uploadpk);
    if (empty($jobpk) || ($jobpk < 0)) {
      $text = _("Failed to insert job record");
      return ($text);
    }

    $jq_args = "$uploadpk - $GetURL $VCSType ";
    if (!empty($Username)) {
      $jq_args .= "--username $Username ";
    }
    if (!empty($Passwd)) 
    {
      $jq_args .= "--password $Passwd";
    } 

    $jobqueuepk = JobQueueAdd($jobpk, "wget_agent", $jq_args, NULL, NULL);
    if (empty($jobqueuepk)) {
      $text = _("Failed to insert task 'wget_agent' into job queue");
      return ($text);
    }
    global $Plugins;
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
    $text = _("The upload");
    $text1 = _("has been queued. It is");
    $msg .= "$text $Name $text1 ";
    $keep =  "<a href='$Url'>upload #" . $uploadpk . "</a>.\n";
    $this->vars['message'] = $msg.$keep;
    return (NULL);
  } // Upload()

  /**
   * \brief Generate the text for this plugin.
   */
  function htmlContent() {
    /* If this is a POST, then process the request. */
    $Folder = GetParm('folder', PARM_INTEGER);
    $VCSType = GetParm('vcstype', PARM_TEXT);
    $GetURL = GetParm('geturl', PARM_TEXT);
    $Desc = GetParm('description', PARM_TEXT); // may be null
    $Name = GetParm('name', PARM_TEXT); // may be null
    $Username = GetParm('username', PARM_TEXT);
    $Passwd = GetParm('passwd', PARM_TEXT);
    $public = GetParm('public', PARM_TEXT); // may be null
    $public_perm = empty($public) ? PERM_NONE : PERM_READ;
    $V = '';
    if (!empty($GetURL) && !empty($Folder)) {
      $rc = $this->Upload($Folder, $VCSType, $GetURL, $Desc, $Name, $Username, $Passwd, $public_perm);
      if (empty($rc)) {
        /* Need to refresh the screen */
        $VCSType = NULL;
        $GetURL = NULL;
        $Desc = NULL;
        $Name = NULL;
        $Username = NULL;
        $Passwd = NULL;
      }
      else {
        $text = _("Upload failed for");
        $this->vars['message'] = "$text $GetURL: $rc";
      }
    }

    /* Set default values */
    if (empty($GetURL)) {
      $GetURL = 'http://';
    }
    /* Display instructions */
    $text22 = _("Starting in FOSSology v 2.2 only your group and any other group you assign will have access to your uploaded files.  To manage your own group go into Admin > Groups > Manage Group Users.  To manage permissions for this one upload, go to Admin > Upload Permissions");
    $V .= "<p><b>$text22</b><p>";
    $V.= _("You can upload source code from a version control system; one risk is that FOSSology will store your username/password of a repository to database, also run checkout source code from command line with username and password explicitly.");
    /* Display the form */
    $V.= "<form method='post'>\n"; // no url = this url
    $V.= "<ol>\n";
    $text = _("Select the folder for storing the uploaded file (directory):");
    $V.= "<li>$text\n";
    $V.= "<select name='folder'>\n";
    $V.= FolderListOption(-1, 0);
    $V.= "</select><P />\n";
    $text = _("Select the type of version control system:");
    $V.= "<li>$text\n";
    $V.= "<select name='vcstype'>\n";
    $V.= "<option value='SVN'>SVN</option>";
    $V.= "<option value='Git'>Git</option>";
    $V.= "</select><P />\n";
    $text = _("Enter the URL of the repo:");
    $V.= "<li>$text<br />\n";
    $V.= "<INPUT type='text' name='geturl' size=60 value='" . htmlentities($GetURL) . "'/><br />\n";
    $text = _("NOTE");
    $text1 = _(": The URL can begin with HTTP://, HTTPS:// . When do git upload, if https url fails, please try http URL.");
    $V.= "<b>$text</b>$text1<P />\n";
    $text = _("(Optional) Enter a description of this file (directory):");
    $V.= "<li>$text<br />\n";
    $V.= "<INPUT type='text' name='description' size=60 value='" . htmlentities($Desc) . "'/><P />\n";
    $text = _("(Optional) Enter a viewable name for this file (directory):");
    $V.= "<li>$text<br />\n";
    $V.= "<INPUT type='text' name='name' size=60 value='" . htmlentities($Name) . "'/><br />\n";
    $text = _("NOTE");
    $text1 = _(": If no name is provided, then the uploaded file (directory) name will be used.");
    $V.= "<b>$text</b>$text1<P />\n";
    $text = _("(Optional) Username:");
    $V.= "<li>$text<br />\n";
    $V.= "<INPUT type='text' name='username' size=60 value='" . htmlentities($Username) . "'/><P />\n";
    $text = _("(Optional) Password:");
    $V.= "<li>$text<br />\n";
    $V.= "<INPUT type='password' name='passwd' size=60 value='" . htmlentities($Passwd) . "'/><P />\n";

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
    $V .= "<p><b>$text22</b>";
        
    return $V;
  }
}
$NewPlugin = new upload_vcs;