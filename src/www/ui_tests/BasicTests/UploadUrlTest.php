<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Upload a file using the UI
 *
 *@TODO need to make sure testing folder exists....
 *
 * @version "$Id: UploadUrlTest.php 4019 2011-03-31 21:23:17Z rrando $"
 *
 * Created on Aug 1, 2008
 */

/*
 * Yuk! This test is ugly!  NOTE: Will need to set a proxy for this to
 * work inside hp.
 */

require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class UploadUrlTest extends fossologyTestCase
{

  function testUploadUrl()
  {
    global $URL;

    print "starting UploadUrlTest\n";
    //$this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    $this->Login();

    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'));
    $this->assertTrue($this->myassertText($loggedIn, '/From URL/'));
    $page = $this->mybrowser->get("$URL?mod=upload_url");
    $this->assertTrue($this->myassertText($page, '/Upload from URL/'));
    $this->assertTrue($this->myassertText($page, '/Enter the URL to the file/'));

    /* select Testing folder, filename based on pid or session number */

    $FolderId = $this->getFolderId('Basic-Testing', $page, 'folder');
    $this->assertTrue($this->mybrowser->setField('folder', $FolderId));
    $simpletest = 'http://downloads.sourceforge.net/simpletest/simpletest_1.0.1.tar.gz';
    $this->assertTrue($this->mybrowser->setField('geturl', $simpletest));
    $desc = 'File uploaded by test UploadUrlTest';
    $this->assertTrue($this->mybrowser->setField('description', "$desc"));
    $pid = getmypid();
    $upload_name = 'TestUploadUrl-' . "$pid";
    $this->assertTrue($this->mybrowser->setField('name', $upload_name));
    /* we won't select any agents this time' */
    $page = $this->mybrowser->clickSubmit('Upload!');
    $this->assertTrue($page);
    //print  "************ page after Upload! *************\n$page\n";
    $this->assertTrue($this->myassertText($page, '/has been scheduled. It is/'));


  }
}
