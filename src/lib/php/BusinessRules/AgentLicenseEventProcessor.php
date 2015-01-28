<?php
/*
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

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\Clearing\AgentClearingEvent;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Util\Object;

class AgentLicenseEventProcessor extends Object
{

  /** @var LicenseDao */
  private $licenseDao;

  /** @var AgentDao */
  private $agentDao;

  public function __construct(LicenseDao $licenseDao, AgentDao $agentDao)
  {
    $this->licenseDao = $licenseDao;
    $this->agentDao = $agentDao;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int
   * @return LicenseRef[]
   */
  public function getScannerDetectedLicenses(ItemTreeBounds $itemTreeBounds, $usageId=LicenseMap::TRIVIAL)
  {
    $details = $this->getScannerDetectedLicenseDetails($itemTreeBounds, $usageId);

    return $this->getScannedLicenses($details);
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return array[][]
   */
  protected function getScannerDetectedLicenseDetails(ItemTreeBounds $itemTreeBounds, $usageId=LicenseMap::TRIVIAL)
  {
    $agentDetectedLicenses = array();

    $licenseFileMatches = $this->licenseDao->getAgentFileLicenseMatches($itemTreeBounds, $usageId);

    foreach ($licenseFileMatches as $licenseMatch)
    {
      $licenseRef = $licenseMatch->getLicenseRef();
      $licenseId = $licenseRef->getId();
      if ($licenseRef->getShortName() === "No_license_found")
      {
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

//    $latestAgentIdPerAgent = $this->agentDao->getLatestAgentResultForUpload($itemTreeBounds->getUploadId(), array_keys($agentDetectedLicenses));
    $uploadId = $itemTreeBounds->getUploadId(); 
    $latestScannerProxy = new \Fossology\Lib\Proxy\LatestScannerProxy($uploadId, array_keys($agentDetectedLicenses), "latest_scanner$uploadId");
    $latestAgentIdPerAgent = $latestScannerProxy->getNameToIdMap();
    
    $latestAgentDetectedLicenses = $this->filterDetectedLicenses($agentDetectedLicenses, $latestAgentIdPerAgent);
    return $latestAgentDetectedLicenses;
  }

  public function getLatestScannerDetectedMatches(ItemTreeBounds $itemTreeBounds)
  {
    $agentDetectedLicenses = array();

    $licenseFileMatches = $this->licenseDao->getAgentFileLicenseMatches($itemTreeBounds);

    foreach ($licenseFileMatches as $licenseMatch)
    {
      $licenseRef = $licenseMatch->getLicenseRef();
      $licenseId = $licenseRef->getId();
      if ($licenseRef->getShortName() === "No_license_found")
      {
        continue;
      }
      $agentRef = $licenseMatch->getAgentRef();
      $agentName = $agentRef->getAgentName();
      $agentId = $agentRef->getAgentId();

      $agentDetectedLicenses[$agentName][$agentId][$licenseId][] = $licenseMatch;
    }

    $latestAgentIdPerAgent = $this->agentDao->getLatestAgentResultForUpload($itemTreeBounds->getUploadId(), array_keys($agentDetectedLicenses));
    $latestAgentDetectedLicenses = $this->filterDetectedLicenses($agentDetectedLicenses, $latestAgentIdPerAgent);
    return $latestAgentDetectedLicenses;
  }
  
  /**
   * (A->B->C->X, A->B) => C->A->X
   * @param mixed[][][]
   * @param array $agentLatestMap
   * @return mixed[][]
   */
  protected function filterDetectedLicenses($agentDetectedLicenses, $agentLatestMap)
  {
    $latestAgentDetectedLicenses = array();

    foreach ($agentDetectedLicenses as $agentName => $licensesFoundPerAgentId)
    {
      if (!array_key_exists($agentName, $agentLatestMap))
      {
        continue;
      }
      $latestAgentId = $agentLatestMap[$agentName];
      if (!array_key_exists($latestAgentId, $licensesFoundPerAgentId))
      {
        continue;
      }
      foreach ($licensesFoundPerAgentId[$latestAgentId] as $licenseId => $properties)
      {
        $latestAgentDetectedLicenses[$licenseId][$agentName] = $properties;
      }
    }

    return $latestAgentDetectedLicenses;
  }

  /**
   * @param array $details
   * @return LicenseRef[]
   */
  public function getScannedLicenses($details)
  {
    $licenses = array();

    foreach ($details as $licenseId => $agentEntries)
    {
      foreach ($agentEntries as $matchProperties)
      {
        $licenses[$licenseId] = $matchProperties[0]['licenseRef'];
        break;
      }
    }

    return $licenses;
  }
  
  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int
   * @return AgentClearingEvent[][] indexed by LicenseId
   */
  public function getScannerEvents(ItemTreeBounds $itemTreeBounds, $usageId=LicenseMap::TRIVIAL)
  {
    $agentDetails = $this->getScannerDetectedLicenseDetails($itemTreeBounds, $usageId);
    
    $result = array();
    foreach ($agentDetails as $licenseId => $properties)
    {
      $agentClearingEvents = array();
      foreach ($properties as $licenseProperties)
      {
        foreach ($licenseProperties as $licenseProperty)
        {
          $agentClearingEvents[] = $this->createAgentClearingEvent($licenseProperty);
        }
      }
      
      $result[$licenseId] = $agentClearingEvents;
    }
    return $result;
  }
  
  /**
   * @param $licenseProperty
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
