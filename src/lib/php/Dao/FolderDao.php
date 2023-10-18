<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Authors: Andreas Würl, Steffen Weber

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Folder\Folder;
use Fossology\Lib\Data\Upload\UploadProgress;
use Fossology\Lib\Db\DbManager;
use Monolog\Logger;

class FolderDao
{
  const FOLDER_KEY = "folder";
  const DEPTH_KEY = "depth";
  const REUSE_KEY = 'reuse';
  const TOP_LEVEL = 1;

  const MODE_FOLDER = 1;
  const MODE_UPLOAD = 2;
  const MODE_ITEM = 4;

  /** @var DbManager */
  private $dbManager;
  /** @var UserDao */
  private $userDao;
  /** @var UploadDao */
  private $uploadDao;
  /** @var Logger */
  private $logger;

  public function __construct(DbManager $dbManager, UserDao $userDao, UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::class);
    $this->uploadDao = $uploadDao;
    $this->userDao = $userDao;
  }

  /**
   * @return boolean
   */
  public function hasTopLevelFolder()
  {
    $folderInfo = $this->dbManager->getSingleRow("SELECT count(*) cnt FROM folder WHERE folder_pk=$1", array(self::TOP_LEVEL), __METHOD__);
    $hasFolder = $folderInfo['cnt'] > 0;
    return $hasFolder;
  }

  public function insertFolder($folderName, $folderDescription, $parentFolderId = self::TOP_LEVEL)
  {

    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName,
      "INSERT INTO folder (folder_name, folder_desc) VALUES ($1, $2) returning folder_pk");
    $res = $this->dbManager->execute($statementName, array($folderName, $folderDescription));
    $folderRow = $this->dbManager->fetchArray($res);
    $folderId = $folderRow["folder_pk"];
    $this->dbManager->freeResult($res);
    $this->insertFolderContents($parentFolderId, self::MODE_FOLDER, $folderId);

    return $folderId;
  }

  public function getFolderId($folderName, $parentFolderId = self::TOP_LEVEL)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "SELECT folder_pk FROM folder, foldercontents fc"
       ." WHERE LOWER(folder_name)=LOWER($1) AND fc.parent_fk=$2 AND fc.foldercontents_mode=$3 AND folder_pk=child_id");
    $res = $this->dbManager->execute($statementName, array( $folderName, $parentFolderId, self::MODE_FOLDER));
    $rows= $this->dbManager->fetchAll($res);

    $rootFolder = !empty($rows) ? intval($rows[0]['folder_pk']) : null;
    $this->dbManager->freeResult($res);

    return $rootFolder;
  }

  public function insertFolderContents($parentId, $foldercontentsMode, $childId)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
      "INSERT INTO foldercontents (parent_fk, foldercontents_mode, child_id) VALUES ($1, $2, $3)");
    $res = $this->dbManager->execute($statementName, array($parentId, $foldercontentsMode, $childId));
    $this->dbManager->freeResult($res);
  }

  protected function fixFolderSequence()
  {
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
  public function getRootFolder($userId)
  {
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
  public function getDefaultFolder($userId)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
      "SELECT f.* FROM folder f INNER JOIN users u ON f.folder_pk = u.default_folder_fk WHERE u.user_pk = $1");
    $res = $this->dbManager->execute($statementName, array($userId));
    $row = $this->dbManager->fetchArray($res);
    $rootFolder = $row ? new Folder(intval($row['folder_pk']), $row['folder_name'], $row['folder_desc'], intval($row['folder_perm'])) : null;
    $this->dbManager->freeResult($res);
    return $rootFolder;
  }

  public function getFolderTreeCte($parentId = null)
  {
    $parentCondition = $parentId ? 'folder_pk=$1' : 'folder_pk=' . self::TOP_LEVEL;

    return "WITH RECURSIVE folder_tree(folder_pk, parent_fk, folder_name, folder_desc, folder_perm, id_path, name_path, depth, cycle_detected) AS (
  SELECT
    f.folder_pk, fc.parent_fk, f.folder_name, f.folder_desc, f.folder_perm,
    ARRAY [f.folder_pk]   AS id_path,
    ARRAY [f.folder_name] AS name_path,
    0                     AS depth,
    FALSE                 AS cycle_detected
  FROM folder f LEFT JOIN foldercontents fc ON fc.foldercontents_mode=" . self::MODE_FOLDER . " AND f.folder_pk=fc.child_id
  WHERE $parentCondition
  UNION ALL
  SELECT
    f.folder_pk, fc.parent_fk, f.folder_name, f.folder_desc, f.folder_perm,
    id_path || f.folder_pk,
    name_path || f.folder_name,
    array_length(id_path, 1),
    f.folder_pk = ANY (id_path)
  FROM folder f, foldercontents fc, folder_tree ft
  WHERE f.folder_pk=fc.child_id AND foldercontents_mode=" . self::MODE_FOLDER . " AND fc.parent_fk = ft.folder_pk AND NOT cycle_detected
)";
  }

  public function getFolderStructure($parentId = null)
  {
    $statementName = __METHOD__ . ($parentId ? '.relativeToParent' : '');
    $parameters = $parentId ? array($parentId) : array();
    $this->dbManager->prepare($statementName, $this->getFolderTreeCte($parentId)
      . " SELECT folder_pk, parent_fk, folder_name, folder_desc, folder_perm, depth FROM folder_tree ORDER BY name_path");
    $res = $this->dbManager->execute($statementName, $parameters);

    $userGroupMap = $this->userDao->getUserGroupMap(Auth::getUserId());

    $results = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $countUploads = $this->countFolderUploads(intval($row['folder_pk']), $userGroupMap);

      $results[] = array(
        self::FOLDER_KEY => new Folder(
          intval($row['folder_pk']), $row['folder_name'], $row['folder_desc'], intval($row['folder_perm'])),
        self::DEPTH_KEY => $row['depth'],
        self::REUSE_KEY => $countUploads
      );
    }
    $this->dbManager->freeResult($res);
    return $results;
  }

  /**
   * @param int $parentId
   * @param string[] $userGroupMap map groupId=>groupName
   * @return array  of array(group_id,count,group_name)
   */
  public function countFolderUploads($parentId, $userGroupMap)
  {
    $trustGroupIds = array_keys($userGroupMap);
    $statementName = __METHOD__;
    $trustedGroups = '{' . implode(',', $trustGroupIds) . '}';
    $parameters = array($parentId, $trustedGroups);

    $this->dbManager->prepare($statementName, "
SELECT group_fk group_id,count(*) FROM foldercontents fc
  INNER JOIN upload u ON u.upload_pk = fc.child_id
  INNER JOIN upload_clearing uc ON u.upload_pk=uc.upload_fk AND uc.group_fk=ANY($2)
WHERE fc.parent_fk = $1 AND fc.foldercontents_mode = " . self::MODE_UPLOAD . " AND (u.upload_mode = 100 OR u.upload_mode = 104)
GROUP BY group_fk
");
    $res = $this->dbManager->execute($statementName, $parameters);
    $results = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $row['group_name'] = $userGroupMap[$row['group_id']];
      $results[$row['group_name']] = $row;
    }
    $this->dbManager->freeResult($res);
    return $results;
  }

  public function getAllFolderIds()
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName, "SELECT DISTINCT folder_pk FROM folder");
    $res = $this->dbManager->execute($statementName);
    $results = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);

    $allIds = array();
    for ($i=0; $i < sizeof($results); $i++) {
      $allIds[] = intval($results[$i]['folder_pk']);
    }

    return $allIds;
  }

  public function getFolderChildUploads($parentId, $trustGroupId)
  {
    $statementName = __METHOD__;
    $parameters = array($parentId, $trustGroupId);

    $this->dbManager->prepare($statementName, $sql = "
SELECT u.*,uc.*,fc.foldercontents_pk FROM foldercontents fc
  INNER JOIN upload u ON u.upload_pk = fc.child_id
  INNER JOIN upload_clearing uc ON u.upload_pk=uc.upload_fk AND uc.group_fk=$2
WHERE fc.parent_fk = $1 AND fc.foldercontents_mode = " . self::MODE_UPLOAD . " AND (u.upload_mode = 100 OR u.upload_mode = 104);");
    $res = $this->dbManager->execute($statementName, $parameters);
    $results = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    return $results;
  }

  /**
   * @param int $parentId
   * @param int $trustGroupId
   * @return UploadProgress[]
   */
  public function getFolderUploads($parentId, $trustGroupId = null)
  {
    if (empty($trustGroupId)) {
      $trustGroupId = Auth::getGroupId();
    }
    $results = array();
    foreach ($this->getFolderChildUploads($parentId, $trustGroupId) as $row) {
      $results[] = UploadProgress::createFromTable($row);
    }
    return $results;
  }

  public function createFolder($folderName, $folderDescription, $parentId)
  {
    $folderId = $this->dbManager->insertTableRow("folder", array("folder_name" => $folderName, "user_fk" => Auth::getUserId(), "folder_desc" => $folderDescription), null, 'folder_pk');
    $this->insertFolderContents($parentId, self::MODE_FOLDER, $folderId);
    return $folderId;
  }


  public function ensureTopLevelFolder()
  {
    if (!$this->hasTopLevelFolder()) {
      $this->dbManager->insertTableRow("folder", array("folder_pk" => self::TOP_LEVEL, "folder_name" => "Software Repository", "folder_desc" => "Top Folder"));
      $this->insertFolderContents(1, 0, 0);
      $this->fixFolderSequence();
    }
  }

  public function isWithoutReusableFolders($folderStructure)
  {
    foreach ($folderStructure as $folder) {
      $posibilities = array_reduce($folder[self::REUSE_KEY], function($sum,$groupInfo)
      {
        return $sum+$groupInfo['count'];
      }, 0);
      if ($posibilities > 0) {
        return false;
      }
    }
    return true;
  }

  protected function isInFolderTree($parentId, $folderId)
  {
    $cycle = $this->dbManager->getSingleRow(
      $this->getFolderTreeCte($parentId) . " SELECT depth FROM folder_tree WHERE folder_pk=$2 LIMIT 1",
      array($parentId, $folderId),
      __METHOD__);
    return !empty($cycle);
  }

  public function getContent($folderContentId)
  {
    $content = $this->dbManager->getSingleRow('SELECT * FROM foldercontents WHERE foldercontents_pk=$1',
      array($folderContentId),
      __METHOD__ . '.getContent');
    if (empty($content)) {
      throw new \Exception('invalid FolderContentId');
    }
    return $content;
  }

  protected function isContentMovable($content, $newParentId)
  {
    if ($content['parent_fk'] == $newParentId) {
      return false;
    }
    $newParent = $this->dbManager->getSingleRow('SELECT * FROM folder WHERE folder_pk=$1',
      array($newParentId),
      __METHOD__ . '.getParent');
    if (empty($newParent)) {
      throw new \Exception('invalid parent folder');
    }

    if ($content['foldercontents_mode'] == self::MODE_FOLDER) {
      if ($this->isInFolderTree($content['child_id'], $newParentId)) {
        throw new \Exception("action would cause a cycle");
      }
    } elseif ($content['foldercontents_mode'] == self::MODE_UPLOAD) {
      $uploadId = $content['child_id'];
      if (!$this->uploadDao->isEditable($uploadId, Auth::getGroupId())) {
        throw new \Exception('permission to upload denied');
      }
    }

    return true;
  }

  public function moveContent($folderContentId, $newParentId)
  {
    $content = $this->getContent($folderContentId);
    if (!$this->isContentMovable($content, $newParentId)) {
      return;
    }

    $this->dbManager->getSingleRow('UPDATE foldercontents SET parent_fk=$2 WHERE foldercontents_pk=$1',
      array($folderContentId, $newParentId), __METHOD__ . '.updateFolderParent');
  }

  public function copyContent($folderContentId, $newParentId)
  {
    $content = $this->getContent($folderContentId);
    if (!$this->isContentMovable($content, $newParentId)) {
      return;
    }

    $this->insertFolderContents($newParentId, $content['foldercontents_mode'], $content['child_id']);
  }

  public function getRemovableContents($folderId)
  {
    $sqlChildren = "SELECT child_id,foldercontents_mode
             FROM foldercontents GROUP BY child_id,foldercontents_mode
             HAVING count(*)>1 AND bool_or(parent_fk=$1)";
    $sql = "SELECT fc.* FROM foldercontents fc,($sqlChildren) chi "
      . "WHERE fc.child_id=chi.child_id AND fc.foldercontents_mode=chi.foldercontents_mode and fc.parent_fk=$1";
    $this->dbManager->prepare($stmt = __METHOD__, $sql);
    $res = $this->dbManager->execute($stmt, array($folderId));
    $contents = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $contents[] = $row['foldercontents_pk'];
    }
    $this->dbManager->freeResult($res);
    return $contents;
  }

  public function isRemovableContent($childId, $mode)
  {
    $sql = "SELECT count(parent_fk) FROM foldercontents WHERE child_id=$1 AND foldercontents_mode=$2";
    $parentCounter = $this->dbManager->getSingleRow($sql, array($childId, $mode), __METHOD__);
    return $parentCounter['count'] > 1;
  }

  public function removeContent($folderContentId)
  {
    $content = $this->getContent($folderContentId);
    if ($this->isRemovableContent($content['child_id'], $content['foldercontents_mode'])) {
      $sql = "DELETE FROM foldercontents WHERE foldercontents_pk=$1";
      $this->dbManager->getSingleRow($sql, array($folderContentId), __METHOD__);
      return true;
    }
    return false;
  }

  public function removeContentById($uploadpk, $folderId)
  {
    $sql = "DELETE FROM foldercontents WHERE child_id=$1 AND parent_fk=$2 AND foldercontents_mode=$3";
    $this->dbManager->getSingleRow($sql,array($uploadpk, $folderId,2),__METHOD__);
  }

  public function getFolderChildFolders($folderId)
  {
    $results = array();
    $stmtFolder = __METHOD__;
    $sqlFolder = "SELECT foldercontents_pk,foldercontents_mode, folder_name FROM foldercontents JOIN folder"
      . " ON foldercontents.child_id=folder.folder_pk WHERE foldercontents.parent_fk=$1"
      . " AND foldercontents_mode=" . self::MODE_FOLDER;
    $this->dbManager->prepare($stmtFolder, $sqlFolder);
    $res = $this->dbManager->execute($stmtFolder, array($folderId));
    while ($row = $this->dbManager->fetchArray($res)) {
      $results[$row['foldercontents_pk']] = $row;
    }
    $this->dbManager->freeResult($res);
    return $results;
  }

  /**
   * @param int $folderId
   * @return Folder|null
   */
  public function getFolder($folderId)
  {
    $folderRow = $this->dbManager->getSingleRow('SELECT * FROM folder WHERE folder_pk = $1', array($folderId));
    if (!$folderRow) {
      return null;
    }
    return new Folder($folderRow['folder_pk'], $folderRow['folder_name'], $folderRow['folder_desc'], $folderRow['folder_perm']);
  }

  /**
   * @param int $folderId
   * @param int $userId
   * @return true|false
   */
  public function isFolderAccessible($folderId, $userId = null)
  {
    $allUserFolders = array();
    if ($userId == null) {
      $userId = Auth::getUserId();
    }
    $rootFolder = $this->getRootFolder($userId)->getId();
    GetFolderArray($rootFolder, $allUserFolders);
    if (in_array($folderId, array_keys($allUserFolders))) {
      return true;
    }
    return false;
  }

  /**
   * Get the folder contents id for a given child id
   * @param integer $childId Id of the child
   * @param integer $mode    Mode of child
   * @return NULL|integer Folder content id if success, NULL otherwise
   */
  public function getFolderContentsId($childId, $mode)
  {
    $folderContentsRow = $this->dbManager->getSingleRow(
      'SELECT foldercontents_pk FROM foldercontents '.
      'WHERE child_id = $1 AND foldercontents_mode = $2', [$childId, $mode]);
    if (!$folderContentsRow) {
      return null;
    }
    return intval($folderContentsRow['foldercontents_pk']);
  }

  /**
   * For a given folder id, get the parent folder id.
   * @param integer $folderPk ID of the folder
   * @return number Parent id if parent exists, null otherwise.
   */
  public function getFolderParentId($folderPk)
  {
    $sql = "SELECT parent_fk FROM foldercontents " .
      "WHERE foldercontents_mode = " . self::MODE_FOLDER .
      " AND child_id = $1;";
    $statement = __METHOD__ . ".getParentId";
    $row = $this->dbManager->getSingleRow($sql, [$folderPk], $statement);
    return (empty($row)) ? null : $row['parent_fk'];
  }
}
