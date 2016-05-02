<?php
/*
Copyright (C) 2016, Siemens AG

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

namespace Fossology\SpdxTwo;

require_once(__DIR__ . '/../../agent/spdx2utils.php');

class spdx2Test extends \PHPUnit_Framework_TestCase
{
  private $assertCountBefore;

  protected function setUp()
  {
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown()
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  public function testPreWorkOnArgsFlpZero()
  {
    $args = array();
    assertThat(SpdxTwoUtils::preWorkOnArgsFlp($args,"key1","key2"), equalTo($args));
  }

  public function testPreWorkOnArgsFlpId()
  {
    $args = array("key1" => "value");
    assertThat(SpdxTwoUtils::preWorkOnArgsFlp($args,"key1","key2"), equalTo($args));
  }

  public function testPreWorkOnArgsFlpRealWork()
  {
    $args = array("key1" => "value --key2=anotherValue");
    $result = SpdxTwoUtils::preWorkOnArgsFlp($args,"key1","key2");
    assertThat($result["key1"], equalTo("value"));
    assertThat($result["key2"], equalTo("anotherValue"));
  }

  public function provideLicenseSet()
  {
    return array(
        'null' => array(null, '',  ''),
        'empty array' => array(array(), '', ''),
        'empty array but prefix' => array(array(), 'pre', ''),
        'single license'=>array(array("LIC1"), '', 'LIC1'),
        'multiple licenses' => array(array("LIC1","LIC2","LIC3"), '', 'LIC1 AND LIC2 AND LIC3'),
        'dual license 1st pos' => array(array("Dual-license", "LIC2", "LIC3"), '', 'LIC2 OR LIC3'),
        'dual license 2nd pos' => array(array("LIC1", "Dual-license", "LIC3"), '', 'LIC1 OR LIC3'),
        'dual license 3rd pos' => array(array("LIC1", "LIC2", "Dual-license"), '',  'LIC1 OR LIC2'),
        'dual license with prefix' => array(array("LIC1","LIC2", "Dual-license"), 'pre-', 'pre-LIC1 OR pre-LIC2'),
        'multiple dualLicense' => array(array("LIC1","LIC2 OR LIC3"),  '', '(LIC2 OR LIC3) AND LIC1'),
        'multiple dualLicense with prefix' => array(array("LIC1","LIC2 OR LIC3"), 'pre-', '(LIC2 OR LIC3) AND pre-LIC1'),
        'dual multi license' => array(array("LIC1","LIC2 OR LIC3", "Dual-license"), '', '(LIC2 OR LIC3) OR LIC1'),
        'dual multi license with prefix' => array(array("LIC1","LIC2 OR LIC3", "Dual-license"), 'pre-', '(LIC2 OR LIC3) OR pre-LIC1'),
    );
  }

  /**
   * @dataProvider provideLicenseSet
   * @param array $lics
   * @param string $prefix
   * @param string $expected
   */
  public function testImplodeLicenses($lics, $prefix, $expected)
  {
    assertThat(SpdxTwoUtils::implodeLicenses($lics, $prefix), equalTo($expected));
  }
}
