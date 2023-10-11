#!/usr/bin/php
<?php
/*
 diffm.php
 SPDX-FileCopyrightText: © 2007 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * diffm: determine if there is a difference between top 1000 Freshmeat
 * project rdf files.
 *
 * The top 1000 rfd files are expected to be extracted from the larger
 * Freshmeat rdf's obtained from the FM site. The general use is for consecutive
 * days to be diff'ed.  If the files are drastically different, then the upload
 * size will approach 1000 projects.
 *
 * See mktop1k to create a top1000 Freshmeat file from the master rdf file.
 *
 * @param string $file1 path to top1000 fm projects (XML file)
 * @param string $file2 path to top1000 fm projects (XML file)
 * @param string $opath optional directory path to place to output files.
 *        default is to use the current directory.
 *
 * diffm produces two output files:
 * FM-projects2update is a list of Freshmeat projects that need updating.
 * Update.fm.rdf is an XML file with the Freshmeat entries that need updating.  This
 * file can be processed by get-projects to get the projects and to create a load file.
 * Use cp2foss to load the projects into the fossology DB, using the inputfile created
 * by get-projects.
 *
 * If there are no differences found, diffm returns 0 and the above files
 * are removed.
 *
 * @return exit status of 0 for no differences found, > 0 for differences
 * found.
 *
 * @author mark.donohoe@hp.com
 * @version $Id: diffm.php 1593 2008-10-30 10:09:41Z taggart $
 *
 */
/*

/*
* This program works by extracting the project short name and the latest
* release version from an rdf and storing them in arrays.  The arrays are
* sorted and then diff'ed. Any differences in the latest release version
* cause that project to be added to the list of projects that need updating.
* The associated xml entries with that project are also added to the xml
* file that will be used by get-projects to retrieve them from the net.
*
*/
require_once("FIXMETOBERELATIVE/pathinclude.php");
require_once("$LIBDIR/lib_projxml.h.php");

$usage = <<< USAGE
Usage: diffm [-h] -f <file1> <file2> [-o <dir-path>]
   Where <file1> path to an uncompressed top1000 Freshmeat rdf XML file
         <file2> path to an uncompressed top1000 Freshmeat rdf XML file

         For the differences to be found as expected file1 should be the newer
         file.  E.g. f1.2008-1-14 f2.2008-1-13.

         <dir-path> fully qualified path where output files will be placed.
         If no -o option given, the cwd is used for the output files.

         Output files are: FM-projects2update and Update.fm.rdf

   See mktop1k to create a top1000 Freshmeat file from the master rdf file.

USAGE;

for ($i = 1; $i < $argc; $i++) {
  switch ($argv[$i]) {
    case '-f':
      $i++;
      $dash_f = true;
      if ((isset($argv[$i])) and (isset($argv[$i+1]))){
        $in_file1 = $argv[$i];
        $i++;
        $in_file2 = $argv[$i];
        //pdbg("DIFFM: input files are 1->$in_file1\n2->$in_file2");
      }
      else {
        echo("ERROR: Must specify 2 uncompressed filenames after -f");
        echo $usage;
        exit(1);
      }
      // is the second argument start with a dash?  if so, wasn't given
      // the correct args.
      if(eregi('^-+', $in_file2)) {
        echo("ERROR: Must specify 2 uncompressed filenames after -f");
        echo $usage;
        exit(1);
      }
      break;
    case '-o':
      $i++;
      if (isset($argv[$i])) {
        $dir_path = $argv[$i];
      }
      else {
        die("ERROR: Must specify fully qualified directory path after -o");
      }
      break;
    case '-h':
      echo $usage;
      exit(0);
      break;
    default:
      die("ERROR: Unknown argument: $argv[$i]\n$usage");
      break;
  }
}

// -f is a required parameter
if(!$dash_f){
  echo "ERROR: -f is a required parameter\n";
  echo $usage;
  exit(-1);
}
// Test for existence then size....
if (false == file_exists($in_file1)) {
  echo "Error: $in_file1 does not exist\n";
  echo $usage;
  exit(-1);
}
if (false == file_exists($in_file2)) {
  echo "Error: $in_file2 does not exist\n";
  echo $usage;
  exit(-1);
}

