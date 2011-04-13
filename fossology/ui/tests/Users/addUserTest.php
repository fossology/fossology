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
 * Add a user using the UI
 *
 *
 * @version "$Id: addUserTest.php 2020 2009-04-25 03:05:38Z rrando $"
 *
 * Created on March 17, 2009
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class AddUserTest extends fossologyTestCase
{
  public $mybrowser;
  private $UserName;

   function setUP()
  {
    global $URL;
    $this->Login();
  }

  function testaddUser()
  {
    global $URL;

    print "starting AddUserTest\n";

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Admin/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Users/'));
    $page = $this->mybrowser->get("$URL?mod=user_add");
    //print "*********** Page after going to upload file **************\n$page\n";
    $this->assertTrue($this->myassertText($page, '/Add A User/'));
    $this->assertTrue($this->myassertText($page, '/To create a new user,/'));
    $pid = getmypid();
    $this->UserName = 'TestUser-' . "$pid";
    $result = $this->addUser($this->UserName,'Created for testing','fosstester',1,1,'test');
  }
 function tearDown(){
    /* Cleanup: remove the user */
    print "Removing user $this->UserName\n";
    $this->deleteUser($this->UserName);
  }
}

?>
