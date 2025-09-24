#!/usr/bin/php
<?php
/*
 get-fsrc.php
 SPDX-FileCopyrightText: © 2007 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Get the fedora sources with cvs.  Then use make prep to really get all
 * the sources.  As the initial co in cvs only gets patches.
 *
 * NOTE: the complete distro is ~ 69gig.  So the area where the sources are
 *       placed MUST be big.
 *
 * @param string $out-path output path where the sources will be checked out.
 * @param string $path-skip-pkg-list fully qualified path to file with list
 * of skipped packages to skip when processing further.
 *
 * @version "$Id: get-fsrc.php 1593 2008-10-30 10:09:41Z taggart $"
 *
 */

/*
 * Steps:
 * 1. establish cvs root in the environment
 *     -> Make sure that set -o noclobber is set in environment.
 * 2. cd to destination
 * 3. cvs co
 * 4. filter for dead packages or packages with no .spec files
 *    - skip processing them, and use a file in the current directory to store them.
 * 5. make prep for each package.
 *
 */

// FIXME: $path = '/usr/local/fossology/agents';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

require_once("/usr/share/fossology/php/pathinclude.php");
global $WEBDIR;
require_once("$WEBDIR/common/common-cli.php");

$usage = "get-fsrc [-h] -s <skip-path> -o <output-path>\n";

/*
 *
 <<< USAGE

Where:
-h standard help, usage message.

-o output-path path where sources will be checked out, the packages will
   be checked out into a directory call devel.
-s skip-path path were list of dead packages and other skipped packages
   will be stored.
   Note is it ALWAYS a good idea to run this script using screen and to
   capture stdout and stderr into a log file.

USAGE;
*/

$options = getopt("ho:s:");
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
if (array_key_exists("o",$options))
  {
  $fedora = $options['o'];
  if (empty($fedora))
  {
    echo $usage;
    exit(1);
  }
  }
  if (array_key_exists("s",$options))
  {
  $spath = $options['s'];
  if (empty($spath))
  {
   $spath = '/tmp/skipped.fedora9.pkgs';
   print "NOTE: setting the path for skipped file to /tmp/skipped.fedora9.pkgs\n";
  }
  else
  {
   // defect here, should check to see if there is a trailing /....
   $spath .= '/skipped.fedora9.pkgs';
  }
  }


chdir($fedora) or die("Can't chdir to $fedora, $php_errormsg\n");

$date=`date`;
echo "Starting at: $date\n";

// checkout and filter
$checked_out = cvs_co($fedora);
if(!empty($checked_out))
{
	print($checked_out);
}
// need to check for trailing / and then do the right thing....add
// later
$devel = "$fedora" . '/devel';
chdir($devel) or die("Can't chdir to $fedora, $php_errormsg\n");

$list = array();

$last = exec('ls', $list, $rtn);
if ($rtn != 0)
{
	print "Error, cannot get list of packages with ls\n";
	exit(1);
}

// Filter and make
foreach($list as $pkg){
  // wrinkle.... common package does not have a devel, need to special
  // case it.
  rtrim($pkg);
  //cli_PrintDebugMessage("\$pkg is:$pkg");
  $dir = `pwd`;
  print "Now at:$dir";
  if(!(chdir("$pkg"))){
    echo "ERROR: Can't chdir to $pkg, skipping: $php_errormsg\n";
    continue;
  }
  $plist=`ls`;

  if (preg_match('/dead.package/', $plist)){
    echo "$pkg is a dead.package, skipping\n";
    $saved = save_skipped($spath, "$pkg is a dead package\n");
    if(!empty($saved))
    {
    	print "Warning! ";
    	print($saved);
    	chdir('..') or die("Can't chdir to .., $php_errormsg\n");
    	continue;
    }
    chdir('..') or die("Can't chdir to .., $php_errormsg\n");
  }
  elseif (!(preg_match('/.spec/', $plist)))
  {
    echo "$pkg has no spec file, skipping\n";
    $saved = save_skipped($spath, "$pkg has no spec file\n");
    if(!empty($saved))
    {
    	print "Warning! ";
    	print($saved);
    	chdir('..') or die("Can't chdir to .., $php_errormsg\n");
    	continue;
    }
    chdir('..') or die("Can't chdir to .., $php_errormsg\n");
  }
  else{
    $dir = `pwd`;
    $date = `date`;
    print "Now at:$dir";
    print "on $date";
    echo "Making $pkg\n";
    $mpcmd = "alias rm='rm -f'; make prep > make-prep.out 2>&1";
    $last = exec("$mpcmd", $mpout, $rtn);
    if($rtn != 0) {
      print "ERROR: make prep for $pkg did not exit zero: return was: $rtn\n\n";
      $saved = save_skipped($spath, "$pkg failed make prep, return code was: $rtn\n");
      if(!empty($saved))
      {
         print "Warning! ";
         print($saved);
      }
    }
    // put the removeal of the make.out here....as else clause...
    chdir('..') or die("Can't chdir to .., $php_errormsg\n");
  }
  // look for and remove the compressed file... look at older scripts.
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
  $cmd = 'export CVSROOT=:pserver:anonymous@cvs.fedoraproject.org:/cvs/pkgs; '
          . 'cvs co -r HEAD devel';

  chdir($fedora) or die("Can't chdir to $fedora, $php_errormsg\n");
  $last = exec("$cmd", $cvs_co_out, $retval);
  if ($retval != 0){
    return("ERROR: cvs co did not return zero status: $retval\n");
  }
  return NULL;
}

function save_skipped ($path, $message)
{
	global $WEBDIR;
	require_once("$WEBDIR/common/common-cli.php");
	// save the message containing the package name that failed in a file at $path

	$logged = cli_logger($path, $message);
	if(!empty($logged))
	{
		return($logged);
	}
	return(NULL);
}
