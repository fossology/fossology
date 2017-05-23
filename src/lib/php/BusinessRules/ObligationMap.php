<?php
/*
Copyright (C) 2017, Siemens AG

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
use Fossology\Lib\Util\Object;

class ObligationMap extends Object
{

  /** @var DbManager */
  private $dbManager;

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
  }

  /** @brief get the license id from the shortname */
  public function getIdFromShortname($shortname)
  {
    $sql = "SELECT * from license_ref where rf_shortname = $1;";
    $result = $this->dbManager->getSingleRow($sql,array($shortname));
    return $result['rf_pk'];
  }

  /** @brief get the shortname of the license by Id */
  public function getShortnameFromId($rfId)
  {
    $sql = "SELECT * FROM license_ref WHERE rf_pk = $1;";
    $result = $this->dbManager->getSingleRow($sql,array($rfId));
    return $result['rf_shortname'];
  }

  /** @brief get the list of licenses associated with the obligation */
  public function getLicenseList($obId)
  {
    $liclist = "";
    $sql = "SELECT rf_fk FROM obligation_map WHERE ob_fk=$obId;";
    $stmt = __METHOD__.".om_$obId";
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt);
    $vars = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    foreach ($vars as $map_entry)
    {
      $licname = $this->getShortnameFromId($map_entry['rf_fk']);
      if ($liclist == "")
      {
        $liclist = "$licname";
      }
      else
      {
        $liclist .= ";$licname";
      }
    }

    return $liclist;
  }

  /** @brief check if the obligation is already associated with the license */
  public function isLicenseAssociated($obId,$licId)
  {
    $sql = "SELECT * from obligation_map where ob_fk = $1 and rf_fk = $2;";
    $result = $this->dbManager->getSingleRow($sql,array($obId,$licId));
    if ($result)
    {
      return True;
    }

    return False;
  }

  /** @brief check if the text of this obligation is existing */
  public function associateLicenseWithObligation($obId,$licId)
  {
    $sql = "INSERT INTO obligation_map (ob_fk, rf_fk) VALUES ($1, $2)";
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt,array($obId,$licId));
    $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);
  }

  /** @brief check if the text of this obligation is existing */
  public function unassociateLicenseFromObligation($obId,$licId)
  {
    $sql = "DELETE FROM obligation_map WHERE ob_fk=$1 AND rf_fk=$2";
    $stmt = __METHOD__.".omdel_$licId";
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt,array($obId,$licId));
    $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);
  }

}
