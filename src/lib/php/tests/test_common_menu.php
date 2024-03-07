<?php

namespace YourNamespace\Tests;

use PHPUnit\Framework\TestCase;

require_once(dirname(__FILE__) . '/../common-menu.php');
require_once(dirname(__FILE__) . '/../common-parm.php');

/**
 * Test suite for common-menu.php
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
        $expected = "<a class='page-link' href='http:\/\/fossology.org\/repo\/&page=9'>Prev<\/a>";
        $result = MenuPage($page, $totalPage, $uri);

        // Assert that the result contains the expected output
        $this->assertStringContainsString("<a class='page-link' href='#'>11<\/a>", $result);
        $this->assertStringContainsString($expected, $result);
    }

    /**
     * Test for MenuEndlessPage() function
     */
    public function testMenuEndlessPage()
    {
        $page = 10;
        $uri = "http://fossology.org/repo/";
        $expected = "<a class='page-link' href='http:\/\/fossology.org\/repo\/&page=9'>Prev<\/a>";
        $result = MenuEndlessPage($page, 1, $uri);

        // Assert that the result contains the expected output
        $this->assertStringContainsString("<a class='page-link' href='#'>11<\/a>", $result);
        $this->assertStringContainsString($expected, $result);
    }

    /**
     * Test for menu_cmp() function
     */
    public function testMenuCmp()
    {
        $menua = new menu;
        $menub = new menu;
        $menua->Name = 'menua';
        $menub->Name = 'menua';
        
        // Assert that menu_cmp returns 0 for identical menus
        $this->assertEquals(0, menu_cmp($menua, $menub));
        
        $menua->Order = 1;
        $menub->Order = 2;

        // Assert that menu_cmp returns 1 when menua has higher order than menub
        $this->assertEquals(1, menu_cmp($menua, $menub));
    }

    /**
     * Test for menu_functions() function
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
        $result = menu_insert($path, $lastOrder, $uri, $title, $target, $html);

        // Assert that menu_insert correctly inserts the menu item
        $this->assertEquals($path, $MenuList[$countMenuListBefore]->SubMenu[0]->SubMenu[0]->FullName);

        $depth = 2;
        $result = menu_find("Test1", $depth);

        // Assert any necessary conditions for menu_find function

        $result = menu_to_1html($MenuList);
        $pattern = "/TestMenu/";

        // Assert that the generated HTML contains the expected pattern
        $this->assertStringMatchesFormat($pattern, $result);

        $parm = "";
        $result = menu_to_1list($MenuList, $parm, "", "");

        // Assert any necessary conditions for menu_to_1list function

        // Assert that the generated list contains the expected pattern
        $this->assertStringMatchesFormat($pattern, $result);
    }

    /**
     * Tear down after each test method
     */
    protected function tearDown(): void
    {
        // Additional teardown code, if needed
    }
}
