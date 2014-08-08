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
 * \file test_common_parm.php
 * \brief unit tests for common-parm.php
 */

require_once(dirname(__FILE__) . '/../common-parm.php');

/**
 * \class test_common_parm
 */
class test_common_parm extends PHPUnit_Framework_TestCase
{
  /* initialization */
  protected function setUp()
  {
    //print "Starting unit test for common-parm.php\n";
  }

  /**
   * \brief test for GetParm($Name, $Type)
   */
  function test_GetParm()
  {
    print "Starting unit test for common-parm.php\n";
    print "test function GetParm()\n";
    //Test Case 1: $_GET[$Name] = "Name", $Type = PARM_INTEGER
    $Name = "Name";
    $_GET[$Name] = 20;
    $Type = PARM_INTEGER;
    $result = GetParm($Name, $Type);
    $this->assertEquals("20", $result);
    //Test Case 2: $_GET[$Name] = "Name", $Type = PARM_NUMBER
    $_GET[$Name] = 20.2;
    $Type = PARM_NUMBER;
    $result = GetParm($Name, $Type);
    $this->assertEquals("20.2", $result);
    //Test Case 3: $_GET[$Name] = "Name", $Type = PARM_STRING
    $_GET[$Name] = "/test/";
    $Type = PARM_STRING;
    $result = GetParm($Name, $Type);
    $this->assertEquals("/test/", $result);
    //Test Case 4: $_GET[$Name] = "Name", $Type = PARM_TEXT
    $_GET[$Name] = "\\test\\";
    $Type = PARM_TEXT;
    $result = GetParm($Name, $Type);
    $this->assertEquals("test", $result);
    //Test Case 5: $_GET[$Name] = "Name", $Type = PARM_RAW
    $_GET[$Name] = "\\test\\";
    $Type = PARM_RAW;
    $result = GetParm($Name, $Type);
    $this->assertEquals("\\test\\", $result);
    //Test Case 5: $_GET[$Name] = NULL, $_POST[$NAME] = "NAME", $Type = PARM_TEXT
    $_POST[$Name] = $_GET[$Name];
    $_GET[$Name] = NULL;
    $Type = PARM_TEXT;
    $result = GetParm($Name, $Type);
    $this->assertEquals("test", $result);
    //Test Case 6: $Type = NULL
    $Type = NULL;
    $result = GetParm($Name, $Type);
    $this->assertEquals("", $result);
  }

  /**
   * \brief test for Traceback()
   */
  function test_Traceback()
  {
    print "test function Traceback()\n";
    $expected = "http://fossology.org/";
    $_SERVER['REQUEST_URI'] = $expected;
    $result = Traceback();
    $this->assertEquals($expected, $result);
  }

  /**
   * \brief test for Traceback_uri()
   */
  function test_Traceback_uri()
  {
    print "test function Traceback_uri()\n";
    $source = "http://fossology.org/?mod=test&parm=abc";
    $expected = "http://fossology.org/";
    $_SERVER['REQUEST_URI'] = $source;
    $result = Traceback_uri();
    $this->assertEquals($expected, $result);
  }

  /**
   * \brief test for Traceback_parm()
   */
  function test_Traceback_parm()
  {
    print "test function Traceback_parm()\n";
    $source1 = "http://fossology.org/?mod=test";
    $source2 = "http://fossology.org/?mod=test&parm=abc";
    $expected1 = "test&parm=abc";
    $expected2 = "&parm=abc";
    $_SERVER['REQUEST_URI'] = $source1;
    $result = Traceback_parm(1);
    $this->assertEquals("test", $result);
    $_SERVER['REQUEST_URI'] = $source2;
    $result = Traceback_parm(1);
    $this->assertEquals($expected1, $result);
    $result = Traceback_parm(0);
    $this->assertEquals($expected2, $result);
  }

  /**
   * \brief test for Traceback_parm_keep()
   */
  function test_Traceback_parm_keep()
  {
    print "test function Traceback_parm_keep()\n";
    $List = array('parm1', 'parm2');
    $_GET['parm1'] = "parm1";
    $expected = "&parm1=parm1";
    $result = Traceback_parm_keep($List);
    $this->assertEquals($expected, $result);
  }

  /**
   * \brief test for Traceback_dir()
   */
  function test_Traceback_dir()
  {
    print "test function Traceback_dir()\n";
    $source = "http://fossology.org/repo/testdir?mod=test&parm=abc";
    $_SERVER['REQUEST_URI'] = $source;
    $expected = "http://fossology.org/repo/";
    $result = Traceback_dir();
    $this->assertEquals($expected, $result);
    print "Ending unit test for common-parm.php\n";
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() {
    //print "Ending unit test for common-parm.php\n";
  }
}

?>
