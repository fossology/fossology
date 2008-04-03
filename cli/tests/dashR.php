<?php
/***********************************************************
 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

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
 * dashR: test various conditions for the -R parameter
 *
 * NOTE: test version of cp2foss should be installed to /usr/local/bin as
 * cp2foss.test.
 *
 * @version "$Id$"
 */


class TestCP2fossRecursion extends UnitTestCase {
  /*
   * This function will use illegal archives, that is we have a file for
   * input, but it is zero length.
   */

  function TestDashRNoArchive(){
    
    $command = '/usr/local/bin/test.cp2foss';
    
    $error = exec("$command -p devnull -n fail -a /dev/null -d \"test should fail\" ",
    $output, $retval);
    //print_r($output);
    $this->assertPattern('/Error, .* not greater than zero/', $output[0]);
    //print_r($output);
    $output = array();
    $error = exec("$command -p stdin -n fail -a /dev/stdin -d 'stdin should fail'",
    $output, $retval);
    //print_r($output);
    $this->assertPattern('/Stopping, can\'t process archive/', $output[1]);
  }

  /*
   * This test needs setup: need some dir tree to process.
   *
   * Tyically the fossology sources are used. Check them out to
   * /tmp/fossology for this test to work.
   *
   * Consider adding setup and teardown methods.  For now the install
   * test script sets this up.
   *
   */

  function TestNoDashR(){
    /*
     * Method: run a real cp2foss run, then examine the tar file created
     * by it and compare to what was tar'ed up.  If no differences,
     * at least cp2foss worked as far as creating the archive to upload
     * correctly.  This test DOES NOT test if the upload worked.
     */
    
    $command = '/usr/local/bin/test.cp2foss';
    
    $last = exec("$command -p FossTest -n fossology -a /tmp/fossology -d 'cp2foss, archive is a directory' ",
    $output, $retval);
    //echo "\$output is:\n"; print_r($output); echo "\n";
    // $output[2] will always have the archive we are loading... in this
    // case it will be a tar file....
    // get only the files under fossology
    $find = "find /tmp/fossology -maxdepth 1  -type f -print";
    $last = exec($find, $findoutput, $retval);
    $last = exec("tar -tf $output[2]", $tarout, $retval);
    // get leaf names from find output
    $basenames = array();
    foreach($findoutput as $path) {
      $basenames[] = basename($path);
    }
    sort($tarout);
    sort($basenames);
    $diffs = array_diff($tarout, $basenames);
    if (empty($diffs)){
      $this->pass();
    }
    else {
      $this->fail();
    }
  }

  function TestDashR(){
    /*
     * Method: run a real cp2foss run, then examine the tar file created
     * by it and compare to what was tar'ed up.  If no differences,
     * at least cp2foss worked as far as creating the archive to upload
     * correctly.  This test DOES NOT test if the upload worked.
     */
    
    $command = '/usr/local/bin/test.cp2foss';
    
    $apath = '/tmp/fossology';
    $last = exec(
    "$command -p FossTest -n fossology -a $apath -R -d 'cp2foss, archive is a dirctory' ",
    $output, $retval);
    // $output[2] will always have the archive we are loading... in this
    // case it will be a tar file....
    // get only the files under fossology
    //echo "o2 is:{$output[2]}\n";
    //print_r($output);
    /*
    * Tried using find and combos of ls etc... issue was getting the
    * trailing / on directories without changing the format.
    * Gave up, and will just retar the archive and then compare to the
    * orginal tar.
    */
    $temp_tar = "/tmp/test.tar.bz2";
    chdir($apath) or die("Can't cd to $path, $php_errormsg\n");

    $tcmd = "tar -cjf $temp_tar --exclude='.svn' --exclude='.cvs' *";
    $last = exec($tcmd, $Rtoutput, $retval);
    $last = exec("tar -tf $output[2]", $tarout, $retval);
    $last = exec("tar -tf $temp_tar", $Rtout, $retval);
    foreach($tarout as $p) {
      //echo "tar path is:$p\n";
      $tpaths[] = rtrim($p);
    }
    foreach($Rtout as $path) {
      $Rtpaths[] = rtrim($path);
    }
    sort($tpaths);
    sort($Rtpaths);
    $diffs = array_diff($tpaths, $Rtpaths);
    if (empty($diffs)){
      $this->pass();
    }
    else {
      echo "diffs are:\n";
      $this->dump($diffs);
      $this->fail();
    }
  }
}
?>