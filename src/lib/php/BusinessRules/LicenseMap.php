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

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\LicenseViewProxy;
use Fossology\Lib\Util\Object;

class LicenseMap extends Object
{
  const CONCLUSION = 1;
  const FAMILY = 2;
  
  /** @var int */
  private $usageId;
  /** @var int */
  private $groupId;
  /** @var array */
  private $map;
  
  /**
   * @param DbManager $dbManager
   * @param int $groupId
   * @param int $usageId
   */
  public function __construct(DbManager $dbManager, $groupId, $usageId=null)
  {
    $this->usageId = $usageId?:self::CONCLUSION;
    $licenseView = new LicenseViewProxy($groupId);
    $query = $licenseView->asCTE()
            .' SELECT rf_fk, rf_shortname parent_shortname, rf_parent FROM license_map, '.$licenseView->getDbViewName()
            .' WHERE rf_pk=rf_parent AND rf_fk!=rf_parent AND usage=$1';
    $stmt = __METHOD__.".$this->usageId,$groupId";
    $dbManager->prepare($stmt,$query);
    $res = $dbManager->execute($stmt,array($this->usageId));
    $this->map = array();
    while($row = $dbManager->fetchArray($res))
    {
      $this->map[$row['rf_fk']] = $row;
    }
    $dbManager->freeResult($res);
  }
  
  
  public function getProjectedId($licenseId)
  {
    if(array_key_exists($licenseId, $this->map))
    {
      return $this->map[$licenseId]['rf_parent'];
    }
    return $licenseId;
  }
  
  
  public function getProjectedShortname($licenseId)
  {
    if(array_key_exists($licenseId, $this->map))
    {
      return $this->map[$licenseId]['parent_shortname'];
    }
    return null;
  }
  
  public function getUsage()
  {
    return $this->usageId;
  }
  
  public function getGroupId()
  {
    return $this->groupId;
  }
  
}