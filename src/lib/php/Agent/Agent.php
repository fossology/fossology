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
use Fossology\Lib\Dao\AgentDao;
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

  /** @var Agent agentDao */
  protected $agentDao;

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
    $this->agentDao = $container->get('dao.agent');

    $this->agentId = $this->agentDao->getCurrentAgentId($this->agentName, $this->agentDesc, $this->agentRev);
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

  function initArsTable()
  {
    if (!$this->agentDao->arsTableExists($this->agentName)) {
      $this->agentDao->createArsTable($this->agentName);
    }
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
        $arsId = $this->agentDao->writeArsRecord($this->agentName, $this->agentId, $uploadId);

        if ($arsId<0) {
          print "cannot insert ars record";
          $this->bail(2);
        }

        try {
          $success = $this->processUploadId($uploadId);
        } catch(\Exception $e) {
          print "Caught exception while processing uploadId=$uploadId: ".$e->getMessage();
          print "";
          print $e->getTraceAsString();
          $success = false;
        }

        $this->agentDao->writeArsRecord($this->agentName, $this->agentId, $uploadId, $arsId, $success);

        if (!$success) {
          print "agent failed on uploadId=$uploadId";
          $this->bail(1);
        }
      }

      $this->heartbeat(0);
    }
  }
}
