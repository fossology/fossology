<?php
/*
 Copyright (C) 2014-2017, Siemens AG
 Author: Daniele Fognini

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

namespace Fossology\Lib\Report;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Data\License;

class LicenseClearedGetter extends ClearedGetterCommon
{
  /** @var Boolean */
  private $onlyComments = false;
  /** @var Boolean */
  private $onlyAcknowledgements = false;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var AgentDao */
  private $agentDao;
  /** @var string[] */
  private $licenseCache = array();
  /** @var agentNames */
  protected $agentNames = array('nomos' => 'N', 'monk' => 'M', 'ninka' => 'Nk');

  public function __construct() {
    global $container;

    $this->clearingDao = $container->get('dao.clearing');
    $this->licenseDao = $container->get('dao.license');
    $this->agentDao = $container->get('dao.agent');

    parent::__construct($groupBy = 'text');
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $groupId = null)
  {
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId,$uploadTreeTableName);
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId);
    $dbManager = $GLOBALS['container']->get('db.manager');
    $licenseMap = new LicenseMap($dbManager, $groupId, LicenseMap::REPORT);
    $ungroupedStatements = array();
    foreach ($clearingDecisions as $clearingDecision) {
      if($clearingDecision->getType() == DecisionTypes::IRRELEVANT)
      {
        continue;
      }
      /** @var ClearingDecision $clearingDecision */
      foreach ($clearingDecision->getClearingLicenses() as $clearingLicense) {
        if ($clearingLicense->isRemoved()){
          continue;
        }
        
        if ($this->onlyComments && !($comment = $clearingLicense->getComment())) {
          continue;
        }

        if ($this->onlyAcknowledgements && !($acknowledgement = $clearingLicense->getAcknowledgement())) {
          continue;
        }

        $originLicenseId = $clearingLicense->getLicenseId();
        $licenseId = $licenseMap->getProjectedId($originLicenseId);

        if($this->onlyAcknowledgements){
          $text = $acknowledgement;
          $risk = "";
        }
        else if ($this->onlyComments)
        {
          $text = $comment;
          $risk = "";
        }
        else
        {
          $reportInfo = $clearingLicense->getReportInfo();
          $text = $reportInfo ? : $this->getCachedLicenseText($licenseId, "any");
          $risk = $this->getCachedLicenseRisk($licenseId, $groupId);
        }

        $ungroupedStatements[] = array(
          'licenseId' => $licenseId,
          'risk' => $risk, 
          'content' => $licenseMap->getProjectedShortname($originLicenseId, $clearingLicense->getShortName()),
          'uploadtree_pk' => $clearingDecision->getUploadTreeId(),
          'text' => $text
        );
      }
    }

    return $ungroupedStatements;
  }
  
  /**
   * @param boolean $displayOnlyCommentedLicenseClearings
   */
  public function setOnlyComments($displayOnlyCommentedLicenseClearings)
  {
    $this->onlyAcknowledgements = false;
    $this->onlyComments = $displayOnlyCommentedLicenseClearings;
  }

  /**
   * @param boolean $displayOnlyAcknowledgements
   */
  public function setOnlyAcknowledgements($displayOnlyAcknowledgements)
  {
    $this->onlyComments = false;
    $this->onlyAcknowledgements = $displayOnlyAcknowledgements;
  }

  /**
   * @param int $licenseId
   * @return License
   */
  protected function getCachedLicenseText($licenseId, $groupId)
  {
    if (!array_key_exists($licenseId, $this->licenseCache)) {
      $this->licenseCache[$licenseId] = $this->licenseDao->getLicenseById($licenseId, $groupId);
    }
    return $this->licenseCache[$licenseId]->getText();
  }

  /**
   * @param int $licenseId, $groupId
   * @return Risk
   */
  protected function getCachedLicenseRisk($licenseId, $groupId)
  {
    if (!array_key_exists($licenseId, $this->licenseCache)) {
      $this->licenseCache[$licenseId] = $this->licenseDao->getLicenseById($licenseId, $groupId);
    }
    return $this->licenseCache[$licenseId]->getRisk();
  }
  /**
   * @param int $uploadId, $groupId
   * @return scannerLicenseHistogram, editedLicensesHist
   */
  protected function getHistogram($uploadId, $groupId)
  {
    $LicenseHistArray = array();
    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($this->agentDao, $uploadId);
    $scannerVars = $scanJobProxy->createAgentStatus($scannerAgents);
    $allAgentIds = $scanJobProxy->getLatestSuccessfulAgentIds();
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId);
    $scannerLicenseHistogram = $this->licenseDao->getLicenseHistogram($itemTreeBounds, $allAgentIds);
    $editedLicensesHist = $this->clearingDao->getClearedLicenseIdAndMultiplicities($itemTreeBounds, $groupId);
    $noScannerLicenseFoundCount = array_key_exists(LicenseDao::NO_LICENSE_FOUND, $scannerLicenseHistogram)
            ? $scannerLicenseHistogram[LicenseDao::NO_LICENSE_FOUND]['count'] : 0;
    $editedNoLicenseFoundCount = array_key_exists(LicenseDao::NO_LICENSE_FOUND, $editedLicensesHist)
            ? $editedLicensesHist[LicenseDao::NO_LICENSE_FOUND]['count'] : 0;

    $totalLicenses = array_unique(array_merge(array_keys($scannerLicenseHistogram), array_keys($editedLicensesHist)));
    foreach($totalLicenses as $licenseShortName){
      if (array_key_exists($licenseShortName, $scannerLicenseHistogram)){
        $count = $scannerLicenseHistogram[$licenseShortName]['unique'];
      }
      $editedCount = array_key_exists($licenseShortName, $editedLicensesHist) ? $editedLicensesHist[$licenseShortName]['count'] : 0;
      if(strcmp($licenseShortName, LicenseDao::NO_LICENSE_FOUND) !== 0){
        $LicenseHistArray[] = array("scannerCount" => $count, "editedCount" => $editedCount, "licenseShortname" => $licenseShortName);
      }else{
        $LicenseHistArray[] = array("scannerCount" => $noScannerLicenseFoundCount, "editedCount" => $editedNoLicenseFoundCount, "licenseShortname" => $licenseShortName);
      }
    }
    return $LicenseHistArray;
  }
}
