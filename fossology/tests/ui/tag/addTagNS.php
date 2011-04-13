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
 * addTagNS
 * \brief test add a tag namespace using the manage tag namespace page
 *
 * @return pass or fail to std out
 *
 * @version "$Id: addTagNS.php 3679 2010-11-17 10:56:59Z madong $"
 *
 * Created on Nov. 9, 2010
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');


/* Globals for test use, most tests need $URL, only login needs the others */
global $URL;

class addTagNSTest extends fossologyTestCase
{
  public $mybrowser;
  public $someOtherVariable;
  public $host;
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
    $this->host = getHost($URL);
  }

  /*
   * add the tag namespace TestTagNS1 with fossy as the admin
   */
  function testAddTagNS()
  {
    global $URL;

    print "starting testAddTagNS\n";

    // go to the page, make sure you are there
    $page = $this->mybrowser->get("$URL?mod=admin_tag_ns");
    $this->assertTrue($this->myassertText($page, '/Manage Tag Namespace/'),
      "testAddTagNS FAILED! Did NOT find Title, 'Manage Tag Namespace'");
    $this->assertTrue($this->myassertText($page, '/Create Tag Namespace/'),
      "testAddTagNS FAILED! Did NOT find phrase 'Create Tag Namespace'");
    // add one tag namespace
    $this->assertTrue($this->mybrowser->setField('tag_ns_name','TestTagNS1'),
      "testAddTagNS FAILED! Could not set the tag_ns_name field");
    $page = $this->mybrowser->clickSubmit('Create');
    $this->assertTrue($this->myassertText($page, '/TestTagNS1/'),
       "testAddTagNS FAILED! TestTagNS1 was not added!\n");
    $this->assertTrue($this->myassertText($page, '/Create Tag Namespace Successful!/'),
       "testAddTagNS FAILED! TestTagNS1 was not added!\n");
  }

  /*
   * add the Tag namespace TestTagNS1 again to check that tag namespace already exists 
   */
  function testAddTagNSExist()
  {
    global $URL;

    print "starting testAddTagNSExist\n";

    // go to the page, make sure you are there
    $page = $this->mybrowser->get("$URL?mod=admin_tag_ns");
    $this->assertTrue($this->myassertText($page, '/Manage Tag Namespace/'),
      "testAddTagNSExist FAILED! Did NOT find Title, 'Manage Tag Namespace'");
    $this->assertTrue($this->myassertText($page, '/Create Tag Namespace/'),
      "testAddTagNSExist FAILED! Did NOT find phrase 'Create Tag Namespace'");
    // add one tag namespace
    $this->assertTrue($this->mybrowser->setField('tag_ns_name','TestTagNS1'),
      "testAddTagNSExist FAILED! Could not set the tag_ns_name field");
    $page = $this->mybrowser->clickSubmit('Create');
    $this->assertTrue($this->myassertText($page, '/Tag Namespace already exists/'),
       "testAddTagNSExist FAILED! TestTagNS1 was added twice.\n");
  }
}
?>

