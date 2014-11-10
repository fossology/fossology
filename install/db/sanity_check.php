<?php
/***********************************************************
 Copyright (C) 2014 Siemens AG

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
 ***********************************************************/

use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Data\LicenseDecision\LicenseEventTypes;
use Fossology\Lib\Db\DbManager;

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

  public function check()
  {
    $this->checkDecisionScopes();
    $this->checkUploadStatus();
    $this->checkLicenseEventTypes();
    return $this->errors;
  }

  private function checkDecisionScopes()
  {
    $decScopes = new DecisionScopes();
    $scopeMap = $decScopes->getMap();
    $this->errors += $this->checkDatabaseEnum($tablename = 'clearing_decision', 'scope', $scopeMap);
    $decTypes = new DecisionTypes();
    $typeMap = $decTypes->getExtendedMap();
    $this->errors += $this->checkDatabaseEnum($tablename = 'clearing_decision', 'decision_type', $typeMap);
  }

  private function checkUploadStatus()
  {
    $uploadStatus = new UploadStatus();
    $statusMap = $uploadStatus->getMap();
    $this->errors += $this->checkDatabaseEnum($tablename = 'upload_status', 'status_pk', $statusMap);
  }

  private function checkLicenseEventTypes()
  {
    $licenseEventTypes = new LicenseEventTypes();
    $map = $licenseEventTypes->getMap();
    $this->errors += $this->checkDatabaseEnum($tablename='license_decision_event', 'type_fk', $map);
  }
  
  /**
   * 
   * @param string $tablename
   * @param string $columnname
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

}
