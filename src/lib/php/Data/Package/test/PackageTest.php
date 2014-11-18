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

namespace Fossology\Lib\Data\Package;

use Fossology\Lib\Data\Upload\Upload;
use Mockery as M;


class PackageTest extends \PHPUnit_Framework_TestCase {

  private $id = 123;

  private $uploads;

  /** @var Package */
  private $package;

  public function setUp() {
    $this->uploads = array(M::mock(Upload::classname()));

    $this->package = new Package($this->id, $this->uploads);
  }

  public function testGetId()
  {
    assertThat($this->package->getId(), is($this->id));
  }

  public function testGetUploads()
  {
    assertThat($this->package->getUploads(), is($this->uploads));
  }
}
 