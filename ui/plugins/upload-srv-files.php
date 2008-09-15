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

class upload_srv_files extends FO_Plugin {
  public $Name       = "upload_srv_files";
  public $Title      = "Upload from Server";
  public $Version    = "1.0";
  public $MenuList   = "Upload::From Server";
  public $Dependency = array("db","agent_unpack");
  public $DBaccess   = PLUGIN_DB_UPLOAD;

  /**
   *
   * Function: Upload()
   *
   * Process the upload request.
   *
   * @param int $Folder parent folder fk
   * @param string $dir_list list of files to upload
   * @param string $Desc description
   * @param string $Name Name for the upload
   *
   * @return NULL on success, string on failure.
   */
  function Upload ($FolderPk, $SourceFiles, $GroupNames, $Desc, $Name)
  {
    global $LIBEXECDIR;
    global $DB;
    global $Plugins;

    $FolderPath = FolderGetName($FolderPk);
    $CMD = "";
    if ($GroupNames == "1") { $CMD .= " -A"; }

    // $FolderPath = str_replace('\\','\\\\',$FolderPath);
    // $FolderPath = str_replace('"','\"',$FolderPath);
    $FolderPath = str_replace('`','\`',$FolderPath);
    $FolderPath = str_replace('$','\$',$FolderPath);
    $CMD .= " -f \"$FolderPath\"";

    if (!empty($Desc))
      {
      // $Desc = str_replace('\\','\\\\',$Desc);
      // $Desc = str_replace('"','\"',$Desc);
      $Desc = str_replace('`','\`',$Desc);
      $Desc = str_replace('$','\$',$Desc);
      $CMD .= " -d \"$Desc\"";
      }

    if (!empty($Name))
      {
      // $Name = str_replace('\\','\\\\',$Name);
      // $Name = str_replace('"','\"',$Name);
      $Name = str_replace('`','\`',$Name);
      $Name = str_replace('$','\$',$Name);
      $CMD .= " -n \"$Name\"";
      }
    else
    {
      $Name = $SourceFiles;
    }

    /* Check for specified agent analysis */
    $AgentList = menu_find("Agents",$Depth);
    $First=1;
    foreach($AgentList as $A)
      {
      if (empty($A)) { continue; }
      if (GetParm("Check_" . $A->URI,PARM_INTEGER) == 1)
        {
	if ($First) { $CMD .= " -q " . $A->URI; $First=0; }
	else { $CMD .= "," . $A->URI; }
	}
      }

    // $SourceFiles = str_replace('\\','\\\\',$SourceFiles);
    // $SourceFiles = str_replace('"','\"',$SourceFiles);
    $SourceFiles = str_replace('`','\`',$SourceFiles);
    $SourceFiles = str_replace('$','\$',$SourceFiles);
    $SourceFiles = str_replace('|','\|',$SourceFiles);
    $SourceFiles = str_replace(' ','\ ',$SourceFiles);
    $SourceFiles = str_replace("\t","\\\t",$SourceFiles);
    $CMD .= " $SourceFiles";
    $jq_args = trim($CMD);

    /* Add the job to the queue */
    // create the job
    $ShortName = basename($Name);
    if (empty($ShortName)) { $ShortName = $Name; }
    // Create an upload record.
    $Mode = (1<<3); // code for "it came from web upload"
    $uploadpk = JobAddUpload($ShortName,$SourceFiles,$Desc,$Mode,$FolderPk);

    $jobq = JobAddJob($uploadpk, 'fosscp_agent', 0);
    if (empty($jobq) || ($jobpk < 0))
	{
	return("Failed to create job record");
	}
    // put the job in the jobqueue
    $jq_type = 'fosscp_agent';
    $jobqueue_pk = JobQueueAdd($jobq, $jq_type, $jq_args, "no" ,NULL ,NULL, 0);
    if (empty($jobqueue_pk))
	{
	return("Failed to place fosscp_agent in job queue");
	}

    $Url = Traceback_uri() . "?mod=showjobs&history=1&upload=$uploadpk";
    print "The upload has been scheduled. ";
    print "It is <a href='$Url'>upload #" . $uploadpk . "</a>.\n";
    print "<hr>\n";
    return(NULL);
  } // Upload()

