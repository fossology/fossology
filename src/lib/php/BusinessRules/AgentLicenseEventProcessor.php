<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @namespace Fossology::Lib::BusinessRules
 * @brief Contains business rules for FOSSology
 */
namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\Clearing\AgentClearingEvent;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Proxy\LatestScannerProxy;

/**
 * @class AgentLicenseEventProcessor
 * @brief Handle events related to license findings
 */
class AgentLicenseEventProcessor
{
  /** @var array $latestAgentMapCache
   * License map cache */
  private $latestAgentMapCache = array();
  /** @var LicenseDao $licenseDao
   * License DAO object */
  private $licenseDao;
  /** @var AgentDao $agentDao
   * Agent DAO object */
  private $agentDao;

  /**
   * Constructor for the event processor
   * @param LicenseDao $licenseDao License DAO to be used
   * @param AgentDao $agentDao     Agent DAO to be used
   */
  public function __construct(LicenseDao $licenseDao, AgentDao $agentDao)
  {
    $this->licenseDao = $licenseDao;
    $this->agentDao = $agentDao;
  }

  /**
   * @brief Get licenses detected by agents for a given upload tree item.
   * @param ItemTreeBounds $itemTreeBounds Upload tree item bound
   * @param int $usageId                   License usage
   * @return LicenseRef[]
   */
  public function getScannerDetectedLicenses(ItemTreeBounds $itemTreeBounds, $usageId=LicenseMap::TRIVIAL)
  {
    $details = $this->getScannerDetectedLicenseDetails($itemTreeBounds, $usageId);

    return $this->getScannedLicenses($details);
  }

  /**
   * @brief Get licenses match from agents for given upload tree items
   * @param ItemTreeBounds $itemTreeBounds Upload tree bounds to get results for
   * @param int $usageId  License usage
   * @param bool $includeExpressions Include license expressions
   * @return array Associative array with
   * \code
   * res => array(
   *     <license-id> => array(
   *         <agent-name> => array(
   *             id         => <license-id>,
   *             licenseRef => <license-ref>,
   *             agentRef   => <agent-ref>,
   *             matchId    => <highlight-match-id>,
   *             percentage => <match-percentage>
   *         )
   *     )
   * )
   * \endcode
   * format
   */
  protected function getScannerDetectedLicenseDetails(ItemTreeBounds $itemTreeBounds, $usageId=LicenseMap::TRIVIAL, $includeExpressions=false)
  {
    $agentDetectedLicenses = array();

    $licenseFileMatches = $this->licenseDao->getAgentFileLicenseMatches($itemTreeBounds, $usageId, $includeExpressions);
    foreach ($licenseFileMatches as $licenseMatch) {
      $licenseRef = $licenseMatch->getLicenseRef();
      $licenseId = $licenseRef->getId();
      if ($licenseRef->getShortName() === "No_license_found") {
        continue;
      }
      if ($licenseRef->getShortName() === "License Expression" && !$includeExpressions) {
        continue;
      }
      $agentRef = $licenseMatch->getAgentRef();
      $agentName = $agentRef->getAgentName();
      $agentId = $agentRef->getAgentId();

      $agentDetectedLicenses[$agentName][$agentId][$licenseId][] = array(
          'id' => $licenseId,
          'licenseRef' => $licenseRef,
          'agentRef' => $agentRef,
          'matchId' => $licenseMatch->getLicenseFileId(),
          'percentage' => $licenseMatch->getPercentage()
      );
    }

    return $this->filterLatestScannerDetectedMatches($agentDetectedLicenses, $itemTreeBounds->getUploadId());
  }

  /**
   * @brief Get all license id matches by agent for a given upload tree item
   * @param ItemTreeBounds $itemTreeBounds Upload tree bound
   * @return LicenseMatch[][][] map licenseId->agentName->licenseMatches
   */
  public function getLatestScannerDetectedMatches(ItemTreeBounds $itemTreeBounds)
  {
    $agentDetectedLicenses = array();

    $licenseFileMatches = $this->licenseDao->getAgentFileLicenseMatches($itemTreeBounds);

    foreach ($licenseFileMatches as $licenseMatch) {
      $licenseRef = $licenseMatch->getLicenseRef();
      $licenseId = $licenseRef->getId();
      if ($licenseRef->getShortName() === "No_license_found") {
        continue;
      }
      $agentRef = $licenseMatch->getAgentRef();
      $agentName = $agentRef->getAgentName();
      $agentId = $agentRef->getAgentId();

      $agentDetectedLicenses[$agentName][$agentId][$licenseId][] = $licenseMatch;
    }

    return $this->filterLatestScannerDetectedMatches($agentDetectedLicenses, $itemTreeBounds->getUploadId());
  }

