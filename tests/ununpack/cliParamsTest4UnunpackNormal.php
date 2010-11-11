<?php

/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
require_once '/usr/share/php/PHPUnit/Framework.php';
require_once './utility.php';

class cliParamsTest4Ununpack extends PHPUnit_Framework_TestCase {
  /* command is ununpack -qCR xxxxx -d xxxxx */
   
  /* initialization */
  function setUp() {
    global $UNUNPACK_PATH;
    global $WORK_PATH;
    exec("cd $UNUNPACK_PATH; make clean; make; cd $WORK_PATH\n");
  }
  
  /* unpack iso file*/
  public function testNormalIso1(){
    global $UNUNPACK_PATH;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir); 
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/523.iso -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523sfp/QMFGOEM.TXT");
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523sfp/p3p10131.bin");
  }

  /* unpack iso, another case */
  public function testNormalIso2(){
    global $UNUNPACK_PATH;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir); 
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/imagefile.iso -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/imagefile.iso.dir/TEST.JAR");
  }
  
  /* unpack rpm file */
  public function testNormalRpm(){
    global $UNUNPACK_PATH;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "fossology-1.2.0-1.el5.i386.rpm -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/fossology-1.2.0-1.el5.i386.rpm.unpacked.dir/".
            "usr/share/fossology/agents/licenses/GPL/LGPL/LGPL v3.0"); exec("/bin/rm -rf $TEST_RESULT_PATH"); $isDir = is_dir($TEST_RESULT_PATH); $this->assertTrue(!$isDir);
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "libgnomeui2-2.24.3-1pclos2010.src.rpm -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/libgnomeui2-2.24.3-1pclos2010.src.rpm.unpacked.dir/".
                            "pclos-libgnomeui2.spec");
  }

  /* unpack tar file */
  public function testNormalTar(){
    global $UNUNPACK_PATH;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "rpm.tar -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/rpm.tar.dir/yast2-trans-bn.rpm.unpacked.dir/". 
                            "yast2-trans-bn.rpm.dir/usr/share/doc/packages/yast2-trans-bn/status.txt"); 
    $this->assertFileExists("$TEST_RESULT_PATH/rpm.tar.dir/yast2-trans-xh-2.17.2-1.15.noarch.rpm.unpacked.dir/".
          "yast2-trans-xh-2.17.2-1.15.noarch.rpm.dir/usr/share/YaST2/locale/xh/LC_MESSAGES/x11.mo");
  }

  /* unpack rar file */
  public function testNormalRar(){
    global $UNUNPACK_PATH;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "winscp376.rar -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/winscp376.rar.dir/winscp376.exe");
  }

  /* unpack archive lib and xx.deb/xx.udeb file */
  public function testNormalAr(){
    global $UNUNPACK_PATH;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* archive file */
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "libfossagent.a -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/libfossagent.a.dir/libfossagent.o");

    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* deb file */
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/bind9-host_1%3a9.7.0.dfsg.P1-1_i386.deb.dir/".
            "control.tar.gz.unpacked.dir/md5sums");

    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* udeb file */
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "libpango1.0-udeb_1.28.1-1_i386.udeb -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/libpango1.0-udeb_1.28.1-1_i386.udeb.dir/".
           "data.tar.gz.unpacked.dir/usr/lib/libpangoxft-1.0.so.0");
  }

  /* unpack jar file */
  public function testNormalJar(){
    global $UNUNPACK_PATH;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "testdir/test.jar -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/test.jar.dir/ununpack");
  }
  
  /* unpack zip file */
  public function testNormalZip(){
    global $UNUNPACK_PATH;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "threezip.zip -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/threezip.zip.dir/twozip.zip.dir/happy_learning.zip.dir/".
                   "SIM_Integration.pptx.dir/docProps/app.xml");
    $this->assertFileExists("$TEST_RESULT_PATH/threezip.zip.dir/Desktop.zip.dir/record.txt");
  }

  /* unpack cab and msi file */
  public function testNormalCatMsi(){
    global $UNUNPACK_PATH;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    
    /* cab file */
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "SKU011.CAB -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/SKU011.CAB.dir/ACWZDAT.MDT");

    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);

    /* msi file */
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "xunzai_Contacts.msi.msi -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/xunzai_Contacts.msi.msi.dir/CONTACTS.CAB.dir/contact");
  }

  /* unpack dsc file */
  public function testNormalDsc(){
    global $UNUNPACK_PATH;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    $command = "$UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "fcitx_3.6.2-1.dsc -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/fcitx_3.6.2-1.dsc.unpacked/src/pyParser.h");
  }






  /* clear up */
  public function tearDown() {
    global $TEST_RESULT_PATH;
    exec("/bin/rm -rf $TEST_RESULT_PATH");
  }
}
