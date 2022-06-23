<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Html;

class LinkElementTest extends \PHPUnit\Framework\TestCase
{

  public function testLinkElement()
  {
    $linkElement = new LinkElement("<url>");

    assertThat($linkElement->getOpeningText(), is("<a href=\"<url>\">"));
    assertThat($linkElement->getClosingText(), is("</a>"));
  }
}