  /**
   * @brief (A->B->C->X) => C->A->X if B=latestScannerId(A)
   * @param array $agentDetectedLicenses  Agent license match map
   * @param int $uploadId Upload to be queried
   * @return LicenseMatch[][][] map licenseId->agentName->licenseMatches
   */
  protected function filterLatestScannerDetectedMatches($agentDetectedLicenses, $uploadId)
  {
    $agentNames = array_keys($agentDetectedLicenses);
    if (empty($agentNames)) {
      return array();
    }

    $latestAgentIdPerAgent = $this->getLatestAgentIdPerAgent($uploadId, $agentNames);
    $latestAgentDetectedLicenses = $this->filterDetectedLicenses($agentDetectedLicenses, $latestAgentIdPerAgent);
    return $latestAgentDetectedLicenses;
  }

  /**
   * @brief Get map for agent name => agent id
   *
   * The function also updates the agent map cache.
   * @param int $uploadId     Upload to query
   * @param array $agentNames Agents required
   * @return array Map of agent name => agent id
   */
  private function getLatestAgentIdPerAgent($uploadId, $agentNames)
  {
    if (!array_key_exists($uploadId,$this->latestAgentMapCache)
            || count(array_diff_key($agentNames, $this->latestAgentMapCache[$uploadId]))>0) {
      $latestScannerProxy = new LatestScannerProxy($uploadId, $agentNames, "latest_scanner$uploadId");
      $latestAgentIdPerAgent = $latestScannerProxy->getNameToIdMap();
      foreach ($latestAgentIdPerAgent as $agentName=>$agentMap) {
        $this->latestAgentMapCache[$uploadId][$agentName] = $agentMap;
      }
    }
    if (array_key_exists($uploadId, $this->latestAgentMapCache)) {
      return $this->latestAgentMapCache[$uploadId];
    } else {
      return array();
    }
  }

  /**
   * @brief (A->B->C->X, A->B) => C->A->X
   * @param mixed[][][] $agentDetectedLicenses
   * @param array $agentLatestMap
   * @return mixed[][]
   */
  protected function filterDetectedLicenses($agentDetectedLicenses, $agentLatestMap)
  {
    $latestAgentDetectedLicenses = array();

    foreach ($agentDetectedLicenses as $agentName => $licensesFoundPerAgentId) {
      if (!array_key_exists($agentName, $agentLatestMap)) {
        continue;
      }
      $latestAgentId = $agentLatestMap[$agentName];
      if (!array_key_exists($latestAgentId, $licensesFoundPerAgentId)) {
        continue;
      }
      foreach ($licensesFoundPerAgentId[$latestAgentId] as $licenseId => $properties) {
        $latestAgentDetectedLicenses[$licenseId][$agentName] = $properties;
      }
    }

    return $latestAgentDetectedLicenses;
  }

  /**
   * @brief Get scanned license as a map of license-id => license-ref
   * @param array $details Result from getScannerDetectedLicenseDetails()
   * @return LicenseRef[] indexed by license id
   */
  public function getScannedLicenses($details)
  {
    $licenses = array();

    foreach ($details as $licenseId => $agentEntries) {
      foreach ($agentEntries as $matchProperties) {
        $licenses[$licenseId] = $matchProperties[0]['licenseRef'];
        break;
      }
    }

    return $licenses;
  }

  /**
   * @brief Get all scanner events that occurred on a given upload tree bound
   * @param ItemTreeBounds $itemTreeBounds Upload tree bound
   * @param int $usageId  License usage
   * @param bool $includeExpressions Include license expressions
   * @return AgentClearingEvent[][] indexed by LicenseId
   */
  public function getScannerEvents(ItemTreeBounds $itemTreeBounds, $usageId=LicenseMap::TRIVIAL, $includeExpressions=false)
  {
    $agentDetails = $this->getScannerDetectedLicenseDetails($itemTreeBounds, $usageId, $includeExpressions);

    $result = array();
    foreach ($agentDetails as $licenseId => $properties) {
      $agentClearingEvents = array();
      foreach ($properties as $licenseProperties) {
        foreach ($licenseProperties as $licenseProperty) {
          $agentClearingEvents[] = $this->createAgentClearingEvent($licenseProperty);
        }
      }

      $result[$licenseId] = $agentClearingEvents;
    }
    return $result;
  }

  /**
   * @brief Create a new AgentClearingEvent
   * @param array $licenseProperty License properties required for
   *        AgentClearingEvent in an associative array
   * @return AgentClearingEvent
   */
  private function createAgentClearingEvent($licenseProperty)
  {
    return new AgentClearingEvent(
        $licenseProperty['licenseRef'],
        $licenseProperty['agentRef'],
        $licenseProperty['matchId'],
        array_key_exists('percentage', $licenseProperty) ? $licenseProperty['percentage'] : null
    );
  }
}
