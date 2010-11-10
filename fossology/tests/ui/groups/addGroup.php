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
 * addGroup
 * \brief add a group using the manage group page
 *
 * @return pass or fail to std out
 *
 * @version "$Id$"
 *
 * Created on Nov. 9, 2010
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

/* Globals for test use, most tests need $URL, only login needs the others */
global $URL;

class addGroupTest extends fossologyTestCase
{
  public $mybrowser;
  public $someOtherVariable;

  /*
   * Every Test needs to login so we use the setUp method for that.
   * setUp is called before any other method by default.
   *
   * If other actions like creating a folder or something are needed,
   * put them in the setUp method after login.
   *
   */
  function setUp()
  {
    global $URL;
    $this->Login();
  }

  /*
   * add the group TestGroup1 with fossy as the admin
   */
  function testAddGroup()
  {
    global $URL;

    print "starting testAddGroup\n";

    // go to the page, make sure you are there
    $page = $this->mybrowser->get("$URL?mod=group_manage");
    $this->assertTrue($this->myassertText($page, '/Manage Group/'),
      "Did NOT find Title, 'Manage Group'");
    $this->assertTrue($this->myassertText($page, '/Add a Group/'),
      "Did NOT find phrase 'Add a Group'");
    // add the group
    $filled = $this->fillGroupForm('TestGroup1', '2');
    $page = $this->mybrowser->clickSubmit('Add!');
    $this->assertTrue($this->myassertText($page, '/Group TestGroup1 added/'),
       "Error! TestGroup1 was not added as a group, does it exist?\n");
  }
}
?>
