<?php

/*
 Copyright (C) 2011-2014 Hewlett-Packard Development Company, L.P.

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
 * @brief test the wget agent thu command line.
 *
 * @note
 * The option -l,  default as 0, maximum recursion depth (0 for infinite).
 * is different with from upload from url on user interface(default as 1)
 * @group wget_agent
 */

require_once (__DIR__ . "/../../../testing/db/createEmptyTestEnvironment.php");

$TEST_RESULT_PATH = "./test_result";

/**
 * @class cliParamsTest4Wget
 * @biref Test wget agent from cli
 */
class cliParamsTest4Wget extends \PHPUnit\Framework\TestCase {

  public $WGET_PATH = "";
  public $DB_COMMAND = "";
  public $DB_NAME = "";
  public $SYSCONF_DIR = "";

  /**
   * @biref Initialization
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() {
    global $WGET_PATH;
    global $DB_COMMAND;
    global $DB_NAME;
    global $db_conf;
    global $REPO_NAME;
    global $SYSCONF_DIR;

    $db_conf = "";

    $cwd = getcwd();
    list($test_name, $db_conf, $DB_NAME, $PG_CONN) = setupTestEnv($cwd, "wget_agent");

    $REPO_NAME = "testDbRepo".$test_name;

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
    $WGET_PATH = $WGET_PATH." -C -c $db_conf";
    $last = exec("$WGET_PATH -h 2>&1", $out, $rtn);
    $this->assertEquals($usage, $out[0]); // check if executable file wget_agent is exited
  }

  /**
	 * @brief download one dir(one url)
	 *
	 * Under this direcotry, also having other directory(s)
   * \test
   * -# Create command to download from a directory
   * -# Set level 0, accept rpm
   * -# Reject few rpms
   * -# Check if an rpm is downloaded
   * -# Check if the rejected rpms are not downloaded
   */
  function testDownloadDirHasChildDirLevel0(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    //$this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');

    $command = "$WGET_PATH https://mirrors.kernel.org/fossology/releases/3.0.0/fedora/20/x86_64/ -A rpm -R fossology-common-3.0.0-1.fc20.x86_64.rpm,fossology-debuginfo-3.0.0-1.fc20.x86_64.rpm,fossology-web-3.0.0-1.fc20.x86_64.rpm,fossology-3.0.0-1.fc20.src.rpm -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/3.0.0/fedora/20/x86_64/fossology-3.0.0-1.fc20.x86_64.rpm");
    $this->assertFileExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/3.0.0/fedora/20/x86_64/fossology-wgetagent-3.0.0-1.fc20.x86_64.rpm");
    $this->assertFileNotExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/3.0.0/fedora/20/x86_64/fossology-debuginfo-3.0.0-1.fc20.x86_64.rpm");
  }

  /**
	 * \brief Download one dir(one url)
	 *
	 * Under this directory, having no other directory(s), having several files
   *
   * \test
   * -# Create command to download from a directory
   * -# Set level as 0, accept deb, reject fossology-* files
   * -# Check if a deb file is downloaded
   * -# Check if the fossology-* files are not downloaded
   */
  function testDownloadDirHasNoChildDirLevel0(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    //$this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');

    $command = "$WGET_PATH https://mirrors.kernel.org/fossology/releases/3.0.0/debian/7.0/ -A deb -R fossology-*  -d $TEST_RESULT_PATH";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/3.0.0/debian/7.0/fossology_3.0.0-1_i386.deb");
    $this->assertFileNotExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/3.0.0/debian/7.0/fossology-ununpack_3.0.0-1_amd64.deb");
  }

  /**
	 * \brief Download one dir(one url)
	 *
	 * Under this direcotry, also having other directory(s).
   * Since the level is 1, so can not download the files under url/dir(s)/, just download the directory(s) under url/
   * \test
   * -# Create command to download a directory
   * -# Set level to 1, accept rpm, reject few rpm
   * -# Check if the rpm are not downloaded
   * -# Check if lower level directory are not downloaded
   */
  function testDownloadDirHasChildDirLevel1(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    //$this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');

    $command = "$WGET_PATH https://mirrors.kernel.org/fossology/releases/2.0.0/Fedora/15/ -A rpm -R fossology-2.0.0-1.fc15.src.rpm,fossology-common-2.0.0-1.fc15.x86_64.rpm -l 1 -d $TEST_RESULT_PATH";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileNotExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/2.0.0/Fedora/15/i386/fossology-common-2.0.0-1.fc15.x86_64.rpm");
    $this->assertFileNotExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/2.0.0/Fedora/15/x86_64/fossology-common-2.0.0-1.fc15.x86_64.rpm");
    $this->assertFileExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/2.0.0/Fedora/15/i386");
    $this->assertFileExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/2.0.0/Fedora/15/x86_64");
  }

  /**
	 * \brief Download one file(one url)
   * \test
   * -# Create command to download a file
   * -# Set level as 0
   * -# Do not specify the output destination, so downloaded file under current directory
   * -# Check if the file was downloaded
   */
  function testDownloadDirCurrentDirLevel0(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    //$this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');

    $command = "$WGET_PATH https://mirrors.kernel.org/fossology/releases/2.0.0/Fedora/15/i386/fossology-db-2.0.0-1.fc15.i386.rpm";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileExists("mirrors.kernel.org/fossology/releases/2.0.0/Fedora/15/i386/fossology-db-2.0.0-1.fc15.i386.rpm");
    exec("/bin/rm -rf 'mirrors.kernel.org'");
  }

  /**
	 * \brief download one file(one url)
	 *
	 * This url and destination are  very special, the path has some blank spaces, '(' and ')'
   * \test
   * -# Create command to download a file
   * -# Set level as 0
   * \todo Ignore this test case, the test data is not existed
   * \note This test case can not pass, because the test data is not existed. so ignore this test case.
   */
  function testDownloadURLDesAbnormal(){
    global $WGET_PATH;
    return;

    $command = "$WGET_PATH 'http://www.fossology.org/~vincent/test/test%20dir(special)/WINKERS%20-%20Final_tcm19-16386.doc' -d './test result(special)'";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileExists("test result(special)/www.fossology.org/~vincent/test/test dir(special)/WINKERS - Final_tcm19-16386.doc");
    exec("/bin/rm -rf 'test result(special)'");
  }

  /**
	 * \brief download one dir(one url)
   * \test
   * -# Create command to download a directory
   * -# Set level as 2
   * -# Accept fossology*, reject few rpm files
   * -# Check if other files are downloaded
   * -# Check if the rpms are not downloaded
   */
  function testDownloadAcceptRejectType1(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    //$this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');

    $command = "$WGET_PATH https://mirrors.kernel.org/fossology/releases/2.0.0/Fedora/15/i386/ -A fossology* -R fossology-2.0.0-1.fc15.i386.rpm,fossology-2.0.0-1.fc15.src.rpm -d $TEST_RESULT_PATH -l 2";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/2.0.0/Fedora/15/i386/fossology-pkgagent-2.0.0-1.fc15.i386.rpm");
    $this->assertFileNotExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/2.0.0/Fedora/15/i386/fossology-2.0.0-1.fc15.src.rpm");
  }

  /**
	 * \brief download one dir(one url)
	 * \test
	 * -# Create command to download a directory
   * -# Set level as 1
   * -# Accept fossology-scheduler_2.0.0*, reject gz, fossology-scheduler_2.0.0-1_i386* files
   * -# Check if other files are downloaded
   * -# Check if rejected files are not downloaded
   */
  function testtDownloadAcceptRejectType2(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    //$this->change_proxy('http_proxy', 'web-proxy.cce.hp.com:8088');

    $command = "$WGET_PATH https://mirrors.kernel.org/fossology/releases/2.0.0/Debian/squeeze/6.0/ -A fossology-scheduler_2.0.0* -R gz,fossology-scheduler_2.0.0-1_i386* -d $TEST_RESULT_PATH -l 1";
    //print "command is:$command\n";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/2.0.0/Debian/squeeze/6.0/fossology-scheduler_2.0.0-1_amd64.deb");
    $this->assertFileNotExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/2.0.0/Debian/squeeze/6.0/fossology-scheduler_2.0.0-1_i386.deb");
  }

  /**
   * \brief Replace default repo with new repo
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
   * \brief Change proxy to test
   */
  function change_proxy($proxy_type, $porxy) {
    global $db_conf;

    $foss_conf = $db_conf."/fossology.conf";
    exec("sudo sed 's/.$proxy_type.*=.*/$proxy_type=$porxy/' $foss_conf >/tmp/fossology.conf");
    exec("sudo mv /tmp/fossology.conf $foss_conf");
  }

  /**
   * \brief Test proxy ftp
   * \test
   * -# Set FTP Proxy
   * -# Download a FTP file behind proxy
   * -# Check if file was downloaded
   */
  function test_proxy_ftp() {
    global $db_conf;
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    return; // TODO ignore this test case, because it is flaky on travis
    // ftp_proxy
    //$this->change_proxy("ftp_proxy", "web-proxy.cce.hp.com:8088");
    $command = "$WGET_PATH ftp://releases.ubuntu.com/releases/trusty/SHA1SUMS  -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/releases.ubuntu.com/releases/trusty/SHA1SUMS");
  }

  /**
   * \brief Test proxy http and no proxy
   * \test
   * -# Set HTTP and NO_PROXY
   * -# Download files behind proxy
   * -# Check if the files are downloaded
   */
  function test_proxy_http() {
    global $db_conf;
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    // http_proxy
    //$this->change_proxy("http_proxy", "web-proxy.cce.hp.com:8088");
    $command = "$WGET_PATH https://mirrors.kernel.org/fossology/releases/2.0.0/Debian/squeeze/6.0/fossology-mimetype_2.0.0-1_amd64.deb  -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/2.0.0/Debian/squeeze/6.0/fossology-mimetype_2.0.0-1_amd64.deb");

    // no proxy
    //$this->change_proxy("no_proxy", "fossology.org");
    $command = "$WGET_PATH https://mirrors.kernel.org/fossology/releases/2.0.0/Debian/squeeze/6.0/fossology-mimetype_2.0.0-1_amd64.deb  -d $TEST_RESULT_PATH";
    exec($command);
    //$this->assertFileNotExists("$TEST_RESULT_PATH/mirrors.kernel.org/fossology/releases/2.0.0/Debian/squeeze/6.0/fossology-mimetype_2.0.0-1_amd64.deb");
  }

  /**
   * \brief Test proxy https
   * \test
   * -# Set HTTPS proxy
   * -# Download https file behind proxy
   * -# Check if file downloaded
   */
  function test_proxy_https() {
    global $db_conf;
    global $TEST_RESULT_PATH;
    global $WGET_PATH;

    // https_proxy
    //$this->change_proxy("https_proxy", "web-proxy.cce.hp.com:8088");
    $command = "$WGET_PATH https://www.google.com/images/srpr/nav_logo80.png -l 1 -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/www.google.com/images/srpr/nav_logo80.png");
  }

  /**
	 * \brief Clean the env
	 * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown() {
    global $TEST_RESULT_PATH;
    global $DB_COMMAND;
    global $DB_NAME;
    global $SYSCONF_DIR;

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH $SYSCONF_DIR");
    // remove the sysconf/db/repo
    if (!empty($DB_COMMAND) && !empty($DB_NAME)) {
      exec("$DB_COMMAND -d $DB_NAME");
    }
  }
}

?>
