<?php
/*
 db_postgres.h.php
 SPDX-FileCopyrightText: © 2007 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * routines that should be useful for programs manipulating Freshmeat
 * XML rdf files or subsets of them.
 *
 * @package lib_projxml.h.php
 *
 * @author mark.donohoe@hp.com
 * @version $Id: lib_projxml.h.php 1558 2007-12-11 00:14:55Z markd $
 *
 */

/**
 * function: write_entry
 * this should be called copy_entry
 *
 * write selected xml from a Freshmeat format xml rdf file to another file.
 *
 * @param int $in_handle open file handle
 * @param int $marker ftell result in the file where the tag <project>
 * was founnd.
 * NOTE: the marker is actually AFTER the tag, but this routine accounts
 * for that.
 * @param int $out_handle open file handle, results written here.
 *
 */

function write_entry($in_handle, $marker, $out_handle){

  // The marker is really set AFTER <project>, so <project> needs to
  // be written.
  $start = fseek($in_handle, $marker);
  fwrite($out_handle, "  <project>\n");
  while( false != ($line = fgets($in_handle, 1024))){
    // </project> is the end tag, write it and return, all done.
    if (preg_match('|</project>|', $line)){
      fwrite($out_handle, $line);
      return;
    }
    fwrite($out_handle, $line);
  }
}

/**
 * function: get_entry
 *
 * get the selected xml project from the Freshmeat rdf file format.
 * this is not the correct way to doc the return... later.
 * Returns an array with the project xml.
 *
 * @param int $in_handle open file handle
 * @param int $marker ftell result in the file where the tag <project>
 * was founnd.
 * NOTE: the marker is actually AFTER the tag, but this routine accounts
 * for that.
 *
 */

function get_entry($in_handle, $marker){

  $project = array();

  // The marker is really set AFTER <project>, so <project> needs to
  // be written.
  $start = fseek($in_handle, $marker);
  $project[] = "  <project>\n";
  while( false != ($line = fgets($in_handle, 1024))){
    // </project> is the end tag, save it and return, all done.
    if (preg_match('|</project>|', $line)){
      array_push(&$project, $line);
      return($project);
    }
    array_push(&$project, $line);
  }
}

/**
 * Function: close_tag
 *
 * Write the xml closing tag e.g. '</project-listing>' for a FM rdf.
 *
 * @param int $handle open file handle to write xml tag to.
 *
 * @author mark.donohoe@hp.com
 *
 */

function close_tag($handle){

  $tag = "</project-listing>\n";
  fwrite($handle, $tag);
  return;
}
/**
 * function: parse_fm_input
 *
 * Parse the freshmeat input and return an array of tokens.
 *
 * @param string $fm_string a string with space seperated tokens.  Some
 * of the tokens will be '...' or "..." with imbeded spaces.
 *
 * @return array $parms array with one token per entry
 *
 */
function parse_fm_input($fm_string){
  $parms = preg_split
  ('/([\'|\"])+?/', $fm_string);
  //("/([\'|\"])/", $fm_string, -1, PREG_SPLIT_DELIM_CAPTURE);
  //("/([0-9]) ([0-9a-zA-Z]) (\'|\")*?/", $fm_string, -1, PREG_SPLIT_DELIM_CAPTURE);
  // the split above leave null entries.... remove them.
  $acnt = count($parms);
  for ($ai=0; $ai<=$acnt; $ai++){
    $len = strlen($parms[$ai]);
    if ($len == 0){
      //echo "unsetting 0 lenght entry\n";
      unset($parms[$ai]);
    }
    elseif (!(isset($parms[$ai]))){
      //echo "value not set, unsetting in parms \$parms[$ai]\n";
      unset($parms[$ai]);
    }
    elseif ((isset($parms[$ai]))){
      if(ereg('^ +', $parms[$ai])){
        //echo "getting rid of space \$parms[$ai]\n";
        unset($parms[$ai]);
      }
    }
  }
  // compact the array so list will work! this is just stupid...
  $lparms = array_values($parms);
  //pdbg("ParseFmI: \$parms is:", $lparms);
  return($lparms);
}

