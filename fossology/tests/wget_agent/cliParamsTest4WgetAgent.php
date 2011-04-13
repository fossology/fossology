<?php

/*
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
 */

/**
 * cliParams
 * \brief test the wget agent thu command line.
 * NOTICE: the option -l,  default as 0, maximum recursion depth (0 for infinite).
           is different with from upload from url on user interface(default as 1)
 * @group wget agent 
 */
require_once '/usr/share/php/PHPUnit/Framework.php';

global $GlobalReady;
$GlobalReady=TRUE;
$TEST_RESULT_PATH = "./test_result";

class cliParamsTest4Wget extends PHPUnit_Framework_TestCase {
   
  public $WGET_PATH = "";

  /* initialization */
  protected function setUp() {
    print "Starting test functional wget agent \n";
    global $WGET_PATH;
    // determine where wget agent is installed
    $upStream = '/usr/local/share/fossology/php/pathinclude.php';
    $pkg = '/usr/share/fossology/php/pathinclude.php';
    $usage= "";
    if(file_exists($upStream))
    {
      require($upStream);
      $usage = 'Usage: /usr/local/lib/fossology/agents/wget_agent [options] [OBJ]';
    }
    else if(file_exists($pkg))
    {
      require($pkg);
      $usage = 'Usage: /usr/lib/fossology/agents/wget_agent [options] [OBJ]';
    }
    else
    {
      $this->assertFileExists($upStream,
      $message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
      $this->assertFileExists($pkg,
      $message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
    }
    $WGET_PATH = "$AGENTDIR/wget_agent";
    // run it
    $last = exec("$WGET_PATH -h 2>&1", $out, $rtn);
    $this->assertEquals($usage, $out[1]); // check if wget agent aready installed
  }

  function testDebug() // test debug start
  {
    return 0;
  } //test debug end

  /** download one dir(one url), under this direcotry, also having other directory(s)
   * level is 0, accept rpm, reject fossology-1.2.1-1.fc10.src.rpm,fossology-1.2.0-1.fc10.src.rpm
   */
  function test1(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    
    $command = "$WGET_PATH http://fossology.org/rpms/fedora/10/ -A rpm -R fossology-1.2.1-1.fc10.src.rpm,fossology-1.2.0-1.fc10.src.rpm  -d $TEST_RESULT_PATH";
    print "command is:$command\n";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/fossology.org/rpms/fedora/10/i386/fossology-debuginfo-1.2.0-1.fc10.i386.rpm");
    $this->assertFileExists("$TEST_RESULT_PATH/fossology.org/rpms/fedora/10/x86_64/fossology-devel-1.2.0-1.fc10.x86_64.rpm");
    $this->assertFileNotExists("$TEST_RESULT_PATH/fossology.org/rpms/fedora/10/SRPMS/fossology-1.2.1-1.fc10.src.rpm");
  }

  /** download one dir(one url), under this direcotry, having no other directory(s), having several files
   * default level as 0, accept gz, reject fossology* files
   */
  function test2(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;
    
    $command = "$WGET_PATH http://fossology.org/debian/1.3.0/ -A gz -R fossology*  -d $TEST_RESULT_PATH";
    print "command is:$command\n";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/fossology.org/debian/1.3.0/Packages.gz");
    $this->assertFileNotExists("$TEST_RESULT_PATH/fossology.org/debian/1.3.0/fossology_1.3.0~3780.orig.tar.gz");
  }

  /** download one dir(one url), under this direcotry, also having other directory(s)
   * level is 1, accept rpm, reject fossology-1.2.1-1.fc10.src.rpm,fossology-1.2.0-1.fc10.src.rpm
   * because the level is 1, so can not download the files under url/dir(s)/, just download the directory(s) under url/
   */
  function test3(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;

    $command = "$WGET_PATH http://fossology.org/rpms/fedora/10/ -A rpm -R fossology-1.2.1-1.fc10.src.rpm,fossology-1.2.0-1.fc10.src.rpm -l 1 -d $TEST_RESULT_PATH";
    print "command is:$command\n";
    exec($command);
    $this->assertFileNotExists("$TEST_RESULT_PATH/fossology.org/rpms/fedora/10/i386/fossology-debuginfo-1.2.0-1.fc10.i386.rpm");
    $this->assertFileNotExists("$TEST_RESULT_PATH/fossology.org/rpms/fedora/10/x86_64/fossology-devel-1.2.0-1.fc10.x86_64.rpm");
    $this->assertFileExists("$TEST_RESULT_PATH/fossology.org/rpms/fedora/10/i386");
    $this->assertFileExists("$TEST_RESULT_PATH/fossology.org/rpms/fedora/10/x86_64");
    $this->assertFileExists("$TEST_RESULT_PATH/fossology.org/rpms/fedora/10/SRPMS");
  }

  /** download one file(one url)
   * default level as 0, do not specify the output destination, so downloaded file under current directory
   */
  function test4(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;

    $command = "$WGET_PATH http://fossology.org/debian/1.3.0/fossology-web-single_1.3.0~3780_all.deb";
    print "command is:$command\n";
    exec($command);
    $this->assertFileExists("./fossology.org/debian/1.3.0/fossology-web-single_1.3.0~3780_all.deb");
    exec("/bin/rm -rf 'fossology.org'");
  }

  /** download one file(one url)
   * default level as 0, this url and destination are  very special, the path has some blank spaces, '(' and ')'
   */
  function test5(){
    global $WGET_PATH;

    $command = "$WGET_PATH 'http://fossology.org/~vincent/test/test%20dir(special)/WINKERS%20-%20Final_tcm19-16386.doc' -d './test result(special)'";
    print "command is:$command\n";
    exec($command);
    $this->assertFileExists("test result(special)/fossology.org/~vincent/test/test dir(special)/WINKERS - Final_tcm19-16386.doc");
    exec("/bin/rm -rf 'test result(special)'");
  }

  /** download one dir(one url)
   * level is 2, accept fossology*, reject fossology-1.2.0-1.fc10.src.rpm,fossology-1.2.1-1.fc10.src.rpm files
   */
  function test6(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;

    $command = "$WGET_PATH http://fossology.org/rpms/fedora/10/SRPMS/ -A fossology* -R fossology-1.2.0-1.fc10.src.rpm,fossology-1.2.1-1.fc10.src.rpm -d $TEST_RESULT_PATH -l 2";
    print "command is:$command\n";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/fossology.org/rpms/fedora/10/SRPMS/fossology-1.1.0-1.fc10.src.rpm");
    $this->assertFileNotExists("$TEST_RESULT_PATH/fossology.org/rpms/fedora/10/SRPMS/fossology-1.2.1-1.fc10.src.rpm");
  }

  /** download one dir(one url)
   * level is 1, accept fossology-scheduler-single*, reject gz, fossology-scheduler-single_1.3.0~3780_i38* files
   */
  function test7(){
    global $TEST_RESULT_PATH;
    global $WGET_PATH;

    $command = "$WGET_PATH http://fossology.org/debian/1.3.0/ -A fossology-scheduler-single* -R gz,fossology-scheduler-single_1.3.0~3780_i38* -d $TEST_RESULT_PATH -l 1";
    print "command is:$command\n";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/fossology.org/debian/1.3.0/fossology-scheduler-single_1.3.0~3780_amd64.deb");
    $this->assertFileNotExists("$TEST_RESULT_PATH/fossology.org/debian/1.3.0/fossology-scheduler-single_1.3.0~3780_i386.deb");
  }

  protected function tearDown() 
  {
    global $TEST_RESULT_PATH;
    print "ending test functional wget agent \n";
    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
  }
}

?>
