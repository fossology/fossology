<?php
/*
 SPDX-FileCopyrightText: © 2015-2017,2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\ReportImport;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Db\DbManager;

require_once 'ReportImportConfiguration.php';

class ReportImportSink
{

  /** @var UserDao */
  private $userDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var CopyrightDao */
  private $copyrightDao;
  /** @var DbManager */
  protected $dbManager;

  /** @var int */
  protected $agent_pk = -1;
  /** @var int */
  protected $groupId = -1;
  /** @var int */
  protected $userId = -1;
  /** @var int */
  protected $jobId = -1;
  /** @var bool */
  protected $nserIsAdmin = false;

  /** @var ReportImportConfiguration */
  protected $configuration;

  /** @var array Cache for license ID lookups to avoid redundant DB queries */
  private $licenseCreationCache = array();

  /**
   * ReportImportSink constructor.
   * @param $agent_pk
   * @param $userDao
   * @param $licenseDao
   * @param $clearingDao
   * @param $copyrightDao
   * @param $dbManager
   * @param $groupId
   * @param $userId
   * @param $jobId
   * @param $configuration
   */
  function __construct($agent_pk, $userDao, $licenseDao, $clearingDao, $copyrightDao, $dbManager, $groupId, $userId, $jobId, $configuration)
  {
    $this->userDao = $userDao;
    $this->clearingDao = $clearingDao;
    $this->licenseDao = $licenseDao;
    $this->copyrightDao = $copyrightDao;
    $this->dbManager = $dbManager;
    $this->agent_pk = $agent_pk;
    $this->groupId = $groupId;
    $this->userId = $userId;
    $this->jobId = $jobId;

    $this->configuration = $configuration;

    $userRow = $userDao->getUserByPk($userId);
    $this->userIsAdmin = $userRow["user_perm"] >= PLUGIN_DB_ADMIN;
  }

  /**
   * @param ReportImportData $data
   */
  public function handleData($data)
  {
    $pfiles = $data->getPfiles();
    if(sizeof($pfiles) === 0)
    {
      return;
    }

    if($this->configuration->isCreateLicensesInfosAsFindings() ||
       $this->configuration->isCreateConcludedLicensesAsFindings() ||
       $this->configuration->isCreateConcludedLicensesAsConclusions())
    {
      $licenseInfosInFile = $data->getLicenseInfosInFile();
      $licensesConcluded = $data->getLicensesConcluded();

      $licensePKsInFile = array();
      foreach($licenseInfosInFile as $dataItem)
      {
        if (strcasecmp($dataItem->getLicenseId(), "noassertion") == 0)
        {
          continue;
        }
        $licenseId = $this->getIdForDataItemOrCreateLicense($dataItem, $this->groupId);
        $licensePKsInFile[] = $licenseId;
      }

      $licensePKsConcluded = array();
      foreach ($licensesConcluded as $dataItem)
      {
        if (strcasecmp($dataItem->getLicenseId(), "noassertion") == 0)
        {
          continue;
        }
        $licenseId = $this->getIdForDataItemOrCreateLicense($dataItem, $this->groupId);
        $licensePKsConcluded[$licenseId] = $dataItem->getCustomText();
      }

      $this->insertLicenseInformationToDB($licensePKsInFile, $licensePKsConcluded, $pfiles);
    }

    if($this->configuration->isAddCopyrightInformation())
    {
      $this->insertFoundCopyrightTextsToDB($data->getCopyrightTexts(),
        $data->getPfiles());
    }
  }

  /**
   * @param ReportImportDataItem $dataItem
   * @param $groupId
   * @return int
   * @throws \Exception
   */
  public function getIdForDataItemOrCreateLicense($dataItem, $groupId)
  {
    $licenseShortName = $dataItem->getLicenseId();
    
    // Check cache first to avoid redundant DB queries
    if (isset($this->licenseCreationCache[$licenseShortName])) {
      return $this->licenseCreationCache[$licenseShortName];
    }
    
    // Try to find existing license
    if ($this->configuration->shouldMatchLicenseNameWithSPDX()) {
      $license = $this->licenseDao->getLicenseBySpdxId($licenseShortName, $groupId);
      if ($license === null) {
        echo "WARNING: Could not find license with spdx id '$licenseShortName' ... trying ShortName\n";
        $license = $this->licenseDao->getLicenseByShortName($licenseShortName, $groupId);
      }
    } else {
      $license = $this->licenseDao->getLicenseByShortName($licenseShortName, $groupId);
    }
    
    if ($license !== null)
    {
      $licenseId = $license->getId();
      $this->licenseCreationCache[$licenseShortName] = $licenseId;
      return $licenseId;
    }
    elseif (! $this->licenseDao->isNewLicense($licenseShortName, $groupId))
    {
      throw new \Exception('shortname already in use');
    }
    elseif ($dataItem->isSetLicenseCandidate())
    {
      echo "INFO: No license with shortname=\"$licenseShortName\" found ... ";

      $licenseCandidate = $dataItem->getLicenseCandidate();
      if($this->configuration->isCreateLicensesAsCandidate() || !$this->userIsAdmin)
      {
        echo "Creating it as license candidate ...\n";
        $licenseId = $this->licenseDao->insertUploadLicense($licenseShortName,
          $licenseCandidate->getText(), $groupId, $this->userId);
        $this->licenseDao->updateCandidate(
          $licenseId,
          $licenseCandidate->getShortName(),
          $licenseCandidate->getFullName(),
          $licenseCandidate->getText(),
          $licenseCandidate->getUrl(),
          "Created for ReportImport with jobId=[".$this->jobId."]",
          date(DATE_ATOM),
          $this->userId,
          false,
          0,
          null
        );
        $this->licenseCreationCache[$licenseShortName] = $licenseId;
        return $licenseId;
      }
      else
      {
        echo "creating it as license ...\n";
        $licenseText = trim($licenseCandidate->getText());
        $licenseId = $this->licenseDao->insertLicense($licenseCandidate->getShortName(), $licenseText, $licenseCandidate->getShortName());
        $this->licenseCreationCache[$licenseShortName] = $licenseId;
        return $licenseId;
      }
    }
    elseif ($dataItem->isSetCustomText())
    {
      // NEW: Auto-create license from custom text when candidate is not set
      echo "INFO: No license candidate set for \"$licenseShortName\", attempting auto-creation from custom text ... ";
      
      // Infer license name from shortname (convert dashes/underscores to spaces, capitalize)
      $inferredName = ucwords(str_replace(array('-', '_'), ' ', $licenseShortName));
      $customText = $dataItem->getCustomText();
      
      if($this->configuration->isCreateLicensesAsCandidate() || !$this->userIsAdmin)
      {
        echo "Creating as license candidate ...\n";
        $licenseId = $this->licenseDao->insertUploadLicense(
          $licenseShortName,
          $customText,
          $groupId,
          $this->userId
        );
        
        $this->licenseDao->updateCandidate(
          $licenseId,
          $licenseShortName,
          $inferredName,
          $customText,
          "",
          "Auto-created from SPDX import custom text (Job: {$this->jobId})",
          date(DATE_ATOM),
          $this->userId,
          false,
          0,
          null
        );
        
        $this->licenseCreationCache[$licenseShortName] = $licenseId;
        return $licenseId;
      }
      else
      {
        echo "Creating as full license ...\n";
        $licenseId = $this->licenseDao->insertLicense($licenseShortName, $customText, $licenseShortName);
        $this->licenseCreationCache[$licenseShortName] = $licenseId;
        return $licenseId;
      }
    }
    
    echo "WARNING: Could not resolve or create license \"$licenseShortName\" (no candidate or custom text available)\n";
    return -1;
  }

  /**
   * @param array $licensePKsInFile
   * @param array $licensePKsConcluded
   * @param array $pfiles
   */
  private function insertLicenseInformationToDB($licensePKsInFile, $licensePKsConcluded, $pfiles)
  {
    if($this->configuration->isCreateLicensesInfosAsFindings())
    {
      $this->saveAsLicenseFindingToDB($licensePKsInFile, $pfiles);
    }

    if($this->configuration->isCreateConcludedLicensesAsFindings())
    {
      $this->saveAsLicenseFindingToDB(array_keys($licensePKsConcluded), $pfiles);
    }

    if($this->configuration->isCreateConcludedLicensesAsConclusions())
    {
      $removeLicenseIds = array();
      foreach ($licensePKsInFile as $licenseId)
      {
        if(! array_key_exists($licenseId,$licensePKsConcluded))
        {
          $removeLicenseIds[] = $licenseId;
        }
      }
      $this->saveAsDecisionToDB($licensePKsConcluded, $removeLicenseIds, $pfiles);
    }
  }

  /**
   * @param array $addLicenseIds
   * @param array $removeLicenseIds
   * @param array $pfiles
   */
  private function saveAsDecisionToDB($addLicenseIds, $removeLicenseIds, $pfiles)
  {
    if(sizeof($addLicenseIds) == 0)
    {
      return;
    }

    foreach ($pfiles as $pfile)
    {
      $eventIds = array();
      foreach ($addLicenseIds as $licenseId => $licenseText)
      {
        // echo "add decision $licenseId to " . $pfile['uploadtree_pk'] . "\n";
        $eventIds[] = $this->clearingDao->insertClearingEvent(
          $pfile['uploadtree_pk'],
          $this->userId,
          $this->groupId,
          $licenseId,
          false,
          ClearingEventTypes::IMPORT,
          trim($licenseText),
          '', // comment
          '', // ack
          $this->jobId);
      }
      foreach ($removeLicenseIds as $licenseId)
      {
        // echo "remove decision $licenseId from " . $pfile['uploadtree_pk'] . "\n";
        $eventIds[] = $this->clearingDao->insertClearingEvent(
          $pfile['uploadtree_pk'],
          $this->userId,
          $this->groupId,
          $licenseId,
          true,
          ClearingEventTypes::IMPORT,
          $licenseText,
          '', // comment
          '', // ack
          $this->jobId);
      }
      $this->clearingDao->createDecisionFromEvents(
        $pfile['uploadtree_pk'],
        $this->userId,
        $this->groupId,
        $this->configuration->getConcludeLicenseDecisionType(),
        DecisionScopes::ITEM,
        $eventIds);
    }
  }

  /**
   * @param array $licenseIds
   * @param array $pfiles
   */
  private function saveAsLicenseFindingToDB($licenseIds, $pfiles)
  {
    foreach ($pfiles as $pfile)
    {
      foreach($licenseIds as $licenseId)
      {
        $this->dbManager->getSingleRow(
          "INSERT INTO license_file (rf_fk, agent_fk, pfile_fk) VALUES ($1,$2,$3) RETURNING fl_pk",
          array($licenseId, $this->agent_pk, $pfile['pfile_pk']),
          __METHOD__."forReportImport");
      }
    }
  }

  public function insertFoundCopyrightTextsToDB($copyrightTexts, $entries)
  {
    foreach ($copyrightTexts as $copyrightText)
    {
      $this->insertFoundCopyrightTextToDB($copyrightText, $entries);
    }
  }

  public function insertFoundCopyrightTextToDB($copyrightText, $entries)
  {
    $copyrightLines = array_map("trim", explode("\n",$copyrightText));
    foreach ($copyrightLines as $copyrightLine)
    {
      if(empty($copyrightLine))
      {
        continue;
      }

      foreach ($entries as $entry)
      {
        $this->saveAsCopyrightFindingToDB(trim($copyrightLine), $entry['pfile_pk']);
      }
    }
  }

  private function saveAsCopyrightFindingToDB($content, $pfile_fk)
  {
    $curDecisions = $this->copyrightDao->getDecisions("copyright_decision", $pfile_fk);
    foreach ($curDecisions as $decision)
    {
      if($decision['textfinding'] == $content){
        return;
      }
    }

    $this->copyrightDao->saveDecision("copyright_decision", $pfile_fk, $this->userId , DecisionTypes::IDENTIFIED,
      "", $content, "imported via reportImport");
  }
}
