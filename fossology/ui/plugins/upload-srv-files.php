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

  function Upload ($Folder, $Path, $Recurse, $Desc, $Name) {

    global $LIBEXECDIR;
    global $DB;
    global $Plugins;

    // valid Folder?
    if (empty($Folder))
    {
      return("Invalid folder");
    }

    // Need to get the name of the folder
    $Sql = "SELECT folder_name from folder WHERE folder_pk='$Folder'";
    $qres = $DB->Action($Sql);
    $folder_name = $qres[0]['folder_name'];

    if (empty($Name))
    {
      $ShortName = basename($PATH);
    }
    $ShortName = basename($Name);
    if (empty($ShortName))
    {
      $ShortName = $Name;
    }
    // Create an upload record.
    $Mode = (1<<3); // code for "it came from web upload"
    $upload_pk = JobAddUpload($ShortName,$Path,$Desc,$Mode,$Folder);

    if (empty($upload_pk))
    {
      return("Failed to insert upload record");
    }
    $jq_args =
    "folder_pk='$Folder' name='$Name' description='$Desc' upload_file='$Path' upload_pk='$upload_pk'";
    if ($Recurse == 'R')
    {
      // Turn on recursion
      $jq_args .= " recurse='y'";
    }
    else
    {
      // just upload the files
      $jq_args .= " recurse='n'";
    }
    // echo "<pre>\$jq_args is:$jq_args\n</pre>";

    // create the job
    $jobq = JobAddJob($upload_pk, 'fosscp_agent', 0);
    if (empty($jobq) || ($jobpk < 0))
    {
      return("Failed to create job record");
    }
    // put the job in the Q
    $jq_type = 'fosscp_agent';
    $jobqueue_pk = JobQueueAdd($jobq, $jq_type, $jq_args, "no" ,NULL ,NULL, 0);
    if (empty($jobqueue_pk))
    {
      return("Failed to place fosscp_agent in job queue");
    }
    // schedule ununpack
    $unpack = &$Plugins[plugin_find_id("agent_unpack")];  // can be null
    if (!empty($unpack))
    {
      $unpack->AgentAdd($upload_pk, array($jobqueue_pk));
    }
    AgentCheckBoxDo($upload_pk);

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
	$V .= "<H1>$this->Title</H1>\n";
	//If this is a POST, then process the request.
	// $Path    = GetParm('getfiles', PARAM_TEXT);
	$Path = trim($_POST['getfiles']);
	$Recurse = $_POST['RecurseUpload'];
	$Folder  = GetParm('folder',PARM_INTEGER);
	$Desc    = GetParm('description',PARM_TEXT); // may be null
	$Name    = GetParm('name',PARM_TEXT);        // may be null

	if (empty($Name)) {
	  $Name = $Path;
	}
	/*
	 * Need another check to see if both files and everything is
	 * checked, that's not really an error, it will just upload everything?
	 *
	 * Also, do we want to confirm the users choices, I think so,
	 * as a large upload is well... LARGE! ??
	 */

	if(!empty($Path))
	{
	  $rc = $this->Upload($Folder, $Path, $Recurse, $Desc, $Name);
	  if (empty($rc)) 
	  {
	    // Need to refresh the screen
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('Upload jobs for $PATH added to job queue')\n";
	    $V .= "</script>\n";
	  }
	  else 
	  {
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('Upload failed: $rc')\n";
	    $V .= "</script>\n";
	  }
	}
	// Set default form values
	$PATH     = NULL;
	$Desc     = NULL;
	$Name     = NULL;
	$Folder   = NULL;

	/* Display the form */
	$V .= "<form enctype='multipart/form-data' method='post'>\n"; // no url = this url
	$V .= "<ol>\n";
	$V .= "<li>Select the folder for storing the uploaded file:\n";
	$V .= "<select name='folder'>\n";
	$V .= FolderListOption(-1,0);
	$V .= "</select><P />\n";
	$V .= "<li>Select the path or file on the server to upload:<br />\n";
	$V .= "<input name='getfiles' size='60' type='text' /><br /><br />\n";
	$V .= "<li>The default is to only load just the files under the ";
	$V .= "path, to load <em>everything</em> select the other option.<br />";
	$V .= "<strong>NOTE:</strong> Large uploads can take many hours or days<br /><br />\n";
	$V .= "<input type='radio' name='RecurseUpload' value='R' />";
	$V .= "Upload everything under path?<br />\n";
	$V .= "<input type='radio' checked name='RecurseUpload' value='F' />";
	$V .= "Upload only files under path? E.G. No subfolders/directories and their contents?<br /><br />\n";
	$V .= "<li>(Optional) Enter a description for this Upload:<br />\n";
	$V .= "<INPUT type='text' name='description' size=60 value='" . htmlentities($Desc) . "'/><P />\n";
	$V .= "<li>(Optional) Enter a viewable name for this Upload:<br />\n";
	$V .= "<INPUT type='text' name='name' size=60 value=''" . htmlentities($Name) . "'/><br />\n";
	$V .= "<b>NOTE</b>: If no name is provided, then the uploaded path name will be used.<P />\n";
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
