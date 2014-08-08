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

namespace Fossology\Lib\Html;


class SimpleHtmlElementTest extends \PHPUnit_Framework_TestCase
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
 