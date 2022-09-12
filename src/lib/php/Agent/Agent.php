<?php
/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @dir
 * @brief Library functions for agents
 * @file
 * @brief Library functions for agents
 */

/**
 * @namespace Fossology::Lib::Agent
 * Contains utility functions required by agents based on PHP language
 */
namespace Fossology\Lib\Agent;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\AgentDao;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once(dirname(dirname(__FILE__))."/common-cli.php");

/**
 * @var int ALARM_SECS
 * Number of seconds to wait for pcntl_alarm() alarm
 */
define("ALARM_SECS", 30);

/**
 * @class Agent
 * @brief Structure of an Agent with all required parameters.
 *
 * All PHP language based agents should inherit from this class.
 */
abstract class Agent
{
  /** @var string $agentName
   * Name of the agent */
  private $agentName;
  /** @var string $agentVersion
   * Version of the agent */
  private $agentVersion;
  /** @var string $agentRev
   * Agent revision */
  private $agentRev;
  /** @var string $agentDesc
   * Agent description (displayed in UI) */
  private $agentDesc;
  /** @var string $agentArs
   * Agent ARS table name */
  private $agentArs;
  /** @var int $agentId
   * Agent ID (from DB) */
  private $agentId;

  /** @var int $userId
   * Current user ID */
  protected $userId;
  /** @var int $groupId
   * Current group ID */
  protected $groupId;
  /** @var int $jobId
   * Job ID for the agent to work on */
  protected $jobId;

  /**
   * @var string $agentSpecifOptions
   * Agent specific CLI options (used for communication with scheduler)
   */
  protected $agentSpecifOptions = "";
  /**
   * @var array $agentSpecifLongOptions
   * Agent specific CLI long options (used for communication with scheduler)
   */
  protected $agentSpecifLongOptions = array();

  /** @var array $args
   * Arguments value (from CLI) map for current agent */
  protected $args = array();

  /** @var DbManager $dbManager
   * DB manager used by agent */
  protected $dbManager;

  /** @var AgentDao $agentDao
   * Agent DAO object */
  protected $agentDao;

  /** @var ContainerBuilder $container
   * Symfony DI container */
  protected $container;

  /** @var bool $schedulerMode
   * Running in scheduler mode or standalone */
  protected $schedulerMode;

