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

use Fossology\Lib\Data\DatabaseEnum;
use Fossology\Lib\Data\LicenseRef;


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
    
    $clu = new ChangeLicenseUtility();
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
  
  function testCreateLicenseSwitchButtons()
  {
    $clu = new ChangeLicenseUtility();
    $buttons = $clu->createLicenseSwitchButtons();
    assertThat(str_replace(' ', '', $buttons), containsString('moveLicense(this.form.licenseLeft,this.form.licenseRight)'));
    assertThat(str_replace(' ', '', $buttons), containsString('moveLicense(this.form.licenseRight,this.form.licenseLeft)'));
  }

  function testCreateDatabaseEnumSelect()
  {
    $clu = new ChangeLicenseUtility();
    $aDbEnum = array(new DatabaseEnum(2,'two'),new DatabaseEnum(3,'three'),new DatabaseEnum(5,'five'),new DatabaseEnum(7,'seven'));
    $selectedValue = 5;
    $sel = $clu->createDatabaseEnumSelect('selectElementName', $aDbEnum, $selectedValue);
    $pattern = '^\<select.* name="selectElementName".*';
    assertThat($sel,matchesPattern("/$pattern/"));
    $pattern = '\<\/select\>$';
    assertThat($sel,matchesPattern("/$pattern/"));
    $pattern = '\<option[^\>]*selected[^\>]*\>five\<\/option\>';
    assertThat($sel,matchesPattern("/$pattern/"));
    $pattern = '\<option[^\>]*selected[^\>]*\>three\<\/option\>';
    assertThat($sel,not(matchesPattern("/$pattern/")));
  }
  
}
 