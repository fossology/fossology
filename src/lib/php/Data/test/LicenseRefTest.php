<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class LicenseRefTest extends \PHPUnit\Framework\TestCase
{
  private $id = 321;
  private $shortName = "<shortName>";
  private $fullName = "<fullName>";
  private $spdxId = "<spdxId>";

  /**
   * @var LicenseRef
   */
  private $licenseRef;

  protected function setUp() : void
  {
    $this->licenseRef = new LicenseRef($this->id, $this->shortName, $this->fullName, $this->spdxId);
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

  public function testGetSpdxId()
  {
    assertThat($this->licenseRef->getSpdxId(), is($this->spdxId));
  }

  public function testDefaultSpdxId()
  {
    $licenseRef = new LicenseRef($this->id, $this->shortName, $this->fullName, "");
    assertThat($licenseRef->getSpdxId(), is(LicenseRef::SPDXREF_PREFIX_FOSSOLOGY . $this->shortName));
  }
}
