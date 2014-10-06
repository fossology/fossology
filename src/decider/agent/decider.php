<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014, Siemens AG

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

define("AGENT_NAME", "decider");

include_once(__DIR__."/version.php");

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\BusinessRules\ClearingDecisionEventProcessor;

class DeciderAgent extends Agent
{
  private $uploadTreeId;
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDecisionEventProcessor */
  private $clearingDecisionEventProcessor;
   /** @var ClearingDao */
  private $clearingDao;

  function __construct()
  {
    parent::__construct(AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $args = getopt("U:",array(""));
    $this->uploadTreeId = @$args['U'];

    global $container;
    $this->uploadDao = $container->get('dao.upload');

    $this->clearingDao = $container->get('dao.clearing');
    $this->clearingDecisionEventProcessor = $container->get('businessrules.clearing_decision_event_processor');

  }

  function decideUploadTreeId($uploadTreeId, $pfileId)
  {
    $userId = $this->userId;




    return true;
  }

  function processUploadId($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $selectQuery = "SELECT * FROM $uploadTreeTableName WHERE upload_fk = $1";
    $params = array($uploadId);
    $statementName = __METHOD__.$uploadTreeTableName;

    if ($this->uploadTreeId !== null)
    {
      list($lft, $rgt) = $this->uploadDao->getLeftAndRight($this->uploadTreeId, $uploadTreeTableName);
      $selectQuery .= " AND lft BETWEEN $2 AND $3";
      $params[] = $lft;
      $params[] = $rgt;
      $statementName .= "leftRight";
    }

    $jobId = $this->jobId;
    $jobIds = array(); //TODO;
    $decidedByMonk = $this->clearingDao->getChangedItemsBy($jobIds);

    $this->dbManager->prepare($statementName, $selectQuery);
    $queryResult = $this->dbManager->execute($statementName, $params);

    while ($uploadEntry = $this->dbManager->fetchArray($queryResult)) {
      $pfileId = $uploadEntry['pfile_fk'];
      $fileMode = $uploadEntry['ufile_mode'];
      $uploadTreeId = $uploadEntry['uploadtree_pk'];

      if (Isdir($fileMode) || Iscontainer($fileMode) || Isartifact($fileMode) || empty($pfileId))
        continue;

      if (!($this->decideUploadTreeId($uploadTreeId, $pfileId, $decidedByMonk)))
      {
        $this->dbManager->freeResult($queryResult);
        return false;
      }
      $this->heartbeat(1);
    }
    $this->dbManager->freeResult($queryResult);

    return true;
  }
}

$agent = new DeciderAgent();
$agent->scheduler_connect();
$agent->run_schedueler_event_loop();
$agent->bail(0);