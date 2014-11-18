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

use Fossology\Lib\Data\Folder\Folder;
use Fossology\Lib\Data\UploadStatus;
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

  public function getRootFolder($userId) {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "SELECT f.* FROM folder f INNER JOIN users u ON f.folder_pk = u.root_folder_fk WHERE u.user_pk = $1;");
    $res = $this->dbManager->execute($statementName, array($userId));
    $row = $this->dbManager->fetchArray($res);
    $rootFolder = $row ? new Folder(intval($row['folder_pk']), $row['folder_name'], $row['folder_desc'], intval($row['folder_perm'])) : null;
    $this->dbManager->freeResult($res);
    return $rootFolder;
  }

  public function getFolderStructure($parentId=null) {
    $statementName = __METHOD__ . ($parentId ? '.relativeToParent' : '');
    $parentCondition = $parentId ? '= $1' : 'IS NULL';

    $parameters = $parentId ? array($parentId) : array();

    $this->dbManager->prepare($statementName, "
WITH RECURSIVE folder_tree(folder_pk, parent_fk, folder_name, folder_desc, folder_perm, id_path, name_path, depth, cycle_detected) AS (
  SELECT
    f.*,
    ARRAY [f.folder_pk]   AS id_path,
    ARRAY [f.folder_name] AS name_path,
    0                     AS depth,
    FALSE                 AS cycle_detected
  FROM folder f
  WHERE parent_fk $parentCondition
  UNION ALL
  SELECT
    f.*,
    id_path || f.folder_pk,
    name_path || f.folder_name,
    array_length(id_path, 1),
    f.folder_pk = ANY (id_path)
  FROM folder f, folder_tree ft
  WHERE f.parent_fk = ft.folder_pk AND NOT cycle_detected
)
SELECT
  folder_pk,
  parent_fk,
  folder_name,
  folder_desc,
  folder_perm,
  depth
FROM folder_tree
ORDER BY name_path;
");
    $res = $this->dbManager->execute($statementName, $parameters);
    $results = array();
    while ($row = $this->dbManager->fetchArray($res))
    {
      $results[] = array(
          'folder' => new Folder(
              intval($row['folder_pk']),
              $row['folder_name'],
              $row['folder_desc'],
              intval($row['folder_perm'])),
          'depth' => $row['depth']
      );
    }
    $this->dbManager->freeResult($res);
    return $results;
  }

  /**
   * @param int $parentId
   * @return array
   */
  public function getFolderUploads($parentId) {
    $statementName = __METHOD__ ;

    $parameters = array($parentId);

    $this->dbManager->prepare($statementName, "
SELECT * from foldercontents fc
INNER JOIN upload u ON u.upload_pk = fc.child_id
WHERE fc.parent_fk = $1 AND fc.foldercontents_mode = 2 AND u.upload_mode = 104;
");
    $res = $this->dbManager->execute($statementName, $parameters);
    $results = array();
    while ($row = $this->dbManager->fetchArray($res))
    {
      $results[$row['child_id']] = $row['upload_filename'];
    }
    $this->dbManager->freeResult($res);
    return $results;
  }

}