<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\FileTreeBounds;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class LicenseDao extends Object
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

  /**
   * \brief get all the licenses for a single file or uploadtree
   *
   * @param \Fossology\Lib\Data\FileTreeBounds $fileTreeBounds
   * @return LicenseMatch[]
   */
  function getFileLicenseMatches(FileTreeBounds $fileTreeBounds)
  {
    $uploadTreeTableName = $fileTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . ".$uploadTreeTableName";

    $this->dbManager->prepare($statementName,
        "SELECT   LFR.rf_shortname AS license_shortname,
                  LFR.rf_fullname AS license_fullname,
                  LFR.rf_pk AS license_id,
                  LFR.fl_pk AS license_file_id,
                  LFR.pfile_fk as file_id,
                  AG.agent_name AS agent_name,
                  AG.agent_pk AS agent_id,
                  AG.agent_rev AS agent_revision,
                  LFR.rf_match_pct AS percent_match
          FROM license_file_ref as LFR
          INNER JOIN $uploadTreeTableName as UT  ON UT.pfile_fk = LFR.pfile_fk
          INNER JOIN agent as AG ON AG.agent_pk = LFR.agent_fk
          WHERE AG.agent_enabled='true' and
           UT.upload_fk=$1 AND UT.lft BETWEEN $2 and $3
          ORDER BY license_shortname ASC, percent_match DESC");

    $result = $this->dbManager->execute($statementName,
        array($fileTreeBounds->getUploadId(), $fileTreeBounds->getLeft(), $fileTreeBounds->getRight()));

    $matches = array();

    while ($row = $this->dbManager->fetchArray($result))
    {
      $licenseRef = new LicenseRef($row['license_id'], $row['license_shortname'], $row['license_fullname']);
      $agentRef = new AgentRef($row['agent_id'], $row['agent_name'], $row['agent_revision']);
      $matches[] = new LicenseMatch(intval($row['file_id']), $licenseRef, $agentRef, $row['license_file_id'], $row['percent_match']);
    }

    $this->dbManager->freeResult($result);
    return $matches;
  }


  /**
   * \brief get all the tried bulk recognitions for a single file or uploadtree (currently unused)
   *
   * @param \Fossology\Lib\Data\FileTreeBounds $fileTreeBounds
   * @return LicenseMatch[]
   */
  function getBulkFileLicenseMatches(FileTreeBounds $fileTreeBounds)
  {
    $uploadTreeTableName = $fileTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . ".$uploadTreeTableName";

    $this->dbManager->prepare($statementName,
        "SELECT   LF.rf_shortname  AS license_shortname,
                  LF.rf_fullname AS license_fullname,
                  LF.rf_pk AS license_id,
                  LFB.lrb_pk AS license_file_id,
                  LFB.removing AS removing,
                  UT.pfile_fk as file_id
          FROM license_ref_bulk as LFB
          INNER JOIN license_ref as LF on LF.rf_pk = LFB.rf_fk
          INNER JOIN $uploadTreeTableName as UT ON UT.uploadtree_pk = LFB.uploadtree_fk
          WHERE UT.upload_fk=$1 AND UT.lft BETWEEN $2 and $3
          ORDER BY license_file_id ASC");

    $result = $this->dbManager->execute($statementName,
        array($fileTreeBounds->getUploadId(), $fileTreeBounds->getLeft(), $fileTreeBounds->getRight()));

    $matches = array();

    while ($row = $this->dbManager->fetchArray($result))
    {
      $licenseRef = new LicenseRef($row['license_id'], $row['license_shortname'], $row['license_fullname']);
      if($row['removing'] == 'f') {
        $agentID=1;
        $agentName="bulk addition";
      }
      else
      {
        $agentID=2;
        $agentName="bulk removal";
      }
      $agentRef = new AgentRef($agentID, $agentName, "empty");
      $matches[] = new LicenseMatch(intval($row['file_id']), $licenseRef, $agentRef, intval($row['license_file_id']));
    }

    $this->dbManager->freeResult($result);
    return $matches;
  }

  /**
   * @return LicenseRef[]
   */
  public function getLicenseRefs()
  {
    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName,
        "select rf_pk,rf_shortname,rf_fullname from license_ref order by rf_shortname");
    $result = $this->dbManager->execute($statementName);

    $licenseRefs = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      $licenseRefs[] = new LicenseRef(intval($row['rf_pk']), $row['rf_shortname'], $row['rf_fullname']);
    }
    $this->dbManager->freeResult($result);
    return $licenseRefs;
  }

  /**
   * @param FileTreeBounds $fileTreeBounds
   * @param $selectedAgentId
   * @return array
   */
  public function getTopLevelLicensesPerFileId(FileTreeBounds $fileTreeBounds, $selectedAgentId = null, $filterLicenses = array('VOID'))//'No_license_found',
  {
    $uploadTreeTableName = $fileTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . '.' . $uploadTreeTableName.implode("",$filterLicenses);
    $param = array($fileTreeBounds->getUploadTreeId());

    $noLicenseFoundStmt = empty($filterLicenses) ? "" : " AND rf_shortname NOT IN ("
        . implode(", ", array_map(function($name) {return "'" . $name . "'";}, $filterLicenses)) . ")";

    $sql = "SELECT license_file_ref.pfile_fk as file_id,
           rf_shortname as license_shortname,
           rf_pk as license_id,
           agent_name,
           parent,
           max(agent_pk) as agent_id,
           rf_match_pct as match_percentage
         FROM license_file_ref
         INNER JOIN $uploadTreeTableName utree ON license_file_ref.pfile_fk = utree.pfile_fk
         INNER JOIN agent ON agent_fk = agent_pk
         WHERE parent = $1
           AND license_file_ref.pfile_fk = utree.pfile_fk
           $noLicenseFoundStmt";

    if (!empty($selectedAgentId)){
      $sql .= " AND agent_pk=$4";
      $param[] = $selectedAgentId;
    }
    $sql .= "GROUP BY file_id, license_shortname, license_id, agent_name, parent, match_percentage
         ORDER BY match_percentage ASC, license_shortname ASC";
    
    $this->dbManager->prepare($statementName, $sql);
    $result = $this->dbManager->execute($statementName, $param);
    $licensesPerFileId = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      $licensesPerFileId[$row['file_id']][$row['license_shortname']][$row['agent_name']] = $row;
    }
    $this->dbManager->freeResult($result);
    return $licensesPerFileId;
  }


  public function getLicenseHistogram(FileTreeBounds $fileTreeBounds, $orderStatement = "", $agentId=null)
  {
    $uploadTreeTableName = $fileTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . '.' . $uploadTreeTableName . ".$orderStatement.$agentId";
    $param = array($fileTreeBounds->getUploadId(), $fileTreeBounds->getLeft(), $fileTreeBounds->getRight());
    $sql = "SELECT rf_shortname AS license_shortname, count(*) AS count
         FROM license_file_ref RIGHT JOIN $uploadTreeTableName UT ON license_file_ref.pfile_fk = UT.pfile_fk
         WHERE rf_shortname NOT IN ('Void') AND upload_fk=$1 AND UT.lft BETWEEN $2 and $3";
    if (!empty($agentId))
    {
      $sql .= ' AND agent_fk=$4';
      $param[] = $agentId;
    }
    $sql .= "GROUP BY license_shortname";
    if ($orderStatement)
    {
      $sql .= $orderStatement;
    }
    $this->dbManager->prepare($statementName, $sql);
    $result = $this->dbManager->execute($statementName,$param);
    $assocLicenseHist = array();
    while ($res = $this->dbManager->fetchArray($result))
    {
      $assocLicenseHist[$res['license_shortname']] = $res['count'];
    }
    $this->dbManager->freeResult($result);
    return $assocLicenseHist;
  }

  public function getLicenseShortnamesContained(FileTreeBounds $fileTreeBounds, $filterLicenses=array('VOID')) //'No_license_found',
  {
    $uploadTreeTableName = $fileTreeBounds->getUploadTreeTableName();

    $noLicenseFoundStmt = empty($filterLicenses) ? "" : " AND rf_shortname NOT IN ("
        . implode(", ", array_map(function($name) {return "'" . $name . "'";}, $filterLicenses)) . ")";


    $statementName = __METHOD__ . '.' . $uploadTreeTableName;
    $this->dbManager->prepare($statementName,
        "SELECT rf_shortname
              FROM license_file_ref
              INNER JOIN $uploadTreeTableName uploadTree ON uploadTree.pfile_fk=license_file_ref.pfile_fk
              WHERE upload_fk=$1
                AND lft BETWEEN $2 AND $3
                $noLicenseFoundStmt
              GROUP BY rf_shortname
              ORDER BY rf_shortname ASC");
    $result = $this->dbManager->execute($statementName,
        array($fileTreeBounds->getUploadId(), $fileTreeBounds->getLeft(), $fileTreeBounds->getRight()));

    $licenses = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      $licenses[] = $row['rf_shortname'];
    }
    $this->dbManager->freeResult($result);

    return $licenses;
  }


  /**
   * @param string $licenseId
   * @return License|null
   */
  public function getLicenseById($licenseId)
  {
    $row = $this->dbManager->getSingleRow(
        "SELECT rf_pk, rf_shortname, rf_fullname, rf_text, rf_url FROM license_ref WHERE rf_pk=$1",
        array($licenseId));
    if (false === $row)
    {
      return null;
    }
    $license = new License(intval($row['rf_pk']), $row['rf_shortname'], $row['rf_fullname'], $row['rf_text'], $row['rf_url']);
    return $license;
  }

  /**
   * @param string $licenseShortname
   * @return License|null
   */
  public function getLicenseByShortName($licenseShortname)
  {
    $row = $this->dbManager->getSingleRow(
        "SELECT rf_pk, rf_shortname, rf_fullname, rf_text, rf_url FROM license_ref WHERE rf_shortname=$1",
        array($licenseShortname));
    if (false === $row)
    {
      return null;
    }
    $license = new License(intval($row['rf_pk']), $row['rf_shortname'], $row['rf_fullname'], $row['rf_text'], $row['rf_url']);
    return $license;
  }

}