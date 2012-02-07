<?php
/***********************************************************
 Copyright (C) 2009-2012 Hewlett-Packard Development Company, L.P.

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

/**
 * \brief pkgagent agent ui
 * \class agent_pkgagent
 */

define("TITLE_agent_pkgagent", _("Package Analysis (Parse package headers)"));

class agent_pkgagent extends FO_Plugin
{
  public $Name       = "agent_pkgagent";
  public $Title      = TITLE_agent_pkgagent;
  //public $MenuList   = "Jobs::Agents::Package Analysis";
  public $Version    = "1.0";
  public $Dependency = array();
  public $DBaccess   = PLUGIN_DB_ANALYZE;

  /**
   * \brief Register additional menus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); } // don't run
    global $PG_CONN;
    $sql = "SELECT agent_enabled FROM agent WHERE agent_name ='pkgagent' order by agent_ts LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (isset($row) && ($row['agent_enabled']== 'f')){return(0);}
    menu_insert("Agents::" . $this->Title,0,$this->Name);
  }

  /**
   * \brief Check if the job can be scheduled.
   *
   * \param $upload_pk
   *
   * \return 0 = not scheduled \n
   *         1 = scheduled but not completed \n
   *         2 = scheduled and completed
   */
  function AgentCheck($upload_pk)
  {
    return CommonAgentCheck($upload_pk, "pkgagent", "package metadata scanner", "pkgagent_ars");

  } // AgentCheck()

  /**
   * \brief Given an uploadpk, add a job.
   *
   * \param $uploadpk - upload id
   * \param $Depends - for specifying other dependencies. \n
   * $Depends can be a jq_pk, or an array of jq_pks, or NULL.
   * \param $Priority - Priority number
   *
   * \return NULL on success \n
   *         An error message string on failure.
   */
  function AgentAdd ($uploadpk,$Depends=NULL,$Priority=0)
  {
    global $PG_CONN;
    /* Get dependency: "pkgagent" don't require "mimetype" and "nomos", require "unpack". */
    $sql = "SELECT jq_pk FROM jobqueue
	    INNER JOIN job ON job.job_upload_fk = '$uploadpk'
	    AND job.job_pk = jobqueue.jq_job_fk
	    WHERE jobqueue.jq_type = 'adj2nest';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    $Dep = $row['jq_pk'];
    if (empty($Dep))
    {
      global $Plugins;
      $Unpack = &$Plugins[plugin_find_id("agent_unpack")];
      $rc = $Unpack->AgentAdd($uploadpk);
      if (!empty($rc)) { return($rc); }
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      pg_free_result($result);
      $Dep = $row['jq_pk'];
      if (empty($Dep)) {
        $text = _("Unable to find dependent job: unpack");
        return($text); }
    }
    $Dep = array($Dep);
    if (is_array($Depends)) { $Dep = array_merge($Dep,$Depends); }
    else if (!empty($Depends)) { $Dep[1] = $Depends; }

    /* Prepare the job: job "Package Agent" */
    $jobpk = JobAddJob($uploadpk,"Package Scan",$Priority=0);
    if (empty($jobpk) || ($jobpk < 0)) 
    {
      $text = _("Failed to insert job record");
      return($text); 
    }

    /* jqargs wants EVERY RPM and DEBIAN pfile in this upload */
    $jqargs = $uploadpk;

    /* Add job: job "Package Scan" has jobqueue item "pkgagent" */
    $jobqueuepk = JobQueueAdd($jobpk,"pkgagent",$jqargs,"no","",$Dep);
    if (empty($jobqueuepk)) 
    {
      $text = _("Failed to insert pkgagent into job queue");
      return($text); 
    }

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (!$success) return $error_msg . "\n" . $output;

    return(NULL);

  } // AgentAdd()

  /**
   * \brief Generate the text for this plugin.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $PG_CONN;
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
            $text = _("Analysis added to job queue");
            $V .= displayMessage($text);
          }
          else
          {
            $text = _("Scheduling of Analysis failed:");
            $V .= displayMessage($text.$rc);
          }
        }

        /* Get list of projects that are not scheduled for uploads */
        $sql = "SELECT upload_pk,upload_desc,upload_filename
		FROM upload
		WHERE upload_pk NOT IN
		(
		  SELECT upload_pk FROM upload
		  INNER JOIN job ON job.job_upload_fk = upload.upload_pk
		  INNER JOIN jobqueue ON jobqueue.jq_job_fk = job.job_pk
		    AND job.job_name = 'Package Scan'
		    AND jobqueue.jq_type = 'pkgagent'
		    ORDER BY upload_pk
		)
		ORDER BY upload_desc,upload_filename;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result);
        if (empty($row['upload_pk']))
        {
          $V .= _("All uploaded files are already analyzed, or scheduled to be analyzed.");
        }
        else
        {
          /* Display the form */
          $V .= "Package analysis extract meta data from RPM files.<P />\n";
          $V .= "<form method='post'>\n"; // no url = this url
          $V .= _("Select an uploaded file for analysis.\n");
          $V .= _("Only uploads that are not already scheduled can be scheduled.\n");
          $text = _("Analyze:");
          $V .= "<p />\n$text <select name='upload'>\n";
          $Results = pg_fetch_all($result);
          foreach($Results as $Row)
          {
            if (empty($Row['upload_pk'])) { continue; }
            if (empty($Row['upload_desc'])) { $Name = $Row['upload_filename']; }
            else { $Name = $Row['upload_desc'] . " (" . $Row['upload_filename'] . ")"; }
            $V .= "<option value='" . $Row['upload_pk'] . "'>$Name</option>\n";
          }
          $V .= "</select><P />\n";
          $text = _("Analyze");
          $V .= "<input type='submit' value='$text!'>\n";
          $V .= "</form>\n";
        }
        pg_free_result($result);
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
$NewPlugin = new agent_pkgagent;
?>
