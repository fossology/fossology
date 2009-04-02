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
 * Edit a user using the UI
 *
 *
 * @version "$Id: $"
 *
 * Created on March 31, 2009
 */

/*
 * NOTE: this routine will not work execept on the default user, as the screen uses
 * javascript to pick the user and fill in the form.  Tried to tweak the DOM to see
 * if it could be worked around... no such luck.
 *
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class userEditAnyTest extends fossologyTestCase {
  public $mybrowser;
  private $UserName;

  function setUP() {
    global $URL;
    /* for this test this should be fosstester, or a user with admin privledges */
    $this->Login();
    /* create the user to edit*/
    $this->UserName = 'User2Edit';
    $this->addUser($this->UserName,'user for edit any user test',NULL,1,1,NULL,'n');
  }

  function testEditUser() {
    global $URL;

    print "starting userEditAnyTest\n";

    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Edit Users');
    $this->assertTrue($this->myassertText($page, '/Edit A User/'));
    $this->assertTrue($this->myassertText($page, '/Select the user to edit/'));
    $UserId = $this->parseSelectStmnt($page,'userid',$this->UserName);
    $attr = $this->getSelectAttr($page,'userid');
    $val = $this->setSelectAttr($page,'userid','onload',"'SetInfo($UserId);'");
    //$page = $this->mybrowser->retry();
    print "page after set of onload:\n$page\n";
    //$this->assertTrue($this->mybrowser->setField('userid', $UserId),
      //"Could not Select the user with userid of:$UserId\n");
    //$page = $this->mybrowser->retry();
    $this->setUserFields('UserEdited','This user edited by userEditAnyTest',
    NULL,1,1,NULL,NULL,NULL,'n');
    $page = $this->mybrowser->clickLink('Edit!');
    $this->assertTrue($this->myassertText($page, '/User edited/'),
      "Did not find User edited phrase, please investigate\n");
    /* Make sure the edit worked */
    $this->assertTrue($this->myassertText($page, '/UserEdited/'),
      "Did not find user name UserEdited on the page, the Edit Failed!\n");
  }
  function tearDown() {
    /* Cleanup: remove the user */
    //print "Removing user $this->UserName\n";
    //$this->deleteUser($this->UserName);
    return(TRUE);
  }
}

?>
