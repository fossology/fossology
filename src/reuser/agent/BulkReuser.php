<?php
/*
 Copyright (C) 2015, Siemens AG

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

namespace Fossology\Reuser;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;

class BulkReuserAgent extends Object
{
  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  function getRelevantReuse($uploadId, $groupId, $userId)
  {
    $sql = "WITH RECURSIVE reuse_tree(upload_fk, reused_upload_fk, group_fk, reused_group_fk, pairs, cycle) AS (
          SELECT upload_fk, reused_upload_fk, group_fk, reused_group_fk,
            array[ARRAY[upload_fk, reused_upload_fk]], false
          FROM upload_reuse ur
          WHERE upload_fk=$1 AND group_fk=$2
        UNION ALL
          SELECT upload_fk, reused_upload_fk, group_fk, reused_group_fk,
            pairs || array[ARRAY[upload_fk, reused_upload_fk]], array[ARRAY[upload_fk, reused_upload_fk]] <@ pairs
          FROM upload_reuse ur, reuses rt
          WHERE NOT cycle AND ur.upload_fk=rt.reused_upload_fk
           AND ur.group_fk=rt.reused_group_fk
           AND EXISTS(SELECT * FROM group_user_member gum WHERE gum.group_fk=ur.group_fk AND gum.user_fk=$3)
        )
        SELECT group_fk, reused_group_fk FROM reuse_tree WHERE NOT cycle GROUP BY group_fk, reused_group_fk";
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($uploadId, $groupId, $userId));
    $rows = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    return $rows;            
  }  


}