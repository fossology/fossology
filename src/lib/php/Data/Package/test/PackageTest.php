<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data\Package;

use Fossology\Lib\Data\Upload\Upload;
use Mockery as M;

class PackageTest extends \PHPUnit\Framework\TestCase
{

  private $id = 123;

  private $name = "<packageName>";

  private $uploads;

  /** @var Package */
  private $package;

  protected function setUp() : void
  {
    $this->uploads = array(M::mock(Upload::class));

    $this->package = new Package($this->id, $this->name, $this->uploads);
  }

  public function testGetId()
  {
    assertThat($this->package->getId(), is($this->id));
  }

  public function testGetName()
  {
    assertThat($this->package->getName(), is($this->name));
  }

  public function testGetUploads()
  {
    assertThat($this->package->getUploads(), is($this->uploads));
  }
}
