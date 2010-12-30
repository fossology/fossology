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

global $GlobalReady;
$GlobalReady=TRUE;

class cliParamsTest4Ununpack extends PHPUnit_Framework_TestCase {
   
  public $UNUNPACK_PATH = "";

  /* initialization */
  protected function setUp() {
    print "Starting test functional ununpack agent \n";
    // determine where ununpack agent is installed
    $upStream = '/usr/local/share/fossology/php/pathinclude.php';
    $pkg = '/usr/share/fossology/php/pathinclude.php';
    $usage= "";
    if(file_exists($upStream))
    {
      require($upStream);
      $usage = 'Usage: /usr/local/lib/fossology/agents/ununpack [options] file [file [file...]]';
    }
    else if(file_exists($pkg))
    {
      require($pkg);
      $usage = 'Usage: /usr/lib/fossology/agents/ununpack [options] file [file [file...]]';
    }
    else
    {
      $this->assertFileExists($upStream,
      $message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
      $this->assertFileExists($pkg,
      $message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
    }
    $this->UNUNPACK_PATH = $AGENTDIR;
    // run it
    $last = exec("$this->UNUNPACK_PATH/ununpack 2>&1", $out, $rtn);
    $this->assertEquals($usage, $out[1]); // check if ununpack aready installed
  }

//function testDebug() // test debug start
//{
//  return 0;
//  } //test debug end

  /* command is ununpack -qCR xxxxx -d xxxxx, begin */
  /* unpack iso file*/
  function testNormalIso1(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/523.iso -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? larry think the file & dir name should be not changed, even just to uupercase */
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523sfp/QMFGOEM.TXT");
    $this->assertFileExists("$TEST_RESULT_PATH/523.iso.dir/523sfp/p3p10131.bin");
  }

  /* unpack iso, another case */
  function testNormalIso2(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/imagefile.iso -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? if the package is unpacked, also need to check if unpacked content is valid */
    $this->assertFileExists("$TEST_RESULT_PATH/imagefile.iso.dir/TEST.JAR");
  }
 
  /* unpack rpm file */
  function testNormalRpm(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    /* the first rpm package */
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "fossology-1.2.0-1.el5.i386.rpm -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/fossology-1.2.0-1.el5.i386.rpm.unpacked.dir/".
            "usr/share/fossology/agents/licenses/GPL/LGPL/LGPL v3.0"); 
    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH); $this->assertTrue(!$isDir);
    /* the second rpm package */
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "libgnomeui2-2.24.3-1pclos2010.src.rpm -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/libgnomeui2-2.24.3-1pclos2010.src.rpm.unpacked.dir/".
                            "pclos-libgnomeui2.spec");
  }

  /* unpack tar file */
  function testNormalTar(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "rpm.tar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select some files to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/rpm.tar.dir/yast2-trans-bn.rpm.unpacked.dir/". 
                            "yast2-trans-bn.rpm.dir/usr/share/doc/packages/yast2-trans-bn/status.txt"); 
    $this->assertFileExists("$TEST_RESULT_PATH/rpm.tar.dir/yast2-trans-xh-2.17.2-1.15.noarch.rpm.unpacked.dir/".
          "yast2-trans-xh-2.17.2-1.15.noarch.rpm.dir/usr/share/YaST2/locale/xh/LC_MESSAGES/x11.mo");
  }

  /* unpack rar file */
  function testNormalRar(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
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
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "libfossagent.a -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/libfossagent.a.dir/libfossagent.o");
    
    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* deb file */
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
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
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
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
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "testdir/test.jar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.jar.dir/ununpack");
  }
  
  /* unpack zip file */
  function testNormalZip(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
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
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "SKU011.CAB -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/SKU011.CAB.dir/ACWZDAT.MDT");

    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);

    /* msi file */
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "xunzai_Contacts.msi.msi -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/xunzai_Contacts.msi.msi.dir/CONTACTS.CAB.dir/contact");
  }


  /* unpack dsc file */
  function testNormalDsc(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
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
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "ununpack.c.Z -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/ununpack.c.Z.dir/ununpack.c");

    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .gz file */
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "argmatch.c.gz -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/argmatch.c.gz.dir/argmatch.c");

    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .bz2 file */
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
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
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
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
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "fcitx_3.6.2.orig.tar.gz -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/fcitx_3.6.2.orig.tar.gz.dir/fcitx_3.6.2.orig.tar.dir/fcitx-3.6.2/configure.in");
    
    // delete the directory ./test_result   
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .bz2 tarball*/
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "test.tar.bz2 -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/test.tar.bz2.dir/test.tar.dir/ununpack");
  }

  /* analyse pdf file, to-do, mybe need to modify this test case */
  function testNormalPdf(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "israel.pdf -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm, 
       now the israel.html is not under destination directory,
       is under source directory
     */ 
    $this->assertFileExists("$TEST_RESULT_PATH/israel.pdf.dir/israel.html");
  }

  /* unpack upx file, to-do, uncertain how is the unpacked result like */
  function testNormalUpx(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    //$command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
    //            " -d $TEST_RESULT_PATH";
    //exec($command);
    //$this->assertFileExists("$TEST_RESULT_PATH/");
  }

  /* unpack disk image(file system) */
  function testNormalFsImage(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    /* ext2 image */
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "ext2test-image -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/ext2test-image.dir/ununpack.c");
   
    // delete the directory ./test_result 
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* ext3 image */
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "ext3test-image -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/ext3test-image.dir/libfossagent.a.dir/libfossagent.o");
    
    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* fat image */
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                  "fattest-image -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/fattest-image.dir/ununpack.c");
    
    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* ntfs image */
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
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
    $command = "$this->UNUNPACK_PATH/ununpack -qCR $TEST_DATA_PATH/".
                "initrd.img-2.6.26-2-686 -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm
       now, can not confirm this assertion is valid, need to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/initrd.img-2.6.26-2-686.dir/Partition_0000");
  }
 
  /* command is ununpack -qCR xxxxx -d xxxxx, end */
  
  /* command is ununpack -qCR -m 10 xxxxx -d xxxxx, begin */

  /* unpack one comlicated package, using -m option, multy-process */
  function testNormalMultyProcess(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $command = "$this->UNUNPACK_PATH/ununpack -qCR -m 10 $TEST_DATA_PATH/".
                "../testdata4unpack.7z -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select some files to confirm */ 
    $this->assertFileExists("$TEST_RESULT_PATH/testdata4unpack.7z.dir/testdata4unpack/"
     ."libgnomeui2-2.24.3-1pclos2010.src.rpm.unpacked.dir/pclos-libgnomeui2.spec");
    $this->assertFileExists("$TEST_RESULT_PATH/testdata4unpack.7z.dir/testdata4unpack/"
     ."FileName.tar.Z.dir/FileName.tar.dir/test.iso.dir/test1.zip.tar.dir/test1.zip.dir/test.dir/test.cpio.dir/ununpack");
    $this->assertFileExists("$TEST_RESULT_PATH/testdata4unpack.7z.dir/testdata4unpack/SKU011.CAB.dir/PRO11.INI");
    $this->assertFileExists("$TEST_RESULT_PATH//testdata4unpack.7z.dir/testdata4unpack/"
         ."xunzai_Contacts.msi.msi.dir/CONTACTS.CAB.dir/contact");
    $this->assertFileExists("$TEST_RESULT_PATH/testdata4unpack.7z.dir/testdata4unpack/"
     ."libpango1.0-udeb_1.28.1-1_i386.udeb.dir/data.tar.gz.dir/data.tar.dir/usr/lib/libpangox-1.0.so.0");
    $this->assertFileExists("$TEST_RESULT_PATH/testdata4unpack.7z.dir/testdata4unpack/imagefile.iso.dir/TEST.JAR");
  }

  /* supporting DB, command is ununpack -RvQf xxxxx -d xxxxx */ 
  /* this case must be executed in root */
  function testNormalDB() {
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $command = "$this->UNUNPACK_PATH/ununpack -RvQf $TEST_DATA_PATH/".
                "ununpack.c.Z -d $TEST_RESULT_PATH";
    //print "pupload_pk is:$upload_pk\n";
    /* set env */
    putenv('pfile_fk=500');
    putenv('upload_pk=500');
    putenv('pfile=DEF532166028490E867514C7817ACA442A0888DD.7D88B8303AC12D01CE75D14842D3985C.29004');
    $link = pg_Connect("host=localhost dbname=fossology user=fossy password=fossy");
    $Sql = "select * from upload where upload_pk = 500;";
    $result = pg_exec($link, $Sql); // judge if already existed
    $numrows = pg_numrows($result);
    //print "numrows is:$numrows\n";
    if ($numrows == 0) { 
      $Sql = "insert into upload(upload_pk, upload_filename, upload_userid, upload_mode, pfile_fk, upload_origin) values(500, 'ununpack.c.Z', 2, 40, 500, 'ununpack.c.Z');";
      pg_exec($link, $Sql); // insert one record
    }
    pg_close($link);
    /* copy original file to the directory files */
    shell_exec("mkdir -p /srv/fossology/repository/localhost/files/de/f5/32/");
    shell_exec("cp  $TEST_DATA_PATH/ununpack.c.Z  /srv/fossology/repository/localhost/files/de/f5/32/def532166028490e867514c7817aca442a0888dd.7d88b8303ac12d01ce75d14842d3985c.29004"); // copy
    //print "pupload_pk is:$upload_pk\n";
    shell_exec($command);
    //print "command2 is:$command2\n";
    //print "command is:$command\n";
    $this->assertFileExists("$TEST_RESULT_PATH/ununpack.c.Z.dir/ununpack.c");
  }



  /* command is ununpack -qCR -m 10 xxxxx -d xxxxx, end */ 

  /* clear up */
  protected function tearDown() {
    global $TEST_RESULT_PATH;
    print "ending test functional ununpack agent \n";
    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
  }
}

?>
