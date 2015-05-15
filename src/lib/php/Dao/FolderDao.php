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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Folder\Folder;
use Fossology\Lib\Data\Upload\UploadProgress;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class FolderDao extends Object
{
  const FOLDER_KEY = "folder" ;
  const DEPTH_KEY = "depth" ;
  const TOP_LEVEL = 1;

  const MODE_FOLDER = 1;
  const MODE_UPLOAD = 2;
  const MODE_ITEM = 4;
  
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
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
    $folderInfo = $this->dbManager->getSingleRow("SELECT count(*) cnt FROM folder WHERE folder_pk=$1",array(self::TOP_LEVEL),__METHOD__);
    $hasFolder = $folderInfo['cnt']>0;
    return $hasFolder;
  }

  public function insertFolder($folderName, $folderDescription, $parentFolderId=self::TOP_LEVEL) {

    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName,
      "INSERT INTO folder (folder_name, folder_desc, parent_fk ) VALUES ($1, $2, $3) returning folder_pk");
    $res = $this->dbManager->execute($statementName, array($folderName, $folderDescription, $parentFolderId));
    $folderRow=$this->dbManager->fetchArray($res);
    $folderId=$folderRow["folder_pk"];
    $this->dbManager->freeResult($res);

    return $folderId;
  }

  public function getFolderId($folderName, $parentFolderId=self::TOP_LEVEL) {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "SELECT folder_pk FROM folder WHERE folder_name=$1 AND parent_fk=$2");
    $res = $this->dbManager->execute($statementName, array( $folderName, $parentFolderId));
    $rows= $this->dbManager->fetchAll($res);

    $rootFolder = !empty($rows) ? intval($rows[0]['folder_pk']) : null;
    $this->dbManager->freeResult($res);

    return $rootFolder;
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

  /**
   * @param int $userId
   * @return Folder|null
   */
  public function getRootFolder($userId) {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "SELECT f.* FROM folder f INNER JOIN users u ON f.folder_pk = u.root_folder_fk WHERE u.user_pk = $1");
    $res = $this->dbManager->execute($statementName, array($userId));
    $row = $this->dbManager->fetchArray($res);
    $rootFolder = $row ? new Folder(intval($row['folder_pk']), $row['folder_name'], $row['folder_desc'], intval($row['folder_perm'])) : null;
    $this->dbManager->freeResult($res);
    return $rootFolder;
  }

  /**
   * @param int $userId
   * @return Folder|null
   */
  public function getParentFolder($userId) {
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
    f.folder_pk, f.parent_fk, f.folder_name, f.folder_desc, f.folder_perm,
    ARRAY [f.folder_pk]   AS id_path,
    ARRAY [f.folder_name] AS name_path,
    0                     AS depth,
    FALSE                 AS cycle_detected
  FROM folder f
  WHERE folder_pk $parentCondition
  UNION ALL
  SELECT
    f.folder_pk, f.parent_fk, f.folder_name, f.folder_desc, f.folder_perm,
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
    
    $userGroupMap = $GLOBALS['container']->get('dao.user')->getUserGroupMap(Auth::getUserId());
    
    $res = $this->dbManager->execute($statementName, $parameters);
    $results = array();
    while ($row = $this->dbManager->fetchArray($res))
    {
      $countUploads = $this->countFolderUploads(intval($row['folder_pk']), $userGroupMap);
      
      $results[] = array(
          self::FOLDER_KEY => new Folder(
                  intval($row['folder_pk']), $row['folder_name'], $row['folder_desc'], intval($row['folder_perm'])),
          self::DEPTH_KEY => $row['depth'],
          'reuse' => $countUploads
      );
    }
    $this->dbManager->freeResult($res);
    return $results;
  }

  /**
   * @param int $parentId
   * @param string[] $userGroupMap map groupId=>groupName
   * @return array map groupId=>count
   */
  public function countFolderUploads($parentId, $userGroupMap)
  {
    $trustGroupIds = array_keys($userGroupMap);
    $statementName = __METHOD__;
    $trustedGroups = '{'. implode(',', $trustGroupIds) .'}';
    $parameters = array($parentId, $trustedGroups);

    $this->dbManager->prepare($statementName, "
SELECT group_fk group_id,count(*) FROM foldercontents fc
  INNER JOIN upload u ON u.upload_pk = fc.child_id
  INNER JOIN upload_clearing uc ON u.upload_pk=uc.upload_fk AND uc.group_fk=ANY($2)
WHERE fc.parent_fk = $1 AND fc.foldercontents_mode = 2 AND u.upload_mode = 104
GROUP BY group_fk
");
    $res = $this->dbManager->execute($statementName, $parameters);
    $results = array();
    while ($row = $this->dbManager->fetchArray($res))
    {
      $row['group_name'] = $userGroupMap[$row['group_id']];
      $results[] = $row;
    }
    $this->dbManager->freeResult($res);
    return $results;
  }
  
  /**
   * @param int $parentId
   * @param int $trustGroupId
   * @return UploadProgress[]
   */
  public function getFolderUploads($parentId, $trustGroupId=null)
  {
    if (empty($trustGroupId)) {
      $trustGroupId = Auth::getGroupId();
    }
    $statementName = __METHOD__;
    $parameters = array($parentId, $trustGroupId);

    $this->dbManager->prepare($statementName, "
SELECT u.*,uc.* FROM foldercontents fc
  INNER JOIN upload u ON u.upload_pk = fc.child_id
  INNER JOIN upload_clearing uc ON u.upload_pk=uc.upload_fk AND uc.group_fk=$2
WHERE fc.parent_fk = $1 AND fc.foldercontents_mode = 2 AND u.upload_mode = 104
");
    $res = $this->dbManager->execute($statementName, $parameters);
    $results = array();
    while ($row = $this->dbManager->fetchArray($res))
    {
      $results[] = UploadProgress::createFromTable($row);
    }
    $this->dbManager->freeResult($res);
    return $results;
  }

  public function ensureTopLevelFolder() {
    if (!$this->hasTopLevelFolder())
    {
      $this->dbManager->insertTableRow("folder", array("folder_pk"=>self::TOP_LEVEL, "folder_name"=>"Software Repository", "folder_desc"=>"Top Folder"));
      $this->insertFolderContents(1,0,0);
      $this->fixFolderSequence();
    }
  }

}