  /**
   * function: Output
   *
   * Generate the text for this plugin.
   */

  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType) {
      case "XML":
	break;
      case "HTML":
	$SourceFiles = GetParm('sourcefiles',PARM_STRING);
	$GroupNames  = GetParm('groupnames',PARM_INTEGER);
	$FolderPk    = GetParm('folder',PARM_INTEGER);
	$Desc        = GetParm('description',PARM_STRING); // may be null
	$Name        = GetParm('name',PARM_STRING);        // may be null

	if (!empty($SourceFiles) && !empty($FolderPk))
	  {
	  $rc = $this->Upload($FolderPk, $SourceFiles, $GroupNames, $Desc, $Name);
	  if (empty($rc))
	    {
	    // Need to refresh the screen
	    $V .= PopupAlert("Upload jobs for $SourceFiles added to job queue");
	    }
	  else
	    {
	    $V .= PopupAlert("Upload failed: $rc");
	    }
	  }

	/* Display instructions */
	$V .= "This option permits uploading a file, set of files, or a directory from the web server to FOSSology.\n";
	$V .= "This option is designed for developers who have large source code directories that they wish to analyze (and the directories are already mounted on the web server's system).\n";
	$V .= "This option only uploads files located on the FOSSology web server.\n";
	$V .= "If your file is located elsewhere, then use one of the other upload options.\n";

	/* Display the form */
	$V .= "<form method='post'>\n"; // no url = this url
	$V .= "<ol>\n";
	$V .= "<li>Select the folder for storing the upload:\n";
	$V .= "<select name='folder'>\n";
	//$V .= FolderListOption($FolderPk,0);
  $V .= FolderListOption(-1,0);
	$V .= "</select>\n";
	$V .= "<p><li>Select the directory or file(s) on the server to upload:<br />\n";
	$V .= "<input type='text' name='sourcefiles' size='60' value='" . htmlentities($SourceFiles,ENT_QUOTES) . "'/><br />\n";
	$V .= "<strong>NOTE</strong>: Contents under a directory will be recursively included.\n";
	$V .= "If you specify a regular expression for the filename, then multiple filenames will be selected.\n";

	$V .= "<p><li>Files can be placed in alphabetized sub-folders for organization.\n";
	$V .= "<br /><input type='radio' name='groupnames' value='0'";
	if ($GroupNames != '1') { $V .= " checked"; }
	$V .= " />Disable alphabetized sub-folders";
	$V .= "<br /><input type='radio' name='groupnames' value='1'";
	if ($GroupNames == '1') { $V .= " checked"; }
	$V .= " />Enable alphabetized sub-folders";

	$V .= "<p><li>(Optional) Enter a description for this Upload:<br />\n";
	$V .= "<INPUT type='text' name='description' size=60 value='" . htmlentities($Desc,ENT_QUOTES) . "'/>\n";

	$V .= "<p><li>(Optional) Enter a viewable name for this Upload:<br />\n";
	$V .= "<INPUT type='text' name='name' size=60 value='" . htmlentities($Name,ENT_QUOTES) . "' /><br />\n";
	$V .= "<b>NOTE</b>: If no name is provided, then the uploaded file name will be used.<P />\n";
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE)
		{
		$V .= "<li>Select optional analysis<br />\n";
		$V .= AgentCheckBoxMake(-1,"agent_unpack");
		}
	$V .= "</ol>\n";
	$V .= "<input type='submit' value='Upload!'>\n";
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
$NewPlugin = new upload_srv_files;
$NewPlugin->Initialize();
?>
