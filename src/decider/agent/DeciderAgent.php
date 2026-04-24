<?php
/*
 Author: Daniele Fognini
 SPDX-FileCopyrightText: © 2014-2019 Siemens AG
 SPDX-FileCopyrightText: © 2021 Orange by Piotr Pszczola <piotr.pszczola@orange.com>
 SPDX-FileCopyrightText: © 2021 Kaushlendra Pratap <kaushlendrapratap.9837@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
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
 * - Auto conclude license finding if they are of a license type
 *
 *   Auto conclude licenses from the scanner agents if they are of specific
 *   license type.
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
use Fossology\Lib\Dao\CompatibilityDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\Tree\Item;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Exceptions\InvalidAgentStageException;
use Fossology\Lib\Proxy\ScanJobProxy;
use Symfony\Component\Process\Process;

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
  const RULES_OJO_NO_CONTRADICTION = 0x10;
  const RULES_COPYRIGHT_FALSE_POSITIVE = 0x20;
  const RULES_COPYRIGHT_FALSE_POSITIVE_CLUTTER = 0x40;
  const RULES_LICENSE_TYPE_CONCLUSION = 0x80;
  const RULES_KOTOBA_NO_CONTRADICTION = 0x100;
  const RULES_ALL = self::RULES_NOMOS_IN_MONK | self::RULES_NOMOS_MONK_NINKA |
    self::RULES_BULK_REUSE | self::RULES_WIP_SCANNER_UPDATES |
    self::RULES_OJO_NO_CONTRADICTION | self::RULES_LICENSE_TYPE_CONCLUSION | self::RULES_KOTOBA_NO_CONTRADICTION;

  /** @var int $activeRules
   * Rules active for upload (nomos in monk; ninka in monk; nomos, ninka and monk)
   */
  private $activeRules;
  /** @var string $licenseType
   * License Type to use if concluding.
   */
  private $licenseType;
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

  /** @var CopyrightDao $copyrightDao
   * CopyrightDao object
   */
  private $copyrightDao;

  /** @var CompatibilityDao $compatibilityDao
   * Compatibility Dao
   */
  private $compatibilityDao;

  /** @var LicenseDao $licenseDao
   * License Dao
   */
  private $licenseDao;

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
    $this->copyrightDao = $this->container->get('dao.copyright');
    $this->compatibilityDao = $this->container->get('dao.compatibility');
    $this->licenseDao = $this->container->get('dao.license');
    $this->licenseMapUsage = $licenseMapUsage;
    $this->agentSpecifOptions = "r:t:";
  }

  /**
   * @copydoc Fossology::Lib::Agent::Agent::processUploadId()
   * @see Fossology::Lib::Agent::Agent::processUploadId()
   */
  function processUploadId($uploadId)
  {
    $args = $this->args;
    $this->activeRules = array_key_exists('r', $args) ? intval($args['r']) : self::RULES_ALL;
    $this->licenseType = array_key_exists('t', $args) ?
        $this->getLicenseType(str_replace(["'", '"'], "", $args['t'])) : "";
    $this->licenseMap = new LicenseMap($this->dbManager, $this->groupId, $this->licenseMapUsage);

    if (array_key_exists("r", $args) && (($this->activeRules&self::RULES_COPYRIGHT_FALSE_POSITIVE)== self::RULES_COPYRIGHT_FALSE_POSITIVE)) {
      $this->getCopyrightsToDisableFalsePositivesClutter($uploadId, false);
    }
    if (array_key_exists("r", $args) && (($this->activeRules&self::RULES_COPYRIGHT_FALSE_POSITIVE_CLUTTER)== self::RULES_COPYRIGHT_FALSE_POSITIVE_CLUTTER)) {
      $this->getCopyrightsToDisableFalsePositivesClutter($uploadId, true);
    }
    if (array_key_exists("r", $args) && (($this->activeRules&self::RULES_BULK_REUSE)== self::RULES_BULK_REUSE)) {
      $bulkReuser = new BulkReuser();
      $bulkIds = $this->clearingDao->getPreviousBulkIds($uploadId, $this->groupId, $this->userId);
      if (count($bulkIds) == 0) {
        return true;
      }
      $jqId=0;
      $minTime="4";
      $maxTime="60";
      foreach ($bulkIds as $bulkId) {
        $jqId = $bulkReuser->rerunBulkAndDeciderOnUpload($uploadId, $this->groupId, $this->userId, $bulkId, $jqId);
        $this->heartbeat(1);
        if (!empty($jqId)) {
          $jqIdRow = $this->showJobsDao->getDataForASingleJob($jqId);
          while ($this->showJobsDao->getJobStatus($jqId)) {
            $this->heartbeat(0);
            $timeInSec = $this->showJobsDao->getEstimatedTime($jqIdRow['jq_job_fk'],'',0,0,1);
            if ($timeInSec > $maxTime) {
              sleep($maxTime);
            } else if ($timeInSec < $minTime) {
              sleep($minTime);
            } else {
              sleep($timeInSec);
            }
          }
        }
      }
    }
    $parentBounds = $this->uploadDao->getParentItemBounds($uploadId);
    foreach ($this->uploadDao->getContainedItems($parentBounds) as $item) {
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
   * @return int 1 if operation resulted in success, 0 otherwise
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
    if (null !== $lastDecision && $projectedScannerMatches
      && ($this->activeRules & self::RULES_WIP_SCANNER_UPDATES) == self::RULES_WIP_SCANNER_UPDATES) {
      $licensesFromDecision = array();
      foreach ($lastDecision->getClearingLicenses() as $clearingLicense) {
        $licenseIdFromEvent = $this->licenseMap->getProjectedId($clearingLicense->getLicenseId());
        $licensesFromDecision[$licenseIdFromEvent] = $licenseIdFromEvent;
      }
      $markAsWip = $this->existsUnhandledMatch($projectedScannerMatches,$licensesFromDecision);
    }

    if (null !== $lastDecision && $markAsWip) {
      $this->clearingDao->markDecisionAsWip($item->getId(), $this->userId, $this->groupId);
      return 1;
    }

    if (null!==$lastDecision || 0<count($currentEvents)) {
      return 0;
    }

    $haveDecided = false;

    if (!$haveDecided && ($this->activeRules&self::RULES_OJO_NO_CONTRADICTION) == self::RULES_OJO_NO_CONTRADICTION) {
      $haveDecided = $this->autodecideIfOjoMatchesNoContradiction($itemTreeBounds, $projectedScannerMatches);
    }

    if (!$haveDecided && ($this->activeRules&self::RULES_OJO_NO_CONTRADICTION) == self::RULES_OJO_NO_CONTRADICTION) {
      $haveDecided = $this->autodecideIfResoMatchesNoContradiction($itemTreeBounds, $projectedScannerMatches);
    }

    if (!$haveDecided && ($this->activeRules&self::RULES_NOMOS_IN_MONK) == self::RULES_NOMOS_IN_MONK) {
      $haveDecided = $this->autodecideNomosMatchesInsideMonk($itemTreeBounds, $projectedScannerMatches);
    }

    if (!$haveDecided && ($this->activeRules&self::RULES_NOMOS_MONK_NINKA)== self::RULES_NOMOS_MONK_NINKA) {
      $haveDecided = $this->autodecideNomosMonkNinka($itemTreeBounds, $projectedScannerMatches);
    }

    if (!$haveDecided && ($this->activeRules &
            self::RULES_LICENSE_TYPE_CONCLUSION) ==
        self::RULES_LICENSE_TYPE_CONCLUSION) {
      $haveDecided = $this->autodecideLicenseType($itemTreeBounds,
          $projectedScannerMatches);
    }

    if (!$haveDecided && $markAsWip) {
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
    foreach (array_keys($projectedScannerMatches) as $projectedLicenseId) {
      if (!array_key_exists($projectedLicenseId, $licensesFromDecision)) {
        return true;
      }
    }
    return false;
  }

  /**
   * @brief Auto decide matches which are in nomos, monk and OJO findings
   *
   * Get the matches which really agree and apply the decisions.
   * @param ItemTreeBounds $itemTreeBounds ItemTreeBounds to apply decisions
   * @param LicenseMatch[][][] $matches    New license matches found
   * @return boolean True if decisions applied, false otherwise
   */
  private function autodecideIfOjoMatchesNoContradiction(ItemTreeBounds $itemTreeBounds, $matches)
  {
    $licenseMatchExists = count($matches) > 0;
    foreach ($matches as $licenseMatches) {
      $licenseMatchExists = $licenseMatchExists && $this->areOtherScannerFindingsAndOJOAgreed($licenseMatches);
    }

    if ($licenseMatchExists) {
      try {
        $this->clearingDecisionProcessor->makeDecisionFromLastEvents(
          $itemTreeBounds, $this->userId, $this->groupId,
          DecisionTypes::IDENTIFIED, false);
      } catch (\Exception $e) {
        echo "Can not auto decide as file '" .
          $itemTreeBounds->getItemId() . "' contains candidate license.\n";
      }
    }
    return $licenseMatchExists;
  }

  /**
   * @brief Auto decide matches which are in nomos, monk, OJO and Reso findings
   *
   * Get the matches which really agree and apply the decisions.
   * @param ItemTreeBounds $itemTreeBounds ItemTreeBounds to apply decisions
   * @param LicenseMatch[][][] $matches    New license matches found
   * @return boolean True if decisions applied, false otherwise
   */
  private function autodecideIfResoMatchesNoContradiction(ItemTreeBounds $itemTreeBounds, $matches)
  {
    $licenseMatchExists = count($matches) > 0;
    foreach ($matches as $licenseMatches) {
      $licenseMatchExists = $licenseMatchExists && $this->areOtherScannerFindingsAndRESOAgreed($licenseMatches);
    }

    if ($licenseMatchExists) {
      try {
        $this->clearingDecisionProcessor->makeDecisionFromLastEvents(
          $itemTreeBounds, $this->userId, $this->groupId,
          DecisionTypes::IDENTIFIED, false);
      } catch (\Exception $e) {
        echo "Can not auto decide as file '" .
          $itemTreeBounds->getItemId() . "' contains candidate license.\n";
      }
    }
    return $licenseMatchExists;
  }

  /**
   * @brief Auto decide matches which are in nomos, monk and ninka findings
   *
   * Get the matches which really agree and apply the decisions.
   * @param ItemTreeBounds $itemTreeBounds ItemTreeBounds to apply decisions
   * @param LicenseMatch[][][] $matches    New license matches found
   * @return boolean True if decisions applied, false otherwise
   */
  private function autodecideNomosMonkNinka(ItemTreeBounds $itemTreeBounds, $matches)
  {
    $canDecide = (count($matches)>0);

    foreach ($matches as $licenseMatches) {
      if (!$canDecide) { // &= is not lazy
        break;
      }
      $canDecide &= $this->areNomosMonkNinkaAgreed($licenseMatches);
    }

    if ($canDecide) {
      $this->clearingDecisionProcessor->makeDecisionFromLastEvents($itemTreeBounds, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, $global=true);
    }
    return $canDecide;
  }

  /**
   * @brief Auto decide matches by nomos which are in monk findings
   *
   * Get the nomos matches which really are inside monk findings and apply the decisions.
   * @param ItemTreeBounds $itemTreeBounds ItemTreeBounds to apply decisions
   * @param LicenseMatch[][][] $matches    New license matches found
   * @return boolean True if decisions applied, false otherwise
   */
  private function autodecideNomosMatchesInsideMonk(ItemTreeBounds $itemTreeBounds, $matches)
  {
    $canDecide = (count($matches)>0);

    foreach ($matches as $licenseMatches) {
      if (!$canDecide) { // &= is not lazy
        break;
      }
      $canDecide &= $this->areNomosMatchesInsideAMonkMatch($licenseMatches);
    }

    if ($canDecide) {
      $this->clearingDecisionProcessor->makeDecisionFromLastEvents($itemTreeBounds, $this->userId, $this->groupId, DecisionTypes::IDENTIFIED, $global=true);
    }
    return $canDecide;
  }

  /**
   * @brief Auto decide matches where there is no license conflict.
   *
   * Get the matches where there is no license conflict and licenses are of
   * provided type.
   * @param ItemTreeBounds $itemTreeBounds ItemTreeBounds to apply decisions
   * @param LicenseMatch[][][] $matches      New license matches found
   * @return boolean True if decisions applied, false otherwise
   */
  private function autodecideLicenseType(ItemTreeBounds $itemTreeBounds,
                                         $matches)
  {
    $canDecide = $this->noLicenseConflict($itemTreeBounds, $matches);
    if ($canDecide) {
      $canDecide &= $this->allLicenseInType($matches);
    }

    if ($canDecide) {
      $this->clearingDecisionProcessor
          ->makeDecisionFromLastEvents($itemTreeBounds, $this->userId,
              $this->groupId, DecisionTypes::IDENTIFIED, DecisionScopes::ITEM, [], true);
    }
    return $canDecide;
  }

  /**
   * @brief Given a set of matches, remap according to project id
   * instead of license id
   * @param LicenseMatch[] $matches Matches to be remapped
   * @return LicenseMatch[][][] Remapped matches
   */
  protected function remapByProjectedId($matches)
  {
    $remapped = array();
    foreach ($matches as $licenseId => $licenseMatches) {
      $projectedId = $this->licenseMap->getProjectedId($licenseId);

      foreach ($licenseMatches as $agent => $agentMatches) {
        $haveId = array_key_exists($projectedId, $remapped);
        $haveAgent = $haveId && array_key_exists($agent, $remapped[$projectedId]);
        if ($haveAgent) {
          $remapped[$projectedId][$agent] = array_merge($remapped[$projectedId][$agent], $agentMatches);
        } else {
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
   * @param LicenseMatch[][] $licenseMatches
   * @return boolean True if matches are inside monk, false otherwise
   */
  private function areNomosMatchesInsideAMonkMatch($licenseMatches)
  {
    if (!array_key_exists("nomos", $licenseMatches)) {
      return false;
    }
    if (!array_key_exists("monk", $licenseMatches)) {
      return false;
    }

    foreach ($licenseMatches["nomos"] as $licenseMatch) {
      $matchId = $licenseMatch->getLicenseFileId();
      $nomosRegion = $this->highlightDao->getHighlightRegion($matchId);

      $found = false;
      foreach ($licenseMatches["monk"] as $monkLicenseMatch) {
        $monkRegion = $this->highlightDao->getHighlightRegion($monkLicenseMatch->getLicenseFileId());
        if ($this->isRegionIncluded($nomosRegion, $monkRegion)) {
          $found = true;
          break;
        }
      }
      if (!$found) {
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
    foreach ($scanners as $scanner) {
      if (!array_key_exists($scanner, $licenseMatches)) {
        return false;
      }
      foreach ($licenseMatches[$scanner] as $licenseMatch) {
        $licId = $licenseMatch->getLicenseId();
        $vote[$licId][$scanner] = true;
      }
    }

    foreach ($vote as $licId=>$voters) {
      if (count($voters) != 3) {
        return false;
      }
    }
    return true;
  }

  /**
   * @brief extracts the matches corresponding to a scanner from a $licenseMatches structure
   * @param $scanner
   * @param LicenseMatch[][] $licenseMatches
   * @return int[] list of license ids
   */
  protected function getLicenseIdsOfMatchesForScanner($scanner, $licenseMatches)
  {
    if (array_key_exists($scanner, $licenseMatches) === true) {
      return array_map(
        function ($match) {
          return $match->getLicenseId();
        }, $licenseMatches[$scanner]);
    }
    return [];
  }

  /**
   * @brief Check if the finding by only contains one single license and that no other scanner (nomos) has produced a contradicting statement
   * @param LicenseMatch[][] $licenseMatches
   * @return boolean True if they match, false otherwise
   */
  protected function areOtherScannerFindingsAndOJOAgreed($licenseMatches)
  {
    $findingsByOjo = $this->getLicenseIdsOfMatchesForScanner('ojo', $licenseMatches);
    if (count($findingsByOjo) == 0) {
      // nothing to do
      return false;
    }

    $findingsByOtherScanner = $this->getLicenseIdsOfMatchesForScanner('nomos', $licenseMatches);
    if (count($findingsByOtherScanner) == 0) {
      // nothing found by other scanner, so no contradiction
      return true;
    }
    foreach ($findingsByOtherScanner as $findingsByScanner) {
      if (in_array($findingsByScanner, $findingsByOjo) === false) {
        // contradiction found
        return false;
      }
    }
    return true;
  }

  /**
   * @brief Check if the finding by only contains one single license and that no other scanner (nomos) has produced a contradicting statement
   * @param LicenseMatch[][] $licenseMatches
   * @return boolean True if they match, false otherwise
   */
  protected function areOtherScannerFindingsAndRESOAgreed($licenseMatches)
  {
    $findingsByReso = $this->getLicenseIdsOfMatchesForScanner('reso', $licenseMatches);
    if (count($findingsByReso) == 0) {
      // nothing to do
      return false;
    }

    $findingsByOtherScanner = $this->getLicenseIdsOfMatchesForScanner('nomos', $licenseMatches);
    if (count($findingsByOtherScanner) == 0) {
      // nothing found by other scanner, so no contradiction
      return true;
    }
    foreach ($findingsByOtherScanner as $findingsByScanner) {
      if (in_array($findingsByScanner, $findingsByReso) === false) {
        // contradiction found
        return false;
      }
    }
    return true;
  }

  /**
   * Use the copyright deactivation script to remove false positive copyrights.
   * @param int $uploadId      Upload to process
   * @param bool $clutter_flag Remove clutter as well?
   * @return void
   */
  private function getCopyrightsToDisableFalsePositivesClutter($uploadId,
                                                               $clutter_flag): void
  {
    if (empty($uploadId)) {
      return;
    }
    $agentName = 'copyright';
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $scanJobProxy = new ScanJobProxy($GLOBALS['container']->get('dao.agent'), $uploadId);
    $scanJobProxy->createAgentStatus(array($agentName));
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (!array_key_exists($agentName, $selectedScanners)) {
      return;
    }
    $latestXpAgentId = $selectedScanners[$agentName];
    $extrawhere = ' agent_fk='.$latestXpAgentId;
    $allCopyrights = $this->copyrightDao->getScannerEntries('copyright',
      $uploadTreeTableName, $uploadId, null, $extrawhere);

    $copyrightJSON = json_encode($allCopyrights);
    $tmpFile = tmpfile();
    $tmpFilePath = stream_get_meta_data($tmpFile)['uri'];
    fwrite($tmpFile, $copyrightJSON);
    $deactivatedCopyrightData = $this->callCopyrightDeactivationClutterRemovalScript($tmpFilePath, $clutter_flag);
    if (empty($deactivatedCopyrightData)) {
      fclose($tmpFile);
      return;
    }
    $deactivatedCopyrights = json_decode($deactivatedCopyrightData, true);
    foreach ($deactivatedCopyrights as $deactivatedCopyright) {
      $item = $deactivatedCopyright['uploadtree_pk'];
      $itemTreeBounds = $this->uploadDao->getItemTreeBounds($item, $uploadTreeTableName);
      $hash = $deactivatedCopyright['hash'];
      $content = $deactivatedCopyright['content'];
      $cpTable = 'copyright';
      if ($deactivatedCopyright['is_copyright'] == "t") {
        $action = '';
        if (array_key_exists('decluttered_content', $deactivatedCopyright) &&
            !empty($deactivatedCopyright['decluttered_content'])) {
          $content = $deactivatedCopyright['decluttered_content'];
        } else {
          // No text update. Nothing to do.
          $this->heartbeat(1);
          continue;
        }
      } else {
        $action = 'delete';
      }
      $this->copyrightDao->updateTable($itemTreeBounds, $hash, $content, $this->userId, $cpTable, $action);
      $this->heartbeat(1);
    }
    fclose($tmpFile);
  }

  /**
   * Run the Python script with required parameters and return the output.
   * @param string $tmpFilePath Path to temp file with input JSON
   * @param bool $clutter_flag  Remove clutter as well?
   * @return string Return from script.
   */
  private function callCopyrightDeactivationClutterRemovalScript($tmpFilePath,
                                                                 $clutter_flag): string
  {
    $script = "copyrightDeactivationClutterRemovalScript.py";
    $args = ["python3", __DIR__ . "/$script", "--file", $tmpFilePath];
    if ($clutter_flag) {
      $args[] = "--clutter";
    }

    $sleepTime = 5;
    $maxSleepTime = 25;

    $process = new Process($args);
    $process->setTimeout(null); // Remove timeout to run indefinitely.
    $process->setEnv(set_python_path());
    $process->start();

    do {
      $this->heartbeat(0);
      sleep($sleepTime);
      if ($sleepTime < $maxSleepTime) {
        $sleepTime += 5;
      }
    } while ($process->isRunning());

    echo $process->getErrorOutput();

    return $process->getOutput();
  }

  /**
   * Convert the license type key from flag to string value.
   * @param string $licenseType License Type from args
   * @return string License type if key found, empty string otherwise.
   */
  private function getLicenseType($licenseType)
  {
    global $SysConf;
    $licenseTypes = array_map('trim', explode(',',
        $SysConf['SYSCONFIG']['LicenseTypes']));
    if (in_array($licenseType, $licenseTypes)) {
      return $licenseType;
    }
    return "";
  }

  /**
   * @brief Check if findings by all agents are same or not
   * @param ItemTreeBounds $itemTreeBounds Item tree bound to check conflicts
   * @param LicenseMatch[][][] $licenseMatches License matches
   * @return boolean True if they match, false otherwise
   * @throws \Exception
   */
  protected function noLicenseConflict($itemTreeBounds, $licenseMatches)
  {
    $shortnames = [];
    foreach ($licenseMatches as $agentMatch) {
      foreach ($agentMatch as $agentLicenseMatches) {
        foreach ($agentLicenseMatches as $licenseMatch) {
          $shortnames[$licenseMatch->getLicenseId()] = $licenseMatch
              ->getLicenseRef()->getShortName();
        }
      }
    }
    foreach ($shortnames as $shortname) {
      try {
        if (! $this->compatibilityDao->getCompatibilityForFile($itemTreeBounds,
            $shortname)) {
          return false;
        }
      } catch (InvalidAgentStageException $_) {
        return false;
      }
    }
    return true;
  }

  /**
   * @brief Check if findings by all agents are same or not
   * @param LicenseMatch[][][] $licenseMatches License matches
   * @return boolean True if they match, false otherwise
   */
  protected function allLicenseInType($licenseMatches)
  {
    $licenseTypes = [];
    foreach ($licenseMatches as $agentMatch) {
      foreach ($agentMatch as $agentLicenseMatches) {
        foreach ($agentLicenseMatches as $licenseMatch) {
          if (!array_key_exists($licenseMatch->getLicenseId(), $licenseTypes)) {
            $licenseTypes[$licenseMatch->getLicenseId()] = $this->licenseDao
                ->getLicenseType($licenseMatch->getLicenseId());
          }
        }
      }
    }
    if (! in_array($this->licenseType, $licenseTypes)) {
      return false;
    }
    if (! empty(array_diff($licenseTypes, [$this->licenseType]))) {
      return false;
    }
    return true;
  }
}
