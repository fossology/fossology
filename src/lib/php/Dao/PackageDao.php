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

  }

  /**
   * @return Package
   */
  public function createPackage()
  {

  }

  /**
   * @param int $uploadId
   * @param $package
   */
  public function addUploadToPackage($uploadId, Package $package)
  {

  }

} 