<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * fossologyTestCase
 *
 * Base Class for all fossology tests.  All fossology tests should
 * extend this class.
 *
 * @package FOSSologyTest
 * @version "$Id: fossologyTestCase.php 4020 2011-03-31 21:30:29Z rrando $"
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
   * @param string $UserName the user name
   * @param string $Description a description of the user
   * @param string $Email the email address for the user
   * @param int    $Access, the access level for the user, valid values are:
   *               0,1,2,3,4,5,6,7,10.
   * @param string $Folder, the folder for the user....can be a 'number'
   * @param string $Password the password for the user
   * @param string $EmailNotify either null or 'y'.  Default is 'y'.
   *
   * @return null on success, prints error on fail.
   */
  public function addUser($UserName, $Description = NULL, $Email = NULL, $Access = 1, $Folder = 1, $Password = NULL, $EmailNotify = 'y')
  {

    global $URL;

    // check user name, everything else defaults (not a good idea to use defaults)
    if (empty ($UserName))
    {
      return ("No User Name, cannot add user");
    }

    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Add');
    $this->assertTrue($this->myassertText($page, '/Add A User/'), "Did NOT find Title, 'Add A User'");

    $this->setUserFields($UserName, $Description, $Email, $Access, $Folder, NULL, NULL, $Password, $EmailNotify);

    /* fields set, add the user */
    $page = $this->mybrowser->clickSubmit('Add!', "Could not select the Add! button");
    $this->assertTrue($page);
    //print "<pre>page after clicking Add!\n"; print_r($page) . "\n</pre>";
    if ($this->myassertText($page, "/User .*? added/"))
    {
      return (NULL);
    } else
    if ($this->myassertText($page, "/User already exists\.  Not added/"))
    {
      return ('User already exists.  Not added');
    } else
    if ($this->myassertText($page, "/ERROR/"))
    {
      return ("addUser FAILED! ERROR when adding users, please investigate");
    }
    return (NULL);
  } // addUser

  /**
   * checkEmailNotification
   *
   * Verify that the user got the email they were supposed to and that it
   * said all the jobs finished without errors.
   *
   * @param int $number is the number of emails that should have been received
   * @TODO actually write code that uses the number!
   *
   * @return NULL on success, array on failure:
   *  The array will with contain an error message starting with the string
   *  ERROR! or it will contain a list of non compliant email headers.
   *
   * NOTE: this test uses the function getMailSubjects, which will fail:
   * - if the user running this test is not the same user as the mail file
   *   being checked, /var/mail/<user-name> is only readable by that user-name.
   */
  public function checkEmailNotification($number)
  {

    if (empty ($number))
    {
      return (array (
      0,
        'ERROR! Must supply a number to verify'
        ));
    }

    $headers = getMailSubjects();
    if (empty ($headers))
    {
      //print "No messages found\n";
      $this->pass();
      return (NULL);
    }
    //print "Got back from getMailSubjects:\n";print_r($headers) . "\n";

    /*
     check for errors
     */
    /**
     * @TODO use exceptions here, so you can indicated the correct item.
     */
    if (preg_match('/ERROR/', $headers[0], $matches))
    {
      $this->fail("{$headers[0]}\n");
      return (FALSE);
    }
    $pattern = 'completed with no errors';

    $failed = array ();
    foreach ($headers as $header)
    {
      /* Make sure all say completed */
      $match = preg_match("/$pattern/", $header, $matches);
      if ($match == 0)
      {
        $failed[] = $header;
      }
    }
    if (!empty ($failed))
    {
      $this->fail("Failures! there were jobs that did not report as completed\n");
      //foreach($failed as $fail) {
      //  print "$fail\n";
      return ($failed);
    }
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
   * @return exists with failure or returns folder id.
   *
   */
  public function createFolder($parent, $name, $description = null)
  {

    global $URL;
    $FolderId = 0;

    /* Need to check parameters */
    if (is_null($parent))
    {
      $FolderId = 1; // default is root folder
    }
    if (is_null($description))
    { // set default if null
      $description = "Folder $name created by createFolder as subfolder of $parent";
    }
    if(empty($name))
    {
      $this->fail("FATAL! No folder name supplied, cannot create folder\n");
    }
    $page = $this->mybrowser->get($URL);
    // There is only 1 create menu, so just select it
    // No need to make sure we are in folders menu.
    $page = $this->mybrowser->clickLink('Create');
    $this->assertTrue($this->myassertText($page, '/Create a new Fossology folder/'));
    /* if $FolderId=0 select the folder to create this folder under */
    if (!$FolderId)
    {
      $FolderId = $this->getFolderId($parent, $page, 'parentid');
    }
    $this->assertTrue($this->mybrowser->setField('parentid', $FolderId));
    $this->assertTrue($this->mybrowser->setField('newname', $name),
      "FATAL! Could not set newname for folder:$name\n");
    $this->assertTrue($this->mybrowser->setField('description', "$description"),
      "FATAL! Could not set description for folder:$name\n");
    $createdPage = $this->mybrowser->clickSubmit('Create!');
    $this->assertTrue($createdPage);
    if ($this->myassertText($createdPage, "/Folder $name Created/"))
    {
      $folderId = $this->getFolderId($name, $createdPage, 'parentid');
      return ($folderId);
    }
    if ($this->myassertText($createdPage, "/Folder $name Exists/"))
    {
      $folderId = $this->getFolderId($name, $createdPage, 'parentid');
      return ($folderId);
    }
    else
    {
      $this->fail("Failure! Did not find phrase 'Folder $name Created'\n");
    }
  }

  /**
   * deleteFolder
   *
   * Remove a folder and all its contents.  This is a very dangerous
   * method.  It does not check who the parent is... so don't have
   * duplicate folder names.  This method works for testing as we don't
   * have duplicate folder names (yet).  When we do, this routine will
   * have to talk to the db.
   *
   * @param string $folder the name of the folder to remove.  Only leaf
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
    if (empty ($FolderId)) // not in the list of folders
    {
      return (true);
    }
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, "/Deletion of folder $folder added to job queue/"), "delete Folder Failed!\nPhrase 'Deletion of folder $folder added to job queue' not found\n");
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
   * @param string $upload the upload name to delete.
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
    if (empty ($UploadId)) // not in the list of uploads on the root page
    {
      return (FALSE);
    }
    $this->assertTrue($this->mybrowser->setField('upload', $UploadId));
    $page = $this->mybrowser->clickSubmit('Delete!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, "/Deletion added to job queue/"), "delete Upload Failed!\nPhrase 'Deletion added to job queue' not found\n");
  }

  /**
   * Delete a fossology user
   *
   * @param string $User, the user name to remove
   *
   */
  public function deleteUser($User)
  {

    global $URL;

    if (empty ($User))
    {
      return ('No User Specified');
    }
    // Should already be logged in... no need to call this: $this->Login();
    $page = $this->mybrowser->get("$URL?mod=user_del");
    /* Get the user id */
    $select = $this->parseSelectStmnt($page, 'userid', $User);
    if (!is_null($select))
    {
      $this->assertTrue($this->mybrowser->setField('userid', $select));
      $this->assertTrue($this->mybrowser->setField('confirm', 1));
      $page = $this->mybrowser->clickSubmit('Delete!', "Could not select the Delete! button");
      $this->assertTrue($page);
      if ($this->myassertText($page, "/User deleted/"))
      {
        print "User $User Deleted\n";
        $this->pass();
      } else
      {
        $this->fail("Delete User Failed!\nPhrase 'User deleted' not found\n");
      }
    }
  } // Delete User
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
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, "/Folder Properties changed/"), "editFolder Failed!\nPhrase 'Folder Properties changed' not found\n");
  }
  /**
   * addUser
   *
   * Edit a Fossology user
   *
   * @param string $UserName the user name
   * @param string $Description a description of the user
   * @param string $Email the email address for the user
   * @param int    $Access, the access level for the user, valid values are:
   *               0,1,2,3,4,5,6,7,10.
   * @param string $Folder, the folder for the user....can be a 'number'
   * @param string $Block, check box for blocking user, default is NULL
   * @param string $Blank check box for blanking the users account, default is NULL
   * @param string $Password the password for the user
   * @param string $EmailNotify either null or 'y'.  Default is 'y'.
   *
   * @return null on success, prints error on fail.
   */
  public function editUser($UserName, $Description = NULL, $Email = NULL, $Access = 1, $Folder = 1, $Block = NULL, $Blank = NULL, $Password = NULL, $EmailNotify = 'y')
  {

    global $URL;

    // check user name, everything else defaults (not a good idea to use defaults)
    if (empty ($UserName))
    {
      return ("No User Name, cannot add user");
    }
    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Edit Users');
    $this->assertTrue($this->myassertText($page, '/Edit A User/'), "Did NOT find Title, 'Edit A User'");
    $this->setUserFields($UserName, $Description, $Email, $Access, $Folder, NULL, NULL, $Password, $EmailNotify);

    /* fields set, edit the user */
    $page = $this->mybrowser->clickSubmit('Edit!', "Could not select the Edit! button");
    $this->assertTrue($page);
    //print "<pre>page after clicking Add!\n"; print_r($page) . "\n</pre>";
    if ($this->myassertText($page, "/User edited/"))
    {
      return (NULL);
    }
    return;
  } // addUser
  /**
  * moveUpload($oldfFolder, $destFolder, $upload)
  *
  *NOTE: this routine was never finished, the screen uses java script.  SO only
  *items in the root folder can be moved....
  *
  *@TODO fix so it only does thnigs with the root folder :)
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
    $page = $this->mybrowser->get($URL);
    /* use the method below till you write a menu function */
    $page = $this->mybrowser->get("$URL?mod=upload_move");
    //$page = $this->mybrowser->clickLink('Move');
    $this->assertTrue($this->myassertText($page, '/Move upload to different folder/'));
    $oldFolderId = $this->getFolderId($oldFolder, $page, 'oldfolderid');
    $this->assertTrue($this->mybrowser->setField('oldfolderid', $oldFolderId));
    $uploadId = $this->getUploadId($upload, $page, 'uploadid');
    if (empty ($uploadId))
    {
      $this->fail("moveUpload FAILED! could not find upload id for upload" .
      "$upload\n is $upload in $oldFolder?\n");
    }
    $this->assertTrue($this->mybrowser->setField('uploadid', $uploadId));
    $destFolderId = $this->getFolderId($destFolder, $page, 'targetfolderid');
    $this->assertTrue($this->mybrowser->setField('targetfolderid', $destFolderId));
    $page = $this->mybrowser->clickSubmit('Move!');
    $this->assertTrue($page);
    print "page after move is:\n$page\n";
    $this->assertTrue($this->myassertText($page,
    //"/Moved $upload from folder $oldFolder to folder $destFolder/"),
    "/Moved $upload from folder /"), "moveUpload Failed!\nPhrase 'Move $upload from folder $oldFolder " .
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
    $FolderId = $this->getFolderId($folder, $page, 'oldfolderid');
    $this->assertTrue($this->mybrowser->setField('oldfolderid', $FolderId));
    if ($destination != 1)
    {
      $DfolderId = $this->getFolderId($destination, $page, 'targetfolderid');
    }
    $this->assertTrue($this->mybrowser->setField('targetfolderid', $DfolderId));
    $page = $this->mybrowser->clickSubmit('Move!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, "/Moved folder $folder to folder $destination/"), "moveFolder Failed!\nPhrase 'Move folder $folder to folder ....' not found\n");
  }

  /**
   * fillGroupForm($groupName, $user)
   * \breif fill in the manage group fields
   *
   * @param string $groupName the name of the group to create
   * @param string $user the user who will admin the group.
   *
   * @return NULL on success, string on error
   */

  public function fillGroupForm($groupName, $user)
  {
    global $URL;

    if (is_NULL($groupName))
    {
      $msg = "FATAL! no group name to set\n";
      $this->fail($msg);
      return ($msg);
    }
    if (is_NULL($user))
    {
      $msg = "FATAL! no user name to set\n";
      $this->fail($msg);
      return ($msg);
    }
    // make sure we are on the page
    $page = $this->mybrowser->get("$URL?mod=group_manage");
    $this->assertTrue($this->myassertText($page, '/Manage Group/'), "Did NOT find Title, 'Manage Group'");
    $this->assertTrue($this->myassertText($page, '/Add a Group/'), "Did NOT find phrase, 'Add a Group'");

    // set the fields
    $groupNotSet = 'FATAL! Could Not set the groupname field';
    if (!empty ($groupName))
    {
      $this->assertTrue($this->mybrowser->setField('groupname', $groupName), $groupNotSet);
    }
    if (!empty ($user))
    {
      $userNotSet = 'FATAL! Could Not set the userid field in select';
      $this->assertTrue($this->mybrowser->setField('userid', $user), $userNotSet);
      //return($userNotSet);
    }
    return (NULL);
  } //fillGroupForm

  /**
   * setUserFields
   *
   * utility function for the user methods.
   *
   * @param $UserName
   * @param $Description
   * @param $Email
   * @param int $Access
   * @param int $Folder
   * @param $Block
   * @param $Blank
   * @param $Password
   * @param $EmailNotify
   * @param int $BucketPool
   * @param string $Ui
   * @return NULL on pass, string on failure (for now only returns NULL)
   */
  protected function setUserFields($UserName = NULL, $Description = NULL,
  $Email = NULL, $Access = 1, $Folder = 1, $Block = NULL, $Blank = NULL,
  $Password = NULL, $EmailNotify = NULL, $BucketPool = 1, $Ui = 'simple')
  {

    $FailStrings = NULL;
    $simple = FALSE;
    $original = FALSE;

    global $URL;

    if (strtoupper($UserName) == 'NULL')
    {
      $this->fail("setUserFields, FATAL! no user name to set\n");
    }
    if (strtoupper($Description) == 'NULL')
    {
      $Description = '';
    }
    if (strtoupper($Email) == 'NULL')
    {
      $Email = '';
    }
    if (strtoupper($Access) == 'NULL')
    {
      $Access = 1;
    }
    if (strtoupper($Folder) == 'NULL')
    {
      $Folder = 1;
    }
    if (strtoupper($Block) == 'NULL')
    {
      $Block = '';
    }
    if (strtoupper($Blank) == 'NULL')
    {
      $Blank = '';
    }
    if (strtoupper($Password) == 'NULL')
    {
      $Password = '';
    }
    if (strtoupper($EmailNotify) == 'NULL' || is_null($EmailNotify))
    {
      unset ($EmailNotify);
    }
    if ($BucketPool == NULL)
    {
      $BucketPool = 1;  // default bucket pool
    }
    if (strcasecmp($Ui, 'simple'))
    {
      $simple = TRUE;
    }
    else if (strcasecmp($Ui, 'original'))
    {
      $orignal = TRUE;
    }

    $page = $this->mybrowser->get($URL);
    $page = $this->mybrowser->clickLink('Add');
    $this->assertTrue($this->myassertText($page, '/Add A User/'),
      "Did NOT find Title, 'Add A User'");

    if (!empty ($UserName))
    {
      $this->assertTrue($this->mybrowser->setField('username', $UserName),
        "Could Not set the username field");
    }
    if (!empty ($Description))
    {
      $this->assertTrue($this->mybrowser->setField('description', $Description),
        "Could Not set the description field");
    }
    if (!empty ($Email))
    {
      $this->assertTrue($this->mybrowser->setField('email', $Email),
        "Could Not set the email field");
    }
    if (!empty ($Access))
    {
      $this->assertTrue($this->mybrowser->setField('permission', $Access),
        "Could Not set the permission field");
    } else
    {
      return ('FATAL: Access/permission is a required field');
    }
    if (!empty ($Folder))
    {
      $this->assertTrue($this->mybrowser->setField('folder', $Folder),
        "Could Not set the folder Field");
    }
    if (!empty ($Block))
    {
      $this->assertTrue($this->mybrowser->setField('block', $Block),
        "Could Not set the block Field");
    }
    if (!empty ($Blank))
    {
      $this->assertTrue($this->mybrowser->setField('blank', $Blank),
        "Could Not set the blank Field");
    }
    if (!empty ($Password))
    {
      $this->assertTrue($this->mybrowser->setField('pass1', $Password),
        "Could Not set the pass1 field");
      $this->assertTrue($this->mybrowser->setField('pass2', $Password),
        "Could Not set the pass2 field");
    }
    if (isset ($EmailNotify))
    {
      $this->assertTrue($this->mybrowser->setField('enote', TRUE),
        "Could Not set the enote Field to non default value");
    } else
    {
      $this->assertTrue($this->mybrowser->setField('enote', FALSE),
        "Could Not set the enote Field");
    }
    if (!empty($BucketPool))
    {
      $this->assertTrue($this->mybrowser->setField('default_bucketpool_fk', $BucketPool),
        "Could Not set the default bucketpool select");
    }
    if ($simple)
    {
      $this->assertTrue($this->mybrowser->setField('simple', 'checked'),
        "Could Not set the simple check box");

    }
    if($original)
    {
      $this->assertTrue($this->mybrowser->setField('original', 'checked'),
        "Could Not set the original check box");
    }
    return (NULL);
  } // setUserFields

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
    $page = $this->mybrowser->clickSubmit('Upload');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, '/The file .*? has been uploaded/'), "FAILURE:Did not find the message 'The file .*? has been uploaded'\n");
  } // uploadFile
  /**
  * uploadServer
  * ($parentFolder,$uploadPath,$description=null,$uploadName=null,$agents=null)
  *
  * Upload a file and optionally schedule the agents.  The web-site must
  * already be logged into before using this method.
  *
  * @param string $parentFolder the parent folder name, default is root
  * folder (1)
  * @param string $uploadPath the path to upload data, can be a file or directory
  * @param string $description a default description is always used. It
  * can be overridden by supplying a description.
  * @param string $uploadName=null optional upload name
  * @param string $agents=null agents to schedule
  *
  * @return pass or fail
  *
  * @TODO determine if setting alpha folders is worth it.  Right now this routine
  * does not use them.....
  */
  public function uploadServer($parentFolder, $uploadPath, $description = null, $uploadName = null, $agents = null)
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
    if (empty ($uploadPath))
    {
      return (FALSE);
    }
    if (is_null($description)) // set default if null
    {
      $description = "File $uploadPath uploaded by test UploadAUrl";
    }
    //print "starting UploadAUrl\n";
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'), "uploadURL FAILED! cannot file Upload menu, did we get a fossology page?\n");
    $page = $this->mybrowser->clickLink("From Server");

    $this->assertTrue($this->myassertText($page, '/Upload from Server/'));
    $this->assertTrue($this->myassertText($page, '/Select the directory or file\(s\) on the server to upload/'));
    /* only look for the the folder id if it's not the root folder */
    $folderId = $parentFolder;
    if ($parentFolder != 1)
    {
      $folderId = $this->getFolderId($parentFolder, $page, 'folder');
    }
    $this->assertTrue($this->mybrowser->setField('folder', $folderId), "Count not set folder field\n");
    $this->assertTrue($this->mybrowser->setField('sourcefiles', $uploadPath), "Count not set sourcefiles field\n");
    $this->assertTrue($this->mybrowser->setField('description', "$description"), "Count not set description field\n");
    /* Set the name field if an upload name was passed in. */
    if (!is_null($uploadName))
    {
      $this->assertTrue($this->mybrowser->setField('name', $uploadName));
    }
    /* selects agents */
    $rtn = $this->setAgents($agents);
    if (!is_null($rtn))
    {
      $this->fail("FAIL: could not set agents in uploadServer test\n");
    }
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, '/The upload for .*? has been scheduled/'), "FAIL! did not see phrase The upload for .*? has been scheduled\n");
    //print  "************ page after Upload! *************\n$page\n";
  } //uploadServer

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
  public function uploadUrl($parentFolder, $url, $description = null, $uploadName = null, $agents = null)
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
    $this->assertFalse($this->myassertText($loggedIn, '/Network Error/'), "uploadURL FAILED! there was a Newtwork Error (dns lookup?)\n");
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'), "uploadURL FAILED! cannot file Upload menu, did we get a fossology page?\n");
    $this->assertTrue($this->myassertText($loggedIn, '/From URL/'));
    $page = $this->mybrowser->get("$URL?mod=upload_url");

    $this->assertTrue($this->myassertText($page, '/Upload from URL/'));
    $this->assertTrue($this->myassertText($page, '/Enter the URL to the file/'));
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
    if (!(is_null($uploadName)))
    {
      $this->assertTrue($this->mybrowser->setField('name', $url));
    }
    /* selects agents using numbers 1,2,3 or names license, mime, pkgmetagetta */
    $rtn = $this->setAgents($agents);
    if (!is_null($rtn))
    {
      $this->fail("FAIL: could not set agents in uploadAFILE test\n");
    }
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue($page);
    $this->assertTrue($this->myassertText($page, '/The upload .*? has been scheduled/'), "FAIL! did find phrase The upload .*? has been scheduled\n");
    //print  "************ page after Upload! *************\n$page\n";
  } //uploadUrl

  /**
   * wait4jobs
   *
   * Are there any jobs running?
   *
   * Wait for 2 hours for the test jobs to finish, check every 5 minutes
   * to see if they are done.
   *
   * @return boolean TRUE/FALSE
   *
   * @version "$Id: fossologyTestCase.php 4020 2011-03-31 21:30:29Z rrando $"
   *
   * @TODO: make a general program that can wait an arbitrary time, should
   * also allow for an interval, e.g. check for 2 hours every 7 min.
   *
   * Created on Jan. 15, 2009
   */
  public function wait4jobs()
  {

    require_once ('testClasses/check4jobs.php');

    define("FiveMIN", "300");

    $Jq = new check4jobs();

    /* wait at most 2 hours for test jobs to finish */
    $done = FALSE;
    for ($i = 1; $i <= 24; $i++)
    {
      //print "DB:W4Q: checking Q...\n";
      $number = $Jq->Check();
      if ($number != 0)
      {
        //print "sleeping 10 min...\n";
        sleep(FiveMIN);
      } else
      {
        //print "$number jobs found in the Queue\n";
        $done = TRUE;
        break;
      }
    }
    if ($done === FALSE)
    {
      print "{$argv[0]} waited for 2 hours and the jobs are still not done\n" .
      "Please investigate\n";
      return (FALSE);
    }
    if ($done === TRUE)
    {
      return (TRUE);
    }
  } // wait4jobs

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
}
