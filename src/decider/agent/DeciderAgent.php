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
  const RULES_ALL = self::RULES_NOMOS_IN_MONK;

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

  function __construct()
  {
    parent::__construct(AGENT_DECIDER_NAME, AGENT_DECIDER_VERSION, AGENT_DECIDER_REV);

    $this->uploadDao = $this->container->get('dao.upload');
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->highlightDao = $this->container->get('dao.highlight');
    $this->decisionTypes = $this->container->get('decision.types');
    $this->clearingDecisionProcessor = $this->container->get('businessrules.clearing_decision_processor');
    $this->agentLicenseEventProcessor = $this->container->get('businessrules.agent_license_event_processor');
  }

  function scheduler_connect($licenseMapUsage=null)
  {
    parent::scheduler_connect();
    $args = getopt($this->schedulerHandledOpts."r:", $this->schedulerHandledLongOpts);
    $this->activeRules = array_key_exists('r', $args) ? $args['r'] : self::RULES_ALL;

    $this->licenseMap = new LicenseMap($this->dbManager, $this->groupId, $licenseMapUsage);
  }

  function processUploadId($uploadId)
  {
    $groupId = $this->groupId;

    $parentBounds = $this->uploadDao->getParentItemBounds($uploadId);
    foreach ($this->uploadDao->getContainedItems($parentBounds) as $item)
    {
      $itemTreeBounds = $item->getItemTreeBounds();
      $matches = $this->agentLicenseEventProcessor->getLatestScannerDetectedMatches($itemTreeBounds);
      $matches = $this->remapByProjectedId($matches);

      $lastDecision = $this->clearingDao->getRelevantClearingDecision($itemTreeBounds, $groupId);
      $currentEvents = $this->clearingDao->getRelevantClearingEvents($itemTreeBounds, $groupId);

      $this->processItem($item, $matches, $lastDecision, $currentEvents);
    }
    return true;
  }

  private function processItem(Item $item, $matches, $lastDecision, $currentEvents)
  {
    $itemTreeBounds = $item->getItemTreeBounds();

    if ($this->activeRules && self::RULES_NOMOS_IN_MONK)
    {
      $this->autodecideNomosMatchesInsideMonk($itemTreeBounds, $matches, $lastDecision, $currentEvents);
    }
  }

  private function autodecideNomosMatchesInsideMonk(ItemTreeBounds $itemTreeBounds, $matches, $lastDecision, $currentEvents)
  {
    $canDecide = (count($matches)>0);
    $canDecide &= null === $lastDecision;
    $canDecide &= 0 == count($currentEvents);

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
      $this->heartbeat(1);
    }
    else
    {
      $this->heartbeat(0);
    }
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
}