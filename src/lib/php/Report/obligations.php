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

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\LicenseDao;

class ObligationsToLicenses
{
  /** @var DbManager */                                                                                                              
  private $dbManager;

  private $licenseDao;

  public function __construct()
  {
    global $container; 
    $this->licenseDao = $container->get('dao.license');
  } 
  
  function getObligations($licenseStatements, $mainLicenseStatements)
  {
    $licenseIds = $this->contentOnly($licenseStatements);
    $mainLicenseIds = $this->contentOnly($mainLicenseStatements);
    if(!empty($mainLicenseIds)){
      $allLicenseIds = array_merge($licenseIds, $mainLicenseIds);
    }
    else{
      $allLicenseIds = array_unique($licenseIds);
    }
    $results = $this->licenseDao->getLicenseObligations($allLicenseIds);
    print_r($results);
  }

  function contentOnly($licenseStatements)
  {
    foreach($licenseStatements as $licenseStatement){
       $licenseId[] = $licenseStatement["licenseId"];
    }
    return $licenseId;
  }
}
