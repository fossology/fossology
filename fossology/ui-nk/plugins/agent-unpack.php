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

class agent_unpack extends Plugin
{
  public $Type       = PLUGIN_UI;
  public $Name       = "agent_unpack";
  public $Title      = "Schedule an Unpack";
  public $MenuList   = "Tools::Agents::Unpack";
  public $Version    = "1.0";
  public $Dependency = array("db");

  /*********************************************
   AgentAdd(): Given an uploadpk, add a job.
   Returns NULL on success, string on failure.
   *********************************************/
  function AgentAdd ($uploadpk,$Depends=NULL)
  {
    /* Prepare the job: job "unpack" */
    $jobpk = JobAddJob($uploadpk,"unpack");
    if (empty($jobpk)) { return("Failed to insert job record"); }

    /* Prepare the job: job "unpack" has jobqueue item "unpack" */
    $jqargs = "SELECT pfile.pfile_sha1 || '.' || pfile.pfile_md5 || '.' || pfile.pfile_size AS pfile,
            upload.upload_pk, ufile_pk, pfile_fk
	    FROM ufile
	    INNER JOIN upload ON upload.upload_pk = '$uploadpk' AND ufile.ufile_pk = upload.ufile_fk
	    INNER JOIN pfile ON ufile.pfile_fk = pfile.pfile_pk;";
    $jobqueuepk = JobQueueAdd($jobpk,"unpack",$jqargs,"no","pfile",$Depends);
    if (empty($jobqueuepk)) { return("Failed to insert item into job queue"); }

    return(NULL);
  } // AgentAdd()

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $DB;
    $V="";
    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
	$V .= "<H1>$this->Title</H1>\n";
	/* If this is a POST, then process the request. */
	$uploadpk = GetParm('upload',PARM_INTEGER);
	if (!empty($uploadpk))
	  {
	  $rc = $this->AgentAdd($uploadpk);
	  if (empty($rc))
	    {
	    /* Need to refresh the screen */
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('Unpack added to job queue')\n";
	    $V .= "</script>\n";
	    }
	  else
	    {
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('Upload failed: $rc')\n";
	    $V .= "</script>\n";
	    }
	  }

	/* Set default values */
	if (empty($GetURL)) { $GetURL='http://'; }

	/* Get list of projects that are not scheduled for uploads */
	$SQL = "SELECT upload_pk,upload_desc,upload_filename
		FROM upload
		WHERE upload_pk NOT IN
		(
		  SELECT upload_pk FROM upload
		  INNER JOIN job ON job.job_upload_fk = upload.upload_pk
		  INNER JOIN jobqueue ON jobqueue.jq_job_fk = job.job_pk
		    AND job_name = 'unpack' ORDER BY upload_pk
		)
		ORDER BY upload_pk DESC;";
	$Results = $DB->Action($SQL);
	if (empty($Results[0]['upload_pk']))
	  {
	  $V .= "All uploaded files are already unpacked, or scheduled to be unpacked.";
	  }
	else
	  {
	  /* Display the form */
	  $V .= "<form method='post'>\n"; // no url = this url
	  $V .= "Select an uploaded file to unpack.\n";
	  $V .= "Only uploads that are not already unpacked (and not already scheduled) can be scheduled.\n";
	  $V .= "<p />\nUnpack: <select name='upload'>\n";
	  foreach($Results as $Row)
	    {
	    if (empty($Row['upload_pk'])) { continue; }
	    if (empty($Row['upload_desc'])) { $Name = $Row['upload_filename']; }
	    else { $Name = $Row['upload_desc'] . " (" . $Row['upload_filename'] . ")"; }
	    $Name = str_replace("'","\'",$Name);
	    $V .= "<option value='" . $Row['upload_pk'] . "'>$Name</option>\n";
	    }
	  $V .= "</select><P />\n";
	  $V .= "<input type='submit' value='Unpack!'>\n";
	  $V .= "</form>\n";
	  }
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
$NewPlugin = new agent_unpack;
$NewPlugin->Initialize();
?>
