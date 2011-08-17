<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 * Create simple UI users for tests to use
 *
 * @version "$Id$"
 *
 * Created on April 27, 2011
 */

require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');

global $URL;

class createSuiUsers extends fossologyTestCase {
  public $mybrowser;
  public $webProxy;

  function setUp() {
    global $URL;
    $this->Login();
  }

  function testcreateSuiUsers()
  {
    global $URL;

    $Users = array(
      'rouser' =>
        'Read only test user: simple ui,NULL,1,1,NULL,NULL,rouser,n,1,simple',
      'downloader' =>
        'Download test user:simple ui,NULL,2,1,NULL,NULL,downloader,n,1,simple',
      'rwuser' =>
        'Read/Write test user: simple ui,NULL,3,1,NULL,NULL,rwuser,n,1,simple',
      'uploader' =>
        'Upload test user: simple ui,NULL,4,1,NULL,NULL,uploader,n,1,simple',
      'anauser' =>
        'Anaylyze test user: simple ui,NULL,5,1,NULL,NULL,anauser,n,1,simple',
    );

    // create the user folder then the user
    foreach($Users as $user => $parms)
    {
      list($description, $email, $access, $folder,
      $pass1, $pass2, $Enote, $Bucketpool, $Ui) = split(',',$parms);
      $created = $this->createFolder(1, $user, $description);
      if($created == 0)
      {
        $this->fail("Could not create folder $user for user $user\n");
      }
      $page = $this->mybrowser->get("$URL?mod=folder_create");
      $folderId = $this->getFolderId($user, $page, 'parentid');
      //echo "suiu: folderid of user is:$folderId\n";
      $added = $this->addUser($user, $description, $email, $access, $folderId,
      $pass1 ,$Enote, $Bucketpool, $Ui);
      if(preg_match('/User already exists/',$added, $matches)) {
        $this->pass();
        continue;
      }
      if(!empty($added)) {
        $this->fail("User $user was not added to the fossology database\n$added\n");
      }
    }
  }
}
?>
