<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;


use Fossology\Lib\Data\Package\Package;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Db\DbManager;
use Monolog\Logger;

class PackageDao
{

  /** @var DbManager */
  private $dbManager;

  /**
   * @param DbManager $dbManager
   * @param Logger $logger
   */
  public function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
  }

  /**
   * @param int $uploadId
   * @return Package|null
   */
  public function findPackageForUpload($uploadId)
  {
    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName, "
SELECT p.*, u.*
FROM package p
  INNER JOIN upload_packages up ON p.package_pk = up.package_fk
  INNER JOIN upload_packages up2 ON p.package_pk = up2.package_fk
  INNER JOIN upload u ON up2.upload_fk = u.upload_pk
WHERE up.upload_fk = $1
ORDER BY up2.upload_fk ASC");
    $res = $this->dbManager->execute($statementName, array($uploadId));
    $packageId = 0;
    $packageName = "";
    $uploads = array();
    while ($row = $this->dbManager->fetchArray($res)) {
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
