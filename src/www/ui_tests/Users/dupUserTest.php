<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

/**
 * try to create a duplicate user using the UI
 *
 * @version "$Id: dupUserTest.php 2020 2009-04-25 03:05:38Z rrando $"
 *
 * Created on March 17, 2009
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class DupUserTest extends fossologyTestCase {
  public $mybrowser;
  private $UserName;

  function setUP() {
    global $URL;
    $this->Login();
  }

  function testDupUser() {
    global $URL;

    print "starting DupUserTest\n";

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Admin/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Users/'));
    $page = $this->mybrowser->get("$URL?mod=user_add");
    $this->assertTrue($this->myassertText($page, '/Add A User/'));
    $this->assertTrue($this->myassertText($page, '/To create a new user,/'));
    $this->UserName = 'TestUserDup';
    $this->addUser($this->UserName,'Created for Duplicate user testing','fosstester',1,1,'test');
    /* Try to add the user again */
    $page = $this->mybrowser->get("$URL?mod=user_add");
    $this->assertTrue($this->myassertText($page, '/Add A User/'));
    $this->assertTrue($this->myassertText($page, '/To create a new user,/'));
    $result = $this->addUser($this->UserName,'Created for Duplicate user testing',
                             'fosstester',1,1,'test');
    if(!empty($result)) {
      $pattern = "/User already exists\.  Not added/";
      if(preg_match($pattern,$result,$match)) {
        $this->pass();
      }
      else {
        $this->fail("Did not match string, got:\n$result\n");
      }
    }
  }
 function tearDown(){
    /* Cleanup: remove the user */
    print "Removing user $this->UserName\n";
    $this->deleteUser($this->UserName);
  }
}

?>
