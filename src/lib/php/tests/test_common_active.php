<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_active.php
 * \brief unit tests for common-active.php
 */

require_once(dirname(__FILE__) .'/../common-active.php');

/**
 * \class test_common_active
 */
class test_common_active extends \PHPUnit\Framework\TestCase
{
  /**
   * \brief initialization
   */
  protected function setUp() : void
  {
  }

  /**
   * \brief test for ActiveHTTPscript
   */
  function testActiveHTTPscript()
  {
    print "Start unit test for common-active.php\n";
    print "test function ActiveHTTPscript\n";
    /** $IncludeScriptTags is default 1 */
    $html_result = ActiveHTTPscript("test");
    $script_header = "<script language='javascript'>\n<!--\n";
    $script_foot  = "\n// -->\n</script>\n";
    $html_expect = "var test=null;\n";
    /* Check for browser support. */
    $html_expect .= "function test_Get(Url)\n";
    $html_expect .= "{\n";
    $html_expect .= "if (window.XMLHttpRequest)\n";
    $html_expect .= "  {\n";
    $html_expect .= "  test=new XMLHttpRequest();\n";
    $html_expect .= "  }\n";
    /* Check for IE5 and IE6 */
    $html_expect .= "else if (window.ActiveXObject)\n";
    $html_expect .= "  {\n";
    $html_expect .= "  test=new ActiveXObject('Microsoft.XMLHTTP');\n";
    $html_expect .= "  }\n";

    $html_expect .= "if (test!=null)\n";
    $html_expect .= "  {\n";
    $html_expect .= "  test.onreadystatechange=test_Reply;\n";
    /***
     'true' means asynchronous request.
     Rather than waiting for the reply, the reply is
     managed by the onreadystatechange event handler.
     ***/
    $html_expect .= "  test.open('GET',Url,true);\n";
    $html_expect .= "  test.send(null);\n";
    $html_expect .= "  }\n";
    $html_expect .= "else\n";
    $html_expect .= "  {\n";
    $html_expect .= "  alert('Your browser does not support XMLHTTP.');\n";
    $html_expect .= "  return;\n";
    $html_expect .= "  }\n";
    $html_expect .= "}\n";
    $html_expect_script = $script_header.$html_expect.$script_foot;
    $this->assertEquals($html_expect_script, $html_result);

    /** $IncludeScriptTags is no default 1 */
    $html_result = ActiveHTTPscript("test", 0);
    $this->assertEquals($html_expect, $html_result);
    print "unit test for common-active.php end\n";
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() : void
  {
  }
}
