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
use Fossology\Lib\Dao\FileTreeBounds;
use Fossology\Lib\Dao\LicenseDao;

class ClearingDecisionEventProcessor {

  /** @var LicenseDao */
  private $licenseDao;

  /** @var AgentsDao */
  private $agentsDao;

  /** @var ClearingDao */
  private $clearingDao;

  public function __construct($licenseDao, $agentsDao, $clearingDao) {
    $this->licenseDao = $licenseDao;
    $this->agentsDao = $agentsDao;
    $this->clearingDao = $clearingDao;
  }

  /**
   * @param FileTreeBounds $fileTreeBounds
   * @return array
   */
  public function getAgentDetectedLicenses(FileTreeBounds $fileTreeBounds)
  {
    $agentDetectedLicenses = array();

    $licenseFileMatches = $this->licenseDao->getAgentFileLicenseMatches($fileTreeBounds);
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
          'matchId' => $licenseMatch->getLicenseFileId(),
          'percent' => $licenseMatch->getPercent()
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
   * @param FileTreeBounds $fileTreeBounds
   * @param int $userId
   * @return array
   */
  public function getCurrentLicenseDecisions(FileTreeBounds $fileTreeBounds, $userId) {
    $uploadTreeId = $fileTreeBounds->getUploadTreeId();
    $uploadId = $fileTreeBounds->getUploadId();

    $agentDetectedLicenses = $this->getAgentDetectedLicenses($fileTreeBounds);

    $agentLatestMap = $this->getLatestAgents($agentDetectedLicenses, $uploadId);

    list($addedLicenses, $removedLicenses) = $this->clearingDao->getCurrentLicenseDecision($userId, $uploadTreeId);

    $currentLicenses = array_unique(array_merge(array_keys($addedLicenses), array_keys($agentDetectedLicenses)));

    $licenseDecisions = array();
    $removed = array();
    foreach ($currentLicenses as $licenseShortName)
    {
      $entries = array();
      $licenseId = 0;

      if (array_key_exists($licenseShortName, $addedLicenses))
      {
        $addedLicense = $addedLicenses[$licenseShortName];
        $entries['direct'] = $addedLicense;
        $licenseId = $addedLicense['licenseId'];
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
            $matches = array();
            $index = 1;
            foreach ($licenseProperties as $licenseProperty)
            {
              $licenseId = $licenseProperty['id'];
              $match = array(
                'agentId' => $agentId,
                'matchId' => $licenseProperty['matchId'],
                'index' => $index++,
              );

              if (array_key_exists('percentage', $licenseProperty))
              {
                $match['percentage'] = $licenseProperty['percentage'];
              }
              $matches[] = $match;
            }
            $entries['agents'][] = array(
              'name' => $agentName,
              'matches' => $matches,
              'properties' => $licenseProperties
            );
          }
        }
      }

      $licenseResult = array(
          'licenseId' => $licenseId,
          'entries' => $entries
      );
      if (array_key_exists($licenseShortName, $removedLicenses))
      {
        $removed[$licenseShortName] = $licenseResult;
      } else {
        $licenseDecisions[$licenseShortName] = $licenseResult;
      }
    }
    return array($licenseDecisions, $removed);
  }



}