if (0 == $size = filesize($in_file1)){
  echo "Error, file $in_file1 is empty\n";
  exit(1);
}
if (0 == $size = filesize($in_file2)){
  echo "Error, file $in_file2 is empty\n";
  exit(-1);
}
// open the files and get a project_name and version from each.

$F1 = fopen($in_file1, 'r') or die("Can't open: $in_file1 $php_errormsg\n");
$F2 = fopen($in_file2, 'r') or die("Can't open: $in_file2 $php_errormsg\n");

echo "Comparing the following files:\n$in_file1\n$in_file2\n\n";

// files are opened outside of the loop, wastful, if nothing to save, but
// this way, won't end up opening them multiple times in the loop.
$dstamp = date('Y-m-d');
$projs2update = $dir_path . 'FM-projects2update.' . $dstamp;
$xml_changes =  $dir_path . 'Update.fm.rdf.' . $dstamp;
//pdbg("output file names are: $projs2update $xml_changes");
$P2up = fopen($projs2update, 'w') or die("Can't open: $php_errormsg\n");
$Cxml = fopen($xml_changes, 'w') or die("Can't open: $php_errormsg\n");

$diffs_found = 0;

/*
 * diff the lists, size of array is number of diffs found.
 * foreach item in the diff list (array), look for that project in the xml file
 * and get the xml for that project, write it to a file.
 */

$list1 = mklists($in_file1);
$list2 = mklists($in_file2);

ksort($list1);
ksort($list2);

$adiffs = array_diff_assoc($list1, $list2);
//pdbg("diffs are:",$adiffs);

// If no diffs, exit 0, nothing to do, remove files and stop
$diffs_found = count($adiffs);
if ($diffs_found == 0){
  echo "NOTE: No differences were found. \n";
  $junk = exec("rm -f $projs2update $xml_changes", $dummy, $rtn);
  if($rtn != 0){
    echo "cound not remove files $projs2update\nand\n$xml_changes\n";
    echo "Please remove them manually";
  }
  exit($diffs_found);        // should be 0
}
// differences found, save them up in the files.  We only save from
// file 1 as that is the newest file.
write_hdr($Cxml);
while( false != ($f1_line = fgets($F1, 1024))) {

  $proj1 = array();
  if (preg_match('/<project>/', $f1_line)) {
    $m1 = ftell($F1);
    $proj1 = get_entry($F1, $m1);
    //pdbg("DIFFM: P1 Entries:",$proj1);
  }
  else {
    continue;
  }
  // we now have a project, is it one of the ones that need updating?
  // If so, save it, and record the project name and version.
  $proj_name = xtract($proj1[4]);
  foreach($adiffs as $name => $version){
    if ($proj_name == $name){
      //pdbg("Found $name, saving");
      $Yupdate = "$name " . "has a new version:" . " $version\n";
      save_Yupdated($P2up, $Yupdate);
      //pdbg("\$proj1 is:", $proj1);
      write_pxml($Cxml, $proj1);
      break;
    }
  }
}
// must write the closing tag before closing the xml file
close_tag($Cxml);
fclose($P2up);
fclose($Cxml);

if($diffs_found > 0){
  echo
 "$diffs_found differences were found.  Please consult the following files:\n";
  echo "$projs2update and $xml_changes\n";
  exit($diffs_found);
}

  /**
   * mklists make a list (array) of the passed in xml file
   *
   * The list is an associative array with the project name as the key and
   * the latest version as the value.
   *
   * @param string $xml_file path to XML file to parse
   * @return array $p associative array with name as key and latest version
   * as the value.
   */
  function mklists($xml_file){
    // Make an array out of the passed in xml file.
    // returns array.
    $pdoc1= simplexml_load_file("$xml_file");

    $projects = $pdoc1->xpath('/project-listing/project');

    foreach($projects as $proj){
      list($pname_short) = $proj->xpath('projectname_short');
      //    echo "\$pname_short:$pname_short\n";
      list($lrv) = $proj->xpath('latest_release/latest_release_version');
      $p["$pname_short"] = "$lrv";
    }
    return($p);
  }
