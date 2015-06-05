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
    $this->ununpack = $this->agentDir . "/agent/ununpack";

    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->install($this->agentDir);

    $this->testDb->createSequences(array(), true);
    $this->testDb->createPlainTables(array(), true);
    $this->testDb->alterTables(array(), true);
  }

  public function tearDown()
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->clear();
    $this->testInstaller->rmRepo();
    $this->testDb = null;

    global $TEST_RESULT_PATH;

    if (!empty($TEST_RESULT_PATH))
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

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/523.iso -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? larry think the file & dir name should be not changed, even just to uupercase */
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523sfp/QMFGOEM.TXT");
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523sfp/p3p10131.bin");
  }

  /* command is ununpack -qCR xxxxx -d xxxxx -L log */
  /* unpack iso file and check log file*/
  function testNormalParamL(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/523.iso -d $TEST_RESULT_PATH -L $TEST_RESULT_PATH/log";
    exec($command);
    /* check if the result is ok? larry think the file & dir name should be not changed, even just to uupercase */
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523sfp/QMFGOEM.TXT");
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523sfp/p3p10131.bin");
    /* check if the log file generated? */
    $this->assertFileExists("$TEST_RESULT_PATH/log");
  }

  /* command is ununpack -qCR -x xxxxx -d xxxxx*/
  /* unpack iso file with -x and check if delete all unpack files.*/
  function testNormalParamx(){

    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/523.iso -d $TEST_RESULT_PATH -x";
    exec($command);
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
  }

  /* command is ununpack -qC -r 0 -d xxxxx*/
  /* unpack iso file with -r 0.*/
  function testNormalParamr(){

    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qC -r 0 $TEST_DATA_PATH/523.iso -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523sfp/QMFGOEM.TXT");
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523sfp/p3p10131.bin");
    $isDir = is_dir("$TEST_RESULT_PATH/523.iso.dir/[BOOT]/Bootable_2.88M.img.dir/");
    $this->assertTrue(!$isDir);
  }

  /* unpack iso, another case */
  function testNormalIso2(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/imagefile.iso -d $TEST_RESULT_PATH";
    exec($command);

    $this->assertFileExists("$TEST_RESULT_PATH/imagefile.iso.dir/TEST.JAR;1");
  }

  /* unpack rpm file */
  function testNormalRpm(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* the first rpm package */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "fossology-1.2.0-1.el5.i386.rpm -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/fossology-1.2.0-1.el5.i386.rpm.unpacked.dir/".
            "usr/share/fossology/agents/licenses/GPL/LGPL/LGPL v3.0"); 
    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH); $this->assertTrue(!$isDir);
    /* the second rpm package */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "libgnomeui2-2.24.3-1pclos2010.src.rpm -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/libgnomeui2-2.24.3-1pclos2010.src.rpm.unpacked.dir/".
                            "pclos-libgnomeui2.spec");
  }

  /* unpack tar file */
  function testNormalTar(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "rpm.tar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select some files to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/rpm.tar.dir/yast2-trans-bn.rpm.unpacked.dir/". 
                            "/usr/share/doc/packages/yast2-trans-bn/status.txt"); 
    $this->assertFileExists("$TEST_RESULT_PATH/rpm.tar.dir/yast2-trans-xh-2.17.2-1.15.noarch.rpm.unpacked.dir/".
          "/usr/share/YaST2/locale/xh/LC_MESSAGES/x11.mo");
  }

  /* unpack rar file, compress if on windows operating system */
  function testNormalRarWin(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "winscp376.rar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/winscp376.rar.dir/winscp376.exe");
  }


  /* unpack archive lib and xx.deb/xx.udeb file */
  function testNormalAr(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* archive file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "libfossagent.a -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/libfossagent.a.dir/libfossagent.o");
    
    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* deb file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb.dir/".
            "control.tar.gz.dir/control.tar.dir/md5sums");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* udeb file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "libpango1.0-udeb_1.28.1-1_i386.udeb -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/libpango1.0-udeb_1.28.1-1_i386.udeb.dir/".
           "data.tar.gz.dir/data.tar.dir/usr/lib/libpangoxft-1.0.so.0");
  }
 
  /* unpack jar file */
  function testNormalJar(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "testdir/test.jar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.jar.dir/ununpack");
  }
   
  /* unpack zip file */
  function testNormalZip(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "threezip.zip -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select some files to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/threezip.zip.dir/twozip.zip.dir/happy_learning.zip.dir/".
                   "SIM_Integration.pptx.dir/docProps/app.xml");
    $this->assertFileExists("$TEST_RESULT_PATH/threezip.zip.dir/Desktop.zip.dir/record.txt");
  }
 
  /* unpack cab and msi file */
  function testNormalCatMsi(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    
    /* cab file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "SKU011.CAB -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/SKU011.CAB.dir/ACWZDAT.MDT");

    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);

    /* msi file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "xunzai_Contacts.msi.msi -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/xunzai_Contacts.msi.msi.dir/CONTACTS.CAB.dir/contact");
  }

  /* unpack dsc file */
  function testNormalDsc(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "fcitx_3.6.2-1.dsc -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/fcitx_3.6.2-1.dsc.unpacked/src/pyParser.h");
  }

  /* unpack .Z .gz .bz2 file */
  function testNormalCompressedFile(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* .Z file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "ununpack.c.Z -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/ununpack.c.Z.dir/ununpack.c");

    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .gz file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "argmatch.c.gz -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/argmatch.c.gz.dir/argmatch.c");

    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .bz2 file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "metahandle.tab.bz2 -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/metahandle.tab.bz2.dir/metahandle.tab");
  }

  /* unpack .Z .gz .bz2 tarball */
  function testNormalTarball(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* .Z tarball*/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "FileName.tar.Z -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/FileName.tar.Z.dir/FileName.tar.dir/test.iso.dir/"
        ."test1.zip.tar.dir/test1.zip.dir/test.dir/test.cpio.gz.dir/test.cpio.dir/ununpack");
    
    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .gz tarball*/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "fcitx_3.6.2.orig.tar.gz -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/fcitx_3.6.2.orig.tar.gz.dir/fcitx_3.6.2.orig.tar.dir/fcitx-3.6.2/configure.in");
    
    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .bz2 tarball*/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.tar.bz2 -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.tar.bz2.dir/test.tar.dir/ununpack");
  }

  /* analyse pdf file, to-do, mybe need to modify this test case */
  function testNormalPdf(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "israel.pdf -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm, 
       now the israel.html is not under destination directory,
       is under source directory
     */ 
    $this->assertFileExists("$TEST_RESULT_PATH/israel.pdf.dir/israel");
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
                  "ext2test-image -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/ext2test-image.dir/ununpack.c");

    // delete the directory ./test_result 
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* ext3 image */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "ext3test-image -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/ext3test-image.dir/libfossagent.a.dir/libfossagent.o");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* fat image */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "fattest-image -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/fattest-image.dir/ununpack.c");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* ntfs image */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "ntfstest-image -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/ntfstest-image.dir/ununpack.c");
  }
 
  /* unpack boot x-x86_boot image, to-do, do not confirm
     how is the boot x-x86 boot image like  */
  function testNormalBootImage(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                "vmlinuz-2.6.26-2-686 -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm
       now, can not confirm this assertion is valid, need to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/vmlinuz-2.6.26-2-686.dir/Partition_0000");
  }
 
  /* command is ununpack -qCR xxxxx -d xxxxx, end */
  
  /* command is ununpack -qCR -m 10 xxxxx -d xxxxx, begin */

  /* unpack one comlicated package, using -m option, multy-process */
  function testNormalMultyProcess(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR -m 10 $TEST_DATA_PATH/".
                "../testdata4unpack.7z -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select some files to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/testdata4unpack.7z.dir/testdata4unpack/"
     ."libgnomeui2-2.24.3-1pclos2010.src.rpm.unpacked.dir/pclos-libgnomeui2.spec");
    $this->assertFileExists("$TEST_RESULT_PATH/testdata4unpack.7z.dir/testdata4unpack/"
     ."FileName.tar.Z.dir/FileName.tar.dir/test.iso.dir/test1.zip.tar.dir/test1.zip.dir/test.dir/test.cpio.dir/ununpack");
    $this->assertFileExists("$TEST_RESULT_PATH/testdata4unpack.7z.dir/testdata4unpack/SKU011.CAB.dir/PRO11.INI");
    $this->assertFileExists("$TEST_RESULT_PATH/testdata4unpack.7z.dir/testdata4unpack/"
         ."xunzai_Contacts.msi.msi.dir/CONTACTS.CAB.dir/contact");
    $this->assertFileExists("$TEST_RESULT_PATH/testdata4unpack.7z.dir/testdata4unpack/"
     ."libpango1.0-udeb_1.28.1-1_i386.udeb.dir/data.tar.gz.dir/data.tar.dir/usr/lib/libpangox-1.0.so.0");

  }
 
  /* analyse EXE file */
  function testNormalEXE(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "PUTTY.EXE -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm.
     */
    $this->assertFileExists("$TEST_RESULT_PATH/PUTTY.EXE.dir/UPX1");
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
