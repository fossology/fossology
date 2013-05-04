#!/usr/bin/php
<?php
/*
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
 */

/**
 *  checkOutSrcs
 *
 *  Check out the fossology sources or do an svn up.
 *
 *  @param [-h] [-c {top | svnPath}] [-u]
 *  -c checkout top is for top of trunk svnPath is the non-default svn path
 *     (e.g. a branch).
 *  -u svn up, expected to be cd'ed to the working copy you want to update.
 *
 * created: Aug 28, 2009
 * @version "$Id:  $"
 */

$Tot = 'svn co https://fossology.svn.sourceforge.net/svnroot/fossology/trunk/fossology';

$options = getopt('hc:u');

$Usage = "$argv[0] [-h] [-c {top | svnPath}] [-u]\n";

if(empty($options)) {
  print $Usage;
  exit(1);
}

if(array_key_exists('h',$options)) {
  print $Usage;
  exit(0);
}
if(array_key_exists('c',$options)) {
  $Opt = $options['c'];
  $coOpt = strtolower($Opt);
  if($coOpt == 'top') {
    $last = exec($Tot, $output, $rtn);
    print "checkout results are, last and output:$last\n";
    print_r($output) . "\n";
    if ($rtn != 0) {
      print "ERROR! Could not check out FOSSology sources at\n$Tot\n";
      exit(1);
    }
  }
  else {
    $last = exec($coOpt, $output, $rtn);
    print "checkout results are, last and output:$last\n";
    print_r($output) . "\n";
    if ($rtn != 0) {
      print "ERROR! Could not check out FOSSology sources at\n$coOpt\n";
      exit(1);
    }
  }
}

if(array_key_exists('u',$options)) {
  $svnUp = 'svn up';
  $last = exec($svnUp, $output, $rtn);
  print "svn up results are, last and output:$last\n";
  print_r($output) . "\n";
  if ($rtn != 0) {
    $dir = getcwd();
    print "ERROR! Could not svn up FOSSology sources at $dir\n";
    exit(1);
  }
}


?>