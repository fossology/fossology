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
 * @package fossologyTestCases
 * @version "$Id$"
 *
 * Created on Sep 1, 2008
 */

require_once ('TestEnvironment.php');
require_once ('fossologyTest.php');

/**
 * fossologyTestCase
 *
 * Base class for all fossology tests.  All fossology tests should
 * extend this class.  This class contains methods to interact with the
 * fossology UI menus and forms.
 *
 * @package fossologyTestCasesClass
 *
 */
class fossologyTestCase extends fossologyTest
{
  public $mybrowser;
  public $debug;
  public $webProxy;


  /**
   * addUser
   *
   * Create a Fossology user
   *
   */
  public function addUser($UserName, $Description=NULL, $Email=NULL, $Access=1,
  $Folder=1, $Password=NULL, $EmailNotify='y'){

    global $URL;

    // check user name, everything else defaults (not a good idea to use defaults)
    if(empty($UserName)) {
      return("No User Name, cannot add user");
    }
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Add');
    $this->assertTrue($this->myassertText($page, '/Add A User/'),
      "Did NOT find Title, 'Add A User'");

    /* Set the user field */
    $this->assertTrue($this->mybrowser->setField('username', $UserName),
      "Could Not set the username field");
    if(!empty($Description)) {
      $this->assertTrue($this->mybrowser->setField('description', $Description),
      "Could Not set the description field");
    }
    if(!empty($Email)) {
      $this->assertTrue($this->mybrowser->setField('email', $Email),
      "Could Not set the email field");
    }
    if(!empty($Access)) {
      $this->assertTrue($this->mybrowser->setField('permission', $Access),
      "Could Not set the permission field");
    }
    else {
      return('FATAL: Access/permission is a required field');
    }
    if(!empty($Folder)) {
      $this->assertTrue($this->mybrowser->setField('folder', $Folder),
      "Could Not set the folder Field");
    }
    if(!empty($Password)) {
      $this->assertTrue($this->mybrowser->setField('pass1', $Password),
      "Could Not set the pass1 field");
      $this->assertTrue($this->mybrowser->setField('pass2', $Password),
      "Could Not set the pass2 field");
    }
    if(!empty($EmailNotifiy)) {
      $this->assertTrue($this->mybrowser->setField('enote', $EmailNotify),
      "Could Not set the enote Field");
    }
    /* all fields set, add the user */
    $page = $this->mybrowser->clickSubmit('Add!',"Could not select the Add! button");
    $this->assertTrue(page);
    //print "<pre>page after clicking Add!\n"; print_r($page) . "\n</pre>";
    if($this->myassertText($page, "/User added/")) {
      return(NULL);
    }
    elseif($this->myassertText($page, "/User already exists\.  Not added/")) {
      return('User already exists.  Not added');
    }
    //$this->assertTrue($this->myassertText($page, "/User added/"),
    // "addUser Failed!\nPhrase 'User added' not found\n");
    return;
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
   * created as a child of the parent folder. If no name is supplied the
   * root folder will be used for the parent folder.
   * @param string $name   the name of the folder to be created
   * @param string $description Optional user defined description, a
   * default description is always created.
   *
   * Reports: pass or fail.
   *
   */
  public function createFolder($parent = null, $name, $description = null)
  {
    global $URL;
    $FolderId = 0;

    /* Need to check parameters */
    if (is_null($parent))
    {
      $parent = 1; // default is root folder
    }
    if (is_null($description)) // set default if null
    {
      $description = "Folder $name created by createFolder as subfolder of $parent";
    }
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Create');
    $this->assertTrue($this->myassertText($page, '/Create a new Fossology folder/'));
    /* if $FolderId=0 select the folder to create this folder under */
    if (!$FolderId) {
      $FolderId = $this->getFolderId($parent, $page, 'parentid');
    }
    $this->assertTrue($this->mybrowser->setField('parentid', $FolderId));
    $this->assertTrue($this->mybrowser->setField('newname', $name));
    $this->assertTrue($this->mybrowser->setField('description', "$description"));
    $page = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Folder $name Created/"),
     "createFolder Failed!\nPhrase 'Folder $name Created' not found\nDoes the Folder $name exist?\n");
  }

