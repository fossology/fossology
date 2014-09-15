<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

use Fossology\Lib\Html\HtmlElement;
use Mockery as M;

class HighlightTest extends \PHPUnit_Framework_TestCase
{
  private $start = 10;
  private $end = 12;
  private $type = "M";
  private $licenseId = 321;
  private $refStart = 2;
  private $refEnd = 3;
  private $infoText = "<infoText>";

  /**
   * @var HtmlElement
   */
  private $htmlElement;

  /**
   * @var Highlight
   */
  private $highlight;

  public function setUp()
  {
    $this->htmlElement = M::mock('Fossology\Lib\Html\HtmlElement');

    $this->highlight = new Highlight($this->start, $this->end, $this->type, $this->refStart, $this->refEnd, $this->infoText, $this->htmlElement);
    $this->highlight->setLicenseId($this->licenseId);
  }

  function tearDown()
  {
    M::close();
  }

  public function testGetStart()
  {
    assertThat($this->highlight->getStart(), is($this->start));
  }

  public function testGetEnd()
  {
    assertThat($this->highlight->getEnd(), is($this->end));
  }

  public function testGetType()
  {
    assertThat($this->highlight->getType(), is($this->type));
  }

  public function testGetLicenseId()
  {
    assertThat($this->highlight->getLicenseId(), is($this->licenseId));
  }

  public function testGetRefStart()
  {
    assertThat($this->highlight->getRefStart(), is($this->refStart));
  }

  public function testGetRefEnd()
  {
    assertThat($this->highlight->getRefEnd(), is($this->refEnd));
  }

  public function testSetInfoText()
  {
    assertThat($this->highlight->getInfoText(), is($this->infoText));
  }

  public function testGetInfoText()
  {
    assertThat($this->highlight->getInfoText(), is($this->infoText));
  }

  public function testGetHtmlElement()
  {
    assertThat($this->highlight->getHtmlElement(), is($this->htmlElement));
  }
}
 