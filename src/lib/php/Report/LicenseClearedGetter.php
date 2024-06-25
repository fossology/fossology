<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Author: Daniele Fognini
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Agent\Agent;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\License;
use Fossology\Lib\Proxy\ScanJobProxy;

class LicenseClearedGetter extends ClearedGetterCommon
{
  /** @var Boolean */
  private $onlyComments = false;
  /** @var Boolean */
  private $onlyAcknowledgements = false;
  /** @var Boolean */
  private $onlyExpressions = false;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var AgentDao */
  private $agentDao;
  /** @var string[] */
  private $licenseCache = array();
  /** @var array $agentNames */
  protected $agentNames = AgentRef::AGENT_LIST;

  public function __construct()
  {
    global $container;

    $this->clearingDao = $container->get('dao.clearing');
    $this->licenseDao = $container->get('dao.license');
    $this->agentDao = $container->get('dao.agent');

    parent::__construct($groupBy = 'text');
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $groupId = null, $includeExpressions=false)
  {
    $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId,$uploadTreeTableName);
    $clearingDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId, true, true, true);
    $dbManager = $GLOBALS['container']->get('db.manager');
    $licenseMap = new LicenseMap($dbManager, $groupId, LicenseMap::REPORT);
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

        $comment = $clearingLicense->getComment();
        if ($this->onlyComments && !($comment)) {
          continue;
        }

        $acknowledgement = $clearingLicense->getAcknowledgement();
        if ($this->onlyAcknowledgements && !($acknowledgement)) {
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
          $acknowledgement = $clearingLicense->getAcknowledgement();
        }
        if ($clearingLicense->getSpdxId() === 'LicenseRef-fossology-License-Expression') {
          if ($includeExpressions) {
            if (empty($text)) {
              $text = 'License Expression';
            }
            $ungroupedStatements[] = array(
              'licenseId' => $originLicenseId,
              'risk' => $risk,
              'content' => $clearingLicense->getLicenseRef()->getExpression($this->licenseDao, $groupId),
              'uploadtree_pk' => $clearingDecision->getUploadTreeId(),
              'text' => $text,
              'acknowledgement' => $acknowledgement
            );
          }
          continue;
        }
        if (!$this->onlyExpressions) {
          $ungroupedStatements[] = array(
            'licenseId' => $licenseId,
            'risk' => $risk,
            'content' => $licenseMap->getProjectedSpdxId(
                $originLicenseId, $clearingLicense->getSpdxId()),
            'uploadtree_pk' => $clearingDecision->getUploadTreeId(),
            'text' => $text,
            'acknowledgement' => $acknowledgement
          );
        }
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
    $extended=true, $agentCall=null, $isUnifiedReport=false, $includeExpressions=false)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $ungroupedStatements = $this->getStatements($uploadId, $uploadTreeTableName,
      $groupId, $includeExpressions);
    $this->changeTreeIdsToPaths($ungroupedStatements, $uploadTreeTableName,
      $uploadId);
    if ($this->onlyAcknowledgements || $this->onlyComments || $this->onlyExpressions) {
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
   * @param boolean $displayOnlyAcknowledgements
   */
  public function setOnlyExpressions($displayOnlyExpressions)
  {
    $this->onlyExpressions = $displayOnlyExpressions;
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

    if ($this->licenseCache[$licenseId] !== null) {
       return $this->licenseCache[$licenseId]->getText();
    } else {
       return null;
    }
  }

  /**
   * @param int $licenseId, $groupId
   * @return int|string
   */
  protected function getCachedLicenseRisk($licenseId, $groupId)
  {
    if (!array_key_exists($licenseId, $this->licenseCache)) {
      $this->licenseCache[$licenseId] = $this->licenseDao->getLicenseById($licenseId, $groupId);
    }

    if ($this->licenseCache[$licenseId] !== null) {
      return $this->licenseCache[$licenseId]->getRisk();
    } else {
      return null;
    }
  }

  /**
   * @param int $uploadId, $groupId
   * @return array scannerLicenseHistogram, editedLicensesHist
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

      if (array_key_exists($licenseShortName, $scannerLicenseHistogram)) {
        $licenseReportId = $scannerLicenseHistogram[$licenseShortName]['spdx_id'];
      } else {
        $licenseReportId = $editedLicensesHist[$licenseShortName]['spdx_id'];
      }

      if (strcmp($licenseShortName, LicenseDao::NO_LICENSE_FOUND) !== 0) {
        $LicenseHistArray[] = array("scannerCount" => $count, "editedCount" => $editedCount, "licenseShortname" => $licenseReportId);
      } else {
        $LicenseHistArray[] = array("scannerCount" => $noScannerLicenseFoundCount, "editedCount" => $editedNoLicenseFoundCount, "licenseShortname" => $licenseReportId);
      }
    }
    return $LicenseHistArray;
  }

  /**
    * @brief callback to compare licenses
    * @param array $licenses1
    * @param array $licenses2
    * @return int difference of license ids
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
    $mainLicensesInIdentifiedFiles = array_uintersect($licenses, $licensesMain, array($this, "checkLicenseId"));
    $onlyLicense = array_udiff($licenses, $licensesMain, array($this, "checkLicenseId"));
    return array(
      array_values(array_merge($onlyMainLic, $mainLicensesInIdentifiedFiles)),
      array_values($onlyLicense)
    );
  }
}
