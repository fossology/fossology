<?php
/*
 Author: Daniele Fognini
 Copyright (C) 2014-2015, Siemens AG

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

include_once(__DIR__ . "/version.php");

class DeciderAgent extends Agent
{
  const RULES_NOMOS_IN_MONK = 0x1;
  const RULES_NOMOS_MONK_NINKA = 0x2;
  const RULES_BULK_REUSE = 0x4;
  const RULES_WIP_SCANNER_UPDATES = 0x8;
  const RULES_ALL = 0xf; // self::RULES_NOMOS_IN_MONK | self::RULES_NOMOS_MONK_NINKA | ... -> feature not available in php5.3

  /** @var int */
  private $activeRules;
  /** @var UploadDao */
  private $uploadDao;
  /** @var ClearingDecisionProcessor */
  private $clearingDecisionProcessor;
  /** @var AgentLicenseEventProcessor */
  private $agentLicenseEventProcessor;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var HighlightDao */
  private $highlightDao;
  /** @var DecisionTypes */
  private $decisionTypes;
  /** @var LicenseMap */
  private $licenseMap = null;
  /** @var int */
  private $licenseMapUsage = null;

  function __construct($licenseMapUsage=null)
  {
    parent::__construct(AGENT_DECIDER_NAME, AGENT_DECIDER_VERSION, AGENT_DECIDER_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->highlightDao = $this->container->get('dao.highlight');
    $this->decisionTypes = $this->container->get('decision.types');
    $this->clearingDecisionProcessor = $this->container->get('businessrules.clearing_decision_processor');
    $this->agentLicenseEventProcessor = $this->container->get('businessrules.agent_license_event_processor');

    $this->licenseMapUsage = $licenseMapUsage;
    $this->agentSpecifOptions = "r:";
  }

  function processUploadId($uploadId)
  {
    $args = $this->args;
    $this->activeRules = array_key_exists('r', $args) ? intval($args['r']) : self::RULES_ALL;
    $this->licenseMap = new LicenseMap($this->dbManager, $this->groupId, $this->licenseMapUsage);
    
    $parentBounds = $this->uploadDao->getParentItemBounds($uploadId);
    foreach ($this->uploadDao->getContainedItems($parentBounds) as $item)
    {
      $process = $this->processItem($item);
      $this->heartbeat($process);
    }

    if (($this->activeRules&self::RULES_BULK_REUSE)== self::RULES_BULK_REUSE)
    {
      $bulkReuser = new BulkReuser();
      $bulkReuser->rerunBulkAndDeciderOnUpload($uploadId, $this->groupId, $this->userId, $this->jobId);
    }
    
    return true;
  }

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
   * @param ItemTreeBounds $itemTreeBounds
   * @param LicenseMatch[] $matches
   * @return boolean
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
   * @param ItemTreeBounds $itemTreeBounds
   * @param LicenseMatch[] $matches
   * @return boolean
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

  private function isRegionIncluded($small, $big)
  {
    return ($big[0] >= 0) && ($small[0] >= $big[0]) && ($small[1] <= $big[1]);
  }

  /**
   * @param LicenseMatch[]
   * @return boolean
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
   * @param LicenseMatch[][] $licenseMatches
   * @return boolean
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