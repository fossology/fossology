#!/usr/bin/php
<?php
/***********************************************************
 get-projects.php
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
 *
 * get-projects: Get projects from Freshmeat.com
 *
 * @abstract <kbd>get-projects [-h] -f
 * <fully-qualified uncompressed xml file> </kbd>
 * <br>
 * <br>
 * Using wget get-projects obtains the  following types of archives: *.tgz,
 * .bz2, *.zip.
 * Places the archives in the standard FOSS area in a directory called
 * golden<i>yymmdd</i>, where <i>yy</i> is the year, <i>mm</i> is the month
 * and <i>dd</i> is the day.
 *
 * If none of the above archives exist, the project is skipped and
 * recorded in the file 'skipped_fmprojects' in the output directory.
 *
 * All packages that were successfully obtained are written into the file
 * called Archives-to-Load.
 *
 * @package get-projects
 *
 * @todo Examine every function and return statement, decide if
 * void is ok or need to return true/false.
 * @todo should add in destination directory so it's not hardcoded
 * below.
 * @todo Bonus: start to pass arrays around by ref....
 *
 * @author mark.donohoe@hp.com
 * @version $Id: get-projects.php 1558 2007-12-11 00:14:55Z markd $
 *
 */

/*
 * Defects:
 * 0. No description ever gets used.  Don't try putting it in 
 *    webgoldimport. That doesn't work. This has me confused, it appears
 *    as if it should get put on teh upload file folder, but wgi does that 
 *    and it doesn't take a description.
 * 1. if you don't pass in any parameters, weirdnes... need to check
 *    for that case.
 */
// pathinclude below is dependent on having fossology installed.
require_once "pathinclude.h.php";       // brings in global $VARDATADIR +
require_once("$LIBDIR/lib_projxml.h.php");
//require_once "./lib_projxml.h.php";            // dev copy

$usage = <<< USAGE
Usage: get-projects [-h] -f <file>
   Where <file> is an uncompressed XML file, fully qualified
   -h displays this usage.

USAGE;

$XML_input_file = '';

