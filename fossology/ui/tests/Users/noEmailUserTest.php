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
 * Add a user using the UI, with no email notification,
 * verify it is set correctly in their session.
 *
 * @version "$Id: $"
 *
 * Created on March 17, 2009
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');
global $URL;
class noEmailUserTest extends fossologyTestCase {

  public $mybrowser;

  function setUP() {
    global $URL;
    $this->Login();
  }
  function testNoEmailUser() {
    global $URL;
    print "starting noEmailUserTest\n";
    /* Create the user */
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Admin/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Users/'));
    $page = $this->mybrowser->get("$URL?mod=user_add");
    $this->assertTrue($this->myassertText($page, '/Add A User/'));
    $this->assertTrue($this->myassertText($page, '/To create a new user,/'));
    $result = $this->addUser('UserNoEmail', 'No email notification user',
                                 'fosstester', 1, 1, 'noetest', NULL);
    if (!is_null($result)) {
      /*
       * The test is just creating the user so we can verify that email
       * notification was not turned on.  So, it's OK to have the user already
       * there, for this test it's not an error.
       */
      if ($result != "User already exists.  Not added") {
        $this->fail("Did not add user UserNoEmail result was:\n$result\n");
      }
    }
    /*
     * Verify, login as the user just created and check their session.
     * TODO: look in the db
     */
    $this->Login('UserNoEmail','noetest');
    print "Verifying Email Notification Setting\n";
    $this->assertTrue($_SESSION['UserEnote'] == NULL);
  } //testNoEmailUser

  function tearDown(){
    /* Cleanup: remove the user */
    print "Logging out UserNoEmail\n";
    $this->Logout('UserNoEmail');
    print "Removing user UserNoEmail\n";
    $this->deleteUser('UserNoEmail');
  }
}
?>
