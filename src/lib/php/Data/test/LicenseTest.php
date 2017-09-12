<?php
/*
Copyright (C) 2014, Siemens AG
Authors: Johannes Najjar, Andreas Würl

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


class LicenseTest extends \PHPUnit_Framework_TestCase {
  /** @var string */
  private $text;
  /** @var string */
  private $url;
  /** @var License */
  private $license;

  protected function setUp()
  {
    $this->text = "The License text";
    $this->url = "http://www.fossology.org";

    $this->license = new License(8,"testSN", "testFN", 4, $this->text, $this->url, 1);
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
 