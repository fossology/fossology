<?php

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
    $error = exec('/usr/local/bin/cp2foss.test -p devnull -n fail -a /dev/null -d "test should fail" ',
    $output, $retval);
    //print_r($output);
    $this->assertPattern('/Error, .* not greater than zero/', $output[1]);
    $output = array();
    $error = exec('/usr/local/bin/cp2foss.test -p stdin -n fail -a /dev/stdin -d "stdin should fail"',
    $output, $retval);
    //print_r($output);
    $this->assertPattern('/Stopping, can\'t process archive/', $output[2]);
  }

  /*
   * This test needs setup: need some dir tree to process.
   *
   * Tyically the fossology sources are used. Check them out to
   * /tmp/fossology for this test to work.
   *
   * Consider adding setup and teardown methods.
   *
   */

  function TestNoDashR(){
    /*
     * Method: run a real cp2foss run, then examine the tar file created
     * by it and compare to what was tar'ed up.  If no differences,
     * at least cp2foss worked as far as creating the archive to upload
     * correctly.  This test DOES NOT test if the upload worked.
     */
    $last = exec('/usr/local/bin/cp2foss.test -p FossTest -n fossology -a /tmp/fossology -d "cp2foss, archive is a dirctory" ',
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
    $apath = '/tmp/fossology';
    $last = exec(
    "/usr/local/bin/cp2foss.test -p FossTest -n fossology -a $apath -R -d 'cp2foss, archive is a dirctory' ",
    $output, $retval);
    // $output[2] will always have the archive we are loading... in this
    // case it will be a tar file....
    // get only the files under fossology
    //echo "o2 is:{$output[2]}\n";
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