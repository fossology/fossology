<?php
/*
 SPDX-FileCopyrightText: © 2010-2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * cliParams
 * @file
 * @brief Test the ununpack agent cli parameters. (Normal)
 *
 * @group ununpack
 */
require_once __DIR__.'/utility.php';

use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\Test\TestInstaller;

/**
 * @class cliParamsTest4Ununpack
 * @brief Test the ununpack agent cli parameters. (Normal)
 */
class cliParamsTest4Ununpack extends \PHPUnit\Framework\TestCase
{
  /** @var string $agentDir
   * Location of agent directory
   */
  private $agentDir;
  /** @var string $ununpack
   * Location of agent binary
   */
  private $ununpack;

  /** @var TestPgDb $testDb
   * Test db
   */
  private $testDb;
  /** @var TestInstaller $testInstaller
   * TestInstaller object
   */
  private $testInstaller;

  /**
   * @brief Setup test repo and db
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  function setUp() : void
  {
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    if (empty($TEST_DATA_PATH) || empty($TEST_RESULT_PATH))
      $this->markTestSkipped();

    $this->testDb = new TestPgDb('ununpackNormal');
    $this->agentDir = dirname(__DIR__, 4)."/build/src/ununpack";

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

  /**
   * @brief Teardown test repo and db
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  public function tearDown() : void
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->clear();
    $this->testInstaller->rmRepo();
    $this->testDb = null;

    global $TEST_RESULT_PATH;

    if (!empty($TEST_RESULT_PATH));
      exec("/bin/rm -rf $TEST_RESULT_PATH");
  }

  /**
   * @brief Call agent with `-i` flag
   * @test
   * -# Call agent with `-i` flag to initialize db
   * -# Check if agent return OK
   */
  function testNormalParamI(){

    $command = $this->ununpack." -i";
    $last = exec($command, $usageOut, $rtn);
    $this->assertEquals($rtn, 0);
  }

  /**
   * @brief Pass an iso to agent
   *
   * Command is `ununpack -qCR xxxxx -d xxxxx`
   * @test
   * -# Pass an ISO to the agent
   * -# Check if it get extracted
   */
  function testNormalIso1(){

    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/test.iso -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? Larry think the file & dir name should be not changed, even just to uppercase */
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip.dir/test.dir/test.zip.dir/ununpack");
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip.dir/test.dir/test.jar.dir/ununpack");
  }

  /**
   * @brief Pass a log file to the agent
   *
   * Command is `ununpack -qCR xxxxx -d xxxxx -L log`
   * @test
   * -# Pass a compressed file and a log file with `-L` flag
   * -# Check if agent extract the compressed file
   * -# Check if agent write to the log file
   */
  function testNormalParamL(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/test.iso -d $TEST_RESULT_PATH -L $TEST_RESULT_PATH/log";
    exec($command);
    /* check if the result is ok? Larry think the file & dir name should be not changed, even just to uppercase */
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip.dir/test.dir/test.zip.dir/ununpack");
    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip.dir/test.dir/test.zip.dir/ununpack");
    /* check if the log file generated? */
    $this->assertFileExists("$TEST_RESULT_PATH/log");
  }

  /**
   * @brief Check clean flag
   *
   * Command is `ununpack -qCR -x xxxxx -d xxxxx`
   * @test
   * -# Pass agent a compressed file and `-x` flag
   * -# Check if the agent removed the unpacked files
   */
  function testNormalParamx(){

    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/test.zip -d $TEST_RESULT_PATH -x";
    exec($command);
    $isDir = is_dir($TEST_RESULT_PATH . "/test.zip.dir");
    $this->assertTrue(!$isDir);
  }

  /**
   * @brief Check recurse flag
   *
   * Command is `ununpack -qC -r 0 -d xxxxx`
   * @test
   * -# Pass a double compressed file to the agent with `-r` flag
   * -# Check if the agent unpack only upto depth passed
   */
  function testNormalParamr(){

    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qC -r 0 $TEST_DATA_PATH/testtwo.zip -d $TEST_RESULT_PATH";
    exec($command);
    $this->assertFileExists("$TEST_RESULT_PATH/testtwo.zip.dir/test.zip");
    $isDir = is_dir("$TEST_RESULT_PATH/testtwo.zip.dir/test.zip.dir/");
    $this->assertTrue(!$isDir);
  }

