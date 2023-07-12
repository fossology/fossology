<?php
/*
 SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file admin-scheduler.php
 * \brief operations on the scheduler from GUI
 **/

define("TITLE_ADMIN_SCHEDULER", _("Scheduler Administration"));

/**
 * \class admin_scheduler
 * \brief This is a class for operations on the scheduler from GUI
 **/
class admin_scheduler extends FO_Plugin
{
  var $error_info = "";
  public $operation_array;

  function __construct()
  {
    $this->Name       = "admin_scheduler";
    $this->Title      = TITLE_ADMIN_SCHEDULER;
    $this->MenuList   = "Admin::Scheduler";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    $this->operation_array = array
    (
      "status" => array(_("Status"), _("Display job or scheduler status.")),
      "database" => array(_("Check job queue"),_("Check for new jobs.")),
      "reload" => array(_("Reload"), _("Reload fossology.conf.")),
      "agents" => array(_("Agents"), _("Show a list of enabled agents.")),
      "verbose" => array(_("Verbose"), _("Change the verbosity level of the scheduler or a job.")),
      "stop" => array(_("Shutdown Scheduler"), _("Shutdown the scheduler gracefully and stop all background processing.  This can take a while for all the agents to quit.")),
      //    "start" => array(_("Start Scheduler"), _("Start Scheduler.")),
      //    "restarts" => array(_("Restart Scheduler"), _("Restart Scheduler.")),
      "restart" => array(_("Unpause a job"), _("Unpause a job.")),
      "pause" => array(_("Pause a running job"), _("Pause a running job.")),
      "priority" => array(_("Priority"), _("Change the priority of a job."))
    );
    parent::__construct();
  }

  /**
   * \brief get the operation list
   * \return operation list
   **/
  function OperationListOption()
  {
    $V = "";
    foreach ($this->operation_array as $key => $value) {
      $V .= "<option value='$key'>$value[0]</option>";
    }
    return $V;
  }

  /**
   * \brief get the job list for the operation 'status'
   * \return job list
   **/
  function JobListOption()
  {
    $job_list_option = "<option value='0'>scheduler</option>";
    $operation = GetParm('operation', PARM_TEXT);
    if ("stop" === $operation) {
      return $job_list_option;
    }
    $job_array = GetRunnableJobList(); /* get all job list */
    if (!empty($job_array)) {
      foreach ($job_array as $job_id) {
        $job_list_option .= "<option value='$job_id'>$job_id</option>";
      }
    }
    return $job_list_option;
  }

  /**
   * \brief get the related operation text, e.g. the operation text of 'stop' is 'Shutdown Schedule'
   * \param $operation operation name, e.g. 'status'
   * \return one operation text
   **/
  public function GetOperationText($operation)
  {
    $operation_text = '';
    $job_id = GetParm('job_list', PARM_TEXT);
    if ('0' == $job_id) {
      $text = _("scheduler");
      $job_type = $text;
    } else {
      $text = _("job");
      $job_type = "$text $job_id";
    }
    switch ($operation) {
      case 'status':
        $text = _("Status of the");
        $operation_text = "$text $job_type";
        break;
      case 'database':
        $text = "Scheduler checked the job queue";
        $operation_text = $text;
        break;
      case 'reload':
        $text = _("Configuration information for the agents and hosts reloaded");
        $operation_text = $text;
        break;
      case 'agents':
        $text = _("Get list of valid agents");
        $operation_text = $text;
        break;
      case 'verbose':
        $level_id = GetParm('level_list', PARM_TEXT);
        $verbose_level = log($level_id + 1, 2);
        $text1 = _("Change the verbosity level of the");
        $text2 = _("as");
        $operation_text = "$text1 $job_type $text2 $verbose_level";
        break;
      case 'stop':
        $text = _("Shutdown Scheduler");
        $operation_text = $text;
        break;
      case 'start':
        $text = _("Start Scheduler");
        $operation_text = $text;
        break;
      case 'restarts':
        $text = _("Restart Scheduler");
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
        $priority_id = GetParm('priority_list', PARM_TEXT);
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
  public function OperationSubmit($operation, $job_id, $priority_id, $level_id)
  {
    if ("start" === $operation) {
      // start the scheduler
      $commu_status = fo_communicate_with_scheduler('status',
        $response_from_scheduler, $this->error_info);
      if ($commu_status) {
        // the scheduler is running
        $response_from_scheduler = "Warning, the scheduler is running";
      } else {
        // start the stopped scheduler
        $this->error_info = null;
        $this->StartScheduler();
        return $this->error_info;
      }
    } else if ("restarts" === $operation) { // restart the scheduler
      $this->StartScheduler('restarts');
      return $this->error_info;
    }
    $commands = $operation;
    if (! empty($job_id) && 'scheduler' != $job_id) {
      $commands .= " $job_id";
    }
    if (isset($priority_id)) {
      $commands .= " $priority_id";
    }
    if (! empty($level_id)) {
      $commands .= " $level_id";
    }
    $commands = trim($commands);
    $commu_status = fo_communicate_with_scheduler($commands,
      $response_from_scheduler, $this->error_info);
    return $response_from_scheduler . $this->error_info;
  } // OperationSubmit()

  /**
   * \brief start the scheduler
   * \param $operation - null maeans start, others mean restart
   */
  function StartScheduler($operation = '')
  {
    if ($operation) {
      $command = "/etc/init.d/fossology restart >/dev/null 2>&1";
    } else {
      $command = "/etc/init.d/fossology start >/dev/null 2>&1";
    }
    $lastline = system($command, $rc);
    if ($rc) {
      $this->error_info = " Failed to start the scheduler, return value is: $rc.";
    }
  }

  public function Output()
  {
    $V="";
    $status_msg = "";

    $operation = GetParm('operation', PARM_TEXT);
    $job_id = GetParm('job_list', PARM_TEXT);
    $priority_id =  GetParm('priority_list', PARM_TEXT);
    $level_id =  GetParm('level_list', PARM_TEXT);
    if (! empty($operation)) {
      $report = "";
      $response_from_scheduler = $this->OperationSubmit($operation, $job_id,
        $priority_id, $level_id);
      $operation_text = $this->GetOperationText($operation);
      if (empty($this->error_info)) {
        $text = _("successfully");
        $status_msg .= "$operation_text $text.";
        if (! empty($response_from_scheduler)) {
          $report .= "<hr style='border-style:dashed'>"; // Add one dashed line
          $report .= $response_from_scheduler;
        }
      } else {
        $text = _("failed");
        $status_msg .= "$operation_text $text.";
        $report .= $this->error_info;
      }
      $this->vars['message'] = $status_msg . $report;
    }

    $text = _("List of operations:");
    $V.= $text;
    $V.= "<ul>";
    foreach ($this->operation_array as $value) {
      $V .= "<li><b>$value[0]</b>: $value[1]</li>";
    }
    $V.= "</ul>";
    $V.= "<hr>";

    $text = _("Select an operation");
    $V.= "<form id='operation_form' method='post'>";
    $V.= "<p><li>$text: ";
    $V.= "<select name='operation' id='operation' onchange='OperationSwich_Get(\""
       . Traceback_uri() . "?mod=ajax_admin_scheduler&operation=\"+this.value)'<br />\n";
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

    return $V;
  }
}
$NewPlugin = new admin_scheduler;
$NewPlugin->Initialize();
