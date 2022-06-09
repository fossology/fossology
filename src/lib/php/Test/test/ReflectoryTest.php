<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
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

class ReflectoryTest extends \PHPUnit\Framework\TestCase
{
  protected function setUp() : void
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
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
