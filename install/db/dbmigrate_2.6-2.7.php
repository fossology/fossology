<?php
/***********************************************************
 Copyright (C) 2014 Siemens AG

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
 ***********************************************************/

use Fossology\Lib\BusinessRules\LicenseMap;

function setLicenseMap($verbose)
{
  /** DbManager */
  global $dbManager;
  $stmt = __METHOD__;
  $sql = "SELECT rf_pk FROM ONLY license_ref LEFT JOIN license_map ON rf_pk=rf_fk WHERE rf_fk IS NULL";
  $dbManager->prepare($stmt,$sql);
  $res = $dbManager->execute($stmt);
  $unmapped = $dbManager->fetchAll($res);
  $dbManager->freeResult($res);
  foreach ($unmapped as $um)
  {
    $dbManager->insertTableRow('license_map',array('rf_fk'=>$um['rf_pk'],'rf_parent'=>$um['rf_pk'],'usage'=>LicenseMap::CONCLUSION));
  }
  if ($verbose)
  {
    echo "Mapped ".count($unmapped)." licenses.\n";
  }
  return count($unmapped);
}


/**
 * @global type $dbManager
 * @param type $verbose tell about changes
 * @return int number of inserted changes
 */
function migrate_26_27($verbose)
{
  $unmappedCnt = setLicenseMap($verbose);
  return $unmappedCnt;
}
