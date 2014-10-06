<?php

namespace Fossology\Lib\Agent;

use Fossology\Lib\Util\Object;
use Fossology\Lib\Db\DbManager;

require_once(dirname(dirname(__FILE__))."/common-cli.php");

define("ALARM_SECS", 30);

class Agent extends Object
{
  private $version;
  private $agentName;
  private $agentDesc;
  private $agentArs;
  private $agentId;

  protected $userId;

  /** @var DbManager dbManager */
  protected $dbManager;

  private $isConnected;

  function __construct($agentName, $version) {
    $this->agentName = $agentName;
    $this->version = $version;
    $this->agentDesc = $agentName. " agent";
    $this->agentArs = $agentName . "_ars";
    $this->isConnected = false;

    $GLOBALS['processed'] = 0;
    $GLOBALS['alive'] = false;

    /* initialize the environment */
    cli_Init();

    global $container;
    $this->dbManager = $container->get('db.manager');

    $this->agentId = $this->queryAgentId();
  }

  function scheduler_connect()
  {
    $args = getopt("scheduler_start", array("userID:"));

    if (array_key_exists('userId', $args))
      $this->userId = $args['userID'];
    else
      $this->userId = null;

    $this->initArsTable();

    $this->scheduler_greet();

    pcntl_signal(SIGALRM, function($signo) { Agent::heartbeat_handler($signo); });
    pcntl_alarm(ALARM_SECS);

    $this->isConnected = true;
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
    global $processed;
    global $alive;

    $processed += $newProcessed;

    $alive = true;
    pcntl_signal_dispatch();
  }

  function bail($exitvalue)
  {
    Agent::heartbeat_handler(SIGALRM);
    echo "BYE $exitvalue\n";
    exit($exitvalue);
  }

  function scheduler_greet()
  {
    echo "VERSION: ".$this->version."\n";
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
    if (!DB_TableExists($this->agentArs)) {
      $this->createArsTable();
    };
  }

  function writeArsRecord($uploadId,$arsId=0,$success=false,$status="")
  {

    $arsTableName = $this->agentArs;
    if ($arsId) {
      $this->dbManager->queryOnce("UPDATE $arsTableName SET ars_success='".($success ? "t" : "f")."', ars_endtime=now() ".(
        !empty($status) ? ", ars_status = $status" : ""
        )." WHERE ars_pk = $arsId");
    } else {
      $row = $this->dbManager->getSingleRow("INSERT INTO $arsTableName(agent_fk,upload_fk) VALUES (".$this->agentId.",$uploadId) RETURNING ars_pk");
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
      $row = $this->dbManager->getSingleRow("INSERT INTO agent(agent_name,agent_desc) VALUES ($1,$2) RETURNING agent_pk", array($this->agentName, $this->agentDesc), __METHOD__."insert");
      return $row['agent_pk'];
    }

    return $row['agent_pk'];
  }

  function processUploadId($uploadId){
    return false;
  }

  function run_schedueler_event_loop(){
    if (!$this->isConnected)
      return false;

    while (false !== ($line = fgets(STDIN)))
    {
      if ("CLOSE\n" === $line)
      {
        $this->bail(0);
      }
      if ("END\n" === $line)
      {
        $this->bail(0);
      }

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
