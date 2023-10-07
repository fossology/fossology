<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Describe your PHP program or function here
 *
 * @version "$Id: login.php 1924 2009-03-27 01:45:55Z rrando $"
 *
 * Created on Jul 21, 2008
 */

require_once('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;
global $USER;
global $PASSWORD;

class TestRepoLogin extends fossologyTestCase{

  function testLogin(){

    global $URL;
    print "login test starting\n";
    $browser = new SimpleBrowser();
    $this->setBrowser($browser);
    $this->Login();
    $page = $this->mybrowser->getContent();
    //print "************LOGIN: Page after Login is:************\n";
    //$this->dump($page);
    preg_match('/FOSSology/', $page, $matches);
    //$this->dump($matches);
    $this->assertTrue($matches);
  }
}
