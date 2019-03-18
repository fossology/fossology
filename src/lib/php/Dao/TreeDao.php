<?php
/*
Copyright (C) 2014-2015, Siemens AG
Copyright (C) 2017 TNG Technology Consulting GmbH
Authors: Andreas Würl, Steffen Weber, Maximilian Huber

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
use Monolog\Logger;

class TreeDao
{
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::class);
  }

  public function getFullPath($itemId, $tableName, $parentId=0, $dropArtifactPrefix=false)
  {
    $statementName = __METHOD__.".".$tableName;

    if ($parentId==$itemId)
    {
      return $this->getFullPath($itemId, $tableName);
    }    
    else if ($parentId > 0) {
      $params = array($itemId, $parentId);
      $parentClause = " = $2";
      $parentLoopCondition = "AND (ut.parent != $2)";
      $statementName .= ".parent";
    } else {
      $params = array($itemId);
      $parentClause = " IS NULL";
      $parentLoopCondition = "";
    }

    $row = $this->dbManager->getSingleRow(
        $sql= "
        WITH RECURSIVE file_tree(uploadtree_pk, parent, ufile_name, path, prev_ufile_mode, artifact_path_prefix, file_path, cycle) AS (
          SELECT ut.uploadtree_pk, ut.parent, ut.ufile_name,
            ARRAY[ut.uploadtree_pk],
            ut.ufile_mode,
            '',
            CASE WHEN ut.ufile_mode & (1<<28) = 0 THEN ut.ufile_name ELSE '' END,
            false
          FROM $tableName ut
          WHERE ut.uploadtree_pk = $1
        UNION ALL
          SELECT ut.uploadtree_pk, ut.parent, ut.ufile_name,
            path || ut.uploadtree_pk,
            ut.ufile_mode,
            CASE WHEN prev_ufile_mode & (1<<28) = 0
             THEN
              CASE WHEN ut.ufile_mode & (1<<28) = 0
               THEN ''
               ELSE artifact_path_prefix
              END
             ELSE
              CASE WHEN ut.ufile_mode & (1<<28) = 0
               THEN ut.ufile_name || '/' || artifact_path_prefix
               ELSE artifact_path_prefix
              END
            END,
            CASE WHEN (prev_ufile_mode & (1<<28) = 0 and ut.ufile_mode & (1<<28) = 0)
             THEN ut.ufile_name || '/' || artifact_path_prefix || file_path
             ELSE file_path
            END,
            (ut.uploadtree_pk = ANY(path)) $parentLoopCondition
          FROM $tableName ut, file_tree ft
          WHERE ut.uploadtree_pk = ft.parent AND NOT cycle
        )
        SELECT artifact_path_prefix, file_path from file_tree WHERE parent $parentClause",
        $params, $statementName);

    if (false === $row) {
      throw new \Exception("could not find path of $itemId:\n$sql--".print_r($params,true));
    }

    if(! $dropArtifactPrefix)
    {
      return $row['artifact_path_prefix'].$row['file_path'];
    }else{
      return $row['file_path'];
    }
  }

  public function getMinimalCoveringItem($uploadId, $tableName)
  {
    $statementName = __METHOD__.".".$tableName;

    $row = $this->dbManager->getSingleRow(
      "SELECT uploadtree_pk FROM $tableName ut WHERE ut.upload_fk = $1
      AND NOT EXISTS (
        SELECT 1 FROM $tableName ut2 WHERE ut2.upload_fk = $1
        AND NOT (ut2.lft BETWEEN ut.lft AND ut.rgt)
        AND (ut2.ufile_mode & (3<<28) = 0)
      )
      ORDER BY ut.lft DESC LIMIT 1",
      array($uploadId),
      $statementName
     );

     return $row ? $row['uploadtree_pk'] : 0;
  }
  
  /**
   * @param int $uploadtreeId
   * @return array with keys sha1, md5
   */
  public function getItemHashes($uploadtreeId, $uploadtreeTablename='uploadtree')
  {
    $pfile = $this->dbManager->getSingleRow("SELECT pfile.* FROM $uploadtreeTablename, pfile WHERE uploadtree_pk=$1 AND pfile_fk=pfile_pk",
        array($uploadtreeId), __METHOD__);
    return array('sha1'=>$pfile['pfile_sha1'],'md5'=>$pfile['pfile_md5']);
  }
  
  public function getRepoPathOfPfile($pfileId, $repo="files")
  {
    $pfileRow = $this->dbManager->getSingleRow('SELECT * FROM pfile WHERE pfile_pk=$1',array($pfileId));
    global $LIBEXECDIR;
    if (empty($pfileRow['pfile_sha1'])) {
      return null;
    }
    $hash = $pfileRow['pfile_sha1'] . "." . $pfileRow['pfile_md5'] . "." . $pfileRow['pfile_size'];
    $path = '';
    exec("$LIBEXECDIR/reppath $repo $hash", $path);
    return($path[0]);
  }
}