  /**
   * deleteFolder
   *
   * Remove a folder and all it's contents.  This is a very dangerous
   * method.  It does not check who the parent is... so don't have
   * duplicate folder names.  This method works for testing as we don't
   * have duplicate folder names (yet).  When we do, this routine will
   * have to talk to the db.
   *
   * @param $string $folder the name of the folder to remove.  Only leaf
   * folder names.
   *
   * @return boolean
   */
  public function deleteFolder($folder)
  {
    global $URL;

    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Delete Folder');
    $this->assertTrue($this->myassertText($page, '/Select the folder to delete/'));
    $FolderId = $this->getFolderId($folder, $page, 'folder');
    if(empty($FolderId))    // not in the list of folders
    {
      return(true);
    }
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Deletion of folder $folder added to job queue/"),
     "delete Folder Failed!\nPhrase 'Deletion of folder $folder added to job queue' not found\n");
  }

  /**
   * deleteUpload
   *
   * delete an uploaded file under the root folder.
   *
   * This method can only remove uploads under the root folder.  It does
   * not talk to the db (yet) so can only 'see' the root folder.  All
   * other uploads stored in subfolders have their pages dyamically
   * generated by javascript.
   *
   * @param $string $upload the upload name to delete.
   *
   * @return boolean
   */
  public function deleteUpload($upload)
  {
    global $URL;

    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Delete Uploaded File');
    $this->assertTrue($this->myassertText($page, '/Select the uploaded file to delete/'));
    $UploadId = $this->getUploadId($upload, $page, 'upload');
    if(empty($UploadId))    // not in the list of uploads on the root page
    {
      return(FALSE);
    }
    $this->assertTrue($this->mybrowser->setField('upload', $UploadId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Deletion added to job queue/"),
     "delete Upload Failed!\nPhrase 'Deletion added to job queue' not found\n");
  }

  /**
   * Delete a fossology user
   *
   * @param string $User, the user name to remove
   *
   */
  public function deleteUser($User) {

    global $URL;

    if(empty($User)) {
      return('No User Specified');
    }
    $this->Login();
    $page = $this->mybrowser->get("$URL?mod=user_del");
    /* Get the user id */
    $select = $this->parseSelectStmnt($page, 'userid',$User);
    if(!is_null($select)) {
      $this->assertTrue($this->mybrowser->setField('userid', $select));
      $this->assertTrue($this->mybrowser->setField('confirm', 1));
      $page = $this->mybrowser->clickSubmit('Delete!',
        "Could not select the Delete! button");
      $this->assertTrue(page);
      if($this->myassertText($page, "/User deleted/")) {
        print "User $User Deleted\n";
        $this->pass();
      }
      else {
        $this->fail("Delete User Failed!\nPhrase 'User deleted' not found\n");
      }
    }
  }  // Delete User
  /**
  * editFolder
  *
  * @param string $folder the folder to edit
  * @param string $newName the new name for the folder
  * @param string $description Optional description, default
  * description is always created, to overide the default, supply a
  * descrtion.
  *
  * Assumes that the caller has already logged in.
  */
  public function editFolder($folder, $newName, $description = null)
  {
    global $URL;

    /* Need to check parameters */
    if (empty ($folder))
    {
      return (FALSE);
    }
    if (is_null($description)) // set default if null
    {
      $description = "Folder $newName edited by editFolder Test, this is the changed description";
    }
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Edit Properties');
    $this->assertTrue($this->myassertText($page, '/Edit Folder Properties/'));
    $FolderId = $this->getFolderId($folder, $page, 'oldfolderid');
    $this->assertTrue($this->mybrowser->setField('oldfolderid', $FolderId));
    if (!(empty ($newName)))
    {
      $this->assertTrue($this->mybrowser->setField('newname', $newName));
    }
    $this->assertTrue($this->mybrowser->setField('newdesc', "$description"));
    $page = $this->mybrowser->clickSubmit('Edit!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Folder Properties changed/"), "editFolder Failed!\nPhrase 'Folder Properties changed' not found\n");
  }
  /**
   * moveUpload($oldfFolder, $destFolder, $upload)
   *
   *NOTE: this routine was never finished, the screen uses java script.  SO only
   *items in the root folder can be moved....
   *
   * Moves an upload from one folder to another. Assumes the caller has
   * logged in.
   * @param string $oldFolder the folder name where the upload currently
   * is stored.
   * @param string $destFolder the folder where the updload will be
   * moved to.  If no folder specified, then the root folder will be
   * used.
   * @param string $upload The upload to move
   *
   */
  public function moveUpload($oldFolder, $destFolder, $upload)
  {
    global $URL;
    print "mU: OF:$oldFolder DF:$destFolder U:$upload\n";
    if (empty ($oldFolder))
    {
      return FALSE;
    }
    if (empty ($destFolder))
    {
      $destFolder = 'root'; // default is root folder
    }
    print "Starting moveUpload in FTC\n";
    $page = $this->mybrowser->get($URL);
    /* use the method below till you write a menu function */
    $page = $this->mybrowser->get("$URL?mod=upload_move");
    //$page = $this->mybrowser->clickLink('Move');
    $this->assertTrue($this->myassertText($page, '/Move upload to different folder/'));
    $oldFolderId = $this->getFolderId($oldFolder, $page);
    print "FTC: oldFolderId is:$oldFolderId\n";
    $this->assertTrue($this->mybrowser->setField('oldfolderid', $oldFolderId));
    $uploadId = $this->getUploadId($upload, $page);
    print "FTC: uploadId is:$uploadId\n";
    if(empty($uploadId))
    {
      $this->fail("moveUpload FAILED! could not find upload id for upload" .
                  "$upload\n is $upload in $oldFolder?\n");
    }
    $this->assertTrue($this->mybrowser->setField('uploadid', $uploadId));
    $destFolderId = $this->getFolderId($destFolder, $page);
    print "FTC: destFolderId is:$destFolderId\n";
    $this->assertTrue($this->mybrowser->setField('targetfolderid', $destFolderId));
    $page = $this->mybrowser->clickSubmit('Move!');
    $this->assertTrue(page);
    print "page after move is:\n$page\n";
    $this->assertTrue($this->myassertText($page,
    //"/Moved $upload from folder $oldFolder to folder $destFolder/"),
       "/Moved $upload from folder /"),
       "moveUpload Failed!\nPhrase 'Move $upload from folder $oldFolder " .
       "to folder $destFolder' not found\n");
  }

  /**
   * mvFolder
   *
   * Move a folder via the UI
   *
   * @param string $folder the folder name to move.
   * @param string $destination the destination folder to move the
   * folder to.  Default is the root folder.
   *
   */
  public function mvFolder($folder, $destination)
  {
    global $URL;
    if (empty ($folder))
    {
      return (FALSE);
    }
    if (empty ($destination))
    {
      $destination = 1;
    }
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Move');
    $this->assertTrue($this->myassertText($page, '/Move Folder/'));
    $FolderId = $this->getFolderId($folder, $page);
    $this->assertTrue($this->mybrowser->setField('oldfolderid', $FolderId));
    if ($destination != 1)
    {
      $DfolderId = $this->getFolderId($destination, $page);
    }
    $this->assertTrue($this->mybrowser->setField('targetfolderid', $DfolderId));
    $page = $this->mybrowser->clickSubmit('Move!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, "/Moved folder $folder to folder $destination/"), "moveFolder Failed!\nPhrase 'Move folder $folder to folder ....' not found\n");
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
  public function uploadFile($parentFolder, $uploadFile, $description = null, $uploadName = null, $agents = null)
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
    $FolderId = $this->getFolderId($parentFolder, $page, 'folder');
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
    if (!(is_null($uploadName)))
    {
      $this->assertTrue($this->mybrowser->setField('name', "$uploadName"),
        "uploadFile FAILED! could not set 'name' field!\n");
    }
    /*
     * Select agents to run, we just pass on the parameter to setAgents,
     * don't bother if null
     */
    if (!(is_null($agents)))
    {
      $this->setAgents($agents);
    }
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue(page);
    //print "************* page after Upload! is *************\n$page\n";
    $this->assertTrue($this->myassertText($page, '/Upload added to job queue/'),
      "FAILURE:Did not find the message'Upload added to job queue'\n");
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
  public function uploadUrl($parentFolder = 1, $url, $description = null, $uploadName = null, $agents = null)
  {
    global $URL;
    global $PROXY;
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
    $this->assertFalse($this->myassertText($loggedIn, '/Network Error/'),
    "uploadURL FAILED! there was a Newtwork Error (dns lookup?)\n");
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'),
    "uploadURL FAILED! cannot file Upload menu, did we get a fossology page?\n");
    $this->assertTrue($this->myassertText($loggedIn, '/From URL/'));
    $page = $this->mybrowser->get("$URL?mod=upload_url");

    $this->assertTrue($this->myassertText($page, '/Upload from URL/'));
    $this->assertTrue($this->myassertText($page, '/Enter the URL to the file:/'));
    /* only look for the the folder id if it's not the root folder */
    $folderId = $parentFolder;
    if ($parentFolder != 1)
    {
      $folderId = $this->getFolderId($parentFolder, $page, 'folder');
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
    if (!is_null($rtn))
    {
      $this->fail("FAIL: could not set agents in uploadAFILE test\n");
    }
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue(page);
    $this->assertTrue($this->myassertText($page, '/Upload added to job queue/'));
    //print  "************ page after Upload! *************\n$page\n";
  } //uploadUrl

  /* possible methods to add */
  public function dbCheck()
  {
    return TRUE;
  }

  public function jobsSummary()
  {
    return TRUE;
  }
  public function jobsDetail()
  {
    return TRUE;
  }

  public function oneShotLicense()
  {
    return TRUE;
  }
  public function rmLicAnalysis()
  {
    return TRUE;
  }
  public function uploadServer()
  {
    return TRUE;
  }
}
?>
