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

class agent_license extends Plugin
{
  public $Name       = "agent_license";
  public $Title      = "Schedule License Analysis";
  public $MenuList   = "Tools::Agents::License Analysis";
  public $Version    = "1.0";
  public $Dependency = array("db");

  /*********************************************
   AgentAdd(): Given an uploadpk, add a job.
   Returns NULL on success, string on failure.
   *********************************************/
  function AgentAdd ($uploadpk,$Depends=NULL)
  {
    global $DB;
    /* Get dependency: "license" require "unpack". */
    $SQL = "SELECT jq_pk FROM jobqueue
	    INNER JOIN job ON job.job_upload_fk = '$uploadpk'
	    AND job.job_pk = jobqueue.jq_job_fk
	    WHERE jobqueue.jq_type = 'wget';";
    $Results = $DB->Action($SQL);
    $Dep = $Results[0]['jq_pk'];
    if (empty($Dep))
	{
	global $Plugins;
	$Unpack = &$Plugins[plugin_find_id("agent_unpack")];
	$rc = $Unpack->AgentAdd($uploadpk);
	if (!empty($rc)) { return($rc); }
	$Results = $DB->Action($SQL);
	$Dep = $Results[0]['jq_pk'];
	if (empty($Dep)) { return("Unable to find dependent job: unpack"); }
	}

    /* Prepare the job: job "license" */
    $jobpk = JobAddJob($uploadpk,"license");
    if (empty($jobpk)) { return("Failed to insert job record"); }

    /* Add job: job "license" has jobqueue item "filter_license" */
    $jqargs = "SELECT DISTINCT(pfile_pk) as Akey,
	pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
	FROM uploadtree
	INNER JOIN ufile ON uploadtree.ufile_fk=ufile.ufile_pk
	INNER JOIN pfile ON ufile.pfile_fk = pfile.pfile_pk
	LEFT JOIN agent_lic_status ON agent_lic_status.pfile_fk = pfile.pfile_pk
	WHERE upload_fk = '$uploadpk'
	AND agent_lic_status.pfile_fk IS NULL
	AND ufile.pfile_fk IS NOT NULL
	AND (ufile.ufile_mode & (1<<29)) = 0
	LIMIT 5000;";
    $jobqueuepk = JobQueueAdd($jobpk,"filter_license",$jqargs,"yes","a",array($Dep));
    if (empty($jobqueuepk)) { return("Failed to insert filter_license into job queue"); }

    /* Add job: job "license" has jobqueue item "license" */
    $jqargs = "SELECT DISTINCT(pfile_pk) as Akey,
	pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
	FROM uploadtree
	INNER JOIN ufile ON uploadtree.ufile_fk=ufile.ufile_pk
	INNER JOIN pfile ON ufile.pfile_fk = pfile.pfile_pk
	INNER JOIN agent_lic_status ON agent_lic_status.pfile_fk = pfile.pfile_pk
	LEFT JOIN agent_lic_meta ON pfile.pfile_pk = agent_lic_meta.pfile_fk
	WHERE agent_lic_status.processed IS FALSE
	AND agent_lic_meta.pfile_fk IS NULL
	AND ufile.pfile_fk IS NOT NULL
	AND (ufile.ufile_mode & (1<<29)) = 0
	AND upload_fk = '$uploadpk'
	LIMIT 5000;";
    $jobqueuepk = JobQueueAdd($jobpk,"license",$jqargs,"yes","a",array($jobqueuepk));
    if (empty($jobqueuepk)) { return("Failed to insert filter_license into job queue"); }

    /* Add job: job "license" has jobqueue item "filter_clean" */
    $jqargs = "SELECT DISTINCT(pfile_pk) as Akey, 
	pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
	FROM uploadtree
	INNER JOIN ufile ON uploadtree.ufile_fk=ufile.ufile_pk
	INNER JOIN pfile ON ufile.pfile_fk = pfile.pfile_pk
	INNER JOIN agent_lic_status ON agent_lic_status.pfile_fk = pfile.pfile_pk
	WHERE agent_lic_status.processed IS TRUE
	AND agent_lic_status.inrepository IS TRUE
	AND upload_fk = '$uploadpk'
	AND (ufile.ufile_mode & (1<<29)) = 0
	LIMIT 5000;";
    $jobqueuepk = JobQueueAdd($jobpk,"filter_clean",$jqargs,"yes","a",array($jobqueuepk));
    if (empty($jobqueuepk)) { return("Failed to insert filter_clean into job queue"); }

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
	    $V .= "alert('Analysis added to job queue')\n";
	    $V .= "</script>\n";
	    }
	  else
	    {
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('Scheduling failed: $rc')\n";
	    $V .= "</script>\n";
	    }
	  }

	/* Get list of projects that are not scheduled for uploads */
	$SQL = "SELECT upload_pk,upload_desc,upload_filename
		FROM upload
		WHERE upload_pk NOT IN
		(
		  SELECT upload_pk FROM upload
		  INNER JOIN job ON job.job_upload_fk = upload.upload_pk
		  INNER JOIN jobqueue ON jobqueue.jq_job_fk = job.job_pk
		    AND job.job_name = 'license'
		    AND jobqueue.jq_type = 'filter_clean'
		    ORDER BY upload_pk
		)
		ORDER BY upload_desc,upload_filename;";
	$Results = $DB->Action($SQL);
	if (empty($Results[0]['upload_pk']))
	  {
	  $V .= "All uploaded files are already analyzed, or scheduled to be analyzed.";
	  }
	else
	  {
	  /* Display the form */
	  $V .= "<form method='post'>\n"; // no url = this url
	  $V .= "Select an uploaded file for license analysis.\n";
	  $V .= "Only uploads that are not already scheduled can be scheduled.\n";
	  $V .= "<p />\nAnalyze: <select name='upload'>\n";
	  foreach($Results as $Row)
	    {
	    if (empty($Row['upload_pk'])) { continue; }
	    if (empty($Row['upload_desc'])) { $Name = $Row['upload_filename']; }
	    else { $Name = $Row['upload_desc'] . " (" . $Row['upload_filename'] . ")"; }
	    $V .= "<option value='" . $Row['upload_pk'] . "'>$Name</option>\n";
	    }
	  $V .= "</select><P />\n";
	  $V .= "<input type='submit' value='Analyze!'>\n";
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
$NewPlugin = new agent_license;
$NewPlugin->Initialize();
?>
