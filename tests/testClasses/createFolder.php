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
 * createFolder
 *
 * Create a folder via the UI
 *
 * @param string $parent the parent folder name
 * @param string $name the name of the folder
 * @param string $description optional description, is class creates a
 * default description which is overidden if a description is passed in.
 *
 * @version "$Id: $"
 *
 * Created on Aug 28, 2008
 */
require_once('fossLogin.php');

//class createFolder extends testUtils (this results in needing to imp repologin)
class createFolder extends fossLogin
{
  public $mybrowser;

  public function createAFolder($parent, $name, $description = null)
  {
    print "createFolder is running\n";
    print "createFolder:Parameters: P:$parent N:$name D:$description\n";

    /* Need to check parameters */
    if (is_null($description)) // set default if null
    {
      $description = "Folder created by testFolder as subfolder of $parent";
    }
    $urlNow = $this->mybrowser->getUrl();
    $page = $this->mybrowser->get($urlNow);
    $this->assertTrue($this->myassertText($page,'/Create a new Fossology folder/'));
    /* select the folder to create this folder under */
    $FolderId = $this->getFolderId($parent, $page);
    $this->assertTrue($this->mybrowser->setField('parentid', $FolderId));
    $this->assertTrue($this->mybrowser->setField('newname', $name));
    $this->assertTrue($this->mybrowser->setField('description', "$description"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Folder $name Created/"),
     "FAIL! Folder $name Created not found\n");
  }
}
?>
