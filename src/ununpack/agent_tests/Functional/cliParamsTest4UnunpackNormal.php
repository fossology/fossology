<?php
/*
 Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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
 * \brief test the ununpack agent cli parameters.
 *
 * @group ununpack
 */
require_once './utility.php';

use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\Test\TestInstaller;

class cliParamsTest4Ununpack extends PHPUnit_Framework_TestCase
{
  private $agentDir;
  private $ununpack;

  /** @var TestPgDb */
  private $testDb;
  /** @var TestInstaller */
  private $testInstaller;

  function setUp()
  {
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    if (empty($TEST_DATA_PATH) || empty($TEST_RESULT_PATH))
      $this->markTestSkipped();

    $this->testDb = new TestPgDb('ununpackNormal');
    $this->agentDir = dirname(dirname(__DIR__))."/";

    $sysConf = $this->testDb->getFossSysConf();

    $this->ununpack = $this->agentDir . "/agent/ununpack -c " . $sysConf;
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->install($this->agentDir);

    $this->testDb->createSequences(array(), true);
    $this->testDb->createPlainTables(array(), true);
    $this->testDb->createInheritedTables(array());
    $this->testDb->alterTables(array(), true);
  }

  public function tearDown()
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->clear();
    $this->testInstaller->rmRepo();
    $this->testDb = null;

    global $TEST_RESULT_PATH;

