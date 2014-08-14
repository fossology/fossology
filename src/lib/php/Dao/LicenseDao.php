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
        "SELECT
                  rf_shortname AS license_shortname,
                  rf_fullname AS license_fullname,
                  rf_pk AS license_id,
                  fl_pk AS license_file_id,
                  file_id,
                  agent_name AS agent_name,
                  agent_pk AS agent_id,
                  agent_rev AS agent_revision,
                  rf_match_pct AS percent_match
          FROM (SELECT pfile_fk AS file_id FROM $uploadTreeTableName
                               WHERE upload_fk=$1
                                 AND lft BETWEEN $2 and $3) AS SS
          INNER JOIN license_file_ref ON SS.file_id = pFile_fk
          INNER JOIN agent ON agent_pk = agent_fk
          WHERE SS.file_id=pfile_fk
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
   * @return array
   */
  public function getLicensesPerFileId(FileTreeBounds $fileTreeBounds)
  {
    $uploadTreeTableName = $fileTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . '.' . $uploadTreeTableName;

    $this->dbManager->prepare($statementName,
        "SELECT
           license_file_ref.pfile_fk as file_id,
           rf_shortname as license_shortname,
           rf_pk as license_id,
           agent_name,
           parent,
           max(agent_pk) as agent_id,
           rf_match_pct as match_percentage
         FROM license_file_ref
         INNER JOIN $uploadTreeTableName utree ON license_file_ref.pfile_fk=utree.pfile_fk
         INNER JOIN agent ON agent_fk=agent_pk
         WHERE utree.upload_fk=$1 and utree.lft BETWEEN $2 and $3
           AND license_file_ref.pfile_fk=utree.pfile_fk
           AND rf_shortname != 'No_license_found'
         GROUP BY file_id, license_shortname, license_id, agent_name, parent, match_percentage
         ORDER BY match_percentage ASC, license_shortname ASC");

    $result = $this->dbManager->execute($statementName,
        array($fileTreeBounds->getUploadId(), $fileTreeBounds->getLeft(), $fileTreeBounds->getRight()));

    $licensesPerFileId = array();
    while ($row = pg_fetch_assoc($result))
    {
      $licensesPerFileId[$row['file_id']][$row['license_shortname']][$row['agent_name']] = $row;
    }
    pg_free_result($result);
    return $licensesPerFileId;
  }


  public function getLicenseHistogram(FileTreeBounds $fileTreeBounds, $orderStatement = "")
  {
    $statementName = __METHOD__ . '.' . $fileTreeBounds->getUploadTreeTableName() . "." . $orderStatement;

    $orderStatement = $orderStatement ? " ORDER BY $orderStatement" : "";

    $this->dbManager->prepare($statementName,
        "SELECT rf_shortname AS license_shortname, count(*) AS count
         FROM license_file_ref
         RIGHT JOIN uploadtree ON license_file_ref.pfile_fk = uploadtree.pfile_fk
         WHERE rf_shortname NOT IN ('Void') AND upload_fk=$1 AND uploadtree.lft BETWEEN $2 and $3
         GROUP BY license_shortname" . $orderStatement);
    $result = $this->dbManager->execute($statementName,
        array($fileTreeBounds->getUploadId(), $fileTreeBounds->getLeft(), $fileTreeBounds->getRight()));

    $assocLicenseHist = array();
    while ($res = $this->dbManager->fetchArray($result))
    {
      $assocLicenseHist[$res['license_shortname']] = $res['count'];
    }

    return $assocLicenseHist;
  }

  public function getLicenseShortnamesContained(FileTreeBounds $fileTreeBounds)
  {
    $uploadTreeTableName = $fileTreeBounds->getUploadTreeTableName();

    $statementName = __METHOD__ . '.' . $uploadTreeTableName;
    $this->dbManager->prepare($statementName,
        "SELECT rf_shortname
              FROM license_file_ref
              INNER JOIN $uploadTreeTableName uploadTree ON uploadTree.pfile_fk=license_file_ref.pfile_fk
              WHERE upload_fk=$1
                AND lft BETWEEN $2 AND $3
                AND rf_shortname NOT IN ('No_license_found', 'VOID')
              GROUP BY rf_shortname
              ORDER BY rf_shortname ASC");
    $result = $this->dbManager->execute($statementName,
        array($fileTreeBounds->getUploadId(), $fileTreeBounds->getLeft(), $fileTreeBounds->getRight()));

    $licenses = array();
    while ($row = pg_fetch_assoc($result))
    {
      $licenses[] = $row['rf_shortname'];
    }
    pg_free_result($result);

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