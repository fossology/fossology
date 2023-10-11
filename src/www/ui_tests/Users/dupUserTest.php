<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

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
