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

  public function testImplodeLicensesEmpty()
  {
    assertThat(SpdxTwoUtils::implodeLicenses(null), equalTo(""));
    assertThat(SpdxTwoUtils::implodeLicenses(array()), equalTo(""));
  }

  public function testImplodeLicensesSingle()
  {
    assertThat(SpdxTwoUtils::implodeLicenses(array("LIC1")), equalTo("LIC1"));
  }

  public function testImplodeLicensesMultiple()
  {
    $lics = array("LIC1","LIC2","LIC3");
    $result = SpdxTwoUtils::implodeLicenses($lics);
    // should be something like "LIC1 AND LIC2 AND LIC3"

    $exploded = array_map("trim", explode("AND",$result));
    foreach ($exploded as $e)
    {
      assertThat(in_array($e,$lics,true), equalTo(true));
    }
    foreach ($lics as $l)
    {
      assertThat(in_array($l, $exploded, true), equalTo(true));
    }
  }

  public function testImplodeLicensesDualLicense()
  {
    $lics = array("LIC1", "Dual-license", "LIC3");
    $result = SpdxTwoUtils::implodeLicenses($lics);
    // should be something like "LIC1 OR LIC3"

    $exploded = array_map("trim", explode("OR",$result));
    foreach ($exploded as $e)
    {
      assertThat(in_array($e,$lics,true));
    }
    foreach ($lics as $l)
    {
      if ($l !== "Dual-license")
      {
        assertThat(in_array($l, $exploded, true));
      }
    }
  }

  public function testImplodeLicensesMultipleAndImplodedDualLicense()
  {
    $lics = array("LIC1","LIC2 OR LIC3");
    $result = SpdxTwoUtils::implodeLicenses($lics);
    // should be something like "LIC1 AND (LIC2 OR LIC3)"

    $exploded = array_map("trim", explode("AND",$result));
    foreach ($exploded as $e)
    {
      if(strpos($e, " OR ") !== false)
      {
        $eWithoutBraces = trim(substr($e,1,-1));
        assertThat(in_array($eWithoutBraces,$lics,true));
      }
      else
      {
        assertThat(in_array($e,$lics,true));
      }
    }
    foreach ($lics as $l)
    {
      if(strpos($l, " OR ") !== false)
      {
        $lWithBraces = "($l)";
        assertThat(in_array($lWithBraces,$exploded,true));
      }
      else
      {
        assertThat(in_array($l, $exploded, true));
      }
    }
  }

  public function testPrefixImplodeLicensesMultipleAndImplodedDualLicense()
  {
    $lics = array("LIC1","pre-LIC2 OR pre-LIC3");
    $prefix = "pre-";
    $result = SpdxTwoUtils::implodeLicenses($lics, $prefix);
    // should be something like "pre-LIC1 AND (pre-LIC2 OR pre-LIC3)"

    $exploded = array_map("trim", explode("AND",$result));
    foreach ($exploded as $e)
    {
      if(strpos($e, " OR ") !== false)
      {
        $eWithoutBraces = trim(substr($e,1,-1));
        assertThat(in_array($eWithoutBraces,$lics,true));
      }
      else
      {
        assertThat(in_array(substr($e,strlen($prefix)),$lics,true));
      }
    }
    foreach ($lics as $l)
    {
      if(strpos($l, " OR ") !== false)
      {
        $lWithBraces = "($l)";
        assertThat(in_array($lWithBraces,$exploded,true));
      }
      else
      {
        assertThat(in_array($prefix.$l, $exploded, true));
      }
    }
  }

  public function testImplodeLicensesDualLicenseAndImplodedDualLicense()
  {
    $lics = array("LIC1","LIC2 OR LIC3", "Dual-license");
    $result = SpdxTwoUtils::implodeLicenses($lics);
    // should be something like "LIC1 OR (LIC2 OR LIC3)"

    foreach ($lics as $l)
    {
      if ($l !== "Dual-license")
      {
        assertThat(strpos($result, $l) !== false);
      }
    }
  }
}
