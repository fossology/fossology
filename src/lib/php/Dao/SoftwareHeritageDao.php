<?php
/*
 Copyright (C) 2019
 Author: Sandip Kumar Bhuyan<sandipbhuyan@gmail.com>

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

namespace Fossology\Lib\Dao;

use  Fossology\Lib\Db\DbManager;
use Monolog\Logger;

/**
 * Class SoftwareHeritageDao
 * @package Fossology\Lib\Dao
 */
class SoftwareHeritageDao
{

  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var UploadDao */
  private $uploadDao;

  public function __construct(DbManager $dbManager, Logger $logger,UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
    $this->uploadDao = $uploadDao;
  }

  /**
  * @brief Get all the pfile_fk stored in software heritage table
  * @param Integer $uploadId
  * @return array
  */
  public function getSoftwareHeritagePfileFk($uploadId)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $stmt = __METHOD__.$uploadTreeTableName;
    $sql = "SELECT software_heritage.pfile_fk AS pfile_fk  
            FROM  $uploadTreeTableName 
            JOIN software_heritage 
            ON $uploadTreeTableName.upload_fk = $1 
            AND software_heritage.pfile_fk = $uploadTreeTableName.pfile_fk";
    $rows = $this->dbManager->getRows($sql,array($uploadId),$stmt);
    $results = [];
    foreach ($rows as $row) {
        $results[] = $row['pfile_fk'];
    }
    return $results;
  }


  /**
  * @brief Store a record of Software Heritage license info in table
  * @param Integer $pfileId
  * @param String  $licenseDetails
  * @return bool
  */
  public function setshDetails($pfileId, $licenseDetails)
  {
    if (!empty($this->dbManager->insertTableRow('software_heritage',['pfile_fk'=> $pfileId, 'license' => $licenseDetails]))) {
        return true;
    }
    return false;
  }
}
