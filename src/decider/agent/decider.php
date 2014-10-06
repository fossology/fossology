<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014, Siemens AG

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

define("ALARM_SECS", 30);
define("AGENT_NAME", "decider");
define("AGENT_DESC", "decider agent");
define("AGENT_ARS", AGENT_NAME . "_ars");

/**
 * \file decider.php
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

  $GLOBALS['agentId'] = queryAgentId(AGENT_NAME, AGENT_DESC);

  initArsTable(AGENT_ARS);
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

function createArsTable($tableName)
{
  global $dbManager;

  $dbManager->queryOnce("CREATE TABLE ".$tableName."() INHERITS(ars_master);
  ALTER TABLE ONLY ".$tableName." ADD CONSTRAINT ".$tableName."_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES agent(agent_pk);
  ALTER TABLE ONLY ".$tableName." ADD CONSTRAINT ".$tableName."_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES upload(upload_pk) ON DELETE CASCADE");
}

function initArsTable($tableName)
{
  if (!DB_TableExists($tableName)) {
    createArsTable($tableName);
  };
}

function writeArsRecord($arsTableName,$uploadId,$arsId=0,$success=false,$status="")
{
  global $dbManager;
  global $agentId;

  if ($arsId) {
    $dbManager->queryOnce("UPDATE $arsTableName SET ars_success='".($success ? "t" : "f")."', ars_endtime=now() ".(
      !empty($status) ? ", ars_status = $status" : ""
      )." WHERE ars_pk = $arsId");
  } else {
    $row = $dbManager->getSingleRow("INSERT INTO $arsTableName(agent_fk,upload_fk) VALUES ($agentId,$uploadId) RETURNING ars_pk");
    if ($row !== false)
    {
      return $row['ars_pk'];
    }
  }
}

function queryAgentId($agentName, $agentDesc)
{
  global $dbManager;

  $row = $dbManager->getSingleRow("SELECT agent_pk FROM agent WHERE agent_name = $1 order by agent_ts desc limit 1", array($agentName), __METHOD__."select");

  if ($row === false)
  {
    $row = $dbManager->getSingleRow("INSERT INTO agent(agent_name,agent_desc) VALUES ($1,$2) RETURNING agent_pk", array($agentName, $agentDesc), __METHOD__."insert");
    return $row['agent_pk'];
  }

  return $row['agent_pk'];
}

function processUploadId($uploadId)
{
  global $userId;
  global $userName;

  global $dbManager;

  $count = $dbManager->getSingleRow("SELECT count(*) AS count FROM uploadtree WHERE upload_fk = $1",
  array($uploadId));

  heartbeat($count['count']);

  return true;
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
  {
    $arsId = writeArsRecord(AGENT_ARS, $uploadId);
    $success = processUploadId($uploadId);
    writeArsRecord(AGENT_ARS, $uploadId, $arsId, $success);
  }
}

bail(0);