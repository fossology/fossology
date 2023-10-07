#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief fointeg integrate fossology
 *
 * This utility can build, install and test fossology. See the usage message
 * for details.
 *
 * @version "$Id$"
 *
 * Created on Aug 12, 2011 by Mark Donohoe
 */

/*
 * Here is the spec:
 *
 * fointeg [-a] [-b] [-f] [-h] [-i] [-t] [-u]
 * -a: run everything, build, install, unit and functional tests
 * -b: make clean, make
 * -f: run functional tests (which build/install and run func tests)
 * -h: this help message
 * -i: install upstream sources
 * -t: run unit and functional tests (assumes a make and or a make install have
 * been done).
 * -u: run unit tests (make tests, and process results)
 *
 * This is what the makefile should call, later, add in --options as well.
 *
 * The plan:
 * - for phpunit, use the xml and junit options
 * - for simpletest? maybe use phpini? or a naming convention? (I like ini)
 * - parse the input files as well so you know what files were run, then
 * compare to the rest of the files in the dir.
 *
 *  May still have to do a file or mimetype on each file to know how to run it.
 *  (I am assuming that the rest of the files will be shell scripts).
 *
 *  Take a look at the shell framework that bob and norton want to use.
 *
 *  ?? Should the different options leave markers so the downstream tasks can
 *  check those to see if a build or ... nevermind.. much better for functional
 *  test to build/install and test.
 */


require_once 'fo_integration.php';

$usage = "fointeg [-a] [-b] [-f] [-h] [-i] [-t] [-u]
 -a: run everything, build, install, unit and functional tests
 -b: make clean, make
 -f: run functional tests (which build/install and run func tests)
 -h: this help message
 -i: install upstream sources
 -t: run unit and functional tests
 -u: run unit tests (make tests, and process results)";

$options = getopt('abfhitu');
if (empty($options))
{
  echo "$usage\n";
  exit(0);
}
if (array_key_exists('h', $options))
{
  echo "$usage\n";
  exit(0);
}
if (array_key_exists('a', $options))
{
  echo "-a option is not yet implimented\n";
  exit(0);
}
if (array_key_exists('b', $options))
{
  cdStart();
  try
  {
    $Make = new Build(getcwd());
  }
  catch (Exception $e)
  {
    echo $e;
    exit(1);
  }
}
if (array_key_exists('f', $options))
{
  echo "-f option is not yet implimented\n";
  exit(0);
}
if (array_key_exists('i', $options))
{
  echo "-i option is not yet implimented\n";
  exit(0);
}
if (array_key_exists('t', $options))
{
  echo "-t option is not yet implimented\n";
  exit(0);
}
if (array_key_exists('u', $options))
{
  // new unitTests()....
}

/**
 * \brief figure out where we are and cd to the correct directory to kick thinkgs
 * off
 *
 * @return void
 */
function cdStart()
{
  $WORKSPACE = NULL;

  // are we running inside jenkins?
  if(array_key_exists('WORKSPACE', $_ENV))
  {
    $WORKSPACE = $_ENV['WORKSPACE'];
  }
  if($WORKSPACE)
  {
    if(!chdir($WORKSPACE . "/fossology2.0/fossology/src"))
    {
      echo "FATAL! Cannot cd to " . $WORKSPACE . "/fossology2.0/fossology/src";
      exit(1);
    }
  }
  else
  {
    // ?? analyze the path and act accordingly?
  }
} // cdStart
