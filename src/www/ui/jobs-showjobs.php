<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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


define("TITLE_jobs_showjobs", _("Show Job Queue"));

class jobs_showjobs extends FO_Plugin
{
  var $Name       = "showjobs";
  var $Title      = TITLE_jobs_showjobs;
  var $Version    = "1.0";
  var $MenuOrder  = 5;
  var $Dependency = array("browse");
  var $DBaccess   = PLUGIN_DB_UPLOAD;

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
    menu_insert("Main::Jobs::Show Queue",$this->MenuOrder -1,$this->Name . "&show=detail",$this->MenuTarget);

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
$text = _("Show all jobs (not just this one)");
	menu_insert("JobDetails::All",-11,"$NewURI",$text);
	$UploadPk = "&upload=$UploadPk";
	}

    menu_insert("JobDetails::[BREAK]",1);
    menu_insert("JobDetails::[BREAK]",-20);
    switch($Show)
      {
      case "detail":
$text = _("Show a summary of jobs");
	menu_insert("JobDetails::Summary",-2,"$URI&show=summary&history=$History$UploadPk",$text);
	menu_insert("JobDetails::Detail",-3);
	menu_insert("JobDetails::Refresh",-21,"$URI&show=$Show&history=$History$UploadPk");
	break;
      case "summary":
$text = _("Show detailed information about each job");
	menu_insert("JobDetails::Summary",-2);
	menu_insert("JobDetails::Detail",-3,"$URI&show=detail&history=$History$UploadPk",$text);
$text = _("Show all jobs (active and completed)");
	menu_insert("JobDetails::Refresh",-21,"$URI&show=$Show&history=$History$UploadPk",$text);
	break;
      case "job":
$text = _("Show the job queue");
	menu_insert("JobDetails::Jobs",-2,"$URI&show=summary&history=$History$UploadPk",$text);
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
$text = _("Show all jobs (active and completed)");
	  menu_insert("JobDetails::History",-12,"$URI&show=$Show&history=1",$text);
	  menu_insert("JobDetails::Active",-12);
	  break;
	case "1":
	default:
	  menu_insert("JobDetails::History",-12);
$text = _("Show only active jobs");
	  menu_insert("JobDetails::Active",-12,"$URI&show=$Show&history=0",$text);
	  break;
	}
      }
  } // RegisterMenus()

  /**
   * @brief Display color legend
   * @return Color legend in a string.
   **/
  function DrawColors()
  {
    $V = "<table border=1 padding=0><tr>\n";
    foreach($this->Colors as $Key => $Val) $V .= "  <td bgcolor='$Val'>$Key</td>\n";
    $V .= "</tr></table>\n";
    return($V);
  } // DrawColors()

  /**
   * @brief Returns the full job information table in a string.
   * @param $job_pk
   * @return Return job and jobqueue record data in an html table.
   **/
  function ShowJob($job_pk)
  {
    global $PG_CONN;
    $V = "";
    $Fields=array('jq_pk','jq_job_fk','job_name','jq_type','job_priority',
	'jq_args','jq_runonpfile',
	'jq_starttime','jq_endtime','jq_end_bits',
	'jq_endtext', 'jq_itemsprocessed',
	'job_submitter','job_queued',
	'job_upload_fk', 'jq_log');
    $Uri = Traceback_uri() . "?mod=" . $this->Name . "&show=job&job=";

    $sql = "SELECT * FROM jobqueue LEFT JOIN job ON job.job_pk = jobqueue.jq_job_fk WHERE jobqueue.jq_pk = $job_pk LIMIT 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $Row = pg_fetch_assoc($result);
    pg_free_result($result);

    if (empty($Row['jq_pk'])) { return; }
    $V .= "<table class='text' border=1 name='jobtable1'>\n";
    $text = _("Field");
    $text1 = _("Value");
    $V .= "<tr><th>$text</th><th>$text1</th></tr>\n";
    foreach($Fields as $F)
    {
      $V .= "  <tr><th align='left'>$F</th><td>";
      switch($F)
	  {
	    case 'jq_itemsprocessed':
            $V .= number_format($Row[$F]);
            break;
    	case 'jq_pk':
    		$V .= "<a href='$Uri" . $Row[$F] . "'>" . htmlentities($Row[$F]) . "</a>";
    		break;
    	case 'job_upload_fk':
    		if (!empty($Row[$F]))
            {
		      $Browse = Traceback_uri() . "?mod=browse&upload=" . htmlentities($Row[$F]);
		      $V .= "<a href='$Browse'>" . htmlentities($Row[$F]) . "</a>";
            }
            break;
        case 'jq_log':
            if (!empty($Row[$F]))
            {
              $V .= "<pre>";
              if (file_exists($Row[$F])) $V .= file_get_contents($Row[$F]);
              $V .= "</pre>";
            }
            break;
	    default:
            if (array_key_exists($F, $Row)) $V .= htmlentities($Row[$F]);
            break;
      }
      $V .= "</td></tr>\n";
    }

    /* List who this depends on */
    $sql = "SELECT * FROM jobdepends WHERE jdep_jq_fk = " . $Row['jq_pk'] . ";";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0)
    {
      $text = _("depends on");
      $V .= "  <tr><th align='left'>$text</th><td>";
      $First=1;
      while ($JobDepsRow = pg_fetch_assoc($result));
      {
        if ($First)  
          $First=0; 
        else 
          $V .= ", "; 
        $V .= "<a href='$Uri" . $JobDepsRow['jdep_jq_depends_fk'] . "'>" 
               . $JobDepsRow['jdep_jq_depends_fk'] . "</a>";
      }
      $V .= "</td></tr>\n";
    }
    pg_free_result($result);

    /* List depends on this */
    $Sql = "SELECT * FROM jobdepends WHERE jdep_jq_depends_fk = " . $Row['jq_pk'] . ";";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $Row = pg_fetch_assoc($result);
    if (pg_num_rows($result) > 0)
    {
      $text = _("required by");
      $V .= "  <tr><th align='left'>$text</th><td>";
      $First=1;
      while ($JobDepsRow = pg_fetch_assoc($result));
      {
        if ($First) 
          $First=0; 
        else  
          $V .= ", "; 
        $V .= "<a href='$Uri" . $JobDepsRow['jdep_jq_fk'] . "'>" 
              . $JobDepsRow['jdep_jq_fk'] . "</a>";
      }
      $V .= "</td></tr>\n";
    }
    pg_free_result($result);

    /* Close the table */
    $V .= "</table>\n";
    return($V);
  } // ShowJob()

  /***********************************************************
   Show(): This function returns the full job queue status.
   ***********************************************************/
  function Show	($History,$UploadPk=-1,$Detail=0)
  {
    global $PG_CONN;
    $V = '';

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
      $WherePage .= "(upload_filename,upload_pk) IN
	(SELECT DISTINCT upload_filename,upload_pk FROM job
	LEFT OUTER JOIN upload ON upload.upload_pk = job.job_upload_fk
	LIMIT 10 OFFSET $Offset)";
      }
    else { $WherePage = ""; }

    /** NOTE: Results are NOT in alphabetical order.  They are in
        LC_COLLATE order.  Changing LC_COLLATE requires re-running
	postgresql's initdb. **/
    $sql = "
    SELECT *
    FROM jobqueue
    INNER JOIN job ON jobqueue.jq_job_fk = job.job_pk
    LEFT OUTER JOIN upload ON upload_pk = job.job_upload_fk
    LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
    $Where $WherePage
    ORDER BY upload_filename,upload.upload_pk,job.job_pk,jobqueue.jq_pk,jobdepends.jdep_jq_fk;
    ";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $Results = pg_fetch_all($result);
    pg_free_result($result);

    /*****************************************************************/
    /* Get Jobs that are NOT associated with uploads (e.g., folder delete). */
    /*****************************************************************/
    $Count = 1; /* count number of jobs */
    /* turn off E_NOTICE kludge so this stops reporting undefined index */
    $errlev = error_reporting(E_ERROR | E_WARNING | E_PARSE);
    for($i=1; !empty($Results[$i]['upload_pk']); $i++)
    {
      if ($Results[$i]['upload_pk'] != $Results[$i+1]['upload_pk'])
	$Count++;
    }
    error_reporting($errlev); /* return to previous error reporting level */

    if (($UploadPk < 0) && (!is_array($Results) || ($Count < 10)))
    {
      if ($History == 1) { $Where = ""; }
      else { $Where = "WHERE jobqueue.jq_starttime IS NULL OR jobqueue.jq_endtime IS NULL OR jobqueue.jq_end_bits > 1"; }

      $sql = "SELECT jobqueue.*,jobdepends.*,job.*, '-1' AS upload_pk, '' AS ufile_name
              FROM jobqueue
              LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
              LEFT JOIN jobqueue AS depends
              ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
              INNER JOIN job ON jobqueue.jq_job_fk = job.job_pk
              AND job.job_upload_fk IS NULL
              $Where
              ORDER BY job.job_pk,jobqueue.jq_pk,jobdepends.jdep_jq_fk ";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $Results2 = pg_fetch_all($result);
      pg_free_result($result);

      if (!is_array($Results) || ($Count <= 0))
        $Results = $Results2;
      else
         if (is_array($Results2)) $Results = array_merge($Results, $Results2);
    }

    /* Only show menu paging if we're viewing history. */
    if (! $History) { $VM = ""; }
    else if ($Count >= 10) { $VM = MenuEndlessPage($Page,1); }
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
    $Upload="-1";
    $Uri = Traceback_uri() . "?mod=" . $this->Name;
    $UriFull = $Uri . Traceback_parm_keep(array("show","history","upload"));

    /* turn off E_NOTICE kludge so this stops reporting undefined index */
    $errlev = error_reporting(E_ERROR | E_WARNING | E_PARSE);

    for($i=0; !empty($Results[$i]['job_name']); $i++)
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
	$V .= "<table class='text' border=1 width='100%' name='jobtable'>\n";
	$JobName = $Row['upload_filename'];
	$JobName = preg_replace("@^.*/@","",$JobName);
	if (empty($JobName)) { $JobName = "[Default]"; }
	if (!empty($Row['upload_desc'])) $JobName .= " (" . $Row['upload_desc'] . ")";
	$Style = "style='background:#202020; color:white;}'";
	$Style1 = "style='font:normal 8pt verdana, arial, helvetica; background:#202020; color:white;'";
	$V .= "<tr><th colspan=3 $Style>";

    if ($Upload)
    {
      /* Find the uploadtree_pk for this upload so that it can be used in the browse link */
      $sql = "select uploadtree_pk from uploadtree 
                  where parent is NULL and upload_fk='$Upload' ";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if ( pg_num_rows($result))
      {
        $row = pg_fetch_assoc($result);
        $Item = $row['uploadtree_pk'];
      }
      pg_free_result($result);
    }

	$V .= "<a title='Click to browse this upload' $Style href='" . Traceback_uri() . "?mod=browse&upload=" . $Row['upload_pk'] . "&item=" . $Item . "'>";
	$V .= $JobName;
	$V .= "</a>";
	$V .= "</th>";
	if ($Upload >= 0)
	  {
	  if ($Detail)
	    {
$text = _("History");
	    $V .= "<th $Style1><a $Style1 title='Display all jobs associated with this upload' href='" . Traceback_uri() . "?mod=" . $this->Name . "&show=detail&history=1&upload=$Upload'>$text</a>";
	    }
	  else
	    {
$text = _("History");
	    $V .= "<th $Style1><a $Style1 title='Display all jobs associated with this upload' href='" . Traceback_uri() . "?mod=" . $this->Name . "&show=summary&history=1&upload=$Upload'>$text</a>";
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
$text = _("Job/Dependency");
	$V .= "<tr><th width='20%'>$text</th>\n";
	$V .= "<th width='60%' colspan=2>Job Name: " . $Row['job_name'] . "</th>";
	if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE)
	  {
	  $Style = "style='font:normal 8pt verdana, arial, helvetica;'";
	  $JobId = $Row['job_pk'];
	  $V .= "<th $Style>";
$text = _("Reset");
	  $V .= "<a href='$UriFull&action=reset&jobid=$JobId' title='Reset this specific job'>$text</a>";
	  $V .= " | ";
$text = _("Delete");
	  $V .= "<a href='$UriFull&action=delete&jobid=$JobId' title='Delete this specific job'>$text</a>";
	  $V .= " | ";
	  $Priority = $Row['job_priority'];
	  $V .= _("Priority: ");
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
	  if ($t == "1") { $V .= "  <td>$t item<br />\n"; }
	  else { $V .= "  <td>$t items<br />\n"; }

	  $V .= "  </tr></table>\n";
	  }
	} /* if show details */
      $endtime = substr($Row['jq_endtime'],0,16);
      $V .= "  <td width='20%' bgcolor='$Color'>$endtime</td>\n";
      $V .= "</tr>\n";
      }
    error_reporting($errlev); /* return to previous error reporting level */
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
