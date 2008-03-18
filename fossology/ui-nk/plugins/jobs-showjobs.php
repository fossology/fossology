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

class jobs_showjobs extends FO_Plugin
  {
  var $Name       = "showjobs";
  var $Title      = "Show Job Queue";
  var $Version    = "1.0";
  var $MenuList   = "Jobs::Queue::Summary";
  var $MenuOrder  = 5;
  var $Dependency = array("db","browse");
  var $DBaccess   = PLUGIN_DB_READ;

  var $Colors=array(
	"Queued" => "#FFFFCC",	// "white-ish",
	"Scheduled" => "#99FFFF", // "blue-ish",
	"Running" => "#99FF99",	// "green",
	"Finished" => "lightgray",
	"Blocked" => "#FFCC66",	// "orange",
	"Failed" => "#FF6666"	// "red"
	);

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    menu_insert("Main::Jobs::Queue::Details",$this->MenuOrder -1,$this->Name . "&show=detail",$this->MenuTarget);

    // For the Browse menu, permit switching between detail and summary.
    $Show = GetParm("show",PARM_STRING);
    if (empty($Show)) { $Show = "summary"; }
    $History = GetParm("history",PARM_INTEGER);
    if (empty($History)) { $History = 0; }
    $URI = $this->Name;

    $UploadPk = GetParm('upload',PARM_INTEGER);
    if (!empty($UploadPk))
	{
	$NewURI = preg_replace('/&upload=[^&]*/','',$URI);
	menu_insert("JobDetails::All",-4,"$NewURI");
	$UploadPk = "&upload=$UploadPk";
	}

    menu_insert("JobDetails::[BREAK]",1);
    switch($Show)
      {
      case "detail":
        menu_insert("JobDetails::Summary",-2,"$URI&show=summary&history=$History$UploadPk");
        menu_insert("JobDetails::Detail",-3);
        break;
      case "summary":
        menu_insert("JobDetails::Summary",-2);
        menu_insert("JobDetails::Detail",-3,"$URI&show=detail&history=$History$UploadPk");
	break;
      case "job":
        menu_insert("JobDetails::Jobs",-2,"$URI&show=summary&history=$History$UploadPk");
	break;
      default:
        break;
      }

    if ($Show != "job")
      {
      menu_insert("JobDetails::[BREAK]",-10);
      switch($History)
        {
        case "0":
          menu_insert("JobDetails::History",-11,"$URI&show=$Show&history=1");
          menu_insert("JobDetails::Active",-11);
          break;
        case "1":
        default:
          menu_insert("JobDetails::History",-11);
          menu_insert("JobDetails::Active",-11,"$URI&show=$Show&history=0");
          break;
        }
      }
    } // RegisterMenus()

  /***********************************************************
   DrawColors(): Display colors and labels.
   ***********************************************************/
  function DrawColors()
    {
    $V = "";
    $V .= "<table border=1 padding=0><tr>\n";
    foreach($this->Colors as $Key => $Val)
      {
      $V .= "  <td bgcolor='$Val'>$Key</td>\n";
      }
    $V .= "</tr></table>\n";
    return($V);
    } // DrawColors()

  /***********************************************************
   GetUfileFromJob(): Give a job number,
   TBD: Allow the user to clear hung jobs, alter priority.
   ***********************************************************/
  function GetUfileFromJob($Job)
    {
    } // GetUfileFromJob()

  /***********************************************************
   ShowJob(): This function returns the full job information.
   TBD: Allow the user to clear hung jobs, alter priority.
   ***********************************************************/
  function ShowJob($Job)
    {
    $V = "";
    global $Plugins;
    $Fields=array('jq_pk','jq_job_fk','job_name','jq_type','job_priority',
	'jq_args','jq_runonpfile',
	'jq_starttime','jq_endtime','jq_end_bits',
	'jq_endtext',
	'jq_elapsedtime','jq_processedtime','jq_itemsprocessed',
	'job_submitter','job_queued',
	'job_email_notify',
	'job_upload_fk');
    $Uri = Traceback_uri() . "?mod=" . $this->Name . "&show=job&job=";

    global $DB;
    $Sql = "SELECT *, job.* FROM jobqueue LEFT JOIN job ON job.job_pk = jobqueue.jq_job_fk WHERE jobqueue.jq_pk = $Job LIMIT 1;";
    $Results = $DB->Action($Sql);
    $Row = $Results[0];
    if (empty($Row['jq_pk'])) { return; }
    $V .= "<table class='text' border=1>\n";
    $V .= "<tr><th>Field</th><th>Value</th></tr>\n";
    foreach($Fields as $F)
      {
      $V .= "  <tr><th align='left'>$F</th><td>";
      switch($F)
	{
	case 'jq_pk':
		$V .= "<a href='$Uri" . $Row[$F] . "'>" . htmlentities($Row[$F]) . "</a>";
		break;
	case 'jq_itemsprocessed':
		$V .= number_format($Row[$F]);
		break;
	case 'jq_elapsedtime':
	case 'jq_processedtime':
		$t = floor($Row[$F] / (60*60*24));
		if ($t == 0) { $V .= ""; }
		else if ($t == 1) { $V .= "$t day "; }
		else { $V .= "$t days "; }
		$V .= gmdate("H:i:s",$Row[$F]);
		break;
	case 'job_upload_fk':
		if (!empty($Row[$F]))
		  {
		  $Browse = Traceback_uri() . "?mod=browse&upload=" . htmlentities($Row[$F]);
		  $V .= "<a href='$Browse'>" . htmlentities($Row[$F]) . "</a>";
		  }
		break;
	default:
		$V .= htmlentities($Row[$F]);
		break;
	}
      $V .= "</td></tr>\n";
      }

    /* List who this depends on */
    $Sql = "SELECT * FROM jobdepends WHERE jdep_jq_fk = " . $Row['jq_pk'] . ";";
    $Results = $DB->Action($Sql);
    if (count($Results) > 0)
      {
      $V .= "  <tr><th align='left'>depends on</th><td>";
      $First=1;
      foreach($Results as $R)
	{
	if ($First) { $First=0; }
	else { $V .= ", "; }
	$V .= "<a href='$Uri" . $R['jdep_jq_depends_fk'] . "'>" . $R['jdep_jq_depends_fk'] . "</a>";
	}
      $V .= "</td></tr>\n";
      }

    /* List depends on this */
    $Sql = "SELECT * FROM jobdepends WHERE jdep_jq_depends_fk = " . $Row['jq_pk'] . ";";
    $Results = $DB->Action($Sql);
    if (count($Results) > 0)
      {
      $V .= "  <tr><th align='left'>required by</th><td>";
      $First=1;
      foreach($Results as $R)
	{
	if ($First) { $First=0; }
	else { $V .= ", "; }
	$V .= "<a href='$Uri" . $R['jdep_jq_fk'] . "'>" . $R['jdep_jq_fk'] . "</a>";
	}
      $V .= "</td></tr>\n";
      }

    /* Close the table */
    $V .= "</table>\n";
    return($V);
    } // ShowJob()

  /***********************************************************
   ShowDetail(): This function returns the full job queue status.
   ***********************************************************/
  function ShowDetail($History,$UploadPk=-1)
    {
    global $Plugins;
    global $DB;

    if ($History == 1) { $Where = ""; }
    else { $Where = "WHERE jobqueue.jq_starttime IS NULL OR jobqueue.jq_endtime IS NULL OR jobqueue.jq_end_bits > 1"; }
    if ($UploadPk != -1)
	{
	if (empty($Where)) { $Where = " WHERE job.job_upload_fk = '$UploadPk'"; }
	else { $Where .= " AND job.job_upload_fk = '$UploadPk'"; }
	}

    $Sql = "
    SELECT jobqueue.jq_pk,jobqueue.jq_job_fk,jobdepends.jdep_jq_depends_fk,
	jobqueue.jq_elapsedtime,jobqueue.jq_processedtime,
	jobqueue.jq_itemsprocessed,job.job_queued,
	jobqueue.jq_type,job.job_name,
	jobqueue.jq_starttime,jobqueue.jq_endtime,jobqueue.jq_end_bits,
	upload.upload_filename,upload.upload_desc,
	upload.upload_pk
    FROM jobqueue
    LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
    LEFT JOIN jobqueue AS depends
    ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
    LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk
    LEFT JOIN upload ON upload_pk = job.job_upload_fk
    $Where
    ORDER BY upload.upload_pk,jobqueue.jq_job_fk,jobdepends.jdep_jq_fk;
    ";

    $Results = $DB->Action($Sql);
    if (!is_array($Results)) { return; }

    /* Now display the summary */
    $Job=-1;
    $JobName="";
    $Blocked=array();
    $First=1;
    $Upload="";
    $Uri = Traceback_uri() . "?mod=" . $this->Name;
    foreach($Results as $Row)
      {
      /* Determine the color */
      $Color=$this->Colors['Queued']; /* default */
      if ($Row['jq_end_bits'] > 1)
	{
	$Color=$this->Colors['Failed'];
	$Blocked[$Row['jq_pk']] = 1;
	}
      else if ($Blocked[$Row['jdep_jq_depends_fk']] == 1)
	{
	$Color=$this->Colors['Blocked'];
	$Blocked[$Row['jq_pk']] = 1;
	}
      else if (!empty($Row['jq_starttime']) && empty($Row['jq_endtime']))
	{
	$Color=$this->Colors['Scheduled'];
	}
      else if (!empty($Row['jq_starttime']) && !empty($Row['jq_endtime']))
	{
	$Color=$this->Colors['Finished'];
	}

      if (empty($Row['upload_pk']) || ($Upload != $Row['upload_pk']))
	{
	$Upload = $Row['upload_pk'];
	if ($First) { $First=0; }
	else { $V .= "</table>\n<P />\n"; }
	$V .= "<table class='text' border=1 width='100%'>\n";
	if (!empty($Row['upload_desc'])) $JobName = $Row['upload_desc'];
	else if (!empty($Row['upload_filename'])) { $JobName = $Row['upload_filename']; }
	else { $JobName = "[Default]"; }
	$V .= "<tr><th colspan=4 style='background:#202020;color:white;'>$JobName</font></th></tr>\n";
	}

      if ($Job != $Row['jq_job_fk'])
	{
	$Job = $Row['jq_job_fk'];
	$V .= "<tr><th width='20%'>Job/Dependency</th>\n";
	$V .= "<th width='60%' colspan=2>Job Name: " . $Row['job_name'] . "</th>";
	if ($History) { $V .= "<th width='20%'>End Time</th></tr>\n"; }
	else { $V .= "<th width='20%'></th></tr>\n"; }
	}
      $V .= "<tr>\n";
      $V .= "  <td bgcolor='$Color'>";
      $V .= "<a href='$Uri&show=job&job=" . $Row['jq_pk'] . "'>" . $Row['jq_pk'] . "</a>";
      if (!empty($Row['jdep_jq_depends_fk']))
	{
	$V .= "/<a href='$Uri&show=job&job=" . $Row['jdep_jq_depends_fk'] . "'>" . $Row['jdep_jq_depends_fk'] . "</a>";
	}
      $V .= "</td>\n";
      $V .= "  <td bgcolor='$Color' width='20%'>" . $Row['jq_type'] . "</td>\n";

      if (($Color == $this->Colors['Queued']) && ($Row['jq_itemsprocessed'] == 0))
	{
	$V .= "  <td bgcolor='$Color'></td>\n";
	}
      else
	{
	$V .= "  <td bgcolor='$Color'><table class='text' border=0 width='100%'><tr><td bgcolor='$Color'>";
	$t = number_format($Row['jq_itemsprocessed'], 0, "", ",");
	if ($t == 1) { $V .= "  <td>$t item<br />\n"; }
	else { $V .= "  <td>$t items<br />\n"; }

	$V .= "Elapsed scheduled:<br />\n";
	$V .= "Elapsed running:</td>\n";

	$V .= "    <td bgcolor='$Color'align='right'><br />";
	$t = floor($Row['jq_elapsedtime'] / (60*60*24));
	if ($t == 0) { $Time = ""; }
	else if ($t == 1) { $Time = "$t day "; }
	else { $Time = "$t days "; }
	$Time .= gmdate("H:i:s",$Row['jq_elapsedtime']);
	$V .= $Time . "<br />\n";

	$t = floor($Row['jq_processedtime'] / (60*60*24));
	if ($t == 0) { $Time = ""; }
	else if ($t == 1) { $Time = "$t day "; }
	else { $Time = "$t days "; }
	$Time .= gmdate("H:i:s",$Row['jq_processedtime']);
	$V .= $Time . "</td>\n";
	$V .= "  </tr></table>\n";
	}

      $endtime = substr($Row['jq_endtime'],0,16);
      $V .= "  <td bgcolor='$Color'>$endtime</td>\n";
      }
    $V .= "</table>\n";
    return($V);
    } // ShowDetail()

  /***********************************************************
   ShowSummary(): Show the summary of the current queue state.
   ***********************************************************/
  function ShowSummary($History,$UploadPk=-1)
    {
    global $Plugins;
    global $DB;

    if ($History == 1) { $Where = ""; }
    else { $Where = "WHERE jobqueue.jq_starttime IS NULL OR jobqueue.jq_endtime IS NULL OR jobqueue.jq_end_bits > 1"; }
    if ($UploadPk != -1)
	{
	if (empty($Where)) { $Where = " WHERE job.job_upload_fk = '$UploadPk'"; }
	else { $Where .= " AND job.job_upload_fk = '$UploadPk'"; }
	}

    $Sql = "
    SELECT jobqueue.jq_pk,jobqueue.jq_job_fk,jobdepends.jdep_jq_depends_fk,
	jobqueue.jq_type,job.job_name,
	jobqueue.jq_starttime,jobqueue.jq_endtime,jobqueue.jq_end_bits,
	upload.upload_filename,upload.upload_desc,
	upload.upload_pk
    FROM jobqueue
    LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
    LEFT JOIN jobqueue AS depends
      ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
    LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk
    LEFT JOIN upload ON upload_pk = job.job_upload_fk
    $Where
    ORDER BY upload.upload_pk,jobqueue.jq_job_fk,jobdepends.jdep_jq_fk;
    ";

    $Results = $DB->Action($Sql);
    if (!is_array($Results)) { return; }

    /* Now display the summary */
    $Job=-1;
    $JobName="";
    $Blocked=array();
    $First=1;
    $Upload="";
    $V="";
    $Uri = Traceback_uri() . "?mod=" . $this->Name;
    foreach($Results as $Row)
      {
      /* Determine the color */
      $Color=$this->Colors['Queued']; /* default */
      if ($Row['jq_end_bits'] > 1)
	{
	$Color=$this->Colors['Failed'];
	$Blocked[$Row['jq_pk']] = 1;
	}
      else if (isset($Blocked[$Row['jdep_jq_depends_fk']]))
	{
	$Color=$this->Colors['Blocked'];
	$Blocked[$Row['jq_pk']] = 1;
	}
      else if (!empty($Row['jq_starttime']) && empty($Row['jq_endtime']))
	{
	$Color=$this->Colors['Scheduled'];
	}
      else if (!empty($Row['jq_starttime']) && !empty($Row['jq_endtime']))
	{
	$Color=$this->Colors['Finished'];
	}

      if (empty($Row['upload_pk']) || ($Upload != $Row['upload_pk']))
	{
	$Upload = $Row['upload_pk'];
	if ($First) { $First=0; }
	else { $V .= "</table>\n<P />\n"; }
	$V .= "<table class='text' border=1 width='100%'>\n";
	if (!empty($Row['upload_desc'])) { $JobName = $Row['upload_desc']; }
	else if (!empty($Row['upload_filename'])) { $JobName = $Row['upload_filename']; }
	else { $JobName = "[Default]"; }
	$V .= "<tr><th colspan=3 style='background:#202020;color:white;'>$JobName</th></tr>\n";
	}
      if ($Job != $Row['jq_job_fk'])
	{
	$Job = $Row['jq_job_fk'];
	$V .= "<tr><th width='20%'>Job/Dependency</th>\n";
	$V .= "<th width='60%'>Job Name: " . $Row['job_name'] . "</th>";
	if ($History) { $V .= "<th width='20%'>End Time</th></tr>\n"; }
	else { $V .= "<th width='20%'></th></tr>\n"; }
	}
      $V .= "<tr>\n";
      $V .= "  <td bgcolor='$Color'>";
      $V .= "<a href='$Uri&show=job&job=" . $Row['jq_pk'] . "'>" . $Row['jq_pk'] . "</a>";
      if (!empty($Row['jdep_jq_depends_fk']))
	{
	$V .= "/<a href='$Uri&show=job&job=" . $Row['jdep_jq_depends_fk'] . "'>" . $Row['jdep_jq_depends_fk'] . "</a>";
	}
      $V .= "</td>\n";
      $V .= "  <td bgcolor='$Color'>" . $Row['jq_type'] . "</td>\n";
      $endtime = substr($Row['jq_endtime'],0,16);
      $V .= "  <td bgcolor='$Color'>$endtime</td>\n";
      }
    $V .= "</table>\n";
    return($V);
    } // ShowSummary()

  /***********************************************************
   Output(): This function returns the job queue status.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V="";
    $UploadPk = GetParm('upload',PARM_INTEGER);
    if (empty($UploadPk)) { $UploadPk = -1; }

    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	/* Get the list of running jobs */
	/** Find how to sort the results **/
	switch(GetParm('show',PARM_STRING))
	  {
	  case 'summary': $Show='summary'; break;
	  case 'detail': $Show='detail'; break;
	  case 'job':
	 	 $Show='job';
		 $Job = GetParm('job',PARM_INTEGER);
		 if (empty($Job)) { return; } // bad URL
		 break;
	  default: $Show='summary';
	  }
	switch(GetParm('history',PARM_STRING))
	  {
	  case '1': $History='1'; break;
	  case '0': $History='0'; break;
	  default: $History='0';
	  }
	$Uri = Traceback_uri() . "?mod=" . $this->Name;

	/* Customize the top menu */
	$V .= menu_to_1html(menu_find("JobDetails",$MenuDepth),1);
	if ($Show != "job") { $V .= $this->DrawColors(); }
	$V .= "<P />\n";

	/* Display the output based on the values */
	switch($Show)
	  {
	  case 'summary': $V .= $this->ShowSummary($History,$UploadPk); break;
	  case 'detail': $V .= $this->ShowDetail($History,$UploadPk); break;
	  case 'job': $V .= $this->ShowJob($Job); break;
	  }
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    return;
    }

  };
$NewPlugin = new jobs_showjobs;
$NewPlugin->Initialize();

?>
