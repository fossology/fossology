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
 * upload utility class, used by test cases and are test cases
 * themselves, but can't be run as test cases.
 *
 * @version "$Id: $"
 *
 * Created on Aug 15, 2008
 */

require_once ('fossologyWebTestCase.php');

/* every test must use these globals, at least $URL */
global $URL;

class uploadFile extends fossologyWebTestCase
{
  public $mybrowser;

  function setUp()
  {
    global $URL;
    $this->mybrowser = & new SimpleBrowser();
    $this->assertTrue(is_object($this->mybrowser));
    $page = $this->mybrowser->get($URL);
    $this->assertTrue($page);
    $cookie = $this->repoLogin($this->mybrowser);
    $host = $this->getHost($URL);
    $this->mybrowser->setCookie('Login', $cookie, $host);
  }
  /**
   * function uploadAFile
   * ($parentFolder,$uploadFile,$description=null,$uploadName=null,$agents=null)
   *
   * Upload a file and optionally schedule the agents.
   *
   * @param string $parentFolder the parent folder name, default is root
   * folder (1)
   * @param string $uploadFile the path to the file to upload
   * @param string $description=null optonal description
   * @param string $uploadName=null optional upload name
   *
   * @todo, add in selecting agents the parameter to this routine will
   * need to be quoted if it contains commas.
   *
   * @return false on error, ?? (what will simpletest return?)
   */
  function uploadAFile($parentFolder, $uploadFile, $description=null, $uploadName=null, $agents=null)
  {
    global $URL;
  /*
   * check parameters:
   * default parent folder is root folder
   * no uploadfile return false
   * description and upload name are optonal
   * future: agents are optional
   */
    if(empty($parentFolder))
    {
      $parentFolder = 1;
    }
    if(empty($uploadFile))
    {
      return(FALSE);
    }
    if(is_null($description))  // set default if null
    {
      $description = "File $uploadFile uploaded by test UploadAFileTest";
    }
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->assertText($loggedIn, '/Upload/'));
    $page = $this->mybrowser->get("$URL?mod=upload_file");
    $this->assertTrue($this->assertText($page, '/Upload a New File/'));
    $this->assertTrue($this->assertText($page, '/Select the file to upload:/'));
    $this->assertTrue($this->mybrowser->setField('parentid', $parentFolder),
                      "FAIL! could not select Parent Folder!\n");
    $this->assertTrue($this->mybrowser->setField('getfile', "$uploadFile" ));
    /* this test always sets description and upload name, usually set to null */
    $this->assertTrue($this->mybrowser->setField('description', "$description" ));
    $this->assertTrue($this->mybrowser->setField('name', $upload_name ));
    /* we won't select any agents for now.... see todo above */
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, '/Upload added to job queue/'));
  }
}
?>
