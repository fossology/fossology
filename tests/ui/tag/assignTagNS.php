<?php
/***********************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 * assignTagNS
 * \brief test assign a tag namespace permission using the manage tag namespace page
 *
 * @return pass or fail to std out
 *
 * @version "$Id: assignTagNS.php 3679 2010-11-17 10:56:59Z madong $"
 *
 * Created on Nov. 9, 2010
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');


/* Globals for test use, most tests need $URL, only login needs the others */
global $URL;

class assignTagNSTest extends fossologyTestCase
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
   * assign the tag namespace TestTagNS1 with TestGroup1 as the admin permission
   */
  function testAssignTagNS()
  {
    global $URL;

    print "starting testAssignTagNS\n";

    // go to the page, make sure you are there
    $page = $this->mybrowser->get("$URL?mod=admin_tag_ns_perm");
    $this->assertTrue($this->myassertText($page, '/Assign Tag Namespace Permission/'),
      "testAssignTagNS FAILED! Did NOT find Title, 'Assign Tag Namespace Permission'");
    // assign tag namespace TestTagNS1 with TestGroup1 as admin permission
    $matche = preg_match("/<option.*?value='(.*)'>TestTagNS1<\/option>/", $page, $tag_ns_pk); 
    $this->assertTrue($this->mybrowser->setField('tag_ns_pk',$tag_ns_pk[1]),
      "testAssignNS FAILED! Could not set the tag_ns_pk field");
    print_r($page);
    $this->assertTrue($this->myassertText($page, 'No Permission!'),
      "testAssignNS FAILED! Could not set the tag_ns_pk field");
  }
}
?>

