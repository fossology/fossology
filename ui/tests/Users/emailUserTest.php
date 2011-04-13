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
 * Add a user using the UI, with email notification,
 * verify it is set correctly in their session.
 *
 * @version "$Id: emailUserTest.php 2590 2009-10-15 21:30:32Z rrando $"
 *
 * Created on March 17, 2009
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');
require_once (TESTROOT . '/testClasses/db.php');

global $URL;

class EmailUserTest extends fossologyTestCase {

  public $mybrowser;
  protected $User;

  function setUP() {

    global $URL;
    $this->Login();
  }
  function testEmailUser() {

    global $URL;
    print "starting EmailUserTest\n";
    /* Create the user */
    print "Creating user: UserwEmail\n";
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Admin/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Users/'));
    $page = $this->mybrowser->get("$URL?mod=user_add");
    $this->assertTrue($this->myassertText($page, '/Add A User/'));
    $this->assertTrue($this->myassertText($page, '/To create a new user,/'));
    $result = $this->addUser('UserwEmail', 'email notification user',
                                 'fosstester', 1, 1, 'uwetest', 'y');
    if (!is_null($result)) {
      /*
       * The test is just creating the user so we can verify that email
       * notification was not turned on.  So, it's OK to have the user already
       * there, for this test it's not an error.
       */
      if ($result != "User already exists.  Not added") {
        $this->fail("Did not add user UserwEmail result was:\n$result\n");
      }
    }
    /*
     * Verify, check the db entry for the user and make sure email_notify is set.
     */
    print "Verifying User email notification\n";
    $dlink = new db('host=localhost dbname=fossology user=fosstester password=fosstester');
    $Sql = "SELECT user_name, email_notify FROM users WHERE user_name='UserwEmail';";
    $User = $dlink->dbQuery($Sql);
    //print "Entryies are: {$User[0]['user_name']}, {$User[0]['email_notify']}\n";
    $this->assertEqual($User[0]['email_notify'],'y',
      "Fail! User UserwEmail does not have email_notify set\n");
  } //testEmailUser
};
?>
