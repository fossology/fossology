<?php

/*
 Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

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
 */

/**
 * \brief test the wget agent thu command line.
 * NOTICE: the option -l,  default as 0, maximum recursion depth (0 for infinite).
           is different with from upload from url on user interface(default as 1)
 * @group wget agent 
 */

$TEST_RESULT_PATH = "./test_result";

/**
 * \class cliParamsTest4Wget - test wget agent from cli
 */
class cliParamsTest4Wget extends PHPUnit_Framework_TestCase {
   
  public $WGET_PATH = "";
  public $DB_COMMAND = "";
  public $DB_NAME = "";

  /* initialization */
  protected function setUp() {
    global $WGET_PATH;
    global $DB_COMMAND;
    global $DB_NAME;
    global $db_conf;
    global $REPO_NAME;

    $db_conf = "";

    $DB_COMMAND  = "../../../testing/db/createTestDB.php";
    exec($DB_COMMAND, $dbout, $rc);
    if (0 != $rc)
    {
      print "Can not create database for this testing sucessfully!\n";
      exit;
    }
    preg_match("/(\d+)/", $dbout[0], $matches);
    $test_name = $matches[1];
    $REPO_NAME = "testDbRepo".$test_name;
    $db_conf = $dbout[0];

    $WGET_PATH = '../../agent/wget_agent';
    $usage= "";
    if(file_exists($WGET_PATH))
    {
      $usage = 'Usage: ../../agent/wget_agent [options] [OBJ]';
    }
    else
    {
      $this->assertFileExists($WGET_PATH,
      $message = 'FATAL: cannot find executable file, stop testing\n');
    }
    // run it
    $last = exec("$WGET_PATH -h 2>&1", $out, $rtn);
    $this->assertEquals($usage, $out[1]); // check if executable file wget_agent is exited

    $WGET_PATH = $WGET_PATH." -C -c $db_conf";
  }

