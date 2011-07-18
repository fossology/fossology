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
    $V.= "<option value='status'>status</option>";
    $V.= "<option value='stop'>stop</option>";
    $V.= "<option value='pause'>pause</option>";
    $V.= "<option value='reload'>reload</option>";
    $V.= "<option value='restart'>restart</option>";
    $V.= "<option value='verbose'>verbose</option>";
    $V.= "<option value='database'>database</option>";
    $V.= "<option value='priority'>priority</option>";
    return($V);
  } // FolderListOption()

  /**
   * \brief get the job list for the operation 'status'
   * \return job list
   * \todo get the job list from DB
   **/
  function JobListOption()
  {
    /* TODO, get job list from dB */
    return $job_list_option;
  } // JobListOption()

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
    if ('ALL' != $job_id && !empty($job_id) && 'scheduler' != $job_id) $msg .= " $job_id";
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
          if (empty($this->error_info))
          {
            $status_msg .= "Operate '$operation' on the scheduler successfully.";
          }
          else   
          {
            $status_msg .= "Operate '$operation' on the scheduler failed."; 
          }
          $report .= $this->error_info;
          if (!empty($response_from_scheduler))
          {
            $report .= "<hr style='border-style:dashed'>";
            $report .= $response_from_scheduler; 
          }
          echo displayMessage($status_msg, $report); 
        }

        $V.= "List of operations on the scheduler:\n";
        $V.= "<br>";
        $V.= "<b>stop:</b> the scheduler will gracefully shutdown. Depending on what is currently running, this could take some time. \n";
        $V.= "<br>";
        $job_id_caption = htmlspecialchars('<job_id>');
        $level_caption = htmlspecialchars('<level>');
        $V.= "<b>pause $job_id_caption:</b> the scheduler will pause the specified job. Used exclusively on jobs that is running, if the job isn't running this will error. \n";
        $V.= "<br>";
        $V.= "<b>reload:</b> the scheduler will reload the configuration information for the agents and hosts. \n";
        $V.= "<br>";
        $V.= "<b>status: </b> this will get scheduler status and simple status for each job as the following:<br>";
        $V.= "# scheduler:[#] daemon:[#] jobs:[#] log:[str] port:[#] verbose:[#]<br>";
        $V.= "# job:[#] status:[str] type:[str] priority:[#] running:[#] finished[#] failed:[#]<br>";
        $V.= ".<br># end";
        $V.= "<br>";
        $V.= "<b>status $job_id_caption:</b> this will get simple status for the specified job and the status of every agent belonging to the specified job as the following:<br>";
        $V.= "# job:[#] status:[str] type:[str] priority:[#] running:[#] finished[#] failed:[#]<br>";
        $V.= "# agent:[#] host:[str] type:[str] status:[str] time:[str]<br>";
        $V.= ".<br># end";
        $V.= "<br>";
        $V.= "<b>restart $job_id_caption:</b> the scheduler will restart the specified job. Used exclusively on jobs that have been paused, if the job isn't paused this will error.<br>";
        $V.= "<b>verbose $level_caption:</b> this will change the verbose level of the scheduler.<br>";
        $V.= "<b>verbose $job_id_caption $level_caption:</b> this will change the the verbose level for all of the agents belonging to specified job.<br>";
        $V.= "level 1: FATAL, ERROR, WARNING, and DEBUG messages from agents; FATAL, ERROR, WARNING messages from scheduler.<br>";
        $V.= "level 2: All files that are loaded for agent information; All agent spawning and death information; All Job creation information; All messages received from user interfaces (GUI and CLI). including level 1.<br>";
        $V.= "level 3: All host, agent and scheduler configuration information when loaded; All agent communications (send and receive); All job update information; All agent status changes. including level 1-2.<br>";
        $V.= "<b>database:</b> the scheduler will check the database job queue for new jobs.<br>";
        $V.= "<b>priority $job_id_caption $level_caption:</b> the scheduler will change the priority for the specified job, the range of nice values is -20..20(19)<br>";
        $V.= "<hr>";

        $text = _("Select the operation on the scheduler:");
        $V.= "<form id='operation_form' method='post'>";
        $V.= "<p><li>$text\n";
        $V.= "<select name='operation' id='operation' onchange='OperationSwich_Get(\"" .Traceback_uri() . "?mod=ajax_admin_scheduler&operation=\"+this.value)'<br />\n";
        $V.= $this->OperationListOption();
        $V.= "</select>\n"; 
        $V.= "<br><br>";
        $V.= "<div id='div_operation'>";
        $text = _("Please select one job:");
        $V.= "$text<select name='job_list' id='job_list'>";
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

