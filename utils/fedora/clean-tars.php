#!/usr/bin/php
<?php
/*
 get-fsrc.php
 SPDX-FileCopyrightText: © 2007 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * cln-tars: clean up tar.*z files left over from a make prep in the 
 *           Fedora sources
 *
 * @param string $input	path to the packages to clean
 * 
 * @return 0 on success, 1 on failure
 *
 */
$usage = <<< USAGE
clean-tars.php [-h] -i <input-path>

Clean cruft out of fedora packages.  The following is cleaned:
make.out - the make prep output
.cvsignore - cvs file
compressed archives - all .tgz, .gz, bzip2, bz2, zip files

Dead packages and packages with no .spec file are skipped.

Where:
-h standard help, usage message.
-i input-path path where sources/packages will be cleaned

USAGE;

$options = getopt("hi:");
//print_r($options);
if (empty($options))
{
  echo $usage;
  exit(1);
}
if (array_key_exists("h",$options))
{
  echo $usage;
  exit(0);
}
if (array_key_exists("i",$options))
{
  $in_path = $options['i'];
  if (empty($in_path))
  {
  	echo $usage;
  	exit(1);
  }
}

chdir($in_path) or die("Can't chdir to $in_path: $php_errormsg\n");

$toss = exec('ls', $list, $rtn);
if($rtn != 0){
  echo "ERROR, ls of $in_path did not return zero: $rtn\n";
  exit(1);
}

$date=`date`;
echo "Starting at: $date";
$whereami = `pwd`;

foreach($list as $pkg)
{
  echo "Package is:$pkg\n";
  if(!(chdir($pkg))){
    print "ERROR: Can't chdir to $pkg, skipping: $php_errormsg\n";
    continue;
  }
  $plist = array();
  $toss = exec('ls -a', $plist, $rtn);
  if($rtn != 0)
  {
    echo "ERROR, ls of $pkg did not return zero: $rtn\n";
    exit(1);
  }
  /*
   * Dam, folks sure do make this part tricky.... need to have very
   * robust patterns.
   * 
   * Remove compressed archives, .cvsignore and make.out
   * skip dead packages and packages with no .spec.
   */

  foreach($plist as $file)
  {
    $match = array();
    if (preg_match('/dead\.package/i', $file))
    {
      print "$pkg is a dead.package, skipping\n";
      continue;
    }
    $alist = `ls`;
    if (!(preg_match('/.*\.spec/i', $alist)))
    {
    	echo "$pkg has no spec file, skipping\n";
    	continue;
    }
    
    // the pattern for gz files is meant to match tgz and gz
    if(preg_match('/.*?gz$/i', $file, $match))
    {
      echo "Executing set -o noclobber; rm -rf $match[0]\n";
      $toss = system("set -o noclobber; rm -rf $match[0]", $rtn);
      if($rtn != 0){
      	echo "ERROR, remove of {$match[0]} did not return zero: $rtn\n";
      }
    }
    if(preg_match('/.*?bz2$/i', $file, $match))
    {
      echo "Executing set -o noclobber; rm -rf $match[0]\n";
      $toss = system("set -o noclobber; rm -rf $match[0]", $rtn);
      if($rtn != 0)
      {
      	echo "ERROR, remove of {$match[0]} did not return zero: $rtn\n";
      }
    }
    if(preg_match('/.*?Bzip2$/i', $file, $match))
    {
      echo "Executing set -o noclobber; rm -rf $match[0]\n";
      $toss = system("set -o noclobber; rm -rf $match[0]", $rtn);
      if($rtn != 0){
      	echo "ERROR, remove of {$match[0]} did not return zero: $rtn\n";
      }
    }
    if(preg_match('/.*?zip$/i', $file, $match))
    {
      echo "Executing set -o noclobber; rm -rf $match[0]\n";
      $toss = system("set -o noclobber; rm -rf $match[0]", $rtn);
      if($rtn != 0){
      	echo "ERROR, remove of {$match[0]} did not return zero: $rtn\n";
      }
    }
    if(preg_match('/\.cvsignore$/i', $file, $match))
    {
      echo "Executing set -o noclobber; rm -rf $match[0]\n";
      $toss = system("set -o noclobber; rm -rf $match[0]", $rtn);
      if($rtn != 0){
      	echo "ERROR, remove of {$match[0]} did not return zero: $rtn\n";
      }
    }
      if(preg_match('/make\.out$/i', $file, $match))
    {
      echo "Executing set -o noclobber; rm -rf $match[0]\n";
      $toss = system("set -o noclobber; rm -rf $match[0]", $rtn);
      if($rtn != 0){
      	echo "ERROR, remove of {$match[0]} did not return zero: $rtn\n";
      }
    }
  } // foreach($plist...
  chdir('..') or die("Can't chdir to ..: $php_errormsg\n");
}  // foreach($list as ...

$date=`date`;
print "Ending at: $date";
