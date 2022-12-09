<?php

/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Test for duplicate folders
 *
 * There are two types of folders, regular and upload.
 * This test only tests for duplicate upload folders.
 *
 * Note: to test regular folders, a verify routine would need to be
 * written, as you can't tell from cp2foss output if it created a dup.
 * One needs to look at the DB or?
 *
 *
 * @return indcates pass or fail
 *
 * @version "$Id: duplicate-Upfolder.php 628 2008-05-24 03:06:01Z rrando $"
 *
 * Created on May 22, 2008
 */

class TestDupFolders extends UnitTestCase
{

  public $command = '/usr/local/bin/test.cp2foss';

  function TestDupUploadFolder()
  {
    $output = array ();
    $error = exec("$this->command -p CP2fossTest/fldr1 -n foo -a /tmp/zlib.tar.bz2 -d \"a comment\"", $output, $retval);
    //print_r($output);
    $this->assertPattern('/Working on /', $output[0]);
    $output = array ();
    //
    $error = exec("$this->command -p CP2fossTest/fldr1 -n foo -a /tmp/zlib.tar.bz2 -d 'Dup Upload folder test'", $output, $retval);
    //print_r($output);
    $this->assertPattern('/Warning: /', $output[5]);
  }
}

