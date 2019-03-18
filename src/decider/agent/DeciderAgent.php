<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014-2018, Siemens AG

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
/**
 * @file DeciderAgent.php
 * @brief Decider agent
 * @page decider Decider Agent
 * @tableofcontents
 * @section deciderabout About decider agent
 * While uploading a new package user has an option to auto conclude a license
 * on a file in the package based on following conditions:
 * - If all Nomos findings are within the Monk findings
 *
 *   Auto conclude if Nomos and Monk have same findings on the file
 * - If all Ninka findings are within the Monk findings
 *
 *   Auto conclude if Ninka and Monk have same findings on the file
 * - If Nomos, Monk and Ninka find the same license
 *
 *   Auto conclude if all Nomos, Ninka and Monk have same findings on the file
 * - Bulk phrases from reused packages
 *
 *   Auto conclude if same phrases found in reused package's bulk scans
 * - New scanner results
 *
 *   Decisions were marked as work in progress in reused upload
 *   and new scanner finds additional licenses.
 *
 * @section decidersource Agent source
 *   - @link src/decider/agent @endlink
 *   - @link src/decider/ui @endlink
 *   - Functional test cases @link src/decider/agent_tests/Functional @endlink
 *   - Unit test cases @link src/decider/agent_tests/Unit @endlink
 */
/**
 * @namespace Fossology::Decider
 * @brief Namespace for decider agent
 */
namespace Fossology\Decider;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\AgentLicenseEventProcessor;
use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\Tree\Item;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Dao\ShowJobsDao;

include_once(__DIR__ . "/version.php");

/**
 * @class DeciderAgent
 * @brief Agent to decide license findings in an upload
 */
class DeciderAgent extends Agent
{
  const RULES_NOMOS_IN_MONK = 0x1;
  const RULES_NOMOS_MONK_NINKA = 0x2;
  const RULES_BULK_REUSE = 0x4;
  const RULES_WIP_SCANNER_UPDATES = 0x8;
  const RULES_ALL = 0xf; // self::RULES_NOMOS_IN_MONK | self::RULES_NOMOS_MONK_NINKA | ... -> feature not available in php5.3

  /** @var int $activeRules
   * Rules active for upload (nomos in monk; ninka in monk; nomos, ninka and monk)
   */
  private $activeRules;
  /** @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;
  /** @var ClearingDecisionProcessor $clearingDecisionProcessor
   * ClearingDecisionProcessor object
   */
  private $clearingDecisionProcessor;
  /** @var AgentLicenseEventProcessor $agentLicenseEventProcessor
   * AgentLicenseEventProcessor object
   */
  private $agentLicenseEventProcessor;
  /** @var ClearingDao $clearingDao
   * ClearingDao object
   */
  private $clearingDao;
  /** @var HighlightDao $highlightDao
   * HighlightDao object
   */
  private $highlightDao;
  /** @var ShowJobsDao $showJobsDao
   * ShowJobsDao object
   */
  private $showJobsDao;
  /** @var DecisionTypes $decisionTypes
   * DecisionTypes object
   */
  private $decisionTypes;
  /** @var LicenseMap $licenseMap
   * LicenseMap object
   */
  private $licenseMap = null;
  /** @var int $licenseMapUsage
   * licenseMapUsage
   */
  private $licenseMapUsage = null;

