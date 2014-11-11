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
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Util\Object;

class AgentLicenseEventProcessor extends Object
{

  /** @var LicenseDao */
  private $licenseDao;

  /** @var AgentsDao */
  private $agentsDao;

  public function __construct(LicenseDao $licenseDao, AgentsDao $agentsDao)
  {
    $this->licenseDao = $licenseDao;
    $this->agentsDao = $agentsDao;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return LicenseRef[]
   */
  public function getScannerDetectedLicenses(ItemTreeBounds $itemTreeBounds)
  {
    $details = $this->getScannerDetectedLicenseDetails($itemTreeBounds);

    return $this->getScannedLicenses($details);
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return array
   */
  public function getScannerDetectedLicenseDetails(ItemTreeBounds $itemTreeBounds)
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

      $agentDetectedLicenses[$agentName][$agentId][$licenseShortName][] = array(
          'id' => $licenseRef->getId(),
          'licenseRef' => $licenseRef,
          'agentRef' => $agentRef,
          'matchId' => $licenseMatch->getLicenseFileId(),
          'percentage' => $licenseMatch->getPercentage()
      );
    }

    $latestAgentIdPerAgent = $this->agentsDao->getLatestAgentResultForUpload($itemTreeBounds->getUploadId(), array_keys($agentDetectedLicenses));
    $latestAgentDetectedLicenses = $this->filterDetectedLicenses($agentDetectedLicenses, $latestAgentIdPerAgent);
    return $latestAgentDetectedLicenses;
  }

  /**
   * (A->B->C->X, A->B) => C->A->X
   * @param array [][][]
   * @param array $agentLatestMap
   * @return array[][]
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
      foreach ($licensesFoundPerAgentId[$latestAgentId] as $licenseShortName => $properties)
      {
        $latestAgentDetectedLicenses[$licenseShortName][$agentName] = $properties;
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

    foreach ($details as $licenseShortName => $agentEntries)
    {
      foreach ($agentEntries as $agentName => $matchProperties)
      {
        $licenses[$licenseShortName] = $matchProperties[0]['licenseRef'];
        break;
      }
    }

    return $licenses;
  }
}
