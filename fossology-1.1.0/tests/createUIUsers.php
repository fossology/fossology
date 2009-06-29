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
 * Create UI users for tests to use
 *
 *
 * @version "$Id$"
 *
 * Created on March 31, 2009
 */

require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');

global $URL;

class createUIUsers extends fossologyTestCase {
  public $mybrowser;
  public $webProxy;

  function setUp() {
    global $URL;
    $this->Login();
  }

  function testcreateUiUsers() {
    global $URL;

    $Users = array(
      'fosstester' =>
        'Primary Test User: runs test suites,fosstester,10,1,NULL,NULL,fosstester,y',
      'noemail' =>
        'test user with NO Email notification,NULL,10,1,NULL,NULL,noemail,NULL',
    );

    print "Starting testcreateUIUsers\n";
    foreach($Users as $user => $params) {
      list($description, $email, $access, $folder,
       $block, $blank, $password, $Enote ) = split(',',$Users[$user]);
      $added = $this->addUser($user, $description, $email, $access, $folder, $password ,$Enote);
      if(preg_match('/User already exists/',$added, $matches)) {
        $this->pass();
        continue;
      }
      if(!empty($added)) {
        $this->fail("User $user was not added to the fossology database\n");
      }
    }
  }
}
?>