  /**
   * @brief Pass an iso to agent
   * @test
   * -# Pass an ISO to the agent
   * -# Check if it get extracted
   */
  function testNormalIso2(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/test.iso -d $TEST_RESULT_PATH";
    exec($command);

    $this->assertFileExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip");
  }

  /**
   * @brief Check for RPM files
   * @test
   * -# Pass an RPM file to the agent
   * -# Check if the contents of RPM get unpacked
   */
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

  /**
   * @brief Check for TAR files
   * @test
   * -# Pass an TAR file to the agent
   * -# Check if the contents of TAR get unpacked
   */
  function testNormalTar(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "emptydirs.tar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? Select some files to confirm */
    $this->assertFileExists("$TEST_RESULT_PATH/emptydirs.tar.dir/emptydirs/dir2/zerolenfile");
  }

  /**
   * @brief Check for RAR files compressed on Windows systems
   * @test
   * -# Pass an RAR file to the agent compressed on windows
   * -# Check if the contents of RAR get unpacked
   * @todo: failing on Travis
   */
  /*
  function testNormalRarWin(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.rar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm *\/
    $this->assertFileExists("$TEST_RESULT_PATH/test.rar.dir/dir1/ununpack");
  } */


  /**
   * unpack archive lib and xx.deb/xx.udeb file
   * @brief Check for archive lib and deb files
   * @test
   * -# Pass the files to the agent
   * -# Check if the contents of files get unpacked
   * \todo Test not working on Xenail but pass on Trusty
   */
  /*
  function testNormalAr(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* archive file *\/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.ar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm *\/
    $this->assertFileExists("$TEST_RESULT_PATH/test.ar.dir/test.tar");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* deb file *\/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.deb -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm *\/
    $this->assertFileExists("$TEST_RESULT_PATH/test.deb.dir/".
            "control.tar.gz.dir/control.tar.dir/md5sums");
  } */

  /**
   * @brief Check for Jar files
   * @test
   * -# Pass a Jar file to the agent
   * -# Check if the contents of Jar get unpacked
   */
  function testNormalJar(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.jar -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */
    $this->assertFileExists("$TEST_RESULT_PATH/test.jar.dir/ununpack");
  }

  /**
   * @brief Check for ZIP files
   * @test
   * -# Pass a ZIP file to the agent
   * -# Check if the contents of ZIP get unpacked
   */
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

  /**
   * @brief Check for CAB and MSI files
   * @test
   * -# Pass CAB and MSI files to the agent
   * -# Check if the contents of files get unpacked
   * \todo Test not working on Xenail but pass on Trusty
   */
  /*
  function testNormalCatMsi(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* cab file *\/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.cab -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm *\/
    $this->assertFileExists("$TEST_RESULT_PATH/test.cab.dir/dir1/ununpack");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);

    /* msi file *\/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.msi -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm *\/
    $this->assertFileExists("$TEST_RESULT_PATH/test.msi.dir/ununpack");
  } */

  /**
   * @brief Check for DSC files
   * @test
   * -# Pass an DSC file to the agent
   * -# Check if the contents of DSC get unpacked
   */
  function testNormalDsc(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test_1-1.dsc -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */
    $this->assertFileExists("$TEST_RESULT_PATH/test_1-1.dsc.unpacked/debian/README.Debian");
  }


