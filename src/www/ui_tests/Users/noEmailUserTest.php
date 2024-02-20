<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Add a user using the UI, with no email notification,
 * verify it is set correctly in their session.
 *
 * @version "$Id: noEmailUserTest.php 2589 2009-10-15 21:29:11Z rrando $"
 *
 * Created on March 17, 2009
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');
require_once (TESTROOT . '/testClasses/db.php');

global $URL;

class noEmailUserTest extends fossologyTestCase {

  public $mybrowser;

  function setUP() {
    global $URL;
    $this->Login();
  }
  function testNoEmailUser() {
    global $URL;

    /* Check that the user exists */
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
        $this->fail("Did not add user UserwEmail result was:\n$result\n");
      }
    }

    /*
     * Verify, check the db for this user to ensure email_notify is NOT set.
     */
    $dlink = new db('host=localhost dbname=fossology user=fosstester password=fosstester;');
    print "Verifying User email notification setting\n";
    $Sql = "SELECT user_name, email_notify FROM users WHERE user_name='UserNoEmail';";
    $User = $dlink->dbQuery($Sql);
    print "DB: User(SQL results) are:\n";print_r($User) . "\n";
    if((int)$User[0]['email_notify'] == 0) {
      $this->pass();
    }
    else {
      $this->fail("Fail! User UserNoEmail email_notify is not NULL\n");
      printf("in octal the entry for email_notify is:%o\n",$User[0]['email_notify']);
    }
  } //testNoEmailUser
}
