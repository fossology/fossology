<?php
/***********************************************************
 Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
***********************************************************/

/**
 * \file common-scheduler.php
 * \brief Core functions for communicating with the scheduler (v2)
 **/


/**
  \brief Connect to the scheduler.
         The scheduler IP address and port are read from fossology.conf.
         But they may be overridden with the optional parameters.

  \param $IPaddr optional IP address, default is 127.0.0.1
  \param $Port optional port, default is 5555
  \param $&$ErrorMsg the error esg is stored

  \return scheduler object or false if failure to connect.  
          Currently this is the socket used to communicate
          with the scheduler.  However, it is highly discouraged to use it directly
          instead of through the functions in this file in case we need to keep
          track of other data associated with a connection.
          NOTE that failures write and error message to stdout.
 **/
function fo_scheduler_connect($IPaddr='', $Port='', &$ErrorMsg)
{ 
  if (empty($IPaddr)) $IPaddr = '127.0.0.1';
  if (empty($Port)) $Port = 5555;
  if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) 
  {
    $ErrorMsg = "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "<br>\n";
    return false;
  }

  $result = @socket_connect($sock, $IPaddr, $Port);
  if ($result === false) 
  {
    $ErrorMsg = "Connection to the scheduler failed.  Is the scheduler running?<br>";
    $ErrorMsg .= "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($sock)) . "<br>\n";
    return false;
  } 
  return $sock;
} // fo_scheduler_connect()


/**
  \brief Read the scheduler socket.

  \param $SchedObj - scheduler object (currently the socket)
  \param $MaxSize -  optional max read size, default is 2048

  \return message from scheduler
 **/
function fo_scheduler_read($SchedObj, $MaxSize=2048)
{
  return socket_read($SchedObj, $MaxSize, PHP_NORMAL_READ);
} // fo_scheduler_read()


/**
  \brief Write to the scheduler socket.

  \param $SchedObj - scheduler object (currently the socket)
  \param $msg - Message to write to scheduler

  \return Number of bytes successfully written or false on failure.
          The error code can be retrieved with socket_last_error() 
          and passed to socket_strerror() for a text explanation.
 **/
function fo_scheduler_write($SchedObj, $msg)
{
  return socket_write($SchedObj, $msg, strlen($msg));
} // fo_scheduler_write()


/**
  \brief Close the scheduler connection (socket).

  \param $SchedObj - scheduler object (currently the socket)
  \return void
 **/
function fo_scheduler_close($SchedObj)
{
  socket_close($SchedObj);
} // fo_scheduler_close()

/**
  \brief communicate with scheduler, send commands to the scheduler, then get the output

  \param $input - the command that you want to send to the scheduler
                  now the commands include: stop, pause <job_id>, agents, reload, status, status <job_id>,
                  restart <job_id>, verbose <level>, verbose <job_id> <level>, database, priority <job_id> <level>
  \param &$output - save the output from the schduler,
                   when received, that means this communication with the scheduler is over
  \param &$error_msg - save the error message

  \return true on sucess, false on failure
 **/
function fo_communicate_with_scheduler($input, &$output, &$error_msg)
{
  global $SysConf;

  $address = $SysConf['FOSSOLOGY']['address'];
  $port =  $SysConf['FOSSOLOGY']['port'];
  $response_from_scheduler;
  $sock = fo_scheduler_connect($address, $port, $error_msg); /* Connect to the scheduler */
  if ($sock)
  {
    $msg = trim($input);
    $write_result = fo_scheduler_write($sock, $msg);
    if ($write_result)
    {
      while ($buf = fo_scheduler_read($sock))
      {
        /* when get all response from the scheduler for the command 'status' or 'status <job_id>', or 'agents' 
           will get a string 'end' */
        if (substr($buf, 0, 3) == "end") break; 
        if (substr($buf, 0, 8) == "received") /* get a string 'received'*/
        {
          /* 1. if the command is not 'status'or 'status <job_id>' or 'agents', when receiving 
                a string 'received', that mean this communication is over.
             2. if the command is 'status'or 'status <job_id>' or 'agents', first receiving
                a string 'received', then will receive related response.
                then a string 'end' as ending.
           */
          if (substr($input, 0, 6) != "status" && substr($input, 0, 6) != "agents")
            break; 
        }
        else /* do not save the symbol string 'received' as the output, they are just symbols */
        {
          $output .= "$buf<br>";
        }
      }
    }
    else
    {
      $error_msg = socket_strerror(socket_last_error($sock));
    }
    fo_scheduler_close($sock);
  }

  /* failed to communicate with the scheduler */
  if (empty($error_msg))
  {
    return true;
  } 
  else  /* communicate with the scheduler successfully */
  {
    return false;
  }
} // fo_communicate_with_scheduler()

/**
 * \brief  Get runnable job list, the process is below:
           1. send command 'status'to scheduler
           2. the scheduler return status of all scheduled jobs
           3. retrieve the job list
           4. return the job list 

 * \return an array, the runnable job list
           the array is like: Array(1, 2, 3, .., i), sorted, if no jobs, return nothing
 */
function GetRunnableJobList()
{
  /* get the raw job list from scheduler 
     send command 'status' to the scheduler, get the all status of runnable jobs and scheduler 
     like:
      scheduler:[#] daemon:[#] jobs:[#] log:[str] port:[#] verbose:[#]
      job:[#] status:[str] type:[str] priority:[#] running:[#] finished[#] failed:[#]
      job:[#] status:[str] type:[str] priority:[#] running:[#] finished[#] failed:[#]
   */
  $command = "status";
  $command_status = fo_communicate_with_scheduler($command, $status_info, $error_msg);
  /* can not get status info from the scheduler, so can not get runnable jobs, probably the scheduler is not running */
  if (false === $command_status) return ; 
  $pattern = '/job:(\d+) /';
  preg_match_all($pattern, $status_info, $matches);
  /* the $matches[1] is like: Array(1, 2, 3, .., i)  */
  $job_array = $matches[1];
  sort($job_array, SORT_NUMERIC);
  return $job_array;
} // GetRunnableJobList()

?>
