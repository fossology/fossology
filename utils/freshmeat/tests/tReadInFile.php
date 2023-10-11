<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * test for ReadInputFile
 *
 * @param
 *
 * @version "$Id: $"
 *
 * Created on Jun 11, 2008
 */

// The require below needs to be fixed up once we know where the class
// file should go.
require_once ('../../../tests/fossologyUnitTestCase.php');
require_once ('../Classes/ReadInputFile.php');

class TestReadinFile extends fossologyUnitTestCase
{
  /* need a set up here, need to have a file to read in the correct
   * format.
   *
   * Dang, just hardcode for now...
   */

   /*
    * Test case, no file, should not get a file resource
    */

  function TestNoInputFile()
  {
    $RF = new ReadInputFile(" ");
    $this->assert_Notresource($RF->file_resource);
  }

  /* Test Case
   * Use a test input file., Should get a file resouce back.
   */
  function TestResource()
  {
    $Rif = new ReadInputFile('/home/markd/workspace/fossology/utils/freshmeat/tests/tfile_small');

    /* make sure we have a resource */
    $this->assert_resource($Rif->file_resource);
  }

  /* Test Case
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
  /* Test Case
   * Read the complete file, make sure eof is dealt with correctly.
   */

  function TestEof()
  {
    $Rif = new ReadInputFile('/home/markd/workspace/fossology/utils/freshmeat/tests/tfile_medium');
    while ($line = $Rif->getline($Rif->file_resource))
    {
      continue;
    }
    if (empty ($line))
    {
      $this->pass("TestEof Passed\n");
    }
  }
}
