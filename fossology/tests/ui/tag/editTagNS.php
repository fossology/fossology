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
 * editTagNS
 * \brief test edit a tag namespace using the manage tag namespace page
 *
 * @return pass or fail to std out
 *
 * @version "$Id: editTagNS.php 3679 2010-11-17 10:56:59Z madong $"
 *
 * Created on Nov. 9, 2010
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');


/* Globals for test use, most tests need $URL, only login needs the others */
global $URL;

class editTagNSTest extends fossologyTestCase
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
   * edit Tag namespace TestTagNS1
   */
  function testEditTagNS()
  {
    global $URL;

    print "starting testEditTagNS\n";

    // go to the page, make sure you are there
    $page = $this->mybrowser->get("$URL?mod=admin_tag_ns");
    $this->assertTrue($this->myassertText($page, '/Manage Tag Namespace/'),
      "testEditTagNS FAILED! Did NOT find Title, 'Manage Tag Namespace'");
    $this->assertTrue($this->myassertText($page, '/Current Tag Namespace/'),
      "testEditTagNS FAILED! Did NOT find phrase 'Current Tag Namespace'");
    
    //print_r($page);
    $matche = preg_match("/TestTagNS1.*?<a href='(.*)'>Edit/", $page, $editlink);
    //print_r($editlink); 
    $url = makeUrl($this->host, $editlink[1]);
    if($url === NULL) {
      $this->fail("FATAL! EditTagNS Failed, host is not set or url cannot be made, Stopping this test");
      exit(1);
    }
    $page = $this->mybrowser->get($url);
    $this->assertTrue($this->myassertText($page, '/<input type=\'text\' name=\'tag_ns_name\' value=\"TestTagNS1\"\/>/'),
      "testEditTagNS FAILED! Did Not find field TestTagNS1.");
    // Edit tag namespace
    $this->assertTrue($this->mybrowser->setField('tag_ns_name','TestTagNS2'),
      "testEditTagNS FAILED! Could not set the tag_ns_name field");
    $page = $this->mybrowser->clickSubmit('Edit');
    $this->assertTrue($this->myassertText($page, '/TestTagNS2/'),
       "testEditTagNS FAILED! TestTagNS2 was not edited!\n");
    $this->assertTrue($this->myassertText($page, '/Edit Tag Namespace Successful!/'),
       "testEditTagNS FAILED! TestTagNS2 was not edited!\n");
  }
}
?>

