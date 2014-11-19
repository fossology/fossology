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


use Fossology\Lib\Data\Package\Package;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class PackageDao extends Object
{

  /** @var DbManager */
  private $dbManager;

  /**
   * @param DbManager $dbManager
   */
  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::className());
  }

  /**
   * @param int $uploadId
   * @return Package|null
   */
  public function findPackageForUpload($uploadId)
  {
    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName, "
SELECT
  p.*,
  u.*
FROM package p
  INNER JOIN upload_packages up ON p.package_pk = up.package_fk
  INNER JOIN upload_packages up2 ON p.package_pk = up2.package_fk
  INNER JOIN upload u ON up2.upload_fk = u.upload_pk
WHERE up.upload_fk = $1
ORDER BY up2.upload_fk ASC;");
    $res = $this->dbManager->execute($statementName, array($uploadId));
    $packageId = 0;
    $packageName = "";
    $uploads = array();
    while ($row = $this->dbManager->fetchArray($res))
    {
      $packageId = intval($row['package_pk']);
      $packageName = $row['package_name'];
      $uploads[] = Upload::createFromTable($row);
    }
    $this->dbManager->freeResult($res);
    return $packageId > 0 ? new Package($packageId, $packageName, $uploads) : null;
  }

  /**
   * @return Package
   */
  public function createPackage($packageName)
  {
    $statementName = __METHOD__;

    $row = $this->dbManager->getSingleRow(
        "INSERT INTO package (package_name) VALUES($1) RETURNING package_pk",
        array($packageName), $statementName);
    return new Package(intval($row['package_pk']), $packageName, array());
  }

  /**
   * @param int $uploadId
   * @param Package $package
   */
  public function addUploadToPackage($uploadId, Package $package)
  {
    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName,
        "INSERT INTO upload_packages (package_fk, upload_fk) VALUES($1, $2)");
    $res = $this->dbManager->execute($statementName, array($package->getId(), $uploadId));
    $this->dbManager->freeResult($res);
  }

} 