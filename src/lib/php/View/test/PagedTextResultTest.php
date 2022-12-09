<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\View;

if (! function_exists('')) {
  require_once (__DIR__ . "/../../common-string.php");
}

class PagedTextResultTest extends \PHPUnit\Framework\TestCase
{

  const START_OFFSET = 15;

  /** @var PagedTextResult */
  private $pagedTextResult;

  protected function setUp() : void
  {
    $this->pagedTextResult = new PagedTextResult(self::START_OFFSET);
  }

  public function testRenderContentTextEscapesForHtml()
  {
    $this->pagedTextResult->appendContentText("&");

    assertThat($this->pagedTextResult->getText(), is("&amp;"));
    $this->addToAssertionCount(1);
  }

  public function testRenderContentTextConvertsToUtf8()
  {
    $this->pagedTextResult->appendContentText("äöü");
    $expected = htmlspecialchars("äöü", ENT_SUBSTITUTE, 'UTF-8');
    assertThat($this->pagedTextResult->getText(), is(equalTo($expected)));
    $this->addToAssertionCount(1);
  }
}
