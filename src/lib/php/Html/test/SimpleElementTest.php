<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Html;

class SimpleHtmlElementTest extends \PHPUnit\Framework\TestCase
{

  public function testElementWithoutAttributes()
  {
    $element = new SimpleHtmlElement("p");

    assertThat($element->getOpeningText(), is("<p>"));
    assertThat($element->getClosingText(), is("</p>"));
  }

  public function testElementWithSingleAttribute()
  {
    $element = new SimpleHtmlElement("p", array("class" => "extra"));

    assertThat($element->getOpeningText(), is("<p class=\"extra\">"));
    assertThat($element->getClosingText(), is("</p>"));
  }

  public function testElementWithSingleAttributeSetAfterConstruction()
  {
    $element = new SimpleHtmlElement("p");
    $element->setAttribute("class", "other");

    assertThat($element->getOpeningText(), is("<p class=\"other\">"));
    assertThat($element->getClosingText(), is("</p>"));
  }

  public function testElementWithTwoAttributes()
  {
    $element = new SimpleHtmlElement("p", array("class" => "extra", "style" => "font-size: 10pt;"));

    assertThat($element->getOpeningText(), is("<p class=\"extra\" style=\"font-size: 10pt;\">"));
    assertThat($element->getClosingText(), is("</p>"));
  }
}