for ($i = 1; $i < $argc; $i++) {
  switch ($argv[$i]) {
    case '-f':
      $i++;
      if (isset($argv[$i])) {
        $XML_input_file = $argv[$i];
      }
      else {
        die("ERROR: Must specify an uncompressed filename after -f");
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

// convention is to put the trailing / at the end of the dir so everyone else
// doesn't have to worry about it.
// FIX THIS: need to have env file created by install process.

$dest_dir = '/srv/fossology/repository/firenze/Freshmeat/';
// create output directory with the date as part of the name.

$yyyymmdd = date('Y-m-d');
$golden = 'golden.' . "$yyyymmdd" . '/';
$dest_dir .= $golden;
$wget_logs   = $dest_dir . 'wget-logs/';
$log_data    = $dest_dir . 'Logs-Data/';
$input_files = $dest_dir . 'Input-files/';
//pdbg("MAIN: \$wget_logs is:$wget_logs");

// Create output directories. They should not exist
if (! is_dir("$dest_dir")){
  exec("mkdir -p $dest_dir", $dummy, $rval);
  if ($rval != 0) {
    echo "ERROR: can't create output directory: $dest_dir";
    exit(1);
  }
}
if (! is_dir($wget_logs)){
  exec("mkdir -p $wget_logs", $dummy, $rval);
  if ($rval != 0) {
    echo "ERROR: can't create output directory: $wget_logs\n";
    exit(1);
  }
}
if (! is_dir($log_data)){
  exec("mkdir -p $log_data", $dummy, $rval);
  if ($rval != 0) {
    echo "ERROR: can't create output directory: $log_data\n";
    exit(1);
  }
}
if (! is_dir($input_files)){
  exec("mkdir -p $input_files", $dummy, $rval);
  if ($rval != 0) {
    echo "ERROR: can't create output directory: $input_files\n";
    exit(1);
  }
}

// make sure we have some sort of valid input (e.g. gp -f)
if (is_null($XML_input_file)){
  echo "Error: null input file\n";
  echo $usage;
  exit(1);
}

// simplexml.... can't deal with a compressed file, make sure it's not.
// possible enhancement is to uncompress the file if passed one....
// Note that the code below still may not catch all of them due to no
// standard naming convention.
$last = strrchr($XML_input_file, ".");
switch($last ) {
  case '.gz':
    echo $usage;
    exit(1);
    break;
  case '.bz2':
    echo $usage;
    exit(1);
    break;
  case '.zip':
    echo $usage;
    exit(1);
    break;
}

echo "Processing Xml file $XML_input_file\n";

// parse the xml file and build the data structure.  read_pfile returns
// the data struncture sorted (asending).
$fm_projects = array();
$fm_projects = read_pfile($XML_input_file);

// Look for projects without any of the 3 archives.  Log any found into
// skipped_fmprojects file and remove it from the fm_projects array.

$projects_skipped = 0;
foreach($fm_projects as $rank => $key){
  foreach ($key as $name => $values){
    list(
    $url_tgz,
    $url_bz2,
    $url_zip,
    $homepage,
    $short_desc,
    $release_version,
    $release_version_id,
    $release_version_date
    ) = $values;
    #      echo "We got:NAME:$name\nTG:$url_tgz\nBZ:$url_bz2\nZ:$url_zip\nHM:$homepage\nDesc:$short_desc\nRV:$release_version\nVID:$release_version_id\nVD:$release_version_date\n\n";

  }
  if (($url_tgz == "") and ($url_bz2 == "") and ($url_zip == "")) {
    $NoUrls = fopen("{$log_data}skipped_fmprojects", 'w') or
    die("Can't open: $php_errormsg");
    if (-1 ==
    fwrite($NoUrls, "$rank $name $homepage $release_version\n")){
      die("Can't write: $php_errormsg");
    }
    $projects_skipped++;
    unset($fm_projects["$rank"]);
    fclose($NoUrls);
  }
}

/*
 * At this point the array should only have the
 * (fm_projects - skipped projects). The working list will have AT LEAST
 * 1 archive.  Go get it.
 * wget_url is called synchonisly(sp) since we only get 1 package and
 * we need to know if what wget return status and what it got us.
 */

$skipped_uploads = array();
$uploads = array();
$mode = 's';
$uploads_scheduled = 0;
foreach ($fm_projects as $pkg_rank => $nkey){
  foreach ($nkey as $pkg_name => $pkg_data){
    // unpack the data so the code is easier to read
    list(
    $tgz_url,
    $bz2_url,
    $zip_url,
    $homepg,
    $short_desc,
    $ver,
    $ver_id,
    $ver_date
    ) = $pkg_data;

    // Repackage the common data needed by all archives and wget_url
    $common_data = array (
    $short_desc,
    $ver,
    $ver_id,
    $ver_date
    );
    // Set up the mode for wget_url
    $gzip  = '.gz';
    $bzip2 = '.bz2';
    $zip1  = '.zip';
    // Select the archives in the following order: .gz, .bz2, .zip
    // There should be at least one of them.
    echo "Trying project #$pkg_rank $pkg_name at:\n";
    if ($tgz_url != "") {
      $cnt = array_unshift($common_data,$tgz_url);
      $tupload = wget_url($pkg_rank, $pkg_name, $gzip, $common_data, $mode);
    }
    elseif ($bz2_url != "") {
      $cnt = array_unshift($common_data,$bz2_url);
      $tupload = wget_url($pkg_rank, $pkg_name, $bzip2, $common_data, $mode);
    }
    elseif ($zip_url != "") {
      $cnt = array_unshift($common_data,$zip_url);
      $tupload = wget_url($pkg_rank, $pkg_name, $zip1, $common_data, $mode);
    }
    //pdbg("MAIN: \$tupload is:",$tupload);
    if(is_null($tupload['N'])){
      echo "Warning! There may have been an undetected error in the wget of $pkg_name\n";
      echo "Check the wget logs in $wget_logs\n";
    }
    if(!(is_null($tupload['C']))){
      $uploads[] = $tupload['C'];
      $uploads_scheduled++;
      echo "#$pkg_rank $pkg_name will be scheduled for an upload\n";
    }
    elseif(!(is_null($tupload['U']))){
      echo "WARNING! did not get a compressed archive from wget\n";
      echo "Will Not upload $pkg_name\n";
      $skipped_uploads[] = $tupload['U'];
      echo "\n-----\n";        // eye-candy, seperates packages in the output
      continue;
    }
    echo "\n-----\n";
  }
}

// save the skipped uploads in a file
echo "Saving skipped uploads (downloaded files that were not compressed)\n";
//pdbg("Skipped uploads is:",$skipped_uploads);
$SUP = fopen("$log_data/skipped_uploads", 'w')
or die("Can't open $log_data/skipped_uploads, $php_errormsg\n");
$skipped_up = count($skipped_uploads);
echo 
"There were $skipped_up skipped uploads, see $log_data/skipped_uploads for details\n";

foreach($skipped_uploads as $skipped){
  fwrite($SUP, "$skipped\n")
  or die("Can't write to $log_data/skipped_uploads, $php_errormsg\n");
}
//pdbg("Main: \$uploads is:\n", $uploads);
// at this point we have done the wgets and made a list of all the ones
// that succeeded.  Now process that list into an input file for cp2foss
// as it will do the actual upload.

create_cp2foss_ifile($uploads, "{$input_files}Freshmeat_to_Upload");

/* Report results */
report($log_data);

// end of Main....

/**
 * function: create_cp2foss_ifile
 *
 * @abstract Create an input file suitable for cp2foss
 *
 * Create the file by using the passed in array and filename.
 *
 * @param array $uploads items to upload into the db
 * @param string $filename filename to write the cp2foss input.
 *
 * @return true/false (die's as well)
 * @author mark.donohoe@hp.com
 *
 */

function create_cp2foss_ifile($uploads, $filename){

  $UPLOAD = fopen($filename, 'w') or
  die("ERROR: can't open $filename, $php_errormsg\n");
  $upload_count = count($uploads);
  for ($uc=0; $uc<$upload_count; $uc++){
    $parms = parse_fm_input($uploads[$uc]);
    //pdbg("CP2iFile: \$parms is:\n",$parms);
    list (
    $rank,
    $name,
    $archive_path,
    $description,
    $version,
    $version_id,
    $version_date
    ) = $parms;
    // don't write an entry that has no archive path (wget either returned
    // an error or a file that was not a compressed archive).
    if(!(isset($archive_path))){
      pdbg("skipping $name");
      continue;
    }
    //dbg("CCP2iF:R:$rank N:$name\nA:$archive_path\nD:$description V:$version, VID:$version_id $VD:$version_date\n");
    $folder_path = '-p Freshmeat';
    $alpha       = '-A';
    $name = "-n '$name-$version'";
    // For now we are going to put the -A at the end to work around a defect in cp2foss.
    $cp2foss_input = "$folder_path $name -a $archive_path -d '$description' $alpha\n";
    //pdbg("Would write the following to the file:", $cp2foss_input);
    fwrite($UPLOAD, $cp2foss_input) or
    die("Errors: can't write $php_error_msg\n");
  }
  fclose($UPLOAD);
  return;
}
/**
 * function: report
 *
 * Determine how many wgets failed and how may projects where skipped.
 * Write results to STDOUT
 *
 * @param string $output_dir $VARDATADIR/goldenyymmdd/
 *
 * output_dir is where the wget logs and the skipped file is
 */

function report($output_dir){

  global $wget_logs;
  global $projects_skipped;
  global $uploads_scheduled;
  $cwd = '.';
  $parentdir = '..';
  $skipped_path = "{$output_dir}skipped_fmprojects";

  $WGLOGS = opendir($wget_logs)
  or die("Can't open: $wget_logs $php_errormsg\n");
  // the code below seems to work.... but the book shows a much different
  // format
  // while (false != ($dir_entry = readdir($TMP))) {
  // ask bob...

  // Save the rank and the name of the project in the file failed-wgets

  $dead_gets = 0;
  while ($dir_entry = readdir($WGLOGS)){
    if ($dir_entry == $cwd || $dir_entry == $parentdir) {
      //pdbg("skipping $dir_entry");
      continue;
    }
    if (eregi('log\..+\...', $dir_entry)){
      $path = "$wget_logs" . "$dir_entry";
      //pdbg("REP: Matched, \$path is:$path");
      $file_contents = file_get_contents($path);
      if (eregi('Downloaded: 0 bytes in 0 files', $file_contents)){
        echo "Found failure, saving $dir_entry\n";
        $dead_gets++;
        save_name($dir_entry, $output_dir);
        continue;
      }
      elseif (eregi('failed: Connection refused', $file_contents)){
        echo "Found failure, saving $dir_entry\n";
        $dead_gets++;
        save_name($dir_entry, $output_dir);
        continue;
      }
    }
  }
  closedir($WGLOGS);

  if ($dead_gets){
    echo "\nFailures Analyized and saved in file failed-wgets\n";
    printf("There were %d wgets that failed\n", $dead_gets);
  }
  else {
    printf("%d wgets failed\n", $dead_gets);
  }
  if ($uploads_scheduled){
    printf("There were %d projects scheduled for uploading\nSee the {$output_dir}Freshmeat_to_Upload file for details\n", $uploads_scheduled);
  }
  else{
    printf("There were %d projects scheduled for uploading\nSee the $output_dir for details\n", $uploads_scheduled);
  }
  if ($projects_skipped){
    printf(
    "There were %d skipped projects for this run\nSee the {$output_dir}skipped_fmprojects file for details\n", $projects_skipped);
  }
  else{
    printf("There were %d skipped projects for this run\n", $projects_skipped);
  }

  return;
}
/**
 * function: save_name
 *
 * save_name: save information in the file <i>failed_wgets</i> for retry.
 * The file is saved in $VARDATADIR/golden<i>yymdd</i>
 *
 * Save the package name, rank and archive type of a failed wget.
 * The package name, rank and archive type are determined by taking apart
 * the passed in log file name which is expected to be in the form
 * '/tmp/meat.log.<pkg-name>-rank.[.gz|.bz2|.zip]'
 *
 * @param string $filename logfile-name in /tmp
 * @param string $failed_path $VARDATADIR/golden<i>yymmdd</i>/
 */

function save_name($filename, $failed_path) {

  $FWG = fopen("{$failed_path}failed_wgets", 'a') or
  die("can't open: $php_errormsg");

  // grab name, rank and archive type
  $nra = substr($filename, 9);
  $wheredot = strpos($nra, '.');

  // strip archive type off, archive type will include the '.'
  $ark_type = strrchr($nra, '.');

  // Split the name into name and rank and write 'rank name ark_type'
  // to the failed_wgets file for retry processing.

  $name_rank = substr($nra, 0, $wheredot);
  $rank_position = strrpos($name_rank, '-');
  $name = substr($name_rank, 0, $rank_position);
  $rank = substr($name_rank, $rank_position + 1);
  fwrite($FWG, "$rank" . " " . "$name" . "$ark_type" . "\n") or
  die("Can't write to: $php_errormsg");

  fclose($FWG);
  return;
}

/**
 * function: wget_url
 *
 * wget_url: This function does the call to wget and uses the helper function
 * _getfmpath to get the location of the downloaded archive.  Failed
 * wgets's are logged in the file ...golden<data>/Logs-Data/failed-wgets
 *
 * @param string $project_rank 1->1000 *
 * @param string $project_name short name of the project
 * @param string $ark_type archive type [.gz | .bz2 | .zip ]
 * @param array  $proj_data contains url, description, and version info.
 * @param string $mode a | s
 *
 * @return string $upload upload data from a successful wget or NULL
 *
 */

function wget_url($project_rank, $project_name, $ark_type, $proj_data, $mode){

  // NOTE: quite a few of the urls that are supposed to point to an archive
  // really end up just depositing a file in various forms:
  // *.html, *.cgi showfiles.php?xxxxxx, etc....
  //
  global $wget_logs;
  global $log_data;
  global $dest_dir;

  list($url,
  $short_desc,
  $ver,
  $ver_id,
  $ver_date
  ) = $proj_data;
  //pdbg("WGET: \$proj_data is:",$proj_data);

  $log_path = "$wget_logs" . "log.$project_name-" . "$project_rank$ark_type";
  //pdbg("WGET: \$log_path is:$log_path");
  // Use this proxy when fetching from inside the firewall.
  $proxy = "export http_proxy='web-proxy.fc.hp.com:8088'; ";
  $wCmd .= "$proxy" . "wget -P $dest_dir -o $log_path $url ";

  if ($mode == 'a'){
    echo "$url\n";
    $wCmd .= ' &';
    $lastline = system("$wCmd", $retval);
  }

  if ($mode == 's'){
    echo "$url\n";
    // set these to null, so the caller knows which one got set.
    $upload['C'] = NULL;
    $upload['N'] = NULL;
    $upload['U'] = NULL;
    //pdbg("_WGPKG:$url");
    //pdbg("_WGPKG: \$wCmd is:\n$wCmd");
    exec("$wCmd", $dummy, $retval);
    //pdbg("Wget return code is:$retval");
    if ($retval != 0){
      $WGF = fopen ("{$log_data}failed-wgets", 'a') or
      die("Can't open: $php_errormsg\n");
      if (-1 == fwrite($WGF, "$pk_rank $name $url\n")) {
        die("Can't write: $php_errormsg");
      }
    }
    // wget can return a 0 (zero) exit status with 404 type errors, see
    // _getfmpath below.  So we check here if $archive_path is null
    // if null, it's a failed wget, return null to indicate that.
    //
    elseif ($retval == 0){
      $archive_path = _getfmpath($log_path);
      if (is_null($archive_path)){
        echo "Warning! returning NULL for an archive path\n";
        return($upload);
      }
      // wget appears to have worked, now what type of file got downloaded?
      // For now we will only process compressed archives, the rest of
      // the files are usually a download of their front page, which
      // is useless to upload.
      $type = exec("file -b $archive_path", $dummy , $ret_val);
      //pdbg("WGURL: For file:\n$archive_path\nTYPE IS:$type");
      //pdbg("\$archive path is:$archive_path");
      if (ereg('compressed data', $type)){
        $upload['C'] =
      "'$project_rank' '$project_name' '$archive_path' '$short_desc' '$ver' '$ver_id' '$ver_date'";
        $upload['N'] = True;
      }
      else{
        $upload['U'] =
      "'$project_name' '$archive_path'";
        $upload['N'] = True;
      }
    }
  }
  // close the file? (Suceeded and WGF), or is it faster to leave open?
  return($upload);
}
/**
 * function: _getfmpath
 *
 * getfmpath - helper routine: deal with wget oddities and return
 * pathname to downloaded archive.
 *
 * wget returns 0 status when there are 40x/50x errros.
 * getfmpath checks for these in the output log and returns null if
 * found.  If non of those types of errors are found, the
 * the pathname for the downloaded archive is extracted from the wget log.
 *
 * @param string $path path to the wget log (e.g. /tmp).
 * @return $path_wanted or null
 * @author mark.donohoe@hp.com
 *
 */

function _getfmpath($path){

  // The Freshmeat rdf uses a fake url and archive name so we need to get
  // the path name of the downloaded archive by looking in the wget
  // log file.

  $path_wanted = NULL;
  $contents = file($path);
  $size = count($contents);
  $stat_line = $contents[$size-2];
  if(ereg('^Removed ',$stat_line)){
    // adjust for a different case, if wget downloads a .listing file
    // it adjusts it be an index.html file instead.
    $stat_line = $contents[$size-1];
  }
  //pdbg("_GFMP: Stat line is:\n$stat_line");
  // We shouldn't find errors like this in the file, wget is supposed to
  // have returned with 0 status.
  if (ereg('ERROR 404:', $stat_line)){
    echo "ERROR 404 found in file $dir_entry\n";
    echo "Line was:\n$stat_line\n";
    return($path_wanted);
  }
  elseif (ereg('ERROR 502:', $stat_line)){
    echo "ERROR 502 found in file $dir_entry\n$stat_line\n";
    echo "Line was:\n$stat_line\n";
    return($path_wanted);
  }
  elseif (ereg('ERROR 503:', $stat_line)){
    echo "ERROR 503 found in file $dir_entry\n$stat_line\n";
    echo "Line was:\n$stat_line\n";
    return($path_wanted);
  }
  elseif (ereg('ERROR 400:', $stat_line)){
    echo "ERROR 400 found in file $dir_entry\n$stat_line\n";
    echo "Line was:\n$stat_line\n";
    return($path_wanted);
  }
  elseif (ereg('--no-check-certificate', $stat_line)){
    echo
	"ERROR Secure connect to sourceforge.net needed: in file $dir_entry\n";
    echo "Line was:\n$stat_line\n";
    return($path_wanted);
  }

  
  $chunks = explode(' ', $stat_line);
  //pdbg("_GFMP: Path Wanted:\n{$chunks[4]}");
  // Strip the ` off the front
  $stmp = ltrim($chunks[4], '`');
  //pdbg("_GFMP: stmp:$stmp");
  $path_wanted = rtrim($stmp, '\'');
  //pdbg("_GFMP: path_wanted:$path_wanted");

  return($path_wanted);
}

?>
