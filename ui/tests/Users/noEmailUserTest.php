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
 * Add a user using the UI, with no email notification
 *
 * @version "$Id: $"
 *
 * Created on March 17, 2009
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class noEmailUserTest extends fossologyTestCase
{
  public $mybrowser;

   function setUP()
  {
    global $URL;
    $this->Login();
  }

  function testNoEmailUser()
  {
    global $URL;

    print "starting noEmailUserTest\n";

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Admin/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Users/'));
    $page = $this->mybrowser->get("$URL?mod=user_add");
    //print "*********** Page after going to upload file **************\n$page\n";
    $this->assertTrue($this->myassertText($page, '/Add A User/'));
    $this->assertTrue($this->myassertText($page, '/To create a new user,/'));
    $result = $this->addUser('UserNoEmail','No email notification user','fosstester',1,1,'noetest',NULL);
    if(!is_null($result)) {
      $this->fail("Did not add user UserNoEmail result was:\n$result\n");
    }
    // should be configed test user, logout */
    print "NEUT: logging out\n";
    $this->Logout();
    //$page = $this->mybrowser->getContent();
    //print "noEUT: ****** Page after Logging Out**************\n$page\n";
    // now need to check to see if the person has email notification turned on.
    $this->assertTrue($this->mybrowser->get("$URL?mod=auth&nopopup=1"));
    $page = $this->mybrowser->getContent();
    print "noEUT: ****** Page BEFORE Logging In**************\n$page\n";
    print "NEUT: logging in with UserNoEmail, fosstester\n";
    $this->Login('UserNoEmail', 'fosstester');
    $this->assertTrue($this->myassertText($page, '/User: UserNoEmail/'));
    $page = $this->mybrowser->getContent();
    print "noEUT: ****** Page BEFORE Logging In**************\n$page\n";

    $this->assertTrue(empty($_SESSION['UserEnote']), "UserEnote is not NULL! (should be");
  }
}

?>
