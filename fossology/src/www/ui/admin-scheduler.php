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
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_DELETE;
  var $error_info = "";
  
  /**
   * \brief get the operation list
   * \return operation list
   **/
  function OperationListOption()
  {
    $operation_name = _("Status");
    $V.= "<option value='status'>$operation_name</option>";
    $operation_name= _("Shutdown Scheduler");
    $V.= "<option value='stop'>$operation_name</option>";
    $operation_name = _("Reload");
    $V.= "<option value='reload'>$operation_name</option>";
    $operation_name = _("Agents");
    $V.= "<option value='agents'>$operation_name</option>";
    $operation_name = _("Pause");
    $V.= "<option value='pause'>$operation_name</option>";
    $operation_name = _("Restart paused job");
    $V.= "<option value='restart'>$operation_name</option>";
    $operation_name = _("Verbose");
    $V.= "<option value='verbose'>$operation_name</option>";
    $operation_name = _("Check job queue");
    $V.= "<option value='database'>$operation_name</option>";
    $operation_name = _("Priority");
    $V.= "<option value='priority'>$operation_name</option>";
    return($V);
  } // FolderListOption()

  /**
   * \brief get the job list for the operation 'status'
   * \return job list
   **/
  function JobListOption()
  {
    $job_list_option .= "<option value='0'>scheduler</option>";
    $job_array = GetJobList(""); /* get all job list */

    foreach ($job_array as $key => $value)
    {
      $job_id = $value['jq_pk'];
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
      $job_type = "scheduler";
    else 
      $job_type = "job $job_id";
    switch ($operation)
    {
      case 'stop':
        $operation_text = "Shutdown Schedule";
        break;
      case 'pause':
        $operation_text = "Pause the $job_type";
        break;
      case 'reload':
        $operation_text = "Reload the configuration information for the agents and hosts";
        break;
      case 'status':
        $operation_text = "Get status of the $job_type";
        break;
      case 'agents':
        $operation_text = "Get list of valid agents";
        break;
      case 'restart':
        $operation_text = "Restart the $job_type";
        break;
      case 'verbose':
        $level_id =  GetParm('level_list', PARM_TEXT);
        $verbose_level =  log($level_id + 1, 2);
        $operation_text = "Change the verbosity level of the $job_type as $verbose_level";
        break;
      case 'database':
        $operation_text = "Force the scheduler to check the job queue";
        break;
      case 'priority':
        $priority_id =  GetParm('priority_list', PARM_TEXT);
        $operation_text = "Change the priority of the $job_type as $priority_id";
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
  	error_reporting(E_ALL);

    set_time_limit(0);
    global $SYSCONFDIR, $PROJECT;
    $config = new Configuration("$SYSCONFDIR/$PROJECT/fossology.conf");
    $address = $config->address;
    $port = $config->port;

    if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
    {
      $this->error_info = "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "<br>\n";
    }
     
    /* Setting the socket timeout */
    if (!socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>1, "usec"=>0)))
    {
      $this->error_info = "Unable to set option on socket: ". socket_strerror(socket_last_error()) . "<br>\n";
    }

    $result = socket_connect($sock, $address, $port);
    if ($result === false)
    {
      $this->error_info .= "<h2>Connection to the scheduler failed.  Is the scheduler running?</h2>";
      $this->error_info .= "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($sock)) . "<br>\n";
      return;
    }
    $msg = $operation;
    if (!empty($job_id) && 'scheduler' != $job_id) $msg .= " $job_id";
    if (!empty($priority_id)) $msg .= " $priority_id";
    if (!empty($level_id)) $msg .= " $level_id";
    $msg = trim($msg);
    socket_write($sock, $msg, strlen($msg));
    $response_from_scheduler;
    while ($buf = socket_read($sock, 2048, PHP_NORMAL_READ))
    {
      $response_from_scheduler .= "$buf<br>";
      if (substr($buf, 0, 3) == "end" || empty($buf)) break;  // end of scheduler response
    }
    socket_close($sock);
    return $response_from_scheduler;
  }
 
  /**
   * \brief output the scheduler admin UI
   **/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V="";
    global $Plugins;
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
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
          }
          else   
          {
            $text = _("failed");
            $status_msg .= "$operation_text $text.";
          }
          $report .= $this->error_info;
          if (!empty($response_from_scheduler))
          {
            $report .= "<hr style='border-style:dashed'>";
            $report .= $response_from_scheduler; 
          }
          echo displayMessage($status_msg, $report); 
        }

        $text = _("List of operations:");
        $V.= $text;
        $V.= "<ul>";
        $text = _("Pause a job.");
        $operation_name = _("Pause");
        $V.= "<li><b>$operation_name</b>: $text</li>";
        $text = _("Reload the configuration information for the agents and hosts.");
        $operation_name = _("Reload");
        $V.= "<li><b>$operation_name</b>: $text</li>";
        $text = _("Display job or scheduler status.");
        $operation_name = _("Status");
        $V.= "<li><b>$operation_name</b>: $text</li>";
        $text = _("Get list of valid agents.");
        $operation_name = _("Agents");
        $V.= "<li><b>$operation_name</b>: $text</li>";
        $text = _("Restart a job that has been paused.");
        $operation_name = _("Restart paused job");
        $V.= "<li><b>$operation_name</b>: $text</li>";
        $text = _("Change the verbosity level of the scheduler or a job.");
        $operation_name = _("Verbose");
        $V.= "<li><b>$operation_name</b>: $text</li>";
        $text = _("Force the scheduler to check the job queue.");
        $operation_name = _("Check job queue");
        $V.= "<li><b>$operation_name</b>: $text</li>";
        $text = _("Change the priority of a job.");
        $operation_name = _("Priority");
        $V.= "<li><b>$operation_name</b>: $text</li>";
        $text = _("Shutdown the scheduler gracefully.  This will stop all background processing, but the user interface will still be available.  Depending on what is currently running, this could take some time.");
        $operation_name = _("Shutdown Scheduler");
        $V.= "<li><b>$operation_name</b>: $text</li>";
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
    if (!$this->OutputToStdout) { return($V); }
    print "$V";
    return;
    }
};
$NewPlugin = new admin_scheduler;
$NewPlugin->Initialize();

/**
 * \class Configuration
 * \brief load configuration file
 *        you can get an item through Configuration->item, e.g. configuration_instance->port
 **/
class Configuration
{
  private $configFile;

  private $items = array();

  function __construct($configFile) 
  {
    $this->configFile = $configFile;
    $this->parse(); 
  }

  function __get($id) 
  { 
    return $this->items[$id]; 
  }

  /**
   * \brief add all key=value item into items(array)
   *        e.g port=8088
   **/
  function parse()
  {
    $fh = fopen( $this->configFile, 'r' );
    while($line = fgets( $fh ))
    {
      if (!empty($line) && preg_match( '/^;/', $line) == false ) /* the line is not empty and and begin with ';'*/
      {
        preg_match( '/^(.*?)=(.*?)$/', $line, $found );
        if (empty($found)) continue;
        $key = trim($found[1]);
        $value= trim($found[2]);
        $this->items[$key] = $value;
      }
    }
    fclose( $fh );
  }
}
?>

