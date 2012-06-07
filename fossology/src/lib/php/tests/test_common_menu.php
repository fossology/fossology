<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

/**
 * \file test_common_menu.php
 * \brief unit tests for common-menu.php
 */

require_once('../common-menu.php');
require_once('../common-parm.php');

/**
 * \class test_common_menu
 */
class test_common_menu extends PHPUnit_Framework_TestCase
{
  /* initialization */
  protected function setUp()
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
    $this->assertRegExp("/<b>11<\/b>/", $result);
    $this->assertRegExp("/$expected/", $result);
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
    $this->assertRegExp("/<b>11<\/b>/", $result);
    $this->assertRegExp("/$expected/", $result);
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
    $result = menu_insert($Path, $LastOrder, $URI, $Title, $Target, $HTML);
    //print_r($MenuList);
    $this->assertEquals($Path,$MenuList[0]->FullName);
     
    print "test function menu_find)\n";
    $depth = 2;
    $result = menu_find("Test1", $depth);
    //print "Result: $result\n";

    print "test function menu_to_1html)\n";
    $result = menu_to_1html($MenuList);
    $this->assertRegExp("/TestMenu/", $result);

    print "test function menu_to_1list)\n";
    $Parm = "";
    $result = menu_to_1list($MenuList, $Parm, "", "");
    $this->assertRegExp("/TestMenu/", $result);
    print "Ending unit test for common-menu.php\n";
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() {
  }
}

?>
