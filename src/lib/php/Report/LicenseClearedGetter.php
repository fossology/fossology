<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Author: Daniele Fognini
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Agent\Agent;

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
  protected $agentNames = AgentRef::AGENT_LIST;

  public function __construct()
  {
    global $container;

    $this->clearingDao = $container->get('dao.clearing');
    $this->licenseDao = $container->get('dao.license');
    $this->agentDao = $container->get('dao.agent');

    parent::__construct($groupBy = 'text');
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $groupId = null)
  {

    echo("getStatements"."\n");

    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId,$uploadTreeTableName);
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId);
    $dbManager = $GLOBALS['container']->get('db.manager');
    $licenseMap = new LicenseMap($dbManager, $groupId, LicenseMap::REPORT);

    echo("clearingDecisions"."\n");
    echo(json_encode($clearingDecisions)."\n");
    echo("licenseMap"."\n");
    echo(json_encode($licenseMap)."\n");

    $ungroupedStatements = array();
    foreach ($clearingDecisions as $clearingDecision) {
      if ($clearingDecision->getType() == DecisionTypes::IRRELEVANT) {
        continue;
      }
      /** @var ClearingDecision $clearingDecision */
      foreach ($clearingDecision->getClearingLicenses() as $clearingLicense) {
        if ($clearingLicense->isRemoved()) {
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

        if ($this->onlyAcknowledgements) {
          $text = $acknowledgement;
          $risk = "";
        } else if ($this->onlyComments) {
          $text = $comment;
          $risk = "";
        } else {
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
   * Override of getCleared() to handle acknowledgement grouping
   * {@inheritDoc}
   * @see Fossology::Lib::Report::ClearedGetterCommon::getCleared()
   */
  public function getCleared($uploadId, $objectAgent, $groupId=null,
    $extended=true, $agentCall=null, $isUnifiedReport=false)
  {
    echo("getCleared begin"."\n");

    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $ungroupedStatements = $this->getStatements($uploadId, $uploadTreeTableName,
      $groupId);

    echo("uploadTreeTableName"."\n");
    echo(json_encode($uploadTreeTableName)."\n");
    echo("ungroupedStatements"."\n");
    echo(json_encode($ungroupedStatements)."\n");

    $this->changeTreeIdsToPaths($ungroupedStatements, $uploadTreeTableName,
      $uploadId);

    echo("ungroupedStatements"."\n");
    echo(json_encode($ungroupedStatements)."\n");
  
    if ($this->onlyAcknowledgements || $this->onlyComments) {
      return $this->groupStatementsSpecial($ungroupedStatements, $objectAgent);
    }
    return $this->groupStatements($ungroupedStatements, $extended, $agentCall,
      $isUnifiedReport, $objectAgent);
  }

  /**
   * Group acknowledgement statements
   * @param array $ungrupedStatements
   * @param Agent $objectAgent
   * @return array
   */
  protected function groupStatementsSpecial($ungrupedStatements, $objectAgent)
  {
    $statements = array();
    $countLoop = 0;
    foreach ($ungrupedStatements as $statement) {
      $licenseId = $statement['licenseId'];
      $content = convertToUTF8($statement['content'], false);
      $content = htmlspecialchars($content, ENT_DISALLOWED);
      $text = convertToUTF8($statement['text'], false);
      $text = htmlspecialchars($text, ENT_DISALLOWED);
      $fileName = $statement['fileName'];

      $statementKey = md5("$content.$text");
      if (!array_key_exists($statementKey, $statements)) {
        $statements[$statementKey] = [
          "licenseId" => $licenseId,
          "content" => $content,
          "text" => $text,
          "files" => [$fileName]
        ];
      } else {
        if (!in_array($fileName, $statements[$statementKey]["files"])) {
          $statements[$statementKey]["files"][] = $fileName;
        }
      }

      //To keep the scheduler alive for large files
      $countLoop += 1;
      if ($countLoop % 500 == 0) {
        $objectAgent->heartbeat(0);
      }
    }
    $statements = array_values($statements);
    usort($statements, function($a, $b) {
      return strnatcmp($a["content"], $b["content"]);
    });
    if (!empty($objectAgent)) {
      $objectAgent->heartbeat(count($statements));
    }
    return array("statements" => array_values($statements));
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
    foreach ($totalLicenses as $licenseShortName) {
      $count = 0;
      if (array_key_exists($licenseShortName, $scannerLicenseHistogram)) {
        $count = $scannerLicenseHistogram[$licenseShortName]['unique'];
      }
      $editedCount = array_key_exists($licenseShortName, $editedLicensesHist) ? $editedLicensesHist[$licenseShortName]['count'] : 0;
      if (strcmp($licenseShortName, LicenseDao::NO_LICENSE_FOUND) !== 0) {
        $LicenseHistArray[] = array("scannerCount" => $count, "editedCount" => $editedCount, "licenseShortname" => $licenseShortName);
      } else {
        $LicenseHistArray[] = array("scannerCount" => $noScannerLicenseFoundCount, "editedCount" => $editedNoLicenseFoundCount, "licenseShortname" => $licenseShortName);
      }
    }
    return $LicenseHistArray;
  }

  /**
    * @brief callback to compare licenses
    * @param array $licenses1
    * @param array $licenses2
    * @return interger difference of license ids
    */
  function checkLicenseId($licenses1, $licenses2)
  {
    return strcmp($licenses1['licenseId'], $licenses2['licenseId']);
  }

  /**
    * @brief Copy identified global licenses
    * @param array $licensesMain
    * @param array $licenses
    * @return array $licensesMain $licenses with identified global license
    */
  function updateIdentifiedGlobalLicenses($licensesMain, $licenses)
  {
    $onlyMainLic = array_udiff($licensesMain, $licenses, array($this, "checkLicenseId"));
    $mainLicensesInIdetifiedFiles = array_uintersect($licenses, $licensesMain, array($this, "checkLicenseId"));
    $onlyLicense = array_udiff($licenses, $licensesMain, array($this, "checkLicenseId"));
    return array(
      array_values(array_merge($onlyMainLic, $mainLicensesInIdetifiedFiles)),
      array_values($onlyLicense)
    );
  }
}
