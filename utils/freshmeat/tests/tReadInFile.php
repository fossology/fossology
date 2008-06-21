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
 * test for ReadInputFile
 *
 * @param
 *
 *
 * @version "$Id: $"
 *
 * Created on Jun 11, 2008
 */

// The require below needs to be fixed up once we know where the class
// file should go.
require_once ('../../../tests/fossologyUnitTestCase.php');
require_once ('../ReadInputFile.php');

class TestReadinFile extends fossologyUnitTestCase
{
  /* need a set up here, need to have a file to read in the correct
   * format.
   *
   * Dang, just hardcode for now...
   */

  /*  function TestNoInputFile()
    {
  // use an empty file
  //
  //
  //
      $RF = new ReadInputFile(" ");
      if (!assert_resource($RF->file_resource))
      {
        $this->pass();
      } else
      {
        $this->fail();
      }
    }
  */
  /* Test Case #1
   * Use a test input file., Should get a file resouce back.
   */
  function TestResource()
  {
    $Rif = new ReadInputFile('/home/markd/workspace/fossology/utils/freshmeat/tests/tfile_small');

    /* make sure we have a resource */
    $exists = $this->CheckForResource($Rif->file_resource);
    if ($exists)
    {
      $this->pass("TestResource Passed\n");
    } else
    {
      print "Failing TestResource, exits is:$exists\n";
      print_r($exists);
      $this->fail('Failed TestResource Test, Readline Failed file open test');
    }
  }
  /* Test Case #2
   * Get all lines of data, check for comments or blank lines, Fail if we
   * find any....
   */
  function TestForCommentBlankLine()
  {
    $Rif = new ReadInputFile('/home/markd/workspace/fossology/utils/freshmeat/tests/tfile_comment');
    //print "DB: T4CBL: Before While loop, file res is: ";
    //$this->dump($Rif->file_resource);
    $line = $Rif->getline($Rif->file_resource);
    while ($line = $Rif->getline($Rif->file_resource))
    {
//      print "DB: T4BCL line is:$line\n";
      $this->assert_NoPattern('/^#/', $line);
      $this->assert_NoPattern('//', $line);
      $line = $Rif->getline($Rif->file_resource);
    }
    $this->pass("TestForCommentBlankLine Passed\n");
  }
  /* Test Case #3
   * Get two lines... they should be different.
   */

  /* read till no more lines... what happens? */
  function TestEof()
  {
    $Rif = new ReadInputFile('/home/markd/workspace/fossology/utils/freshmeat/tests/tfile_medium');
    while ($line = $Rif->getline($Rif->file_resource))
    {
      continue;
    }
    if(empty($line))
    {
      $this->pass("TestEof Passed\n");
    }
  }
}
?>