  /**
   * @brief Check for Z, GZ and BZ2 files
   * @test
   * -# Pass Z, GZ and BZ2 files to the agent
   * -# Check if the contents of files get unpacked
   */
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
    $this->assertFileExists("$TEST_RESULT_PATH/fossI16L335U29.tar.bz2.dir/fossI16L335U29.tar.dir/fossology/README");
  }

  /**
   * @brief Check for Z, GZ and BZ2 tarballs
   * @test
   * -# Pass Z, GZ and BZ2 tarballs to the agent
   * -# Check if the contents of files get unpacked
   */
  function testNormalTarball(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* .Z tarball*/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.tar.Z -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */
    $this->assertFileExists("$TEST_RESULT_PATH/test.tar.Z.dir/test.tar.dir/dir1/ununpack");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
    /* .bz2 tarball*/
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "fossI16L335U29.tar.bz2 -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */
    $this->assertFileExists("$TEST_RESULT_PATH/fossI16L335U29.tar.bz2.dir/fossI16L335U29.tar.dir/fossology/README");
  }

  /**
   * @brief Check for PDF files
   * @test
   * -# Pass a PDF file to the agent
   * -# Check if the contents of file get unpacked
   */
  function testNormalPdf(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.pdf -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm. */
    $this->assertFileExists("$TEST_RESULT_PATH/test.pdf.dir/test");
  }

  /**
   * @brief Check for UPX files
   * @test
   * -# Pass a UPX file to the agent
   * -# Check if the contents of file get unpacked
   * @todo Uncertain how the unpack results looks like
   */
//   function testNormalUpx(){
//     global $TEST_DATA_PATH;
//     global $TEST_RESULT_PATH;

    //$command = "$this->UNUNPACK_PATH -qCR $TEST_DATA_PATH/".
    //            " -d $TEST_RESULT_PATH";
    //exec($command);
    //$this->assertFileExists("$TEST_RESULT_PATH/");
//   }

  /**
   * @brief Check for disk images (file systems)
   * @test
   * -# Pass ext2, ext3, fat and ntfs disk images to the agent
   * -# Check if the contents of images get unpacked
   */
  function testNormalFsImage(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* ext2 image */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "ext2file.fs -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */
    $this->assertFileExists("$TEST_RESULT_PATH/ext2file.fs.dir/testtwo.zip.dir/test.zip.dir/ununpack");

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

  /**
   * @brief Check for multi process flag
   *
   * Command is `ununpack -qCR -m 10 xxxxx -d xxxxx`
   * @test
   * -# Pass a complex file to the agent with `-m` flag
   * -# Check if the contents of file get unpacked
   */
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

  /**
   * @brief Check for EXE files
   * @test
   * -# Pass a EXE file to the agent
   * -# Check if the contents of file get unpacked
   */
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

  /**
   * @brief Check for CPIO files
   * @test
   * -# Pass a CPIO file to the agent
   * -# Check if the contents of file get unpacked
   */
  function testNormalcpio(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.cpio -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? */
    $this->assertFileExists("$TEST_RESULT_PATH/test.cpio.dir/ununpack");
  }

  /**
   * unpack ZST file
   * @brief Check for ZST file
   * @test
   * -# Pass the files to the agent
   * -# Check if the contents of files get unpacked
   */
  function testNormalZst(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* archive file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.zst -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */
    $this->assertFileExists("$TEST_RESULT_PATH/test.zst.dir/test.tar");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
  }

  /**
   * unpack lz4 file
   * @brief Check for lz4 file
   * @test
   * -# Pass the files to the agent
   * -# Check if the contents of files get unpacked
   */
  function testNormalLz4(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* archive file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.lz4 -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */
    $this->assertFileExists("$TEST_RESULT_PATH/test.lz4.dir/test.tar");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
  }

  /**
   * unpack lzma file
   * @brief Check for lzma file
   * @test
   * -# Pass the files to the agent
   * -# Check if the contents of files get unpacked
   */
  function testNormalLzma(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;

    /* archive file */
    $command = $this->ununpack." -qCR $TEST_DATA_PATH/".
                  "test.lzma -d $TEST_RESULT_PATH";
    exec($command);
    /* check if the result is ok? select one file to confirm */
    $this->assertFileExists("$TEST_RESULT_PATH/test.lzma.dir/test.tar");

    // delete the directory ./test_result
    exec("/bin/rm -rf $TEST_RESULT_PATH");
    $isDir = is_dir($TEST_RESULT_PATH);
    $this->assertTrue(!$isDir);
  }
}
