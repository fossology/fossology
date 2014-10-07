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

  function processUploadId($uploadId)
  {
    return true;

    $jobId = $this->jobId;

    foreach ($this->clearingDao->getItemsChangedBy($jobId) as $uploadTreeId)
    {
      $itemTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId);
      list($added, $removed) = $this->clearingDao->getCurrentLicenseDecision($itemTreeBounds, $this->userId);
    }

    return true;
  }
}

$agent = new DeciderAgent();
$agent->scheduler_connect();
$agent->run_schedueler_event_loop();
$agent->bail(0);