/**
 * function pdbg
 *
 * print a debug message and optionally dump a structure
 *
 * prints the message prepended with a DBG-> as the prefix.   The string
 * will have a new-line added to the end so that the caller does not have
 *  to supply it.
 *
 * @param string $message the debug message to display
 * @param mixed  $dump    if not null, will be printed using print_r.
 *
 * @return void
 *
 */

function pdbg($message, $dump=''){

  $dbg_msg = 'DBG->' . $message . "\n";

  echo $dbg_msg;

  if(isset($dump)){
    //    echo "\$dump is:\n";
    print_r($dump);
    echo "\n";
  }
  return;
}

/**
 * Function: write_hdr
 *
 * Write the xml header for the xml definition of an FM rdf,
 *
 * @param int $handle open file handle to write xml header to.
 * @todo check for a write error and return either true or false
 *
 * @author mark.donohoe@hp.com
 *
 */

function write_hdr($handle){

  $xml_hdr = <<< HDR
<?xml version="1.0" encoding="ISO-8859-1"?>
<!DOCTYPE project-listing SYSTEM "http://freshmeat.net/backend/fm-projects-0.4.dtd">
<project-listing>

HDR;
  //pdbg("WHDR: \$xml_hdr is:$xml_hdr");
  fwrite($handle, $xml_hdr);
  return;
}

/**
 * Function: write_pxml
 *
 * Given an array that holds the xml of a FM project, write it to a file.
 * The array is expected to hold valid xml.  No checking is done.
 *
 * @param int $file_handle open file handle to write the xml to.
 * @param array $pxml array containing the project xml.
 *
 * @author mark.donohoe@hp.com
 *
 */

function write_pxml($file_handle, $pxml){
  //write_hdr($file_handle);
  for ($i=0; $i < count($pxml); $i++){
    fwrite($file_handle, $pxml[$i]);
  }
  return;
}

/**
 * Function: save_Yupdated
 *
 * Save the name of the  FM project, in a file
 *
 * @param int $file_handle open file handle for writing.
 * @param string $Updata string containing the project name.
 *
 * @author mark.donohoe@hp.com
 *
 */

function save_Yupdated($file_handle, $Updata){
  //$Updata .= "\n";
  fwrite($file_handle, $Updata);
  return;
}

/**
 * Function: xtract
 *
 * Returns the value from an xml token that has the following format:
 * <some-tag>value</some-end-tag>
 *
 * If the tag is not of that form, NULL is returned.
 *
 * @todo Fix multiline (desc_full) case.  All else seems to work.
 *       (see test program t.xtract.php).  Note some seem to work!?... hmmmm
 *
 */

function xtract($string){

  $pos = strpos($string, '>');
  $val_start = $pos + 1;
  $val_end = strpos($string, '</', $val_start);
  if(!(is_numeric($val_end))){     // not a valid tag... return null.
    return(NULL);
  }
  $val_len = $val_end - $val_start;
  $value = substr($string, $val_start, $val_len);
  return($value);
}

/**
 * function: read_pfile
 *
 * Reads the input file into a structure and returns the structure sorted
 * by rank. See the internal comments for the format of the structure.
 *
 * The input file is expected to be in the FM rdf format.
 *
 * @param string $xml_file path to xml file in FM rdf format
 * @todo this and get1000 should really be a class with different methods
 *
 * @author mark.donohoe@hp.com
 * @version 1.0
 */

function read_pfile($xml_file) {
  /*
   * Data Structure:
   *
   *     Key          Key          Value(s)
   *     ---          ---          --------
   * project_rank     project_name <zero or more urls to archives>,
   *                               <home_url>, <short-description>,
   *                               version-info (3 tokens).
   *
   */
  $meatdoc= simplexml_load_file("$xml_file");
  #  echo "read_pfile: Read XML file\n";
  $fmprojs = array();
  foreach ($meatdoc->project as $project) {
    $fmprojs["$project->popularity_rank"] ["$project->projectname_short"] =
    array ("$project->url_tgz",
	     "$project->url_bz2",
	     "$project->url_zip",
	     "$project->url_homepage",
	     "$project->desc_short"
    );
    foreach($project->latest_release as $verdata){
      array_push(
      &$fmprojs["$project->popularity_rank"] ["$project->projectname_short"],
      $verdata->latest_release_version,
      $verdata->latest_release_id,
      $verdata->latest_release_date
      );
    }
  }
  ksort($fmprojs);
  return($fmprojs);
}
