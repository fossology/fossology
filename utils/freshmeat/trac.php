#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Trac a list of projects
 *
 * @param string $infile the path to the input file of projects to
 * track.
 * @param string $rdfile the path to the uncompressed Freshmeat rdf
 * file.
 *
 * @return
 *
 * @version "$Id: trac.php 1473 2008-10-07 21:12:49Z rrando $"
 *
 * Status: now working, need to adjust names for easier parseing from
 * the file created.  e.g. some names have spaces and other have things
 * like eclipse-plugin: foobar  bla!
 *
 * Defect: Does it detect not found in FM RDF?
 *
 * Defect: if proxy needs to be set, this thing just sits there...no
 * error message or nothing....fix this!
 *
 * Created on Jun 6, 2008
 */

require_once ('Classes/GetFreshmeatRdf.php');
require_once ('Classes/FreshmeatRdfs.php');
require_once ('Classes/ReadInputFile.php');

/*
 * format for input file is 1 project per line, # for comments, blank
 * lines OK. (make a class to do this!)
 */
// open/ & get a line from input file
// open fm rdf and get that data
// find each input in the rdf file (or not)

/*
 * record the results in a file (space seperated)
 * 1. the package name
 * 2. the url
 * 3. the version
 * 4. description
 * 5. ??
 */

$usage = "trac [-h] -i input-file [-o path-to-output] -r path-to-rdf-file\n";
$options = getopt("hi:o:r:");
//print_r($options);
if (empty ($options))
{
  echo $usage;
  exit (1);
}

if (array_key_exists("h", $options))
{
  echo $usage;
  exit (0);
}

if (array_key_exists("i", $options))
{
  $in_file = $options['i'];
} else
{
  print "ERROR, -i is a required parameter\n";
  exit (1);
}

if (array_key_exists("o", $options))
{
  $in_file = $options['o'];
} else
{
  // default
  $out_file = 'STDOUT';
}

if (array_key_exists("r", $options))
{
  $rdf_file = $options['r'];
} else
{
  print "ERROR, -r is a required parameter\n";
  exit (1);
}

// Should still check $in_file and $rdf_file

$INF = new ReadInputFile($in_file);

$gRdf = new GetFreshMeatRdf('');

$gRdf->get_rdf($gRdf->rdf_name);
if ($gRdf->error_code != 0)
{
  print "ERROR getting the Freshmeat RDF file\n";
  print "ERROR code was:$gRdf->error_code\n";
  print "command output was:";
  print_r($gRdf->error_out);
}

$FRdf = new FreshMeatRdfs($gRdf->rdf_name);

if (!$FRdf->Uncompress($gRdf->rdf_name))
{
  print "Could not uncompress the file $gRdf->rdf_name\n";
  print "return code from uncompress:$FRdf->error_code\n";
  print "Output from uncompress:$FRdf->error_out\n";
}

$FMprojects = $FRdf->XtractProjInfo($FRdf->uncompressed_file);

//print "We got the following from the rdf\n";
//var_dump($FMprojects);

print "starting read and search\n";
while ($line = $INF->GetLine($INF->file_resource))
{
  // Convert to lower case, as FM does not capitalize....

  $lc_proj = strtolower($line);
  //print "DB-TRAC: Looking for $lc_proj\n";
  $found_it = $FRdf->FindInProjInfo($lc_proj, $FMprojects);
  //print "DB: TRAC: found_it is:\n";
  //var_dump($found_it);
  if (!is_null($found_it))
  {
    //print "Found a match in Freshmeat: $found_it\n";
    $found["$lc_proj"] = $found_it;
  }
}
//print "DB: TRAC: After while: found in FM is:\n";
//var_dump($found);

/*
 * at this point, need to determine if there is something to get, then
 * write the results to a file (even if there is nothing to get)
 *
 * Additionally, some of the url's may point to something that doesn't
 * really download.  Need to explore cURL.
 */
print "DB: Looking for valid download urls\n";
$projects = get_proj_url($found);

$PF = fopen('ol-projects-in-FM', 'w') or die("Can't open file, $php_errormsg\n");
foreach($projects as $line)
{
//  print "line is:$line\n";
  if(fputcsv($PF, $line) === false)
  {
    print "ERROR: can't write $line\n";
  }
}
fclose($PF);
// for now just open the file and try to parse

$PL = fopen('ol-projects-in-FM', 'r') or die("Can't open file, $php_errormsg\n");
while ($tokens = fgetcsv($PL, 1024))
{
  print "tokens is:\n";
  var_dump($tokens);
}


/**
 * function get_proj_url
 *
 * Given an array of project names and possible url's' pick a url to
 * use.  Creates an array of strings.  The string is
 * project-name url version
 *
 * @param array $pdata array of projects and urls. see Class
 * FreshmeatRdfs::XtractProjInfo for the format of the array.
 *
 * @return array $projs array of arrays.
 */
function get_proj_url($pdata)
{
  $url = NULL;
  foreach ($pdata as $proj_name=>$aindex)
  {
    foreach ($aindex as $value)
    {

      if (empty ($value))
      {
        continue;
      }
      /* test in this order, our preference is bz2, tgz then zip */
      else
      {
        //print "inner loop, else: proj_name is:$proj_name\n";
        //print "inner loop, else: value is:\n$value\n";
        if(preg_match('/[0-9.]/', $value))
        {
          //print "*****DB:GPU: setting version*****\n";
          $version = $value;
        }
        /* have we already pick a url?  if so, skip */
        /*if(!is_null($url))
        {
          print "DB:GPU: URL is NULL, Skipping to next entry\n";
          continue;
        }
        */
        if (preg_match('/\/url_zip\/$/', $value))
        {
          //print "DB:GPU: matched zip, value is:$value\n";
          $url = $value;
        }
        elseif (preg_match('/\/url_tgz\/$/', $value))
        {
          //print "DB:GPU: matched tgz, value is:$value\n";
          $url = $value;
        }
        elseif (preg_match('/\/url_bz2\/$/', $value))
        {
          //print "DB:GPU: matched bz2, value is:$value\n";
          $url = $value;
        }
        else
        {
          print "DB:GPU: Testing URL for NULL\n";
          if(is_null($url))
          {
           print "DB:GPU: Setting URL is NULL\n";
           $url = 'NO URL FOR THIS PROJECT';
          }
        }
      }
    }

    //print "DB: GPU: pname url, version are:\n$proj_name\n$url\n$version\n";
    $proj_data[0] = $proj_name;
    $proj_data[1] = $url;
    $proj_data[2] = $version;
    $projects[] = $proj_data;
    $url = NULL;
  }
  //print "DB: projURL: the projects are:\n";
  //var_dump($projects);
  return ($projects);
}
