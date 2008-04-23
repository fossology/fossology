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
	"Finished" => "#D3D3D3", // "lightgray",
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
	menu_insert("JobDetails::All",-11,"$NewURI");
	$UploadPk = "&upload=$UploadPk";
	}

    menu_insert("JobDetails::[BREAK]",1);
    menu_insert("JobDetails::[BREAK]",-20);
    switch($Show)
      {
      case "detail":
	menu_insert("JobDetails::Summary",-2,"$URI&show=summary&history=$History$UploadPk");
	menu_insert("JobDetails::Detail",-3);
	menu_insert("JobDetails::Refresh",-21,"$URI&show=$Show&history=$History$UploadPk");
	break;
      case "summary":
	menu_insert("JobDetails::Summary",-2);
	menu_insert("JobDetails::Detail",-3,"$URI&show=detail&history=$History$UploadPk");
	menu_insert("JobDetails::Refresh",-21,"$URI&show=$Show&history=$History$UploadPk");
	break;
      case "job":
	menu_insert("JobDetails::Jobs",-2,"$URI&show=summary&history=$History$UploadPk");
	$Job = GetParm("job",PARM_INTEGER);
	if (!empty($Job)) { $Job = "&job=$Job"; }
	menu_insert("JobDetails::Refresh",-21,"$URI&show=$Show&history=$History$UploadPk$Job");
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
	  menu_insert("JobDetails::History",-12,"$URI&show=$Show&history=1");
	  menu_insert("JobDetails::Active",-12);
	  break;
	case "1":
	default:
	  menu_insert("JobDetails::History",-12);
	  menu_insert("JobDetails::Active",-12,"$URI&show=$Show&history=0");
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
    $Sql = "SELECT * FROM jobqueue LEFT JOIN job ON job.job_pk = jobqueue.jq_job_fk WHERE jobqueue.jq_pk = $Job LIMIT 1;";
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
   Show(): This function returns the full job queue status.
   ***********************************************************/
  function Show	($History,$UploadPk=-1,$Detail=0)
    {
    global $Plugins;
    global $DB;

    if ($History == 1) { $Where = ""; }
    else { $Where = "WHERE (jobqueue.jq_starttime IS NULL OR jobqueue.jq_endtime IS NULL OR jobqueue.jq_end_bits > 1)"; }
    if ($UploadPk != -1)
	{
	if (empty($Where)) { $Where = ' WHERE '; }
	else { $Where = ' AND '; }
	$Where .= "job.job_upload_fk = '$UploadPk'";
	}

    /* Add in paging */
    $Page=getparm('page',PARM_INTEGER);
    if (empty($Page)) { $Offset = 0; }
    else { $Offset = $Page * 10; }

    /*****************************************************************/
    /* Get Jobs that ARE associated with uploads. */
    /*****************************************************************/
    if ($History && (empty($UploadPk) || ($UploadPk < 0)))
      {
      if (empty($Where)) { $WherePage = ' WHERE '; }
      else { $WherePage = ' AND '; }
      $WherePage .= "(ufile_name,upload_pk) IN
	(SELECT DISTINCT ufile_name,upload_pk FROM job
	INNER JOIN upload ON upload.upload_pk = job.job_upload_fk
	INNER JOIN ufile ON ufile.ufile_pk = upload.ufile_fk";
      $WherePage .= " ORDER BY ufile_name,upload_pk";
      $WherePage .= " LIMIT 10 OFFSET $Offset)";
      }
    else { $WherePage = ""; }

    $Sql = "
    SELECT jobqueue.jq_pk,jobqueue.jq_job_fk,jobdepends.jdep_jq_depends_fk,
	jobqueue.jq_elapsedtime,jobqueue.jq_processedtime,
	jobqueue.jq_itemsprocessed,job.job_queued,jobqueue.jq_type,
	jobqueue.jq_starttime,jobqueue.jq_endtime,jobqueue.jq_end_bits,
	upload.*,job.*,ufile.ufile_name
    FROM jobqueue
    LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
    LEFT JOIN jobqueue AS depends
      ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
    INNER JOIN job ON jobqueue.jq_job_fk = job.job_pk
    INNER JOIN upload ON upload_pk = job.job_upload_fk
    INNER JOIN ufile ON ufile.ufile_pk = upload.ufile_fk
    $Where $WherePage
    ORDER BY ufile_name,upload.upload_pk,job.job_pk,jobqueue.jq_pk,jobdepends.jdep_jq_fk;
    ";
    // print "<pre>" . htmlentities($Sql) . "</pre>";
    $Results = $DB->Action($Sql);

    /*****************************************************************/
    /* Get Jobs that are NOT associated with uploads (e.g., folder delete). */
    /*****************************************************************/
    $Count = 0; /* count number of jobs */
    for($i=1; !empty($Results[$i]['upload_pk']); $i++)
      {
      if ($Results[$i]['upload_pk'] != $Results[$i+1]['upload_pk'])
	$Count++;
      }

    if (($Upload < 0) && (!is_array($Results) || ($Count < 10)))
	{
	if ($History == 1) { $Where = ""; }
	else { $Where = "WHERE jobqueue.jq_starttime IS NULL OR jobqueue.jq_endtime IS NULL OR jobqueue.jq_end_bits > 1"; }

	$Sql = "
    SELECT jobqueue.jq_pk,jobqueue.jq_job_fk,jobdepends.jdep_jq_depends_fk,
	jobqueue.jq_elapsedtime,jobqueue.jq_processedtime,
	jobqueue.jq_itemsprocessed,job.job_queued,jobqueue.jq_type,
	jobqueue.jq_starttime,jobqueue.jq_endtime,jobqueue.jq_end_bits,
	job.*, '-1' AS upload_pk, '' AS ufile_name
    FROM jobqueue
    LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
    LEFT JOIN jobqueue AS depends
      ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
    INNER JOIN job ON jobqueue.jq_job_fk = job.job_pk
      AND job.job_upload_fk IS NULL
    $Where
    ORDER BY job.job_pk,jobqueue.jq_pk,jobdepends.jdep_jq_fk
    ";
	if (!is_array($Results) || ($Count <= 0))
	  {
	  $Results = $DB->Action($Sql);
	  }
	else
	  {
	  $Results = array_merge($Results,$DB->Action($Sql));
	  }
	}

    if ($Count >= 10) { $VM = MenuEndlessPage($Page,1); }
    else if ($Page > 0) { $VM = MenuEndlessPage($Page,0); }
    else { $VM = ""; }
    if (!is_array($Results)) { return; }

    $V .= "<P />$VM<P />";

    /*****************************************************************/
    /* Now display the summary */
    /*****************************************************************/
    $Job=-1;
    $JobName="";
    $Blocked=array();
    $First=1;
    $Upload="";
    $Uri = Traceback_uri() . "?mod=" . $this->Name;
    $UriFull = $Uri . Traceback_parm_keep(array("show","history"));
    for($i=0; !empty($Results[$i]['jq_pk']); $i++)
      {
      $Row = &$Results[$i];
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

      if ($Upload != $Row['upload_pk'])
	{
	$Upload = $Row['upload_pk'];
	if ($First) { $First=0; }
	else { $V .= "</table>\n<P />\n"; }
	$V .= "<table class='text' border=1 width='100%'>\n";
	$JobName = $Row['ufile_name'];
	if (empty($JobName)) { $JobName = "[Default]"; }
	if (!empty($Row['upload_desc'])) $JobName .= " (" . $Row['upload_desc'] . ")";
	$V .= "<tr><th colspan=3 style='background:#202020;color:white;'>$JobName";
	$Style = "style='font:normal 8pt verdana, arial, helvetica; background:#202020;color:white;}'";
	if ($Upload >= 0)
	  {
	  if ($Detail)
	    {
	    $V .= "</th><th $Style><a $Style href='" . Traceback_uri() . "?mod=" . $this->Name . "&show=detail&history=1&upload=$Upload'>History</a>";
	    }
	  else
	    {
	    $V .= "</th><th $Style><a $Style href='" . Traceback_uri() . "?mod=" . $this->Name . "&show=summary&history=1&upload=$Upload'>History</a>";
	    }
	  }
	else
	  {
	  $V .= "</th><th $Style>";
	  }
	$V .= "</th></tr>\n";
	}

      if ($Job != $Row['jq_job_fk'])
	{
	$Job = $Row['jq_job_fk'];
	$V .= "<tr><th width='20%'>Job/Dependency</th>\n";
	$V .= "<th width='60%' colspan=2>Job Name: " . $Row['job_name'] . "</th>";
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE)
	  {
	  $Style = "style='font:normal 8pt verdana, arial, helvetica;'";
	  $JobId = $Row['job_pk'];
	  $V .= "<th $Style>";
	  $V .= "<a href='$UriFull&action=reset&jobid=$JobId'>Reset</a>";
	  $V .= " | ";
	  $V .= "<a href='$UriFull&action=delete&jobid=$JobId'>Delete</a>";
	  $V .= " | ";
	  $Priority = $Row['job_priority'];
	  $V .= "Priority: ";
	  $V .= "<a title='Decrease priority' href='$UriFull&action=priority&priority=" . ($Priority-1);
	  $V .= "&jobid=$JobId'>&laquo;</a>";
	  $V .= " $Priority ";
	  $V .= "<a title='Increase priority' href='$UriFull&action=priority&priority=" . ($Priority+1);
	  $V .= "&jobid=$JobId'>&raquo;</a>";
	  $V .= "</th>";
	  }
	else { $V .= "<th width='20%'></th>\n"; }
	$V .= "</tr>\n";
	}

      /* Display each jobqueue line */
      $V .= "<tr>\n";

      /** Job ID and dependencies **/
      $V .= "  <td bgcolor='$Color' width='20%'>";
      $V .= "<a href='$Uri&show=job&job=" . $Row['jq_pk'] . "'>" . $Row['jq_pk'] . "</a>";
      if (!empty($Row['jdep_jq_depends_fk']))
	{
	$Dep = " / <a href='$Uri&show=job&job=" . $Row['jdep_jq_depends_fk'] . "'>" . $Row['jdep_jq_depends_fk'] . "</a>";
	for( ; $Results[$i+1]['jq_pk'] == $Row['jq_pk']; $i++)
	  {
	  $Dep .= ", <a href='$Uri&show=job&job=" . $Results[$i+1]['jdep_jq_depends_fk'] . "'>" . $Results[$i+1]['jdep_jq_depends_fk'] . "</a>";
	  }
	$V .= $Dep;
	}
      $V .= "</td>\n";

      /** Job name and details **/
      if (!$Detail) /* Show summary */
	{
	$V .= "  <td bgcolor='$Color' colspan='2'>" . $Row['jq_type'] . "</td>\n";
	}
      else /* Show details */
	{
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
	} /* if show details */
      $endtime = substr($Row['jq_endtime'],0,16);
      $V .= "  <td width='20%' bgcolor='$Color'>$endtime</td>\n";
      $V .= "</tr>\n";
      }
    $V .= "</table>\n";
    $V .= "<P />$VM<P />";
    return($V);
    } // Show()

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
	/* Process any actions */
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE)
	  {
	  $JobPk = GetParm("jobid",PARM_INTEGER);
	  $Action = GetParm("action",PARM_STRING);
	  switch($Action)
	      {
	      case 'reset':
		JobChangeStatus($JobPk,"reset");
		break;
	      case 'delete':
		JobChangeStatus($JobPk,"delete");
		break;
	      case 'priority':
		JobSetPriority($JobPk,GetParm("priority",PARM_INTEGER));
		break;
	      default:
		break;
	      }
	  }

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
	$V .= menu_to_1html(menu_find("JobDetails",$MenuDepth),0);
	if ($Show != "job") { $V .= $this->DrawColors(); }
	$V .= "<P />\n";

	/* Display the output based on the values */
	switch($Show)
	  {
	  case 'summary': $V .= $this->Show($History,$UploadPk,0); break;
	  case 'detail': $V .= $this->Show($History,$UploadPk,1); break;
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
