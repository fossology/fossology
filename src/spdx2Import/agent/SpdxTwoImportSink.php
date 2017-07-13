<?php
/*
 * Copyright (C) 2015-2017, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace Fossology\SpdxTwoImport;

use Fossology\Lib\Dao\LicenseDao;
use Fossology\SpdxTwoImport\SpdxTwoImportHelper;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;

class SpdxTwoImportSink
{

  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var DbManager */
  protected $dbManager;

  private $agent_pk;

  function __construct($agent_pk, $licenseDao, $clearingDao, $dbManager)
  {
    $this->licenseDao = $licenseDao;
    $this->clearingDao = $clearingDao;
    $this->dbManager = $dbManager;
    $this->agent_pk = $agent_pk;
  }

  // public function insertFoundLicenseConclusionToDB(&$licenseExpressions, &$entries,
  //                                                  $userId=0, $groupId=0, $jobId=0)
  // {
  //   $this->insertFoundLicenseInfoToDB($licenseExpressions, $entries, true, $userId, $groupId, $jobId);
  // }

  public function getIdForLicenseOrCreateIt($licenseShortName, $licenseCandidate, $groupId)
  {
    $license = $this->licenseDao->getLicenseByShortName($licenseShortName, $groupId);
    if ($license !== null)
    {
      return $license->getId();
    }
    elseif (! $this->licenseDao->isNewLicense($licenseShortName, $groupId))
    {
      throw new \Exception('shortname already in use');
    }
    elseif ($licenseCandidate)
    {
      echo "INFO: No license with shortname=\"$licenseShortName\" found ... ";
      if(true) // TODO
      {
        echo "Creating it as license candidate ...\n";
        $licenseId = $this->licenseDao->insertUploadLicense($licenseShortName, $licenseCandidate->getText(), $groupId);
        $this->licenseDao->updateCandidate(
          $licenseId,
          $licenseCandidate->getShortName(),
          $licenseCandidate->getFullName(),
          $licenseCandidate->getText(),
          $licenseCandidate->getUrl(),
          "Created for Spdx2Import",
          false,
          0);
        return $licenseId;
      }
      else
      {
        echo "creating it as license ...\n";
        return $this->dbManager->getSingleRow(
          "INSERT INTO license_ref (rf_shortname, rf_text, rf_detector_type, rf_spdx_compatible) VALUES ($1, $2, 2, $3) RETURNING rf_pk",
          array($licenseCandidate->getShortName(), $licenseCandidate->getText(), $licenseCandidate->getSpdxCompatible()),
          __METHOD__.".addLicense" )[0];
      }

    }
    return -1;
  }

  public function insertFoundLicenseInfoToDB(&$licenseExpressions, &$entries, $groupId,
                                             $asConclusion=false, $userId=0, $jobId=0)
  {
    foreach ($licenseExpressions as $licenseShortName => $licenseCandidate)
    {
      if (strcasecmp($licenseShortName, "noassertion") == 0 || sizeof($entries) == 0)
      {
        continue;
      }

      $licenseId = $this->getIdForLicenseOrCreateIt($licenseShortName, $licenseCandidate, $groupId);
      if ($licenseId == -1)
      {
        continue;
      }

      foreach ($entries as $entry)
      {
        if ($asConclusion && $userId && $groupId && $jobId)
        {
          $this->saveAsLicenseConclutionToDB($licenseId, $entry,
                                             $userId, $groupId, $jobId);
        }
        $this->saveAsLicenseFindingToDB($licenseId, $entry['pfile_pk']);
      }
    }
  }

  private function saveAsLicenseConclutionToDB($licenseId, $entry,
                                               $userId, $groupId, $jobId)
  {
    echo "add decision $licenseId to " . $entry['uploadtree_pk'] . "\n";
    $eventId = $this->clearingDao->insertClearingEvent(
      $entry['uploadtree_pk'],
      $userId,
      $groupId,
      $licenseId,
      false,
      ClearingEventTypes::IMPORT,
      '', // reportInfo
      'Imported from SPDX2 report', // comment
      $jobId);
    $this->clearingDao->createDecisionFromEvents(
      $entry['uploadtree_pk'],
      $userId,
      $groupId,
      DecisionTypes::IDENTIFIED,
      DecisionScopes::ITEM,
      [$eventId]);
  }

  private function saveAsLicenseFindingToDB($licenseId, $pfile_fk)
  {
    return $this->dbManager->getSingleRow(
      "INSERT INTO license_file (rf_fk, agent_fk, pfile_fk) VALUES ($1,$2,$3) RETURNING fl_pk",
      array($licenseId, $this->agent_pk, $pfile_fk),
      __METHOD__."forSpdx2Import");
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
        $this->saveAsCopyrightFindingToDB($copyrightLine, $entry['pfile_pk']);
      }
    }
  }

  private function saveAsCopyrightFindingToDB($content, $pfile_fk)
  {
    return $this->dbManager->getSingleRow(
      "insert into copyright(agent_fk, pfile_fk, content, hash, type) values($1,$2,$3,md5($3),$4) RETURNING ct_pk",
      array($this->agent_pk, $pfile_fk, $content, "statement"),
      __METHOD__."forSpdx2Import");
  }
}
