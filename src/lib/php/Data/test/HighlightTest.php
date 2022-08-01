<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Html\HtmlElement;
use Mockery as M;

class HighlightTest extends \PHPUnit\Framework\TestCase
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

  protected function setUp() : void
  {
    $this->htmlElement = M::mock('Fossology\Lib\Html\HtmlElement');

    $this->highlight = new Highlight($this->start, $this->end, $this->type, $this->refStart, $this->refEnd, $this->infoText, $this->htmlElement);
    $this->highlight->setLicenseId($this->licenseId);
  }

  protected function tearDown() : void
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
