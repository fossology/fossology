<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014, Siemens AG

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Fossology\Lib\Agent;

use Fossology\Lib\Util\Object;
use Fossology\Lib\Db\DbManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once(dirname(dirname(__FILE__))."/common-cli.php");

define("ALARM_SECS", 30);

abstract class Agent extends Object
{
  private $agentName;
  private $agentVersion;
  private $agentRev;
  private $agentDesc;
  private $agentArs;
  private $agentId;

  protected $userId;
  protected $groupId;
  protected $jobId;
  
  protected $schedulerHandledOpts = "c:";
  protected $schedulerHandledLongOpts = array("userID:","groupID:","jobId:","scheduler_start");

  /** @var DbManager dbManager */
  protected $dbManager;

  /** @var ContainerBuilder */
  protected $container;

  private $schedulerMode;

  function __construct($agentName, $version, $revision) {
    $this->agentName = $agentName;
    $this->agentVersion = $version;
    $this->agentDesc = $agentName. " agent";
    $this->agentRev = $version.".".$revision;
    $this->agentArs = strtolower( $agentName ) . "_ars";
    $this->schedulerMode = false;

    $GLOBALS['processed'] = 0;
    $GLOBALS['alive'] = false;

    /* initialize the environment */
    cli_Init();

    global $container;
    $this->container = $container;
    $this->dbManager = $container->get('db.manager');

    $this->agentId = $this->queryAgentId();
  }

  function scheduler_connect()
  {
    $args = getopt($this->schedulerHandledOpts, $this->schedulerHandledLongOpts);

    $this->schedulerMode = (array_key_exists("scheduler_start", $args));

    $this->userId = $args['userID'];
    $this->groupId = $args['groupID'];
    $this->jobId = $args['jobId'];

    $this->initArsTable();

    if ($this->schedulerMode)
    {
      $this->scheduler_greet();

      pcntl_signal(SIGALRM, function($signo) { Agent::heartbeat_handler($signo); });
      pcntl_alarm(ALARM_SECS);
    }
  }

  static function heartbeat_handler($signo)
  {
    global $processed;
    global $alive;

    echo "HEART: ".$processed." ".($alive ? 1 : 0)."\n";
    $alive = false;
    pcntl_alarm(ALARM_SECS);
  }

  function heartbeat($newProcessed)
  {
    if ($this->schedulerMode)
    {
      global $processed;
      global $alive;

      $processed += $newProcessed;

      $alive = true;
      pcntl_signal_dispatch();
    }
  }

  function bail($exitvalue)
  {
    debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $this->scheduler_disconnect($exitvalue);
    exit($exitvalue);
  }

  function scheduler_disconnect($exitvalue)
  {
    if ($this->schedulerMode)
    {
      Agent::heartbeat_handler(SIGALRM);
      echo "BYE $exitvalue\n";
    }
  }

  function scheduler_greet()
  {
    echo "VERSION: ".$this->agentVersion."\n";
    echo "OK\n";
  }

  function createArsTable()
  {
    $tableName = $this->agentArs;

    $this->dbManager->queryOnce("CREATE TABLE ".$tableName."() INHERITS(ars_master);
    ALTER TABLE ONLY ".$tableName." ADD CONSTRAINT ".$tableName."_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES agent(agent_pk);
    ALTER TABLE ONLY ".$tableName." ADD CONSTRAINT ".$tableName."_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES upload(upload_pk) ON DELETE CASCADE");
  }

  function initArsTable()
  {
    if (!$this->dbManager->existsTable($this->agentArs)) {
      $this->createArsTable();
    }
  }

  function writeArsRecord($uploadId,$arsId=0,$success=false,$status="")
  {
    $arsTableName = $this->agentArs;
    if ($arsId)
    {
      $successDb = $this->dbManager->booleanToDb($success);
      $parms = array($successDb, $arsId);

      $stmt = __METHOD__.".$arsTableName";

      if (!empty($status)) {
        $stmt .= ".status";
        $parms[] = $status;
        $statusClause = ", ars_status = $".count($parms);
      }
      else 
      {
        $statusClause = "";
      }

      $this->dbManager->getSingleRow(
              "UPDATE $arsTableName
              SET ars_success=$1,
                  ars_endtime=now() $statusClause
              WHERE ars_pk = $2",
              $parms, $stmt);
    } else
    {
      $row = $this->dbManager->getSingleRow(
              "INSERT INTO $arsTableName(agent_fk,upload_fk)
               VALUES ($1,$2) RETURNING ars_pk",
              array($this->agentId, $uploadId),
              __METHOD__.".update.".$arsTableName);
      if ($row !== false)
      {
        return $row['ars_pk'];
      }
    }

    return -1;
  }

  function queryAgentId()
  {
    $row = $this->dbManager->getSingleRow("SELECT agent_pk FROM agent WHERE agent_name = $1 order by agent_ts desc limit 1", array($this->agentName), __METHOD__."select");

    if ($row === false)
    {
      $row = $this->dbManager->getSingleRow("INSERT INTO agent(agent_name,agent_desc,agent_rev) VALUES ($1,$2,$3) RETURNING agent_pk", array($this->agentName, $this->agentDesc, $this->agentRev), __METHOD__."insert");
      return $row['agent_pk'];
    }

    return $row['agent_pk'];
  }

  abstract protected function processUploadId($uploadId);

  private function scheduler_current()
  {
    ($line = fgets(STDIN));
    if ("CLOSE\n" === $line)
    {
      return false;
    }
    if ("END\n" === $line)
    {
      return false;
    }

    return $line;
  }

  function run_scheduler_event_loop()
  {
    while (false !== ($line = $this->scheduler_current()))
    {
      $uploadId = intval($line);

      if ($uploadId > 0)
      {
        $arsId = $this->writeArsRecord($uploadId);

        if ($arsId<0)
          $this->bail(2);

        $success = $this->processUploadId($uploadId);
        $this->writeArsRecord($uploadId, $arsId, $success);
        if (!$success)
          $this->bail(1);
      }

      $this->heartbeat(0);
    }
  }
}