  /**
	 * \brief download one dir(one url), under this direcotry, also having other directory(s)
   * level is 0, accept rpm, reject fossology-1.2.1-1.fc10.src.rpm,fossology-1.2.0-1.fc10.src.rpm
   */
  function testDownloadDirHasChildDirLevel0(){
    print "Starting test functional wget agent \n";
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    $this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');
    
    $command = "$WGET_PATH http://www.fossology.org/testdata/wgetagent/rpms/fedora/10/ -A rpm -R fossology-1.2.1-1.fc10.src.rpm,fossology-1.2.0-1.fc10.src.rpm  -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/rpms/fedora/10/i386/fossology-debuginfo-1.2.0-1.fc10.i386.rpm");
    $this->assertFileExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/rpms/fedora/10/x86_64/fossology-devel-1.2.0-1.fc10.x86_64.rpm");
    $this->assertFileNotExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/rpms/fedora/10/SRPMS/fossology-1.2.1-1.fc10.src.rpm");
  }

  /** 
	 * \brief download one dir(one url), under this direcotry, having no other directory(s), having several files
   * default level as 0, accept gz, reject fossology* files
   */
  function testDownloadDirHasNoChildDirLevel0(){
    print "Starting test functional wget agent 1 \n";
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    $this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');
    
    $command = "$WGET_PATH http://www.fossology.org/testdata/wgetagent/debian/2.0.0/ -A gz -R fossology*  -d $TEST_RESULT_PATH";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/debian/2.0.0/squeeze/Packages.gz");
    $this->assertFileNotExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/debian/2.0.0/squeeze/  fossology_2.0.0-1.tar.gz");
  }

  /** 
	 * \brief download one dir(one url), under this direcotry, also having other directory(s)
   * level is 1, accept rpm, reject fossology-1.2.1-1.fc10.src.rpm,fossology-1.2.0-1.fc10.src.rpm
   * because the level is 1, so can not download the files under url/dir(s)/, just download the directory(s) under url/
   */
  function testDownloadDirHasChildDirLevel1(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    $this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');

    $command = "$WGET_PATH http://www.fossology.org/testdata/wgetagent/rpms/fedora/10/ -A rpm -R fossology-1.2.1-1.fc10.src.rpm,fossology-1.2.0-1.fc10.src.rpm -l 1 -d $TEST_RESULT_PATH";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileNotExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/rpms/fedora/10/i386/fossology-debuginfo-1.2.0-1.fc10.i386.rpm");
    $this->assertFileNotExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/rpms/fedora/10/x86_64/fossology-devel-1.2.0-1.fc10.x86_64.rpm");
    $this->assertFileExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/rpms/fedora/10/i386");
    $this->assertFileExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/rpms/fedora/10/x86_64");
    $this->assertFileExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/rpms/fedora/10/SRPMS");
  }

  /**
	 * \brief download one file(one url)
   * default level as 0, do not specify the output destination, so downloaded file under current directory
   */
  function testDownloadDirCurrentDirLevel0(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    $this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');

    $command = "$WGET_PATH http://www.fossology.org/testdata/wgetagent/debian/2.0.0/squeeze/fossology-ununpack_2.0.0-1_i386.deb";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileExists("www.fossology.org/testdata/wgetagent/debian/2.0.0/squeeze/fossology-ununpack_2.0.0-1_i386.deb");
    exec("/bin/rm -rf 'www.fossology.org'");
  }

  /**
	 * \brief download one file(one url)
   * default level as 0, this url and destination are  very special, the path has some blank spaces, '(' and ')'
   * \note this test case can not pass, because the test data is not existed. so ignore this test case.
   */
  function testDownloadURLDesAbnormal(){
    global $WGET_PATH;
    return; // TODO ignore this test case, the test data is not existed

    $command = "$WGET_PATH 'http://www.fossology.org/~vincent/test/test%20dir(special)/WINKERS%20-%20Final_tcm19-16386.doc' -d './test result(special)'";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileExists("test result(special)/www.fossology.org/~vincent/test/test dir(special)/WINKERS - Final_tcm19-16386.doc");
    exec("/bin/rm -rf 'test result(special)'");
  }

  /** 
	 * \brief download one dir(one url)
   * level is 2, accept fossology*, reject fossology-1.2.0-1.fc10.src.rpm,fossology-1.2.1-1.fc10.src.rpm files
   */
  function testDownloadAcceptRejectType1(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    $this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');

    $command = "$WGET_PATH http://www.fossology.org/testdata/wgetagent/rpms/fedora/10/SRPMS/ -A fossology* -R fossology-1.2.0-1.fc10.src.rpm,fossology-1.2.1-1.fc10.src.rpm -d $TEST_RESULT_PATH -l 2";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/rpms/fedora/10/SRPMS/fossology-1.1.0-1.fc10.src.rpm");
    $this->assertFileNotExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/rpms/fedora/10/SRPMS/fossology-1.2.1-1.fc10.src.rpm");
  }

  /**
	 * \brief download one dir(one url)
   * level is 1, accept fossology-scheduler_2.0.0*, reject gz, fossology-scheduler_2.0.0-1_i386* files
   */
  function testtDownloadAcceptRejectType2(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    $this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');

    $command = "$WGET_PATH http://www.fossology.org/testdata/wgetagent/debian/2.0.0/squeeze/ -A fossology-scheduler_2.0.0* -R gz,fossology-scheduler_2.0.0-1_i386* -d $TEST_RESULT_PATH -l 1";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/debian/2.0.0/squeeze/fossology-scheduler_2.0.0-1_amd64.deb");
    $this->assertFileNotExists("$TEST_RESULT_PATH/www.fossology.org/testdata/wgetagent/debian/2.0.0/squeeze/fossology-scheduler_2.0.0-1_i386.deb");
    print "ending test functional wget agent \n";
  }

  /**
   * \brief replace default repo with new repo
   */
  function preparations() {
    global $REPO_NAME;
    global $db_conf;

    if (is_dir("/srv/fossology/$REPO_NAME")) {
      exec("sudo chmod 2770 /srv/fossology/$REPO_NAME"); // change mode to 2770
      exec("sudo chown fossy /srv/fossology/$REPO_NAME -R"); // change owner of REPO to fossy
      exec("sudo chgrp fossy /srv/fossology/$REPO_NAME -R"); // change grp of REPO to fossy
    }
    if (is_dir($db_conf)) {
      exec("sudo chown fossy $SYSCONF_DIR -R"); // change owner of sysconfdir to fossy
      exec("sudo chgrp fossy $SYSCONF_DIR -R"); // change grp of sysconfdir to fossy
    }
  }


  /**
   * \brief change proxy to test
   */
  function change_proxy($proxy_type, $porxy) {
    global $db_conf;

    $foss_conf = $db_conf."/fossology.conf";
    exec("sudo sed 's/.$proxy_type.*=.*/$proxy_type=$porxy/' $foss_conf >/tmp/fossology.conf");
    exec("sudo mv /tmp/fossology.conf $foss_conf");
  }

  /**
   * \brief test proxy ftp
   */
  function test_proxy_ftp() {
    global $db_conf;
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    // ftp_proxy
    $this->change_proxy("ftp_proxy", "web-proxy.cce.hp.com:8088");
    $command = "$WGET_PATH ftp://ftp.gnu.org/gnu/wget/wget-1.10.1.tar.gz  -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/ftp.gnu.org/gnu/wget/wget-1.10.1.tar.gz");
  }

  /**
   * \brief test proxy http and no proxy
   */
  function test_proxy_http() {
    global $db_conf;
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    // http_proxy
    $this->change_proxy("http_proxy", "web-proxy.cce.hp.com:8088");
    $command = "$WGET_PATH http://www.fossology.org/rpms/fedora/10/x86_64/fossology-1.1.0-1.fc10.x86_64.rpm  -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/www.fossology.org/rpms/fedora/10/x86_64/fossology-1.1.0-1.fc10.x86_64.rpm");

    // no proxy
    $this->change_proxy("no_proxy", "fossology.org");
    $command = "$WGET_PATH http://www.fossology.org/rpms/epel/6Server/i386/fossology-1.4.1-1.el6.i686.rpm  -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileNotExists("$TEST_RESULT_PATH/www.fossology.org/rpms/epel/6Server/i386/fossology-1.4.1-1.el6.i686.rpm");
  }

  /**
   * \brief test proxy https
   */
  function test_proxy_https() {
    global $db_conf;
    global $TEST_RESULT_PATH;
    global $WGET_PATH;

    // https_proxy
    $this->change_proxy("https_proxy", "web-proxy.cce.hp.com:8088");
    $command = "$WGET_PATH https://www.google.com/images/srpr/nav_logo80.png -l 1 -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/www.google.com/images/srpr/nav_logo80.png");
  }

  /**
	 * \brief clean the env
	 */
  protected function tearDown() {
    global $TEST_RESULT_PATH;
    global $DB_COMMAND;
    global $DB_NAME;

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    // remove the sysconf/db/repo
    exec("$DB_COMMAND -d $DB_NAME");
  }
}

?>
