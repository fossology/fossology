<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Authors: Johannes Najjar, Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

class LicenseTest extends \PHPUnit\Framework\TestCase
{
  /** @var string */
  private $text;
  /** @var string */
  private $url;
  /** @var License */
  private $license;

  protected function setUp() : void
  {
    $this->text = "The License text";
    $this->url = "http://www.fossology.org";

    $this->license = new License(8, "testSN", "testFN", 4, $this->text, $this->url, 1);
  }

  public function testText()
  {
    assertThat($this->license->getText(), is($this->text));
  }

  public function testUrl()
  {
    assertThat($this->license->getUrl(), is($this->url));
  }
}
