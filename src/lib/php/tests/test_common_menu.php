<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_menu.php
 * \brief Unit tests for common-menu.php
 */

namespace Fossology\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__) . '/../common-menu.php';
require_once dirname(__FILE__) . '/../common-parm.php';

/**
 * \class CommonMenuTest
 * \brief Unit test for common-menu.php
 */
class CommonMenuTest extends TestCase
{
  /**
   * Test for MenuPage() function
   */
  public function testMenuPage()
  {
    $page = 10;
    $totalPage = 15;
    $uri = "http://fossology.org/repo/";
    $expected = "<a class='page-link' href='http://fossology.org/repo/&page=9'>Prev</a>";
    $result = MenuPage($page, $totalPage, $uri);
    // Assert that the result contains the expected output
    $this->assertStringContainsString("<a class='page-link' href='#'>11</a>", $result);
    $this->assertStringContainsString($expected, $result);
  }

  /**
   * Test for MenuEndlessPage() function
   */
  public function testMenuEndlessPage()
  {
    $page = 10;
    $uri = "http://fossology.org/repo/";
    $expected = "<a class='page-link' href='http://fossology.org/repo/&page=9'>Prev</a>";
    $result = MenuEndlessPage($page, 1, $uri);
    // Assert that the result contains the expected output
    $this->assertStringContainsString("<a class='page-link' href='#'>11</a>", $result);
    $this->assertStringContainsString($expected, $result);
  }

  /**
   * Test for menu_cmp() function
   */
  public function testMenuCmp()
  {
    $menua = new \menu();
    $menub = new \menu();
    $menua->Name = 'menua';
    $menub->Name = 'menua';

    // Assert that menu_cmp returns 0 for identical menus
    $this->assertEquals(0, menu_cmp($menua, $menub));

    $menua->Order = 1;
    $menub->Order = 2;
    // Assert that menu_cmp returns 1 when menua has a higher order than menub
    $this->assertEquals(1, menu_cmp($menua, $menub));
  }

  /**
   * Test for menu functions
   */
  public function testMenuFunctions()
  {
    global $MenuList;

    $path = "TestMenu::Test1::Test2";
    $lastOrder = 0;
    $uri = "TestURI";
    $title = "TestTitle";
    $target = "TestTarget";
    $html = "TestHTML";
    $countMenuListBefore = count($MenuList);

    menu_insert($path, $lastOrder, $uri, $title, $target, $html);

    // Assert that menu_insert correctly inserts the menu item
    $this->assertEquals($path, $MenuList[$countMenuListBefore]->SubMenu[0]->SubMenu[0]->FullName);

    $depth = 2;
    $result = menu_find("Test1", $depth);

    // Assert that menu_to_1html() generates correct output
    $result = menu_to_1html($MenuList);
    $pattern = "/TestMenu/";
    $this->assertRegExp($pattern, $result);

    $parm = "";
    $result = menu_to_1list($MenuList, $parm, "", "");
    // Assert that menu_to_1list() generates correct output
    $this->assertRegExp($pattern, $result);
  }

  /**
   * Tear down after each test method
   */
  protected function tearDown(): void
  {
    // Additional teardown code, if needed
  }
}
