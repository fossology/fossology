<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014, Siemens AG

 This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

define("AGENT_NAME", "decider");

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\ClearingDecisionEventProcessor;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionResult;
use Fossology\Reportgen\CopyClearedGetter;
use Fossology\Reportgen\EccClearedGetter;
use Fossology\Reportgen\IpClearedGetter;
use Fossology\Reportgen\LicenseClearedGetter;
use Fossology\Reportgen\XpClearedGetter;

define("CLEARING_DECISION_IS_GLOBAL", false);

include_once(__DIR__ . "/version.php");

class ReadmeOssAgent extends Agent
{

  /** @var  LicenseClearedGetter  */
  private $licenseClearedGetter;

  /** @var CopyClearedGetter */
  private $cpClearedGetter;
  /** @var IpClearedGetter */
  private $ipClearedGetter;
  /** @var EccClearedGetter */
  private $eccClearedGetter;

  /** @var DecisionTypes */
  private $decisionTypes;

  function __construct()
  {
    $this->cpClearedGetter = new CopyClearedGetter();
    $this->ipClearedGetter = new IpClearedGetter();
    $this->eccClearedGetter = new EccClearedGetter();
    $this->licenseClearedGetter = new LicenseClearedGetter();

    parent::__construct(AGENT_NAME, AGENT_VERSION, AGENT_REV);


  }



  function processUploadId($uploadId)
  {

    $userId = $this->userId;

    $licenses  = $this->licenseClearedGetter->getCleared($uploadId, $userId);
    $copyrights = $this->cpClearedGetter->getCleared($uploadId,$userId);
    $ecc  = $this->eccClearedGetter ->getCleared($uploadId,$userId);
    $ip  = $this->ipClearedGetter->getCleared($uploadId,$userId);

    $this->generateReport($licenses,$copyrights,$ecc,$ip);

    return true;
  }

  private function generateReport($licenses, $copyrights, $ecc, $ip)
  {
    var_dump($licenses);
    var_dump($copyrights);
  }


}

$agent = new ReadmeOssAgent();
$agent->scheduler_connect();
$agent->run_scheduler_event_loop();
$agent->bail(0);