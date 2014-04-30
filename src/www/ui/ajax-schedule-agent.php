<?php
/***********************************************************
 Copyright (C) 2014 Hewlett-Packard Development Company, L.P.

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
 * \file ajax_schedule_agent.php
 * \brief schedule one agent on one upload
 * This is intended as an active plugin to provide support
 * data to the UI.
 */

define("TITLE_ajax_schedule_agent", _("Schedule agent"));

/**
 * \class ajax_upload_agents extends from FO_Plugin
 * \brief list all agents that can be scheduled for a given upload.
 */
class ajax_schedule_agent extends FO_Plugin
{
  var $Name       = "schedule_agent";
  var $Title      = TITLE_ajax_schedule_agent;
  var $Version    = "1.0";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_READ;
  var $NoHTML     = 1; /* This plugin needs no HTML content help */

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V="";
    global $Plugins;
    global $PG_CONN;
    global $SysConf;
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $UploadPk = GetParm("upload",PARM_INTEGER);
        $Agent = GetParm("agent",PARM_STRING);
        if (empty($UploadPk) && empty($Agent)) {
          return;
        }
        /* Make sure the uploadpk is valid */
        if (!$UploadPk) return "agent-add.php AgentsAdd(): No upload_pk specified";
        $sql = "SELECT upload_pk, upload_filename FROM upload WHERE upload_pk = '$UploadPk';";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        if (pg_num_rows($result) < 1)
        {
          $ErrMsg = __FILE__ . ":" . __LINE__ . " " . _("Upload") . " " . $UploadPk. " " .  _("not found");
          return($ErrMsg);
        }
        $UploadRow = pg_fetch_assoc($result);
        $ShortName = $UploadRow['upload_filename'];
        pg_free_result($result);

        /* Create Job */
        $user_pk = $SysConf['auth']['UserId'];
        $job_pk = JobAddJob($user_pk, $ShortName, $UploadPk);


        $Dependencies = array();
        $P = &$Plugins[plugin_find_id($Agent)];
        $rv = $P->AgentAdd($job_pk, $UploadPk, $ErrorMsg, $Dependencies);
        if ($rv > 0)
        {
          /** check if the scheudler is running */
          $status = GetRunnableJobList();
          $scheduler_msg = "";
          if (empty($status))
          {
            $scheduler_msg .= _("Is the scheduler running? ");
          }

          $URL = Traceback_uri() . "?mod=showjobs&upload=$UploadPk";
          /* Need to refresh the screen */
          $text = _("Your jobs have been added to job queue.");
          $LinkText = _("View Jobs");
          $msg = "$scheduler_msg"."$text <a href=$URL>$LinkText</a>";
          $V .= displayMessage($msg);
        }
        else
        {
          $text = _("Scheduling of Agent(s) failed: ");
          $V .= displayMessage($text.$rv.$ErrorMsg);
        }

        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return($V);
    }
    print($V);
    return;
  } // Output()

};
$NewPlugin = new ajax_schedule_agent;
$NewPlugin->Initialize();

?>