    if (!empty($TEST_RESULT_PATH));
      exec("/bin/rm -rf $TEST_RESULT_PATH");
  }

  /* command is ununpack -i */
  function testNormalParamI(){

    $command = $this->ununpack." -i";
    $last = exec($command, $usageOut, $rtn);
    $this->assertEquals($rtn, 0);
  }

  /* command is ununpack -qCR xxxxx -d xxxxx, begin */
  /* unpack iso file*/
  function testNormalIso1(){

    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/test.iso -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? larry think the file & dir name should be not changed, even just to uupercase */
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip.dir/test.dir/test.zip.dir/ununpack");
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip.dir/test.dir/test.jar.dir/ununpack");
  }

  /* command is ununpack -qCR xxxxx -d xxxxx -L log */
  /* unpack iso file and check log file*/
  function testNormalParamL(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/test.iso -d $TEST_RESULT_PATH -L $TEST_RESULT_PATH/log";
    exec($command);
    /* check if the result is ok? larry think the file & dir name should be not changed, even just to uupercase */
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip.dir/test.dir/test.zip.dir/ununpack");
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip.dir/test.dir/test.zip.dir/ununpack");
    /* check if the log file generated? */
    $this->assertFileExists("$TEST_RESULT_PATH/log");
  }

  /* command is ununpack -qCR -x xxxxx -d xxxxx*/
  /* unpack zip file with -x and check if delete all unpack files.*/
  function testNormalParamx(){

    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/test.zip -d $TEST_RESULT_PATH -x";
    exec($command);
    $isDir = is_dir($TEST_RESULT_PATH . "/test.zip.dir");
    $this->assertTrue(!$isDir);
  }

  /* command is ununpack -qC -r 0 -d xxxxx*/
  /* unpack zip file with -r 0.*/
  function testNormalParamr(){

    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qC -r 0 $TEST_DATA_PATH/testtwo.zip -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/testtwo.zip.dir/test.zip");
    $isDir = is_dir("$TEST_RESULT_PATH/testtwo.zip.dir/test.zip.dir/");
    $this->assertTrue(!$isDir);
  }

  /* unpack iso, another case */
  function testNormalIso2(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/test.iso -d $TEST_RESULT_PATH";
    exec($command);

    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip");
  }

  /* unpack rpm file */
  function testNormalRpm(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* the first rpm package */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.rpm -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/test.rpm.unpacked.dir/".
            "usr/share/fossology/bsam/VERSION"); 
    $this->assertFileExists("$TEST_RESULT_PATH/test.rpm.unpacked.dir/".
                            "usr/share/fossology/bsam/ui/ui-license.php");
  }

  /* unpack tar file */
  function testNormalTar(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "emptydirs.tar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select some files to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/emptydirs.tar.dir/emptydirs/dir2/zerolenfile");
  }

  /* unpack rar file, compress if on windows operating system */
  function testNormalRarWin(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.rar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.rar.dir/dir1/ununpack");
  }


  /* unpack archive lib and xx.deb/xx.udeb file */
  function testNormalAr(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* archive file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.ar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.ar.dir/test.tar");
    
    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* deb file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.deb -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.deb.dir/".
            "control.tar.gz.dir/md5sums");
  }
 
  /* unpack jar file */
  function testNormalJar(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.jar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.jar.dir/ununpack");
  }
   
  /* unpack zip file */
  function testNormalZip(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "testthree.zip -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select some files to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/testthree.zip.dir/testtwo.zip.dir/test.zip.dir/".
                   "ununpack");
  }
 
  /* unpack cab and msi file */
  function testNormalCatMsi(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    
    /* cab file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.cab -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.cab.dir/dir1/ununpack");

    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);

    /* msi file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.msi -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.msi.dir/ununpack");
  }

  /* unpack dsc file */
  function testNormalDsc(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test_1-1.dsc -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test_1-1.dsc.unpacked/debian/README.Debian");
  }

  /* unpack .Z .gz .bz2 file */
  function testNormalCompressedFile(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* .Z file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.z -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.z.dir/test");

    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .gz file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "testdir.tar.gz -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/testdir.tar.gz.dir/testdir.tar");

    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .bz2 file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "fossI16L335U29.tar.bz2 -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/fossI16L335U29.tar.bz2.dir/fossology/README");
  }

  /* unpack .Z .gz .bz2 tarball */
  function testNormalTarball(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* .Z tarball*/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.tar.Z -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.tar.Z.dir/dir1/ununpack");
    
    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .bz2 tarball*/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "fossI16L335U29.tar.bz2 -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/fossI16L335U29.tar.bz2.dir/fossology/README");
  }

  /* analyse pdf file, to-do, mybe need to modify this test case */
  function testNormalPdf(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.pdf -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm, 
       now the israel.html is not under destination directory,
       is under source directory
     */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.pdf.dir/test");
  }

  /* unpack upx file, to-do, uncertain how is the unpacked result like */
  function testNormalUpx(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    //$command = "$this->UNUNPACK_PATH -qCR $TEST_DATA_PATH/".
    //            " -d $TEST_RESULT_PATH";
    //exec($command);
    //$this->assertFileExists("$TEST_RESULT_PATH/");
  }
 
  /* unpack disk image(file system) */
  function testNormalFsImage(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* ext2 image */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "ext2file.fs -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/ext2file.fs.dir/test.zip.dir/ununpack");

    // delete the directory ./test_result 
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* ext3 image */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "ext3file.fs -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/ext3file.fs.dir/testtwo.zip.dir/test.zip.dir/ununpack");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* fat image */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "fatfile.fs -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/fatfile.fs.dir/testtwo.zip");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* ntfs image */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "ntfsfile.fs -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/ntfsfile.fs.dir/testtwo.zip.dir/test.zip.dir/ununpack");
  }
 
  /* unpack boot x-x86_boot image, to-do, do not confirm
     how is the boot x-x86 boot image like  */
  /*function testNormalBootImage(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                "vmlinuz-2.6.26-2-686 -d $TEST_RESULT_PATH";
    exec($command);
    // check if the result is ok? select one file to confirm
    // now, can not confirm this assertion is valid, need to confirm 
    $this->assertFileExists("$TEST_RESULT_PATH/vmlinuz-2.6.26-2-686.dir/Partition_0000");
  }*/
 
  /* command is ununpack -qCR xxxxx -d xxxxx, end */
  
  /* command is ununpack -qCR -m 10 xxxxx -d xxxxx, begin */

  /* unpack one comlicated package, using -m option, multy-process */
  function testNormalMultyProcess(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR -m 10 $TEST_DATA_PATH/".
                "test.iso -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select some files to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/"
     ."test1.zip.dir/test.dir/test.cpio");
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/"
     ."test1.zip.dir/test.dir/test.cpio.dir/ununpack");
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/"
      ."test1.zip.dir/test.dir/test.jar.dir/ununpack");

  }
 
  /* analyse EXE file */
  function testNormalEXE(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.exe -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm.
     */
    $isDir = is_dir($TEST_RESULT_PATH . "/test.ext.dir");
    $this->assertTrue(!$isDir);
  }

  /* test ununpack cpio file */
  function testNormalcpio(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.cpio -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok?
     */
    $this->assertFileExists("$TEST_RESULT_PATH/test.cpio.dir/ununpack");
  }
}
