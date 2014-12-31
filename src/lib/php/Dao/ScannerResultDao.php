<?php
/*
Copyright (C) 2014, Siemens AG

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

use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;

class ScannerResultDao extends Object
{
  /** @var DbManager*/
  protected $dbManager;

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $agentId
   * @return array[]
   */
  public function getItemLicenseMatches(ItemTreeBounds $itemTreeBounds, $agentId)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . ".$uploadTreeTableName";

    $sql = "SELECT UT.uploadtree_pk,
                LFR.rf_pk AS license_id,
                LFR.pfile_fk as file_id,
                LFR.rf_match_pct AS percent_match
          FROM license_file as LFR, $uploadTreeTableName as UT 
          WHERE LFR.agent_fk=$1 AND UT.pfile_fk = LFR.pfile_fk AND UT.lft BETWEEN $2 and $3";
    $params = array($agentId, $itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    if($uploadTreeTableName=='uploadtree_a')
    {
      $params[] = $itemTreeBounds->getUploadId();
      $sql .= "  AND UT.upload_fk=$".count($params);
    }
    $this->dbManager->prepare($statementName,$sql);
    $result = $this->dbManager->execute($statementName,$params);
    $matches = $this->dbManager->fetchAll($result);
    $this->dbManager->freeResult($result);
    return $matches;
  }

}