  function __construct($licenseMapUsage=null)
  {
    parent::__construct(AGENT_DECIDER_NAME, AGENT_DECIDER_VERSION, AGENT_DECIDER_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->highlightDao = $this->container->get('dao.highlight');
    $this->showJobsDao = $this->container->get('dao.show_jobs');
    $this->decisionTypes = $this->container->get('decision.types');
    $this->clearingDecisionProcessor = $this->container->get('businessrules.clearing_decision_processor');
    $this->agentLicenseEventProcessor = $this->container->get('businessrules.agent_license_event_processor');

    $this->licenseMapUsage = $licenseMapUsage;
    $this->agentSpecifOptions = "r:";
  }

  /**
   * @copydoc Fossology::Lib::Agent::Agent::processUploadId()
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $args = $this->args;
    $this->activeRules = array_key_exists('r', $args) ? intval($args['r']) : self::RULES_ALL;
    $this->licenseMap = new LicenseMap($this->dbManager, $this->groupId, $this->licenseMapUsage);

    if (array_key_exists("r", $args) && (($this->activeRules&self::RULES_BULK_REUSE)== self::RULES_BULK_REUSE))
    {
      $bulkReuser = new BulkReuser();
      $bulkIds = $this->clearingDao->getPreviousBulkIds($uploadId, $this->groupId, $this->userId);
      if (count($bulkIds) == 0) {
        return true;
      }
      $jqId=0;
      $minTime="4";
      $maxTime="60";
      foreach($bulkIds as $bulkId) {
        $jqId = $bulkReuser->rerunBulkAndDeciderOnUpload($uploadId, $this->groupId, $this->userId, $bulkId, $jqId);
        $this->heartbeat(1);
        if(!empty($jqId)) {
          $jqIdRow = $this->showJobsDao->getDataForASingleJob($jqId);
          while($this->showJobsDao->getJobStatus($jqId)) {
            $this->heartbeat(0);
            $timeInSec = $this->showJobsDao->getEstimatedTime($jqIdRow['jq_job_fk'],'',0,0,1);
            if($timeInSec > $maxTime) {
              sleep($maxTime);
            } else if($timeInSec < $minTime) {
              sleep($minTime);
            } else {
              sleep($timeInSec);
            }
          }
        }
      }
    }

    $parentBounds = $this->uploadDao->getParentItemBounds($uploadId);
    foreach ($this->uploadDao->getContainedItems($parentBounds) as $item)
    {
      $process = $this->processItem($item);
      $this->heartbeat($process);
    }
    return true;
  }

  /**
   * @brief Given an item, check with the $activeRules and apply rules to it
   *
   * Get an UploadTree item, get the previous matches, current matches.
   * Mark new licenses as WIP.
   * Check the $activeRules and apply them on the item
   * @param Item $item Item to be processes
   * @return boolean True if operation resulted in success, false otherwise
   */
  private function processItem(Item $item)
  {
    $itemTreeBounds = $item->getItemTreeBounds();

    $unMappedMatches = $this->agentLicenseEventProcessor->getLatestScannerDetectedMatches($itemTreeBounds);
    $projectedScannerMatches = $this->remapByProjectedId($unMappedMatches);

    $lastDecision = $this->clearingDao->getRelevantClearingDecision($itemTreeBounds, $this->groupId);

    if (null!==$lastDecision && $lastDecision->getType()==DecisionTypes::IRRELEVANT) {
      return 0;
    }

    $currentEvents = $this->clearingDao->getRelevantClearingEvents($itemTreeBounds, $this->groupId);

    $markAsWip = false;
    if(null!==$lastDecision && $projectedScannerMatches && ($this->activeRules&self::RULES_WIP_SCANNER_UPDATES)== self::RULES_WIP_SCANNER_UPDATES)
    {
      $licensesFromDecision = array();
      foreach($lastDecision->getClearingLicenses() as $clearingLicense)
      {
        $licenseIdFromEvent = $this->licenseMap->getProjectedId($clearingLicense->getLicenseId());
        $licensesFromDecision[$licenseIdFromEvent] = $licenseIdFromEvent;
      }
      $markAsWip = $this->existsUnhandledMatch($projectedScannerMatches,$licensesFromDecision);
    }

    if(null!==$lastDecision && $markAsWip)
    {
      $this->clearingDao->markDecisionAsWip($item->getId(), $this->userId, $this->groupId);
      return 1;
    }

    if (null!==$lastDecision || 0<count($currentEvents))
    {
      return 0;
    }

    $haveDecided = false;

    if (($this->activeRules&self::RULES_NOMOS_IN_MONK)== self::RULES_NOMOS_IN_MONK)
    {
      $haveDecided = $this->autodecideNomosMatchesInsideMonk($itemTreeBounds, $projectedScannerMatches);
    }

    if (!$haveDecided && ($this->activeRules&self::RULES_NOMOS_MONK_NINKA)== self::RULES_NOMOS_MONK_NINKA)
    {
      $haveDecided = $this->autodecideNomosMonkNinka($itemTreeBounds, $projectedScannerMatches);
    }

    if (!$haveDecided && $markAsWip)
    {
      $this->clearingDao->markDecisionAsWip($item->getId(), $this->userId, $this->groupId);
    }

    return ($haveDecided||$markAsWip ? 1 : 0);
  }

  /**
   * @brief Check if matches contains unhandled match
   * @param array $projectedScannerMatches
   * @param array[] $licensesFromDecision
   * @return boolean True if any unhandled match exists, false otherwise
   */
  private function existsUnhandledMatch($projectedScannerMatches, $licensesFromDecision)
  {
    foreach(array_keys($projectedScannerMatches) as $projectedLicenseId)
    {
      if(!array_key_exists($projectedLicenseId, $licensesFromDecision))
      {
        return true;
      }
    }
    return false;
  }

  /**
   * @brief Auto decide matches which are in nomos, monk and ninka findings
   *
   * Get the matches which really agree and apply the decisions.
   * @param ItemTreeBounds $itemTreeBounds ItemTreeBounds to apply decisions
   * @param LicenseMatch[] $matches        New license matches found
   * @return boolean True if decisions applied, false otherwise
   */
  private function autodecideNomosMonkNinka(ItemTreeBounds $itemTreeBounds, $matches)
  {
    $canDecide = (count($matches)>0);

    foreach($matches as $licenseMatches)
    {
      if (!$canDecide) // &= is not lazy
      {
        break;
      }
      $canDecide &= $this->areNomosMonkNinkaAgreed($licenseMatches);
    }

    if ($canDecide)
    {
      $this->clearingDecisionProcessor->makeDecisionFromLastEvents($itemTreeBounds, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, $global=true);
    }
    return $canDecide;
  }

  /**
   * @brief Auto decide matches by nomos which are in monk findings
   *
   * Get the nomos matches which really are inside monk findings and apply the decisions.
   * @param ItemTreeBounds $itemTreeBounds ItemTreeBounds to apply decisions
   * @param LicenseMatch[] $matches        New license matches found
   * @return boolean True if decisions applied, false otherwise
   */
  private function autodecideNomosMatchesInsideMonk(ItemTreeBounds $itemTreeBounds, $matches)
  {
    $canDecide = (count($matches)>0);

    foreach($matches as $licenseMatches)
    {
      if (!$canDecide) // &= is not lazy
      {
        break;
      }
      $canDecide &= $this->areNomosMatchesInsideAMonkMatch($licenseMatches);
    }

    if ($canDecide)
    {
      $this->clearingDecisionProcessor->makeDecisionFromLastEvents($itemTreeBounds, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, $global=true);
    }
    return $canDecide;
  }

  /**
   * @brief Given a set of matches, remap according to project id
   * instead of license id
   * @param LicenseMatch[] $matches Matches to be remaped
   * @return array[][] Remaped matches
   */
  protected function remapByProjectedId($matches)
  {
    $remapped = array();
    foreach($matches as $licenseId => $licenseMatches)
    {
      $projectedId = $this->licenseMap->getProjectedId($licenseId);

      foreach($licenseMatches as $agent => $agentMatches)
      {
        $haveId = array_key_exists($projectedId, $remapped);
        $haveAgent = $haveId && array_key_exists($agent, $remapped[$projectedId]);
        if ($haveAgent)
        {
          $remapped[$projectedId][$agent] = array_merge($remapped[$projectedId][$agent], $agentMatches);
        }
        else
        {
          $remapped[$projectedId][$agent] = $agentMatches;
        }
      }
    }
    return $remapped;
  }

  /**
   * @brief Check if the small highlight region is inside big one
   * @param int[] $small The smaller region, start at index 0, end at 1
   * @param int[] $big   The bigger region, start at index 0, end at 1
   * @return boolean True if region is inside, else false
   */
  private function isRegionIncluded($small, $big)
  {
    return ($big[0] >= 0) && ($small[0] >= $big[0]) && ($small[1] <= $big[1]);
  }

  /**
   * @brief Check if matches by nomos are inside monk findings
   * @param LicenseMatch[] $licenseMatches
   * @return boolean True if matches are inside monk, false otherwise
   */
  private function areNomosMatchesInsideAMonkMatch($licenseMatches)
  {
    if (!array_key_exists("nomos", $licenseMatches))
    {
      return false;
    }
    if (!array_key_exists("monk", $licenseMatches))
    {
      return false;
    }

    foreach($licenseMatches["nomos"] as $licenseMatch)
    {
      $matchId = $licenseMatch->getLicenseFileId();
      $nomosRegion = $this->highlightDao->getHighlightRegion($matchId);

      $found = false;
      foreach($licenseMatches["monk"] as $monkLicenseMatch)
      {
        $monkRegion = $this->highlightDao->getHighlightRegion($monkLicenseMatch->getLicenseFileId());
        if ($this->isRegionIncluded($nomosRegion, $monkRegion))
        {
          $found = true;
          break;
        }
      }
      if (!$found)
      {
        return false;
      }
    }

    return true;
  }

  /**
   * @brief Check if findings by all agents are same or not
   * @param LicenseMatch[][] $licenseMatches
   * @return boolean True if they match, false otherwise
   */
  protected function areNomosMonkNinkaAgreed($licenseMatches)
  {
    $scanners = array('nomos','monk','ninka');
    $vote = array();
    foreach ($scanners as $scanner)
    {
      if (!array_key_exists($scanner, $licenseMatches))
      {
        return false;
      }
      foreach($licenseMatches[$scanner] as $licenseMatch)
      {
        $licId = $licenseMatch->getLicenseId();
        $vote[$licId][$scanner] = true;
      }
    }

    foreach($vote as $licId=>$voters)
    {
      if (count($voters) != 3)
      {
        return false;
      }
    }
    return true;
  }

}
