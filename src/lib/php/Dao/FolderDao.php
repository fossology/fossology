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

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class FolderDao extends Object
{
  /**
   * @var DbManager
   */
  private $dbManager;

  /**
   * @var Logger
   */
  private $logger;

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::className());
  }

  /**
   * @return boolean
   */
  public function hasTopLevelFolder()
  {
    $folderInfo = $this->dbManager->getSingleRow("SELECT count(*) cnt FROM folder WHERE folder_pk=$1",array(1),__METHOD__);
    $hasFolder = $folderInfo['cnt']>0;
    return $hasFolder;
  }

  public function insertFolder($folderId, $folderName, $folderDescription) {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "INSERT INTO folder (folder_pk, folder_name, folder_desc) VALUES ($1, $2, $3)");
    $res = $this->dbManager->execute($statementName, array($folderId, $folderName, $folderDescription));
    $this->dbManager->freeResult($res);
  }

  public function insertFolderContents($parentId, $foldercontentsMode, $childId) {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "INSERT INTO foldercontents (parent_fk, foldercontents_mode, child_id) VALUES ($1, $2, $3)");
    $res = $this->dbManager->execute($statementName, array($parentId, $foldercontentsMode, $childId));
    $this->dbManager->freeResult($res);
  }

  public function fixFolderSequence() {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "SELECT setval('folder_folder_pk_seq', (SELECT max(folder_pk) + 1 FROM folder LIMIT 1))");
    $res = $this->dbManager->execute($statementName);
    $this->dbManager->freeResult($res);
  }

}