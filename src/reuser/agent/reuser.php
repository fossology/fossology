<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini, Andreas Würl

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */


use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\ClearingEventProcessor;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;

include_once(__DIR__ . "/version.php");

class ReuserAgent extends Agent
{
  const FORCE_DECISION = 1;

  /** @var int */
  private $conflictStrategyId;

  /** @var UploadDao */
  private $uploadDao;

  /** @var ClearingEventProcessor */
  private $clearingEventProcessor;

  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;

  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;

  /** @var ClearingDao */
  private $clearingDao;

  /** @var int */
  private $decisionIsGlobal = CLEARING_DECISION_IS_GLOBAL;

  /** @var DecisionTypes */
  private $decisionTypes;

  function __construct()
  {
    parent::__construct(AGENT_NAME, AGENT_VERSION, AGENT_REV);

    $args = getopt("k:", array(""));
    $this->conflictStrategyId = array_key_exists('k', $args) ? $args['k'] : NULL;

    $this->uploadDao = $this->container->get('dao.upload');

    $this->clearingDao = $this->container->get('dao.clearing');
    $this->decisionTypes = $this->container->get('decision.types');
    $this->clearingEventProcessor = $this->container->get('businessrules.clearing_event_processor');
    $this->clearingDecisionProcessor = $this->container->get('businessrules.clearing_decision_processor');
    $this->agentLicenseEventProcessor = $this->container->get('businessrules.agent_license_event_processor');
  }


  function processUploadId($uploadId)
  {
    $reusedUploadId = $this->uploadDao->getReusedUpload();

    return true;
  }

}

$agent = new ReuserAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->bail(0);