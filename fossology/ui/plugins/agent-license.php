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

class agent_license extends FO_Plugin
{
  public $Name       = "agent_license";
  public $Title      = "Schedule License Analysis";
  // public $MenuList   = "Jobs::Agents::License Analysis";
  public $Version    = "1.1";
  public $Dependency = array("db");
  public $DBaccess   = PLUGIN_DB_ANALYZE;

  /***********************************************************
   RegisterMenus(): Register additional menus.
   ***********************************************************/
  function RegisterMenus()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); } // don't run
    menu_insert("Agents::" . $this->Title,0,$this->Name);
    }

  /*********************************************
   AgentCheck(): Check if the job is already in the
   queue.  Returns:
     0 = not scheduled
     1 = scheduled but not completed
     2 = scheduled and completed
   *********************************************/
  function AgentCheck($uploadpk)
  {
    global $DB;
    $SQL = "SELECT jq_pk,jq_starttime,jq_endtime FROM jobqueue INNER JOIN job ON job_upload_fk = '$uploadpk' AND job_pk = jq_job_fk AND jq_type = 'filter_clean';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['jq_pk'])) { return(0); }
    if (empty($Results[0]['jq_endtime'])) { return(1); }
    return(2);
  } // AgentCheck()

  /*********************************************
   AgentAdd(): Given an uploadpk, add a job.
   $Depends is for specifying other dependencies.
   $Depends can be a jq_pk, or an array of jq_pks, or NULL.
   Returns NULL on success, string on failure.
   *********************************************/
  function AgentAdd ($uploadpk,$Depends=NULL,$Priority=0)
  {
    global $DB;
    /* Get dependency: "license" require "adj2nest". */
    $SQL = "SELECT jq_pk FROM jobqueue
	    INNER JOIN job ON job.job_upload_fk = '$uploadpk'
	    AND job.job_pk = jobqueue.jq_job_fk
	    WHERE jobqueue.jq_type = 'adj2nest';";
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
    $Dep = array($Dep);
    if (is_array($Depends)) { $Dep = array_merge($Dep,$Depends); }
    else if (!empty($Depends)) { $Dep[1] = $Depends; }

    /* Prepare the job: job "license" */
    $jobpk = JobAddJob($uploadpk,"license",$Priority);
    if (empty($jobpk) || ($jobpk < 0)) { return("Failed to insert job record"); }

    /*****
     Performance notes:
     The bSAM algorithm is slow.  In order to speed it up, it has been
     divided into specific tasks.
       - filter_license: convert files to tokens for comparison.
       - license: use bSAM to compare tokenized files.
       - filter_clean: remove tokenized files that are no longer needed.
     The SQL for each of these steps does not scale well.
     In particular, finding all pfiles to process takes longer and longer
     as the pfile and ufile tables grow.
     Since the list of all pfiles is needed for each of the three
     processing stages, two more stages are being introduced:
       - sqlagent: Create a temporary table containing the pfile list.
         This reduces the need to determine the list every time.
       - filter_license: convert files to tokens for comparison.
       - license: use bSAM to compare tokenized files.
       - filter_clean: remove tokenized files that are no longer needed.
       - sqlagent: Remove the temporary database table.
     While the first sqlagent command may be time-consuming, the
     remaining steps should be much faster.

     The scheduler uses a greedy algorithm to identify which jobs to run.
     Jobs are segmented into groups of tasks (right now, 5000 tasks per
     segment).  A segment does not end (and the next does not start) until
     all of the tasks complete.
     The best case has the largest jobs run first, so they will complete as
     soon as possible.  Meanwhile parallel tasks will start later and
     (hopefully) finish earlier.
     (In the worst case, everything will take a long time.)
     Thus: records are sorted by size.  NOTE: This is the original file
     size and not the tokenized file size.  But it should be good enough
     for a greedy algorithm.
     *****/

    /* Before starting, make sure the temp table does not exist. */
    $TempTable = "license_" . $uploadpk; /* must be lowercase */
    $SQL = "SELECT * FROM pg_tables WHERE tablename='$TempTable';";
    $Results = $DB->Action($SQL);
    if (!empty($Results[0]['tablename']))
	{
	$DB->Action("DROP TABLE $TempTable;");
	}

    /* Add job: job "license" has jobqueue item "sqlagent" */
    /** $jqargs = list of pfiles in the upload **/
    $jqargs = "SELECT DISTINCT(pfile_pk) as Akey,
	pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A,
	pfile_size as Size
	INTO $TempTable
	FROM uploadtree,pfile
    WHERE upload_fk = '$uploadpk'
	  AND pfile_fk IS NOT NULL
	  AND (ufile_mode & (1<<29)) = 0
      AND pfile_fk = pfile_pk
	ORDER BY Size DESC;";

    /** sqlagent does not like newlines! **/
    $jqargs = str_replace("\n"," ",$jqargs);
    $jobqueuepk = JobQueueAdd($jobpk,"sqlagent",$jqargs,"no","",$Dep);
    if (empty($jobqueuepk)) { return("Failed to insert first sqlagent into job queue"); }

    /* job "license" has jobqueue item "filter_license" */
    /** $jqargs = pfiles NOT processed and NOT with tokens in repository **/
    $jqargs = "SELECT DISTINCT(Akey),A,Size
	FROM $TempTable
	LEFT JOIN agent_lic_status ON agent_lic_status.pfile_fk = Akey
	WHERE agent_lic_status.inrepository IS NOT TRUE
	AND agent_lic_status.processed IS NOT TRUE
	ORDER BY Size DESC
	LIMIT 5000;";
    $jobqueuepk = JobQueueAdd($jobpk,"filter_license",$jqargs,"yes","a",array($jobqueuepk));
    if (empty($jobqueuepk)) { return("Failed to insert filter_license into job queue"); }

    /* job "license" has jobqueue item "license" */
    /** jqargs = all pfiles NOT processed and WITH tokens in repository **/
    $jqargs = "SELECT DISTINCT(Akey),A,Size
	FROM $TempTable
	INNER JOIN agent_lic_status ON agent_lic_status.pfile_fk = Akey
	WHERE agent_lic_status.inrepository IS TRUE
	AND agent_lic_status.processed IS NOT TRUE
	ORDER BY Size DESC
	LIMIT 5000;";
    $jobqueuepk = JobQueueAdd($jobpk,"license",$jqargs,"yes","a",array($jobqueuepk));
    if (empty($jobqueuepk)) { return("Failed to insert license into job queue"); }

    /* job "license" has jobqueue item "licinspect" */
    /** jqargs = all pfiles NOT processed and WITH tokens in repository **/
    $jqargs = "SELECT DISTINCT(Akey),A,Size
	FROM $TempTable
	INNER JOIN agent_lic_status ON agent_lic_status.pfile_fk = Akey
	WHERE agent_lic_status.inspect_name IS NOT TRUE
	ORDER BY Size DESC
	LIMIT 5000;";
    $jobqueuepk = JobQueueAdd($jobpk,"licinspect",$jqargs,"yes","a",array($jobqueuepk));
    if (empty($jobqueuepk)) { return("Failed to insert licinspect into job queue"); }

    /* job "license" has jobqueue item "filter_clean" */
    /** jqargs = all pfiles with tokens in the repository **/
    $jqargs = "SELECT DISTINCT(Akey),A,Size
	FROM $TempTable
	INNER JOIN agent_lic_status ON agent_lic_status.pfile_fk = Akey
	WHERE agent_lic_status.inrepository IS TRUE
	AND agent_lic_status.processed IS TRUE
	AND agent_lic_status.inspect_name IS TRUE
	ORDER BY Size DESC
	LIMIT 5000;";
    $jobqueuepk = JobQueueAdd($jobpk,"filter_clean",$jqargs,"yes","a",array($jobqueuepk));
    if (empty($jobqueuepk)) { return("Failed to insert filter_clean into job queue"); }

    /* job "license" has jobqueue item "sqlagent" */
    /** This updates the license counts **/
    $TempTable2 = $TempTable . "_1";
    /** The SET statement_timeout = 0; disables any timeouts (this can be slow) **/
    $jqargs = "BEGIN;
	SET statement_timeout = 0;
	SELECT licterm_name.pfile_fk,COUNT(licterm_name.pfile_fk) AS count
	  INTO TEMP $TempTable2 FROM licterm_name
	  INNER JOIN uploadtree ON upload_fk = $uploadpk
	  AND licterm_name.pfile_fk = uploadtree.pfile_fk
	  GROUP BY licterm_name.pfile_fk
	  ORDER BY licterm_name.pfile_fk;
	UPDATE pfile SET pfile_liccount = $TempTable2.count
	  FROM $TempTable2
	  WHERE pfile.pfile_pk = $TempTable2.pfile_fk;
	DROP TABLE $TempTable2;
	COMMIT;";
    $jobqueuepk = JobQueueAdd($jobpk,"sqlagent",$jqargs,"no","",array($jobqueuepk));
    if (empty($jobqueuepk)) { return("Failed to insert count-update sqlagent into job queue"); }

    /* job "license" has jobqueue item "sqlagent" */
    /** This removes the temp table and flushes the cache **/
    $jqargs = "DROP TABLE $TempTable; DELETE FROM report_cache WHERE report_cache_uploadfk = '$uploadpk';";
    $jobqueuepk = JobQueueAdd($jobpk,"sqlagent",$jqargs,"no","",array($jobqueuepk));
    if (empty($jobqueuepk)) { return("Failed to insert final sqlagent into job queue"); }

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
	/* If this is a POST, then process the request. */
	$uploadpk = GetParm('upload',PARM_INTEGER);
	if (!empty($uploadpk))
	  {
	  $rc = $this->AgentAdd($uploadpk);
	  if (empty($rc))
	    {
	    /* Need to refresh the screen */
	    $V .= PopupAlert('Analysis added to job queue');
	    }
	  else
	    {
	    $V .= PopupAlert("Scheduling failed: $rc");
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
