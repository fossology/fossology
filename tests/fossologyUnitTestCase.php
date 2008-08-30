<?php


/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

/**
 * UnitTest case for fossology
 *
 * @package fossology
 * @subpackage tests
 *
 * @version "$Id$"
 *
 * Created on Jun 20, 2008
 */

/* make sure we have the correct path.  We will use the Debian path
 * which is in /usr/share/php/simpletest. if php-simpltest has been
 * installed.
 *
 */

if (!defined('SIMPLE_TEST'))
  define('SIMPLE_TEST', '/usr/share/php/simpletest/');

/* simpletest includes */
require_once SIMPLE_TEST . 'unit_tester.php';
require_once SIMPLE_TEST . 'reporter.php';
require_once SIMPLE_TEST . 'mock_objects.php';
require_once SIMPLE_TEST . 'web_tester.php';

/* does the path need to be modified?, I don't recommend running the
 * ../copy of the program to test.  I think the test should define/create
 * it when doing setup.
 */

/**
 * UnitTest case for fossology
 *
 * This is the base class for fossology unit tests.  All tests should
 * require_once this class and then extend it.
 *
 * This class defines where simpletest is and includes the modules
 * needed.
 *
 */
class fossologyUnitTestCase extends UnitTestCase
{
  /* Utility methods go here NOTE fix the example below see extending
   * on the web site
   */
  function myassert($pattern, $space)
  {
    return TRUE;
  }
  public function assert_resource($resource)
  {
    if (is_resource($resource))
    {
      $this->pass("Resource assertion passed\n");
    }
    else
    {
      $this->fail();
    }
  }
  public function assert_Notresource($resource)
  {
    if (!is_resource($resource))
    {
      $this->pass("Not Resource assertion passed\n");
    }
    else
    {
      $this->fail();
    }
  }

/* make it easy to run cli or the web
if (TextReporter :: inCli())
{
  exit ($test->run(new TextReporter()) ? 0 : 1);
}
$test->run(new HtmlReporter());
*/
}
?>
