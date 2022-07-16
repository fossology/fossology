<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Db\DbManager;

/**
 * @file sanity_check.php
 * Check the sanity of the database tables
 */

/**
 * @class SanityChecker Check sanity of the database
 */
class SanityChecker
{
  /** @var DbManager */
  protected $dbManager;
  /** @var bool */
  protected $verbose;
  /** @var int */
  protected $errors = 0;
  
  function __construct(&$dbManager,$verbose)
  {
    $this->dbManager = $dbManager;
    $this->verbose = $verbose;
  }

  /**
   * @brief Check the sanity of decision, upload status, License Event types
   *        license_candidate table and ensures Top level folder for each user.
   *
   * @return int error code; 0 on success
   */
  public function check()
  {
    $this->checkDecisionScopes();
    $this->checkUploadStatus();
    $this->checkLicenseEventTypes();
    $this->checkExistsTable('license_candidate');
    $folderDao = new FolderDao($this->dbManager, $GLOBALS['container']->get('dao.user'), $GLOBALS['container']->get('dao.upload'));
    $folderDao->ensureTopLevelFolder();
    
    return $this->errors;
  }

  /**
   * @brief Check if clearing_decision have proper values in scope and decision_type columns
   */
  private function checkDecisionScopes()
  {
    $decScopes = new DecisionScopes();
    $scopeMap = $decScopes->getMap();
    $this->errors += $this->checkDatabaseEnum($tablename = 'clearing_decision', 'scope', $scopeMap);
    $decTypes = new DecisionTypes();
    $typeMap = $decTypes->getExtendedMap();
    $this->errors += $this->checkDatabaseEnum($tablename = 'clearing_decision', 'decision_type', $typeMap);
  }

  /**
   * @brief Check if upload_clearing have proper values in status_fk column
   */
  private function checkUploadStatus()
  {
    $uploadStatus = new UploadStatus();
    $statusMap = $uploadStatus->getMap();
    $this->errors += $this->checkDatabaseEnum($tablename = 'upload_clearing', 'status_fk', $statusMap);
  }

  /**
   * @brief Check if clearing_event have proper values in type_fk column
   */
  private function checkLicenseEventTypes()
  {
    $licenseEventTypes = new ClearingEventTypes();
    $map = $licenseEventTypes->getMap();
    $this->errors += $this->checkDatabaseEnum($tablename='clearing_event', 'type_fk', $map);
  }
  
  /**
   * @brief Check if every values in given column are values from the given map
   * @param string $tablename Table in which the values have to be looked upon
   * @param string $columnname Name of column to check values
   * @param array $map using keys
   * @return int
   */
  private function checkDatabaseEnum($tablename,$columnname,$map)
  {
    $errors = 0;
    $stmt = __METHOD__.".$tablename.$columnname";
    $sql = "SELECT $columnname,count(*) FROM $tablename GROUP BY $columnname";
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt);
    while($row = $this->dbManager->fetchArray($res))
    {
      if(!array_key_exists($row[$columnname], $map))
      {
        echo "(-) found invalid $columnname '".$row[$columnname]."' in table '$tablename'\n";
        $errors++;
      }
      else if($this->verbose)
      {
        echo "(+) found valid $columnname '".$row[$columnname]."' in table '$tablename'\n";
      }
    }
    $this->dbManager->freeResult($res);
    return $errors;
  }
  
  /**
   * @biref Check if table exists in database
   * @param string $tableName Name of table to be checked
   * @return int
   */
  private function checkExistsTable($tableName)
  {
    $error = intval(!$this->dbManager->existsTable($tableName));
    if($error){
      echo "(-) table $tableName does not exists";
    }
    else if($this->verbose)
    {
      echo "(+) table $tableName exists";
    }
    $this->errors += $error;
  }
}
