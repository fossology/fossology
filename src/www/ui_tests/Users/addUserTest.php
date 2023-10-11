<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

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
