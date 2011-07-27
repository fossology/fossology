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
 * \file common-scheduler.php
 * \brief Core functions for communicating with the scheduler (v2)
 **/


/**
  \brief Connect to the scheduler.
         The scheduler IP address and port are read from fossology.conf.
         But they may be overridden with the optional parameters.

  \param $IPaddr optional IP address, default is 127.0.0.1
  \param $Port optional port, default is 5555

  \return scheduler object or false if failure to connect.  
          Currently this is the socket used to communicate
          with the scheduler.  However, it is highly discouraged to use it directly
          instead of through the functions in this file in case we need to keep
          track of other data associated with a connection.
          NOTE that failures write and error message to stdout.
          If an error occurs, ErrMsg will contain a text error message.
 **/
function fo_scheduler_connect($IPaddr='', $Port='', &$ErrMsg='')
{ 
  if (empty($IPaddr)) $IPaddr = '127.0.0.1';
  if (empty($Port)) $Port = 5555;

  if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) 
  {
    $ErrMsg = socket_strerror(socket_last_error());
    return false;
  }

  $result = socket_connect($sock, $IPaddr, $Port);
  if ($result === false) 
  {
    $ErrMsg = socket_strerror(socket_last_error());
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
  return socket_read($sock, $MaxSize, PHP_NORMAL_READ);
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
?>
