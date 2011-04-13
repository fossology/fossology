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
 * @version "$Id: userEditAnyTest.php 2020 2009-04-25 03:05:38Z rrando $"
 *
 * Created on March 31, 2009
 */

/*
 * NOTE: this routine will not work execept on the default user, as the screen uses
 * javascript to pick the user and fill in the form.  Tried to tweak the DOM to see
 * if it could be worked around... no such luck.
 *
 * For now, the tests should not mess with the default user so this will just be a
 * pass with a message to test by hand.
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
  }

  function testEditUser() {
    global $URL;

    print "starting userEditAnyTest\n";
    print "Please test this screen by hand.  Simpletest cannot test JavaScript\n";
    $this->pass();
  }
}

?>
