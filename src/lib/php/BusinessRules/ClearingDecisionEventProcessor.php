<?php
/*
Copyright (C) 2014, Siemens AG

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

namespace Fossology\Lib\BusinessRules;


use Fossology\Lib\Dao\AgentsDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\LicenseDecision\AgentLicenseDecisionEvent;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionEvent;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionResult;
use Fossology\Lib\Data\Tree\ItemTreeBounds;

class ClearingDecisionEventProcessor
{
  /** @var LicenseDao */
  private $licenseDao;

  /** @var AgentsDao */
  private $agentsDao;

  /** @var ClearingDao */
  private $clearingDao;

  public function __construct($licenseDao, $agentsDao, $clearingDao)
  {
    $this->licenseDao = $licenseDao;
    $this->agentsDao = $agentsDao;
    $this->clearingDao = $clearingDao;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return array
   */
  public function getAgentDetectedLicenses(ItemTreeBounds $itemTreeBounds)
  {
    $agentDetectedLicenses = array();

    $licenseFileMatches = $this->licenseDao->getAgentFileLicenseMatches($itemTreeBounds);
    foreach ($licenseFileMatches as $licenseMatch)
    {
      $licenseRef = $licenseMatch->getLicenseRef();
      $licenseShortName = $licenseRef->getShortName();
      if ($licenseShortName === "No_license_found")
      {
        continue;
      }
      $agentRef = $licenseMatch->getAgentRef();
      $agentName = $agentRef->getAgentName();
      $agentId = $agentRef->getAgentId();

      $agentDetectedLicenses[$licenseShortName][$agentName][$agentId][] = array(
          'id' => $licenseRef->getId(),
          'licenseRef' => $licenseRef,
          'agentRef' => $agentRef,
          'matchId' => $licenseMatch->getLicenseFileId(),
          'percentage' => $licenseMatch->getPercentage()
      );
    }

    return $agentDetectedLicenses;
  }

  /**
   * @param $agentDetectedLicenses
   * @return array
   */
  protected function getAgentsWithResults($agentDetectedLicenses)
  {
    $agentsWithResults = array();
    foreach ($agentDetectedLicenses as $agentMap)
    {
      foreach ($agentMap as $agentName => $agentResultMap)
      {
        $agentsWithResults[$agentName] = $agentName;
      }
    }
    return $agentsWithResults;
  }

  /**
   * @param $agentDetectedLicenses
   * @param $uploadId
   * @return array
   */
  public function getLatestAgents($agentDetectedLicenses, $uploadId)
  {
    $agentsWithResults = $this->getAgentsWithResults($agentDetectedLicenses);

    $agentLatestMap = $this->agentsDao->getLatestAgentResultForUpload($uploadId, $agentsWithResults);
    return $agentLatestMap;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $userId
   * @return array
   */
  public function getCurrentSelectedLicenses(ItemTreeBounds $itemTreeBounds, $userId)
  {
    $uploadTreeId = $itemTreeBounds->getUploadTreeId();
    $uploadId = $itemTreeBounds->getUploadId();

    $agentDetectedLicenses = $this->getAgentDetectedLicenses($itemTreeBounds);

    $agentLatestMap = $this->getLatestAgents($agentDetectedLicenses, $uploadId);

    list($addedLicenses, $removedLicenses) = $this->clearingDao->getCurrentSelectedLicenses($userId, $uploadTreeId);

    $currentLicenses = array_unique(array_merge(array_keys($addedLicenses), array_keys($agentDetectedLicenses)));

    $licenseDecisions = array();
    $removed = array();
    foreach ($currentLicenses as $licenseShortName)
    {
      $licenseDecisionEvent = null;
      $agentLicenseDecisionEvents = array();

      if (array_key_exists($licenseShortName, $addedLicenses))
      {
        /** @var LicenseDecisionEvent $addedLicense */
        $addedLicense = $addedLicenses[$licenseShortName];
        $licenseDecisionEvent = $addedLicense;
      }

      if (array_key_exists($licenseShortName, $agentDetectedLicenses))
      {
        foreach ($agentDetectedLicenses[$licenseShortName] as $agentName => $agentResultMap)
        {
          foreach ($agentResultMap as $agentId => $licenseProperties)
          {
            if (!array_key_exists($agentName, $agentLatestMap) || $agentLatestMap[$agentName] != $agentId)
            {
              continue;
            }

            foreach ($licenseProperties as $licenseProperty)
            {
              $agentLicenseDecisionEvents[] = new AgentLicenseDecisionEvent(
                  $licenseProperty['licenseRef'],
                  $licenseProperty['agentRef'],
                  $licenseProperty['matchId'],
                  array_key_exists('percentage', $licenseProperty) ? $licenseProperty['percentage'] : null
              );
            }
          }
        }
      }

      if (($licenseDecisionEvent !== null) || (count($agentLicenseDecisionEvents) > 0))
      {
        $licenseDecisionResult = new LicenseDecisionResult($licenseDecisionEvent, $agentLicenseDecisionEvents);

        if (array_key_exists($licenseShortName, $removedLicenses))
        {
          $removed[$licenseShortName] = $licenseDecisionResult;
        } else
        {
          $licenseDecisions[$licenseShortName] = $licenseDecisionResult;
        }
      }
    }

    return array($licenseDecisions, $removed);
  }

  public function makeDecisionFromLastEvents(ItemTreeBounds $itemBounds, $userId, $type, $isGlobal)
  {
    $item = $itemBounds->getUploadTreeId();
    if ($type <= 1)
    {
      return;
    }
    $events = $this->clearingDao->getRelevantLicenseDecisionEvents($userId, $item);
    $clearingDecision = $this->clearingDao->getRelevantClearingDecision($userId, $item);

    list($added, $removed) = $this->getCurrentSelectedLicenses($itemBounds, $userId);

    $lastDecision = null;
    if ($clearingDecision)
    {
      $lastDecision = $clearingDecision['date_added'];
    }

    $insertDecision = false;
    foreach (array_merge($added, $removed) as $licenseShortName => $licenseDecisionResult)
    {
      /** @var LicenseDecisionResult $licenseDecisionResult */
      if (!$licenseDecisionResult->hasLicenseDecisionEvent())
      {
        $insertDecision = true;
        break;
      }

      $entryTimestamp = $licenseDecisionResult->getLicenseDecisionEvent()->getDateTime();
      if ($lastDecision === null || $lastDecision < $entryTimestamp)
      {
        $insertDecision = true;
        break;
      }
    }

    $removedSinceLastDecision = array();
    foreach ($events as $event)
    {
      $licenseShortName = $event->getLicenseShortName();
      $entryTimestamp = $event->getDateTime();
      if ($event->isRemoved() && !array_key_exists($licenseShortName, $added) && $lastDecision < $entryTimestamp)
      {
        $removedSinceLastDecision[$licenseShortName] = $event;
        $insertDecision = true;
      }
    }

    if ($type === 2)
    {
      // handle "No license known"
      $insertDecision = true;
      $added = array();
      $removedSinceLastDecision = array();
    }

    if ($insertDecision)
    {
      $this->clearingDao->insertClearingDecision($item, $userId, $type, $isGlobal, $added, $removedSinceLastDecision);
    }
  }

}