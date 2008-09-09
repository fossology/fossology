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
 * fossologyTestCase
 *
 * Base Class for all fossology tests.  All fossology tests should
 * extend this class.
 *
 * @version "$Id$"
 *
 * Created on Sep 1, 2008
 */

require_once('TestEnvironment.php');
require_once('fossologyTest.php');

 class fossologyTestCase extends fossologyTest
{
  public $mybrowser;
  public $debug;

  /* possible methods to add */
  public function uploadServer(){
    return TRUE;
  }
  public function oneShotLicense(){
    return TRUE;
  }
  public function deleteFolder(){
    return TRUE;
  }
  public function deleteUpload(){
    return TRUE;
  }
  public function moveUpload(){
    return TRUE;
  }
  public function rmLicAnalysis(){
    return TRUE;
  }
  public function editFolder(){
    return TRUE;
  }
  public function mvFolder(){
    return TRUE;
  }
  public function jobsSummary(){
    return TRUE;
  }
  public function jobsDetail(){
    return TRUE;
  }
  public function dbCheck(){
    return TRUE;
  }
  /**
   * createFolder
   * Uses the UI 'Create' menu to create a folder.  Always creates a
   * default description.  To change the default descritpion, pass a
   * description in.
   *
   * Assumes the caller is already logged into the Repository
   *
   * @param string $parent the parent folder name the folder will be
   * created as a child of the parent folder.
   * @param string $name   the name of the folder to be created
   * @param string $description Optional user defined description, a
   * default description is always created.
   *
   * Reports: pass or fail.
   *
   */
  public function createFolder($parent=null, $name, $description=null)
  {
    global $URL;
    $FolderId = 0;

    /* Need to check parameters */
    if(is_null($parent))
    {
      $FolderId = 1;              // default is root folder
    }
    if (is_null($description)) // set default if null
    {
      $description = "Folder $name created by testFolder as subfolder of $parent";
    }
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Create');
    $this->assertTrue($this->myassertText($page,'/Create a new Fossology folder/'));
    /* if $FolderId=0 select the folder to create this folder under */
    if(!$FolderId)
    {
      $FolderId = $this->getFolderId($parent, $page);
    }
    $this->assertTrue($this->mybrowser->setField('parentid', $FolderId));
    $this->assertTrue($this->mybrowser->setField('newname', $name));
    $this->assertTrue($this->mybrowser->setField('description', "$description"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Folder $name Created/"),
     "createFolder Failed!\nPhrase 'Folder $name Created' not found\nDoes the Folder exist?\n");
  }

 /**
  * uploadFile
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
  * @todo add ability to specify uploadName
  *
  * @return pass or fail
  */
  public function uploadFile($parentFolder, $uploadFile, $description=null, $uploadName=null, $agents=null)
  {
    global $URL;
    /*
     * check parameters:
     * default parent folder is root folder
     * no uploadfile return false
     * description and upload name are optonal
     * future: agents are optional
     */
    if (empty ($parentFolder))
    {
      $parentFolder = 1;
    }
    if (empty ($uploadFile))
    {
      return (FALSE);
    }
    if (is_null($description)) // set default if null
    {
      $description = "File $uploadFile uploaded by test UploadFileTest";
    }
    //print "starting uploadFile\n";
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'));
    $page = $this->mybrowser->get("$URL?mod=upload_file");
    $this->assertTrue($this->myassertText($page, '/Upload a New File/'));
    $this->assertTrue($this->myassertText($page, '/Select the file to upload:/'));
    $FolderId = $this->getFolderId($parentFolder, $page);
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId),
        "uploadFile FAILED! could not set 'folder' field!\n");
    $this->assertTrue($this->mybrowser->setField('getfile', "$uploadFile"),
        "uploadFile FAILED! could not set 'getfile' field!\n");
    $this->assertTrue($this->mybrowser->setField('description', "$description"),
        "uploadFile FAILED! could not set 'description' field!\n");
    /*
     * the test will fail if name is set to null, so we special case it
     * rather than just set it.
     */
    if(!(is_null($uploadName)))
    {
      $this->assertTrue($this->mybrowser->setField('name', "$uploadName"),
          "uploadFile FAILED! could not set 'name' field!\n");
    }
    /*
     * Select agents to run, we just pass on the parameter to setAgents,
     * don't bother if null
     */
    if(!(is_null($agents)))
    {
      $this->setAgents($agents);
    }
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue(page);
    //print "************* page after Upload! is *************\n$page\n";
    $this->assertTrue($this->myassertText($page, '/Upload added to job queue/'));
  }
  /**
  * function uploadUrl
  * ($parentFolder,$uploadFile,$description=null,$uploadName=null,$agents=null)
  *
  * Upload a file and optionally schedule the agents.  The web-site must
  * already be logged into before using this method.
  *
  * @param string $parentFolder the parent folder name, default is root
  * folder (1)
  * @param string $url the url of the file to upload, no url sanity
  * checking is done.
  * @param string $description a default description is always used. It
  * can be overridden by supplying a description.
  * @param string $uploadName=null optional upload name
  * @param string $agents=null agents to schedule, the default is to
  * schedule license, pkgettametta, and mime.
  *
  * @return pass or fail
  */
  public function uploadUrl($parentFolder=1, $url, $description=null, $uploadName=null, $agents=null)
  {
    global $URL;
    /*
     * check parameters:
     * default parent folder is root folder
     * no uploadfile return false
     * description and upload name are optonal
     * future: agents are optional
     */
    if (empty ($parentFolder))
    {
      $parentFolder = 1;
    }
    if (empty ($url))
    {
      return (FALSE);
    }
    if (is_null($description)) // set default if null
    {
      $description = "File $url uploaded by test UploadAUrl";
    }
    //print "starting UploadAUrl\n";
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'));
    $this->assertTrue($this->myassertText($loggedIn, '/From URL/'));
    $page = $this->mybrowser->get("$URL?mod=upload_url");
    $this->assertTrue($this->myassertText($page, '/Upload from URL/'));
    $this->assertTrue($this->myassertText($page, '/Enter the URL to the file:/'));
    /* only look for the the folder id if it's not the root folder */
    $folderId = $parentFolder;
    if ($parentFolder != 1)
    {
      $FolderId = $this->getFolderId($parentFolder, $page);
    }
    $this->assertTrue($this->mybrowser->setField('folder', $folderId));
    $this->assertTrue($this->mybrowser->setField('geturl', $url));
    $this->assertTrue($this->mybrowser->setField('description', "$description"));
    /* Set the name field if an upload name was passed in. */
    if (!(is_null($upload_name)))
    {
      $this->assertTrue($this->mybrowser->setField('name', $url));
    }
    /* selects agents 1,2,3 license, mime, pkgmetagetta */
    $rtn = $this->setAgents($agents);
    if(is_null($rtn)) { $this->fail("FAIL: could not set agents in uploadAFILE test\n"); }
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, '/Upload added to job queue/'));
    //print  "************ page after Upload! *************\n$page\n";
  } //uploadUrl
}
?>
