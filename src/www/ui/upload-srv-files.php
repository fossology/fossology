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

use Fossology\Lib\Auth\Auth;

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
   * \brief checks, whether a string contains some special character without
   * escaping
   *
   * \param $str - the string to check
   * \param $char - the character to search for
   *
   * \return boolean
   */
  function str_contains_notescaped_char($str, $char)
  {
    $pos = 0;
    while ($pos < strlen($str) &&
           ($pos = strpos($str,$char,$pos)) !== FALSE)
    {
      foreach(range(($pos++) -1, 1, -2) as $tpos)
      {
        if ($tpos > 0 && $str[$tpos] !== '\\')
          break;
        if ($tpos > 1 && $str[$tpos - 1] !== '\\')
          continue 2;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * \brief checks, whether a path is a pattern from the perspective of a shell
   *
   * \param $path - the path to check
   *
   * \return boolean
   */
  function path_is_pattern($path)
  {
    return $this->str_contains_notescaped_char($path, '*')
      || $this->str_contains_notescaped_char($path, '?')
      || $this->str_contains_notescaped_char($path, '[')
      || $this->str_contains_notescaped_char($path, '{');
  }

  /**
   * \brief checks, whether a path contains substrings, which could enable it to
   * escape his prefix
   *
   * \param $path - the path to check
   *
   * \return boolean
   */
  function path_is_evil($path)
  {
    return $this->str_contains_notescaped_char($path, '$')
      || strpos($path,'..')!==FALSE;
  }

  /**
   * \brief normalizes an path and returns FALSE on errors
   *
   * \param $path - the path to normalize
   * \param $appendix - optional parameter, which is used for the recursive call
   *
   * \return normalized path on success
   *         FALSE on error
   *
   */
  function normalize_path($path, $appendix="")
  {
    if(strpos($path,'/')===FALSE || $path === '/')
      return FALSE;
    if($this->path_is_pattern($path))
    {
      $bpath = basename($path);
      if ($this->path_is_evil($bpath))
        return FALSE;

      return $this->normalize_path(dirname($path),
                                   $bpath . ($appendix == '' ?
                                             '' :
                                             '/' . $appendix));
    }
    else
    {
      $rpath = realpath($path);
      if ($rpath === FALSE)
        return FALSE;
      // if (!@fopen($rpath, 'r'))
      //   return FALSE;
      return $rpath . ($appendix == '' ?
                       '' :
                       '/' . $appendix);
    }
  }

  /**
   *
   * \brief checks, whether a normalized path starts with an path in the
   * whiteliste
   *
   * \param $path - the path to check
   *
   * \return boolean
   *
   */
  function check_by_whitelist($path)
  {
    // TODO: get whitelist from configuration file / DB
    $whitelist = ["/tmp"];

    foreach ($whitelist as $item)
      if (substr($path,0,strlen($item)) === $item)
        return TRUE;
    return FALSE;
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
   * \param $HostName -
   * \param $public_perm public permission on the upload
   *
   * \return NULL on success, string on failure.
   */
  function Upload($FolderPk, $SourceFiles, $GroupNames, $Desc, $Name, $HostName, $public_perm)
  {
    global $Plugins;

    $FolderPath = FolderGetName($FolderPk);
    $SourceFiles = $this->normalize_path(trim($SourceFiles));
    if ($SourceFiles == FALSE)
    {
      $text = _("failed to normalize/validate given path");
      return ($text);
    }
    if ($this->check_by_whitelist($SourceFiles) == FALSE)
    {
      $text = _("no suitable prefix found in the whitelist");
      return ($text);
    }

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
    $SourceFiles = str_replace("\t", "\\t", $SourceFiles);
    /* Add the job to the queue */
    // create the job
    if(!$this->path_is_pattern($Name))
    {
      $ShortName = basename($Name);
      if (empty($ShortName)) {
        $ShortName = $Name;
      }
    }
    else
    {
      $ShortName = $Name;
    }

    // Create an upload record.
    $Mode = (1 << 3); // code for "it came from web upload"
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    $uploadpk = JobAddUpload($userId, $groupId, $ShortName, $SourceFiles, $Desc, $Mode, $FolderPk, $public_perm);

    /* Prepare the job: job "wget" */
    $jobpk = JobAddJob($userId, $groupId, "wget", $uploadpk);
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
    $public_perm = empty($public) ? Auth::PERM_NONE : Auth::PERM_READ;
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
    $text22 = _("To manage your own group permissions go into Admin > Groups > Manage Group Users.  To manage permissions for this on
e upload, go to Admin > Upload Permissions");
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

    if ($_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_WRITE) {
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
