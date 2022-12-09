<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_dir.php
 * \brief unit tests for common-dir.php
 */

require_once(dirname(__FILE__) . '/../common-dir.php');

/**
 * \class test_common_dir
 */
class test_common_dir extends \PHPUnit\Framework\TestCase
{
  /* initialization */
  protected function setUp() : void
  {
    // print "Starting unit test for common-dir.php\n";
    print('.');
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() : void
  {
    //print "Ending unit test for common-dir.php\n";
  }

  /**
   * \brief test for Isdir Isartifact Iscontainer
   */
  function test_Is()
  {
    print "Starting unit test for common-dir.php\n";
    print "test function Isdir()\n";
    $mode = 536888320;
    $result = Isdir($mode);
    $this->assertEquals(true, $result);
    $mode = 33188;
    $result = Isdir($mode);
    $this->assertEquals(false, $result);
    print "test function Isartifact()\n";
    $mode = 536888320;
    $result = Isartifact($mode);
    $this->assertEquals(false, $result);
    $mode = 805323776;
    $result = Isartifact($mode);
    $this->assertEquals(true, $result);
    print "test function Iscontainer()\n";
    $mode = 536888320;
    $result = Iscontainer($mode);
    $this->assertEquals(true, $result);
    $mode = 805323776;
    $result = Iscontainer($mode);
    $this->assertEquals(true, $result);

    print "test function DirMode2String()\n";
    $result = DirMode2String($mode);
    $this->assertEquals("a-d-----S---", $result);
    //print "Ending unit test for common-dir.php\n";
  }
  /**
   * \brief test of ExtensionGetter
   */
  public function test_GetFileExt()
  {
    $this->assertEquals(GetFileExt('autodestroy.exe.bak'),'bak');
  }

   /**
   * \brief test for DirMode2String
   */
  public function test_DirMode2String()
  {
    // print "test function DirMode2String()\n";
    $result = DirMode2String(805323776);
    $this->assertEquals("a-d-----S---", $result);
    $result = DirMode2String(0644);
    $this->assertEquals("---rw-r--r--", $result);
  }
}
