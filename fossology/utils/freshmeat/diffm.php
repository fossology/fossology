#!/usr/bin/php
<?php
/***********************************************************
 diffm.php
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
 * @todo add a parameter to specify where to place the output files... just drops them
 *        where diffm is running...tsk, tsk.
 * @todo make the output files have a date that is compatible with the shell script format: y-m-d
 *
 * @author mark.donohoe@hp.com
 * @version $Id: diffm.php 1558 2007-12-11 00:14:55Z markd $
 *
 */
/*

/*
* This program works by isolating one project in each xml file at a time.
* Projects are delimited by the xml tag <project> </project>.
* The projects are stored in arrays and then the arrays are compared for differences.
* The differences are then examined to determine if any of them are of interest.
* Any differences that we care about cause that project to be added to the list
* of projects that need updating.  The associated xml entries with that project are also
* added to the xml file that will be used by get-projects to retrieve them from the net.
*/

$LIBDIR = '/usr/local/lib';
require_once("pathinclude.h.php");
require_once("$LIBDIR/lib_projxml.h.php");
#require_once("./lib_projxml.h.php");            // dev copy


$usage = <<< USAGE
Usage: diffm -f <file1> <file2> [-o <dir-path>]
   Where <file1> path to an uncompressed top1000 Freshmeat rdf XML file
         <file2> path to an uncompressed top1000 Freshmeat rdf XML file
         
         <dir-path> fully qualified path where output files will be placed.
         If no -o option given, the cwd is used for the output files.
         
         Output files are: FM-projects2update and Update.fm.rdf

   See mktop1k to create a top1000 Freshmeat file from the master rdf file.

USAGE;

if ($argc < 3) {          // program name and two parameters.
  echo $usage;
  exit(1);
}

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
// open the files and get a project from each.  if there are any differences
// add the project name to the results.

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
// This will always create an xml output file, but if there are no 
// differences, the file is removed later, so no harm.

write_hdr($Cxml);
while( (false != ($f1_line = fgets($F1, 1024))) and
(false != ($f2_line = fgets($F2, 1024))))
{

  $proj1 = array();
  $proj2 = array();

  if (preg_match('/<project>/', $f1_line)) {
    $m1 = ftell($F1);
    $proj1 = get_entry($F1, $m1);
    //pdbg("DIFFM: P1 Entries:",$proj1);
  }
  if (preg_match('/<project>/', $f2_line)) {
    $m2 = ftell($F2);
    $proj2 = get_entry($F2, $m2);
    //pdbg("DIFFM: P2 Entries",$proj2);
  }

  // This is a sanity check to make sure the diffs are not way out of
  // wack.  If we are always comparing different projects, then something
  // is broke... should never see warnings in the output.
  if ($proj1[1] != $proj2[1]){
    if ($proj1[4] != $proj2[4]){
      echo "Warning! project Names do not match\n";
      echo "Current->{$proj1[4]}, Previous->{$proj2[4]}\n";
    }
    echo "Warning! project id's do not match\n";
    echo "Current->{$proj1[1]}, Previous->{$proj2[1]}\n";
  }
  
  $diffs = array_diff($proj1, $proj2);
  //pdbg("DIFFM: Diffs found:",$diffs);
   /*
   * We only care if we find differences for the following tags:
   * popularity_rank
   * date_updated
   * latest_release_version
   */
  $soma = array_filter($diffs, "inthere");
  //pdbg("DIFFM: After filter:\$soma is:",$soma);
  $matched = count($soma);
  if($matched) {
    //pdbg("found change, saving data");
    $diffs_found++;
    $proj_name = xtract($proj1[4]);
    $reason = array_shift($soma);
    rtrim($reason);
    $Yupdate = $proj_name . $reason;
    save_Yupdated($P2up, $Yupdate);
    write_pxml($Cxml, $proj1);
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
else {
  echo "NOTE: No differences were found. \n";
  $junk = exec("rm -f $projs2update $xml_changes", $dummy, $rtn);
  if($rtn != 0){
    echo "cound not remove files $projs2update\nand\n$xml_changes\n";
    echo "Please remove them manually";
  }
  exit($diffs_found);        // should be 0
}

/**
 * Function inthere
 *
 * determines if any of the wanted strings are in the value passed to it.
 * used as a call back for array_filter
 *
 * @param string $in_value value from the array
 *
 */
function inthere($in_value){
  // only one of the below needs to be true to update the archive.
  // may not need this function is we only care about version....

	if(preg_match('/<latest_release_version>/', $in_value)){
    //pdbg("Found <latest_release_version.>");
    return($in_value);
  }
  /*
  elseif (preg_match('/<date_updated>/', $in_value)) {
    //pdbg("Found <date_updated>");
    return($in_value);
  }
  elseif (preg_match('/<popularity_rank>/', $in_value)) {
    //pdbg("Found <latest_release_version>");
    return($in_value);
  }
*/
  return(False);
}

?>