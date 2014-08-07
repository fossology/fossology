<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

namespace Fossology\Lib\Data;

class LicenseRefTest extends \PHPUnit_Framework_TestCase
{
  private $id = 321;
  private $shortName = "<shortName>";
  private $fullName = "<fullName>";

  /**
   * @var LicenseRef
   */
  private $licenseRef;

  public function setUp()
  {
    $this->licenseRef = new LicenseRef($this->id, $this->shortName, $this->fullName);
  }

  public function testGetId()
  {
    assertThat($this->licenseRef->getId(), is($this->id));
  }

  public function testGetShortName()
  {
    assertThat($this->licenseRef->getShortName(), is($this->shortName));
  }

  public function testGetFullName()
  {
    assertThat($this->licenseRef->getFullName(), is($this->fullName));
  }

}
 