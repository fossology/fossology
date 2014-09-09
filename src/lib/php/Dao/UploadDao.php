<?php
/*
Copyright (C) 2014, Siemens AG
Authors: Andreas Würl, Steffen Weber

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

use Fossology\Lib\Data\DatabaseEnum;
use Fossology\Lib\Data\FileTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class UploadDao extends Object
{
  /**
   * @var DbManager
   */
  private $dbManager;

  /**
   * @var Logger
   */
  private $logger;

  function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::className());
  }

  function getUploadEntry($uploadTreeId, $uploadTreeTableName = "uploadtree")
  {
    $stmt = __METHOD__ . ".$uploadTreeTableName";
    $uploadEntry = $this->dbManager->getSingleRow("SELECT * FROM $uploadTreeTableName WHERE uploadtree_pk = $1",
        array($uploadTreeId), $stmt);
    return $uploadEntry;
  }

  function getUploadInfo($uploadId)
  {
    $stmt = __METHOD__;
    $uploadEntry = $this->dbManager->getSingleRow("SELECT * FROM upload WHERE upload_pk = $1",
        array($uploadId), $stmt);
    return $uploadEntry;
  }

  /**
   * @param $uploadTreeId
   * @param $uploadTreeTableName
   * @return FileTreeBounds
   */
  function getFileTreeBounds($uploadTreeId, $uploadTreeTableName = "uploadtree")
  {
    $uploadEntry = $this->getUploadEntry($uploadTreeId, $uploadTreeTableName);
    if ($uploadEntry===FALSE)
    {
      $this->logger->addWarning("did not find uploadTreeId $uploadTreeId in $uploadTreeTableName");
     return new FileTreeBounds($uploadTreeId, $uploadTreeTableName, 0, 0, 0);
    }
    return new FileTreeBounds($uploadTreeId, $uploadTreeTableName, intval($uploadEntry['upload_fk']), intval($uploadEntry['lft']), intval($uploadEntry['rgt']));
  }

  public function countPlainFiles(FileTreeBounds $fileTreeBounds)
  {
    $uploadTreeTableName = $fileTreeBounds->getUploadTreeTableName();
    $stmt = __METHOD__ . ".$uploadTreeTableName";
    $row = $this->dbManager->getSingleRow("SELECT count(*) as count FROM $uploadTreeTableName
        WHERE upload_fk = $1
          AND lft BETWEEN $2 AND $3
          AND ((ufile_mode & (3<<28))=0)
          AND pfile_fk != 0",
        array($fileTreeBounds->getUploadId(), $fileTreeBounds->getLeft(), $fileTreeBounds->getRight()), $stmt);
    $fileCount = intval($row["count"]);
    return $fileCount;
  }



  /**
   * @return DatabaseEnum[]
   */
  public function getStatusTypes()
  {
    $clearingTypes = array();
    $statementN = __METHOD__;

    $this->dbManager->prepare($statementN, "select * from upload_status");
    $res = $this->dbManager->execute($statementN);
    while ($rw = pg_fetch_assoc($res))
    {
      $clearingTypes[] = new DatabaseEnum($rw['status_pk'], $rw['meaning']);
    }
    pg_free_result($res);
    return $clearingTypes;
  }


}