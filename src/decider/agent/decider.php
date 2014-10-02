<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014, Siemens AG

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

define("ALARM_SECS", 30);

/**
 * \file cp2foss.php // TODO
 */

/**
 * include common-cli.php directly, common.php can not include common-cli.php
 * becuase common.php is included before UI_CLI is set
 */
require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();


function init()
{
  $GLOBALS['processed'] = 0;
  $GLOBALS['alive'] = false;

  $args = getopt("scheduler_start", array("userID:"));

  $userId = $args['userID'];
  $GLOBALS['userId'] = $userId;

  global $container;

  $dbManager = $container->get('db.manager');
  $GLOBALS['dbManager'] = $dbManager;

  $res = $dbManager->getSingleRow("SELECT user_name FROM users WHERE user_pk = $1", array($userId));


  $GLOBALS['userName'] = $res['user_name'];
}

function heartbeat_handler($signo)
{
  global $processed;
  global $alive;
  echo "HEART: ".$processed." ".($alive ? 1 : 0)."\n";
  $alive = false;
  pcntl_alarm(ALARM_SECS);
}

function heartbeat($newProcessed)
{
  global $processed;
  global $alive;

  $processed += $newProcessed;
  $alive = true;
  pcntl_signal_dispatch();
}

pcntl_signal(SIGALRM, "heartbeat_handler");
pcntl_alarm(ALARM_SECS);

function bail($exitvalue)
{
  heartbeat_handler(SIGALRM);
  echo "BYE $exitvalue\n";
  exit($exitvalue);
}

function greet()
{
  global $VERSION;

  echo "VERSION: $VERSION\n";
  echo "OK\n";
}

function processUploadId($uploadId)
{
  global $userId;
  global $userName;

  global $dbManager;

  $count = $dbManager->getSingleRow("SELECT count(*) AS count FROM uploadtree WHERE upload_fk = $1",
  array($uploadId));

  heartbeat($count['count']);
}

greet();
init();



while (false !== ($line = fgets(STDIN)))
{
  if ("CLOSE\n" === $line)
  {
    bail(0);
  }
  if ("END\n" === $line)
  {
    bail(0);
  }

  $uploadId = intval($line);

  if ($uploadId > 0)
    processUploadId($uploadId);
}

bail(0);