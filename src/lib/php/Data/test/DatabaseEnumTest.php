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

namespace Fossology\Lib\Data;


class DatabaseEnumTest extends \PHPUnit_Framework_TestCase {


  function testCreateDatabaseEnumSelect()
  {
    $aDbEnum = array(new DatabaseEnum(2,'two'),new DatabaseEnum(3,'three'),new DatabaseEnum(5,'five'),new DatabaseEnum(7,'seven'));
    $selectedValue = 5;
    $sel = DatabaseEnum::createDatabaseEnumSelect('selectElementName', $aDbEnum, $selectedValue);
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
 