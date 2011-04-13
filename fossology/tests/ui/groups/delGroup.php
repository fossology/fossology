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
 * delGroup
 * \brief del a group using the manage group page
 *
 * @return pass or fail to std out
 *
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');


/* Globals for test use, most tests need $URL, only login needs the others */
global $URL;

class delGroupTest extends fossologyTestCase
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
   * add the group TestGroup1 with fossy as the admin
   */
  function testDelGroup()
  {
    global $URL;

    print "starting testDelGroup\n";

    // go to the page, make sure you are there
    $page = $this->mybrowser->get("$URL?mod=group_manage");
    $this->assertTrue($this->myassertText($page, '/Manage Group/'),
      "delGroup FAILED! Did NOT find Title, 'Manage Group'.");
    $this->assertTrue($this->myassertText($page, '/TestGroup1/'),
      "delGroup FAILED! Did NOT find 'TestGroup1' to delete.");
  
    //print_r($page); 
    $matche = preg_match("/TestGroup1.*?\n.*?\n.*?<a href='(.*)'/", $page, $dellink);
    //print_r($dellink); 
    $url = makeUrl($this->host, $dellink[1]);
    if($url === NULL) {
      $this->fail("FATAL! delGroup Failed, host is not set or url cannot be made, Stopping this test");
      exit(1);
    }
    $page = $this->mybrowser->get($url);
    $this->assertTrue($this->myassertText($page, '/Group Delete Successful!/'),
      "delGroup FAILED! Delete 'TestGroup1' failed.");
  }  
}
?>
