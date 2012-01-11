<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 * \file admin-scheduler.php
 * \brief operations on the scheduler from GUI
 **/

define("TITLE_admin_scheduler", _("Scheduler Administration"));

/**
 * \class admin_scheduler
 * \brief This is a class for operations on the scheduler from GUI
 **/
class admin_scheduler extends FO_Plugin
{
  var $Name       = "admin_scheduler";
  var $Title      = TITLE_admin_scheduler;
  var $Version    = "1.0";
  var $MenuList   = "Admin::Scheduler";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_DELETE;
  var $error_info = "";
  var $operation_array;
  /**
   * \brief get the operation list
   * \return operation list
   **/
  function OperationListOption()
  {
    $V = "";
    foreach ($this->operation_array as $key => $value)
    {
      $V.= "<option value='$key'>$value[0]</option>";
    }

    return($V);
  } // FolderListOption()

  /**
   * \brief get the job list for the operation 'status'
   * \return job list
   **/
  function JobListOption()
  {
    $job_list_option = "<option value='0'>scheduler</option>";
    $job_array = GetRunnableJobList(); /* get all job list */
    $size = sizeof($job_array);
    for($i = 0; $i < sizeof($job_array); $i++)
    {
      $job_id = $job_array[$i];
      $job_list_option .= "<option value='$job_id'>$job_id</option>";
    }
    return $job_list_option;
  } // JobListOption()

  /**
   * \brief get the related operation text, e.g. the operation text of 'stop' is 'Shutdown Schedule'
   * \param $operation operation name, e.g. 'status'
   * \return one operation text
   **/
  function GetOperationText($operation)
  {
    $operation_text = '';
    $job_id = GetParm('job_list', PARM_TEXT);
    if ('0' ==  $job_id)
    {
      $text = _("scheduler");
      $job_type = $text;
    }
    else
    {
      $text = _("job");
      $job_type = "$text $job_id";
    }
    switch ($operation)
    {
      case 'status':
        $text = _("Get status of the");
        $operation_text = "$text $job_type";
        break;
      case 'database':
        $text = "Force the scheduler to check the job queue";
        $operation_text = $text;
        break;
      case 'reload':
        $text =_("Reload the configuration information for the agents and hosts");
        $operation_text = $text;
        break;
      case 'agents':
        $text = _("Get list of valid agents");
        $operation_text = $text;
        break;
      case 'verbose':
        $level_id =  GetParm('level_list', PARM_TEXT);
        $verbose_level =  log($level_id + 1, 2);
        $text1 = _("Change the verbosity level of the");
        $text2 = _("as");
        $operation_text = "$text1 $job_type $text2 $verbose_level";
        break;
      case 'stop':
        $text = _("Shutdown Scheduler");
        $operation_text = $text;
        break;
      case 'restart':
        $text = _("Restart the");
        $operation_text = "$text $job_type";
        break;
      case 'pause':
        $text = _("Pause the");
        $operation_text = "$text $job_type";
        break;
      case 'priority':
        $priority_id =  GetParm('priority_list', PARM_TEXT);
        $text1 = _("Change the priority of the");
        $text2 = _("as");
        $operation_text = "$text1 $job_type $text2 $priority_id";
        break;
    }
    return $operation_text;
  }

  /**
   * \brief submit the specified operation
   * \param $operation operation name, e.g. 'status'
   * \param $job_id selected job id
   * \param $priority_id selected priority id
   * \param $level_id selected level id
   * \return return response from the scheduler
   **/
  function OperationSubmit($operation, $job_id, $priority_id, $level_id)
  {
    $commands = $operation;
    if (!empty($job_id) && 'scheduler' != $job_id) $commands .= " $job_id";
    if (isset($priority_id)) $commands .= " $priority_id";
    if (!empty($level_id)) $commands .= " $level_id";
    $commands = trim($commands);
    $commu_status = fo_communicate_with_scheduler($commands, $response_from_scheduler, $this->error_info);
    return $response_from_scheduler;
  } // OperationSubmit()

  /**
   * \brief output the scheduler admin UI
   **/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }
    $V="";
    global $Plugins;
    $status_msg = "";

    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $this->operation_array = array
        (
        "status" => array(_("Status"), _("Display job or scheduler status.")), 
        "database" => array(_("Check job queue"),_("Force the scheduler to check the job queue.")), 
        "reload" => array(_("Reload"), _("Reload the configuration information for the agents and hosts.")), 
        "agents" => array(_("Agents"), _("Get list of valid agents.")), 
        "verbose" => array(_("Verbose"), _("Change the verbosity level of the scheduler or a job.")), 
        "stop" => array(_("Shutdown Scheduler"), _("Shutdown the scheduler gracefully.  This will stop all background processing, but the user interface will still be available.  Depending on what is currently running, this could take some time.")), 
        "restart" => array(_("Restart paused job"), _("Restart a job that has been paused.")), 
        "pause" => array(_("Pause started job"), _("Pause a job that has been started.")), 
        "priority" => array(_("Priority"), _("Change the priority of a job."))
        );

        $operation = GetParm('operation', PARM_TEXT);
        $job_id = GetParm('job_list', PARM_TEXT);
        $priority_id =  GetParm('priority_list', PARM_TEXT);
        $level_id =  GetParm('level_list', PARM_TEXT);
        if (!empty($operation))
        {
          $report = "";
          $response_from_scheduler = $this->OperationSubmit($operation, $job_id, $priority_id, $level_id);
          $operation_text = $this->GetOperationText($operation);
          if (empty($this->error_info))
          {
            $text = _("successfully");
            $status_msg .= "$operation_text $text.";
            if (!empty($response_from_scheduler))
            {
              $report .= "<hr style='border-style:dashed'>"; /* add one dashed line */
              $report .= $response_from_scheduler;
            }
          }
          else
          {
            $text = _("failed");
            $status_msg .= "$operation_text $text.";
            $report .= $this->error_info;
          }
          echo displayMessage($status_msg, $report);
        }

        $text = _("List of operations:");
        $V.= $text;
        $V.= "<ul>";
        foreach ($this->operation_array as $value)
        {
          $V.= "<li><b>$value[0]</b>: $value[1]</li>";
        }
        $V.= "</ul>";
        $V.= "<hr>";

        $text = _("Select an operation");
        $V.= "<form id='operation_form' method='post'>";
        $V.= "<p><li>$text: ";
        $V.= "<select name='operation' id='operation' onchange='OperationSwich_Get(\"" .Traceback_uri() . "?mod=ajax_admin_scheduler&operation=\"+this.value)'<br />\n";
        $V.= $this->OperationListOption();
        $V.= "</select>\n";
        $V.= "<br><br>";
        $V.= "<div id='div_operation'>";
        $text = _("Select the scheduler or a job");
        $V.= "$text: <select name='job_list' id='job_list'>";
        $V.= $this->JobListOption('status');
        $V.= "</select>";
        $V.= "</div>";
        $V .= "<hr>";
        $text = _("Submit");
        $V.= "<input type='submit' value='$text' />\n";
        $V.= "</form>";

        $choice = ActiveHTTPscript("OperationSwich");
        $choice .= "<script language='javascript'>\n
        function OperationSwich_Reply()\n
        {\n
          if ((OperationSwich.readyState==4) && (OperationSwich.status==200)) \n
          {\n
            document.getElementById('div_operation').innerHTML = OperationSwich.responseText;\n
          }\n
        }\n
        </script>\n";
        $V.= $choice;
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return($V);
    }
    print "$V";
    return;
  }
};
$NewPlugin = new admin_scheduler;
$NewPlugin->Initialize();

?>
