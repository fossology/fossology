#!/usr/bin/php
/***********************************************************
 get-fsrc.php
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
 
<?php
/**
 * Get the fedora sources with cvs.  Then use make prep to really get all
 * the sources.  As the initial co in cvs only gets patches.
 *
 * NOTE: the complete distro is ~ 69gig.  So the area where the sources are
 *       placed MUST be big.
 *
 * @param string $out-path output path where the sources will be checked out.
 * @param string $path-dead-pkg-list fully qualified path to file with list
 * of dead packages to skip when processing.
 * 
 * @version "$Id$"
 *
 */

# Steps:
#
# 1. establish cvs root and set proxy in the environment
#     -> Make sure that set -o noclobber is set in environment.
# 2. cd to destination
# 3. cvs co
# 4. filter for dead packages or packages with no .spec files
#    - make a file of each for use in next step or just keep in memory?
#    - if no dead package file exists, process all.
# 5. Using fileter output, make prep for each package.

# Paramters: path where sources will be placed
#            filepath where list of dead packages is
#
#            e.g. -o path -d path2dead
# $1 should be the path to packages?
# for now we just hardcode it:

require_once("./pathinclude.h.php");
require_once("$LIBDIR/libcp2foss.h.php");

// Sad, but this just doesn't work!
//$_ENV['http_proxy'] = 'http://web-proxy.fc.hp.com:8088';
//$_ENV['CVSROOT'] = ':pserver:anonymous@cvs.fedoraproject.org:/cvs/pkgs';

# sirius path....
//$fedora='/home/fossy/repository/fawkes.rags/Fedora-8/devel';
# fawkes path....
$fedora='/home/repository/fawkes.rags/FT/devel';

$usage = <<< USAGE

get-fsrc [-h] -d <dead-path> -o <output-path>

Where:
-h standard help, usage message.
-d dead-path path were list of dead packages will be stored.
-o output-path path where sources will be checked out.

NOTE: parameters are NOT processed at this time!

USAGE;

for ($i = 1; $i < $argc; $i++) {
  switch ($argv[$i]) {
    case '-d':
      $i++;
      if (isset($argv[$i])) {
        $dead_path = $argv[$i];
      }
      else {
        $dead_path = NULL;
      }
      break;
    case '-h':
      $i++;
      echo "$usage\n";
      exit(0);
    case '-o':
      $i++;
      if (isset($argv[$i])) {
        $fedora = $argv[$i];
      }
      else {
        die("ERROR: Must supply a valid path to a file after -o");
      }
      break;
    default:
      die("ERROR: Unknown argument: $argv[$i]\n$usage");
      break;
  }
}

chdir($fedora) or die("Can't chdir to $fedora, $php_errormsg\n");

$date=`date`;
echo "Starting at: $date\n";

// checkout and filter
cvs_co($fedora);
chdir($fedora) or die("Can't chdir to $fedora, $php_errormsg\n");

//$list=`ls`;
// For testing....
$list = array(abiword, amanda, bugzilla, 'configure-thinkpad', common, eclipse);

foreach($list as $pkg){
  // wrinkle.... common package does not have a devel, need to special
  // case it.
  rtrim($pkg);
  pdbg("\$pkg is:$pkg");
  if(!(chdir("$pkg/devel"))){
    echo "ERROR: Can't chdir to $pkg, skipping: $php_errormsg\n";
    continue;
  }
  $plist=`ls`;
  if (preg_match('/dead.package/', $plist)){
    echo "$pkg is a dead.package\n";
    chdir('../..') or die("Can't chdir to ../.., $php_errormsg\n");
  }
  else{
    $dir = `pwd`;
    $date = `date`;
    print "Now at:$dir";
    print "on $date";
    echo "Makeing $pkg\n";
    $mpcmd = 'export http_proxy=http://web-proxy.fc.hp.com:8088; ' .
             "alias rm='rm -f'; make > make.out 2>&1";
    $last = exec("$mpcmd", $mpout, $rtn);
    if($rtn != 0) {
      print "ERROR: make prep did not exit zero: return was: $rtn\n\n";
    }
    chdir('../..') or die("Can't chdir to ../.., $php_errormsg\n");
  }
  echo "-----\n\n";
}

$date=`date`;
print "Ending at: $date";

/**
 * funciton: cvs_co
 *
 * check out fedora cvs sources
 *
 * @param string $fedora fully qualified path to an existing directory
 * where the sources will be checked out.
 *
 */

function cvs_co($fedora){

  // make sure cvs root is set
  $cmd = 'export http_proxy=http://web-proxy.fc.hp.com:8088; ' .
         'export CVSROOT=:pserver:anonymous@cvs.fedoraproject.org:/cvs/pkgs; ' .
         'cvs co abiword amanda bugzilla configure-thinkpad common eclipse';
  chdir($fedora) or die("Can't chdir to $fedora, $php_errormsg\n");
  $last = exec("$cmd", $cvs_co_out, $retval);
  if ($retval != 0){
    echo "ERROR: cvs co did not return zero status: $retval\n";
    return(FALSE);
  }
  return TRUE;
}
?>