  /**
   * Constructor for Agent class
   * @param string $agentName Name of the agent
   * @param string $version   Version of the agent
   * @param string $revision  Revision of the agent
   */
  function __construct($agentName, $version, $revision)
  {
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

  /**
   * @brief Connect with scheduler and initialize options.
   *
   * This function reads arguments passed from the CLI to the agent and
   * initialize parameters according to them. It also greets the scheduler
   * and setup signal alarms to keep connection active.
   * @sa scheduler_greet()
   */
  function scheduler_connect()
  {

    // echo("scheduler_connect begin\n");
    

    $schedulerHandledOpts = "c:";
    $schedulerHandledLongOpts = array("userID:","groupID:","jobId:","scheduler_start",'config:');

    $longOpts = array_merge($schedulerHandledLongOpts, $this->agentSpecifLongOptions);
    $shortOpts = $schedulerHandledOpts . $this->agentSpecifOptions;


    


    $args = getopt($shortOpts, $longOpts);



    $this->schedulerMode = (array_key_exists("scheduler_start", $args));

    $this->userId = $args['userID'];
    $this->groupId = $args['groupID'];
    $this->jobId = $args['jobId'];

    unset ($args['jobId']);
    unset ($args['userID']);
    unset ($args['groupID']);

    $this->initArsTable();

    if ($this->schedulerMode) {
      $this->scheduler_greet();

      pcntl_signal(SIGALRM, function($signo)
      {
        Agent::heartbeat_handler($signo);
      });
      pcntl_alarm(ALARM_SECS);
    }

    $this->args = $args;

    // echo("longOpts\n");
    // echo(json_encode($longOpts));
    // echo("shortOpts\n");
    // echo(json_encode($shortOpts));

    // echo("args\n");
    // echo(json_encode($args));
  }

  /**
   * @brief Function to handle hear beats from the agent and send them to the
   * scheduler from STDOUT.
   *
   * The function reads from global parameters `processed` and `alive` to know
   * the state of the agent. `processed` contains the number of items processed
   * by the agent and `alive` flag have to be reset to `TRUE` by the agent to
   * signal its status. The function resets the `alive` flag to `FALSE`.
   * @param int $signo Interrupt signal.
   */
  static function heartbeat_handler($signo)
  {
    global $processed;
    global $alive;

    echo "HEART: $processed ".($alive ? '1' : '0')."\n";
    $alive = false;
    pcntl_alarm(ALARM_SECS);
  }

  /**
   * @brief Send hear beat to the scheduler.
   *
   * If the agent is running in scheduler mode, it will dispatch the heart beat
   * signal for the scheduler. This signal is handled by heartbeat_handler() .
   *
   * The function set the global `processed` variable and `alive` variable.
   * @param int $newProcessed Number of items processed since last call.
   */
  function heartbeat($newProcessed)
  {
    if ($this->schedulerMode) {
      global $processed;
      global $alive;

      $processed += $newProcessed;

      $alive = true;
      pcntl_signal_dispatch();
    }
  }

  /**
   * @brief Bail the agent, print the stack and disconnect from scheduler.
   * @param int $exitvalue Exit value to sent to scheduler
   * @throws \Exception
   */
  function bail($exitvalue)
  {
    debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $this->scheduler_disconnect($exitvalue);
    throw new \Exception('agent fail in '.__FILE__.':'.__LINE__,$exitvalue);
  }

  /**
   * @brief Closes connection from scheduler.
   *
   * The function sends a `BYE <exit-value>` to the scheduler to close the
   * connection along with final heart beat only if the agent is running in
   * scheduler mode.
   * @param int $exitvalue Exit value to sent to scheduler
   */
  function scheduler_disconnect($exitvalue)
  {
    if ($this->schedulerMode) {
      Agent::heartbeat_handler(SIGALRM);
      echo "BYE $exitvalue\n";
    }
  }

  /**
   * @brief Greet the scheduler at the beginning of connection.
   *
   * The function sends the agent version and an `OK` message through STDOUT.
   */
  function scheduler_greet()
  {
    echo "VERSION: ".$this->agentVersion."\n";
    echo "OK\n";
  }

  /**
   * @brief Initialize ARS table
   */
  function initArsTable()
  {
    if (!$this->agentDao->arsTableExists($this->agentName)) {
      $this->agentDao->createArsTable($this->agentName);
    }
  }

  /**
   * @brief Given an upload ID, process the items in it.
   *
   * This function is implemented by agent and should call heartbeat() at
   * regular intervals.
   * @param int $uploadId Upload to be processed by the agent.
   */
  abstract protected function processUploadId($uploadId);

  /**
   * @brief Read the commands from scheduler.
   *
   * Read the commands sent from scheduler (from STDIN). The function returns
   * `FALSE` if the connection is closed by the scheduler (received `CLOSE` or
   * `END`) otherwise the command received.
   * @return boolean|string Command from scheduler (FALSE if CLOSE or END is
   * received).
   */
  private function scheduler_current()
  {
    ($line = fgets(STDIN));
    if ("CLOSE\n" === $line) {
      return false;
    }
    if ("END\n" === $line) {
      return false;
    }

    return $line;
  }

  /**
   * @brief Runs a loop to read commands from scheduler and process them.
   *
   * The function loops till scheduler_current() returns `FALSE` (end of
   * connection).
   *
   * The flow of the function:
   * -# Send new heat beat to the scheduler.
   * -# Extract upload id from the scheduler.
   * -# Write the ARS record.
   * -# Process the upload using processUploadId().
   * -# Write processed ARS record.
   * -# Loop back and read for next command from scheduler.
   */
  function run_scheduler_event_loop()
  {
    while (false !== ($line = $this->scheduler_current())) {
      $this->heartbeat(0);

      $uploadId = intval($line);
      if ($uploadId <= 0) {
        continue;
      }

      $arsId = $this->agentDao->writeArsRecord($this->agentName, $this->agentId, $uploadId);
      if ($arsId<0) {
        print "cannot insert ars record";
        $this->bail(2);
      }

      try {
        $success = $this->processUploadId($uploadId);
      } catch(\Exception $e) {
        print "Caught exception while processing uploadId=$uploadId: ".$e->getMessage();
        print $e->getTraceAsString();
        $success = false;
      }

      $this->agentDao->writeArsRecord($this->agentName, $this->agentId, $uploadId, $arsId, $success);

      if (!$success) {
        print "agent failed on uploadId=$uploadId";
        $this->bail(1);
      }
    }
  }
}
