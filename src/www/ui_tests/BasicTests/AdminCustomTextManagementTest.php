<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Test the admin custom text management functionality
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class AdminCustomTextManagementTest extends fossologyTestCase
{
  public $mybrowser;

  function setUp()
  {
    global $URL;
    $this->Login();
  }

  function testAdminCustomTextManagementAccess()
  {
    global $URL;

    print "Starting AdminCustomTextManagementTest\n";

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Admin/'));
    
    // Navigate to the custom text management page
    $page = $this->mybrowser->get("$URL?mod=admin_custom_text_management");
    
    // Check if the page loads correctly
    $this->assertTrue($this->myassertText($page, '/Custom Text Management/'));
    $this->assertTrue($this->myassertText($page, '/Add New Custom Text/'));
    
    print "AdminCustomTextManagementTest completed successfully\n";
  }

  function testAddCustomText()
  {
    global $URL;

    // Navigate to add new text page
    $page = $this->mybrowser->get("$URL?mod=admin_custom_text_management&edit=0");
    
    // Check if the add form loads correctly
    $this->assertTrue($this->myassertText($page, '/Text/'));
    $this->assertTrue($this->myassertText($page, '/Acknowledgement/'));
    $this->assertTrue($this->myassertText($page, '/Comments/'));
    $this->assertTrue($this->myassertText($page, '/Associated License/'));
    
    print "Add custom text form test completed successfully\n";
  }
} 