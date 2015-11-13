<?php
/*
Copyright (C) 2015, Siemens AG

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

namespace Fossology\Lib\Test;

class ClassWithPrivateMethod
{
  private $internal = 1;
  private function add($inc)
  {
    $this->internal += $inc;
    return $this->internal;
  }
  public function getInternal()
  {
    return $this->internal;
  }
}

class ReflectoryTest extends \PHPUnit_Framework_TestCase
{
  protected function setUp()
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }
  
  public function testInvokeObjectsMethodnameWith()
  {
    $instanceWithPrivateMethod = new ClassWithPrivateMethod();
    assertThat(Reflectory::invokeObjectsMethodnameWith($instanceWithPrivateMethod, 'add', array(2)),is(1+2));
    assertThat(Reflectory::invokeObjectsMethodnameWith($instanceWithPrivateMethod, 'add', array(4)),is(1+2+4));
  }
  
  public function testGetObjectsProperty()
  {
    $instanceWithPrivateMethod = new ClassWithPrivateMethod();
    assertThat(Reflectory::getObjectsProperty($instanceWithPrivateMethod, 'internal'),is(1));
  }

  public function testSetObjectsProperty()
  {
    $instanceWithPrivateMethod = new ClassWithPrivateMethod();
    Reflectory::setObjectsProperty($instanceWithPrivateMethod, 'internal', 3);
    assertThat($instanceWithPrivateMethod->getInternal(),is(3));
  }
  
  
}
