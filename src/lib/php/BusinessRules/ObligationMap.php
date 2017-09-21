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
  public function getAvailableShortnames($candidate=false)
  {
    if ($candidate)
    {
      $sql = "SELECT rf_shortname from license_candidate;";
      $stmt = __METHOD__.".rf_candidate_shortnames";
    }
    else
    {
      $sql = "SELECT rf_shortname from license_ref;";
      $stmt = __METHOD__.".rf_shortnames";
    }
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt);
    $vars = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);

    $licshortnames = array();
    foreach ($vars as $rf_entry)
    {
      $shortname = $rf_entry['rf_shortname'];
      $licshortnames[$shortname] = $shortname;
    }

    return $licshortnames;
  }

  /** @brief get the license id from the shortname */
  public function getIdFromShortname($shortname,$candidate=false)
  {
    if ($candidate)
    {
      $sql = "SELECT * from license_candidate where rf_shortname = $1;";
    }
    else
    {
      $sql = "SELECT * from license_ref where rf_shortname = $1;";
    }
    $result = $this->dbManager->getSingleRow($sql,array($shortname));
    return $result['rf_pk'];
  }

  /** @brief get the shortname of the license by Id */
  public function getShortnameFromId($rfId,$candidate=false)
  {
    if ($candidate)
    {
      $sql = "SELECT * FROM license_candidate WHERE rf_pk = $1;";
    }
    else
    {
      $sql = "SELECT * FROM license_ref WHERE rf_pk = $1;";
    }
    $result = $this->dbManager->getSingleRow($sql,array($rfId));
    return $result['rf_shortname'];
  }

  /** @brief get the list of licenses associated with the obligation */
  public function getLicenseList($obId,$candidate=false)
  {
    $liclist = "";
    if ($candidate)
    {
      $sql = "SELECT rf_fk FROM obligation_candidate_map WHERE ob_fk=$obId;";
      $stmt = __METHOD__.".om_candidate_$obId";
    }
    else
    {
      $sql = "SELECT rf_fk FROM obligation_map WHERE ob_fk=$obId;";
      $stmt = __METHOD__.".om_license_$obId";
    }
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt);
    $vars = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    foreach ($vars as $map_entry)
    {
      $licname = $this->getShortnameFromId($map_entry['rf_fk'], $candidate);
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
  public function isLicenseAssociated($obId,$licId,$candidate=false)
  {
    if ($candidate)
    {
      $sql = "SELECT * from obligation_candidate_map where ob_fk = $1 and rf_fk = $2;";
      $stmt = __METHOD__.".om_testcandidate_$obId";
    }
    else
    {
      $sql = "SELECT * from obligation_map where ob_fk = $1 and rf_fk = $2;";
      $stmt = __METHOD__.".om_testlicense_$obId";
    }
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt,array($obId,$licId));
    $vars = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);

    if (!empty($vars)) {
      return true;
    }

    return false;
  }

  /** @brief check if the text of this obligation is existing */
  public function associateLicenseWithObligation($obId,$licId,$candidate=false)
  {
    if ($candidate)
    {
      $sql = "INSERT INTO obligation_candidate_map (ob_fk, rf_fk) VALUES ($1, $2)";
      $stmt = __METHOD__.".om_addcandidate_$obId";
    }
    else
    {
      $sql = "INSERT INTO obligation_map (ob_fk, rf_fk) VALUES ($1, $2)";
      $stmt = __METHOD__.".om_addlicense_$obId";
    }
    $this->dbManager->prepare($stmt,$sql);
    $res = $this->dbManager->execute($stmt,array($obId,$licId));
    $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);
  }

  /** @brief check if the text of this obligation is existing */
  public function unassociateLicenseFromObligation($obId,$licId=0,$candidate=false)
  {
    if ($licId == 0)
    {
      if ($candidate)
      {
        $sql = "DELETE FROM obligation_candidate_map WHERE ob_fk=$1";
      }
      else
      {
        $sql = "DELETE FROM obligation_map WHERE ob_fk=$1";
      }
      $stmt = __METHOD__.".omdel_all";
      $this->dbManager->prepare($stmt,$sql);
      $res = $this->dbManager->execute($stmt,array($obId));
    }
    else
    {
      if ($candidate)
      {
        $sql = "DELETE FROM obligation_candidate_map WHERE ob_fk=$1 AND rf_fk=$2";
      }
      else
      {
        $sql = "DELETE FROM obligation_map WHERE ob_fk=$1 AND rf_fk=$2";
      }
      $stmt = __METHOD__.".omdel_$licId";
      $this->dbManager->prepare($stmt,$sql);
      $res = $this->dbManager->execute($stmt,array($obId,$licId));
    }
    $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);
  }

}
