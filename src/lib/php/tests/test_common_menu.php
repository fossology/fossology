<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_menu.php
 * \brief unit tests for common-menu.php
 */

use PHPUnit\Runner\Version as PHPUnitVersion;

require_once(dirname(__FILE__) . '/../common-menu.php');
require_once(dirname(__FILE__) . '/../common-parm.php');

/**
 * \class test_common_menu
 */
class test_common_menu extends \PHPUnit\Framework\TestCase
{
  /* initialization */
  protected function setUp() : void
  {
  }

  /**
   * \brief test for MenuPage()
   */
  function test_MenuPage()
  {
    print "Starting unit test for common-menu.php\n";
    print "test function MenuPage()\n";

    $Page = 10;
    $TotalPage = 15;
    $Uri = "http://fossology.org/repo/";
    $expected = "<a href='http:\/\/fossology.org\/repo\/&page=9'>\[Prev\]<\/a>";
    $result = MenuPage($Page, $TotalPage, $Uri);
    if (intval(explode('.', PHPUnitVersion::id())[0]) >= 9) {
      $this->assertMatchesRegularExpression("/<b>11<\/b>/", $result);
      $this->assertMatchesRegularExpression("/$expected/", $result);
    } else {
      $this->assertRegExp("/<b>11<\/b>/", $result);
      $this->assertRegExp("/$expected/", $result);
    }
  }

  /**
   * \brief test for MenuEndlessPage()
   */
  function test_MenuEndlessPage()
  {
    print "test function MenuEndlessPage()\n";

    $Page = 10;
    $Uri = "http://fossology.org/repo/";
    $expected = "<a href='http:\/\/fossology.org\/repo\/&page=9'>\[Prev\]<\/a>";
    $result = MenuEndlessPage($Page, 1, $Uri);
    if (intval(explode('.', PHPUnitVersion::id())[0]) >= 9) {
      $this->assertMatchesRegularExpression("/<b>11<\/b>/", $result);
      $this->assertMatchesRegularExpression("/$expected/", $result);
    } else {
      $this->assertRegExp("/<b>11<\/b>/", $result);
      $this->assertRegExp("/$expected/", $result);
    }
  }

  /**
   * \brief test for menu_cmp()
   */
  function test_menu_cmp()
  {
    print "test function menu_cmp()\n";

    $menua = new menu;
    $menub = new menu;
    $menua->Name = 'menua';
    $menub->Name = 'menua';
    $result = menu_cmp($menua, $menub);
    $this->assertEquals(0,$result);
    $menua->Order = 1;
    $menub->Order = 2;
    $result = menu_cmp($menua, $menub);
    $this->assertEquals(1,$result);
  }
  /**
   * \brief test for menu_functions()
   */
  function test_menu_functions()
  {
    print "test function menu_insert()\n";

    global $MenuList;

    $Path = "TestMenu::Test1::Test2";
    $LastOrder = 0;
    $URI = "TestURI";
    $Title = "TestTitle";
    $Target = "TestTarget";
    $HTML = "TestHTML";
    $countMenuListBefore = count($MenuList);
    $result = menu_insert($Path, $LastOrder, $URI, $Title, $Target, $HTML);
    $this->assertEquals($Path,$MenuList[$countMenuListBefore]->FullName);

    print "test function menu_find)\n";
    $depth = 2;
    $result = menu_find("Test1", $depth);

    print "test function menu_to_1html)\n";
    $result = menu_to_1html($MenuList);
    $pattern = "/TestMenu/";
    if (intval(explode('.', PHPUnitVersion::id())[0]) >= 9) {
      $this->assertMatchesRegularExpression($pattern, $result);
    } else {
      $this->assertRegExp($pattern, $result);
    }

    print "test function menu_to_1list)\n";
    $Parm = "";
    $result = menu_to_1list($MenuList, $Parm, "", "");
    if (intval(explode('.', PHPUnitVersion::id())[0]) >= 9) {
      $this->assertMatchesRegularExpression($pattern, $result);
    } else {
      $this->assertRegExp($pattern, $result);
    }
    print "Ending unit test for common-menu.php\n";
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() : void
  {
  }
}
