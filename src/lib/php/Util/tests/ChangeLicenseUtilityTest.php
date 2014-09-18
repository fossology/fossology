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

namespace Fossology\Lib\Util;

use Fossology\Lib\BusinessRules\NewestEditedLicenseSelector;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\View\Renderer;
use Fossology\Lib\Data\DatabaseEnum;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Monolog\Logger;


class ChangeLicenseUtilityTest extends \PHPUnit_Framework_TestCase {

  function testFilterLists()
  {
    $bigListArray = array(1=>'lic A',2=>'lic B',3=>'lic C');
    $smallListArray = array(1=>'lic A',4=>'lic D');
    $bigListArray = array_merge($bigListArray,$smallListArray);
    
    $bigList = array();
    foreach ($bigListArray as $id=>$name)
    {
      $bigList[] = new LicenseRef($id, $name, $name);
    }
    $smallList = array();
    foreach ($smallListArray as $id=>$name)
    {
      $smallList[] = new LicenseRef($id, $name, $name);
    }

    $dbManager = new DbManager( new Logger(__FILE__));
    $newEditedLicenseSelector = new NewestEditedLicenseSelector();
    $uploadDao =  new UploadDao($dbManager);
    $clu = new ChangeLicenseUtility($newEditedLicenseSelector, $uploadDao, new LicenseDao($dbManager), new ClearingDao($dbManager,$newEditedLicenseSelector, $uploadDao), new Renderer() );
    $clu->filterLists($bigList, $smallList);
    
    $cloneBigListArray = $bigListArray;
    $bigListArray = array_diff($bigListArray, $smallListArray);
    $smallListArray = array_diff($cloneBigListArray, $bigListArray);
            
    $bigListExpect = array();
    foreach ($bigListArray as $id => $name)
    {
      $bigListExpect[] = new LicenseRef($id, $name, $name);
    }
    $shortListExpect = array();
    foreach ($smallListArray as $id=>$name)
    {
      $shortListExpect[] = new LicenseRef($id, $name, $name);
    }

    assertThat($smallList,is(equalTo($shortListExpect)));
    assertThat($bigList,is(equalTo($bigListExpect)));
  }
  
}
 