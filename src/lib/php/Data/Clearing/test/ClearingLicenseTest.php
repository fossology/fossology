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

namespace Fossology\Lib\Data\Clearing;

use Fossology\Lib\Data\LicenseRef;
use Mockery as M;

class ClearingLicenseTest extends \PHPUnit_Framework_TestCase {

  /**
   * @var LicenseRef|M\MockInterface
   */
  private $licenseRef;

  /**
   * @var ClearingLicense
   */
  private $clearingLicense;

  public function setUp() {
    $this->licenseRef = M::mock(LicenseRef::classname());

    $this->clearingLicense = new ClearingLicense($this->licenseRef, false);
  }

  public function tearDown() {
    M::close();
  }

  public function testGetId() {
    $value = "<id>";
    $this->licenseRef->shouldReceive("getId")->once()->withNoArgs()->andReturn($value);

    assertThat($this->clearingLicense->getId(), is($value));
  }

  public function testGetShortName() {
    $value = "<shortName>";
    $this->licenseRef->shouldReceive("getShortName")->once()->withNoArgs()->andReturn($value);

    assertThat($this->clearingLicense->getShortName(), is($value));
  }

  public function testGetFullName() {
    $value = "<fullName>";
    $this->licenseRef->shouldReceive("getFullName")->once()->withNoArgs()->andReturn($value);

    assertThat($this->clearingLicense->getFullName(), is($value));
  }

  public function testIsRemoved() {
    assertThat($this->clearingLicense->isRemoved(), is(false));

    $this->clearingLicense = new ClearingLicense($this->licenseRef, true);
    assertThat($this->clearingLicense->isRemoved(), is(true));
  }
}
 