#!/usr/bin/php
<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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

/** @file This file is a quick hack to read all the pfiles in an upload and tag those
 *  that the Antelink public server identifies as FOSS files.  
 *  As is it isn't ready for prime time but needs to solve an immediate
 *  problem.  I'll improve this at a future date but wanted to check it in
 *  because others my find it useful (with minor modificaitons).
 */

// $DATAROOTDIR and $PROJECT come from Makefile
//require_once "$DATAROOTDIR/$PROJECT/lib/php/bootstrap.php";
//require_once "/usr/local/share/fossology/lib/php/bootstrap.php";
require_once "/usr/share/fossology/lib/php/bootstrap.php";

// NOTE THIS IS A PRIVATE KEY - read from file acme.key
$acmekey = file_get_contents("acme.key");
// Antepedia Computing Machinery Engine (acme) url
$acmebaseurl = 'https://api.antepedia.com/acme/v3';
//$acmequeryurl = $acmebaseurl . "/fquery/$acmekey";     // Full query, slow.  Antelink support recommends only sending one sha1 at a time.
$acmeBinaryqueryurl = $acmebaseurl . "/bquery/$acmekey";
$acmequeryurl = $acmebaseurl . "/squery/$acmekey";
$acmekeycheckurl = $acmebaseurl . "/checkey/$acmekey";

$SysConf = array();  // fo system configuration variables
//$PG_CONN = 0;   // Database connection
include('/usr/share/fossology/lib/php/common-db.php');
$PG_CONN =  DBconnect("/etc/fossology/");
$SYSCONFDIR= "/etc/fossology/";//forcibly pass configuration
/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap();

/* Initialize global system configuration variables $SysConfig[] */
ConfigInit($SYSCONFDIR, $SysConf);

/* Check Antelink acme key */
$ch = curl_init($acmekeycheckurl);
SetCurlArgs($ch);
$contents = curl_exec( $ch );
$response=json_decode($contents);
curl_close( $ch );
if (!$response->authorized)
{
  echo "Invalid antelink acme key.\n";
  exit;
}

// Maximum number of sha1's to send to antelink in a single batch 
$MaxBinarySend = 500;
$MaxSend = 10;

/*  -p  -u {upload_pk} -t {tag_pk} 
 *  -u and -t are manditory
 */
$Options = getopt("vpt:u:");
if ( array_key_exists('t', $Options)
     and array_key_exists('u', $Options)
   )
{
  $tag_pk = $Options['t'];
  $upload_pk = $Options['u'];
}
else
{
  echo "Fatal: Missing parameter\n";
  Usage($argc, $argv);
  exit -1;
}

$PrintOnly = ( array_key_exists('p', $Options)) ? true : false;
$Verbose  = ( array_key_exists('v', $Options)) ? true : false;

//$sql = "select distinct(pfile_fk), pfile_sha1, ufile_name from uploadtree,pfile where upload_fk='$upload_pk' and pfile_pk=pfile_fk";
$sql = "SELECT pfile_pk, pfile_sha1, ufile_name, acme_pfile_pk  FROM (SELECT distinct(pfile_fk) AS PF, ufile_name FROM uploadtree
WHERE upload_fk='$upload_pk' and (ufile_mode&x'10000000'::int)=0) as SS
inner join pfile on (PF=pfile_pk)
left join acme_pfile on (PF=acme_pfile.pfile_fk) where acme_pfile_pk is null;";
$result = pg_query($PG_CONN, $sql);
DBCheckResult($result, $sql, __FILE__, __LINE__);
if (pg_num_rows($result) == 0)
{
  echo "Empty upload_pk $upload_pk\n";
  exit;
}


/* loop through each row identifying each as foss or not
 * Put the FOSS SHA1 into an array to send to the squery server.
 * This two step process is needed because bquery can handle requests of 500 hashes 
 * but squery can only handle requests of 10 hashes.  */
$MasterFOSSarray = array();
$ToAntelink = array();
$FoundFOSSfiles = 0;
$PrecheckFileCount = 0;
while ($row = pg_fetch_assoc($result))
{
  $PrecheckFileCount++;
  $ToAntelink[] = $row;
  if (count($ToAntelink) >= $MaxBinarySend) 
  {
    if ($Verbose) echo "Precheck $PrecheckFileCount, found $FoundFOSSfiles\n";
    $FoundFOSSfiles += QueryBinaryServer($ToAntelink, $MasterFOSSarray);
    $ToAntelink = array();
  }
}
pg_free_result($result);
if (count($ToAntelink) ) 
{
  $FoundFOSSfiles += QueryBinaryServer($ToAntelink, $MasterFOSSarray);
  if ($Verbose) echo "Precheck $PrecheckFileCount, found $FoundFOSSfiles\n";
}

/* loop through each row accumulating groups of $MaxSend files (sha1's) to send to antelink */
$ToAntelink = array();
$TaggedFileCount = 0;
$TotalFileCount = 0;
foreach ($MasterFOSSarray as $row)
{
  $TotalFileCount++;
  $ToAntelink[] = $row;
  if (count($ToAntelink) >= $MaxSend) 
  {
    $TaggedFileCount += QueryTag($ToAntelink, $tag_pk, $PrintOnly, $Verbose);
    $ToAntelink = array();
  }
}

if (count($ToAntelink) ) $TaggedFileCount += QueryTag($ToAntelink, $tag_pk, $PrintOnly, $Verbose);

echo "$TaggedFileCount files tagged out of $TotalFileCount files.\n";

return (0);


/**
 * @brief Query the Antelink public server and tag the results.
 * @param $ToAntelink array of pfile_fk, pfile_sha1, ufile_name records
 * @param $MasterFOSSarray master array of FOSS records.  This will be used for squery. 
 * @return number of FOSS files in $ToAntelink.
 **/
function QueryBinaryServer($ToAntelink, &$MasterFOSSarray)
{
  global $PG_CONN;
  global $acmeBinaryqueryurl;

  $NumFound = 0;

  /* construct array of just sha1's */
  $sha1array = array();
  foreach($ToAntelink as $row) $sha1array[] = $row['pfile_sha1'];
  $PostData = json_encode($sha1array);

  $curlch = curl_init($acmeBinaryqueryurl);
  SetCurlArgs($curlch);

  curl_setopt($curlch, CURLOPT_POST, TRUE);
  curl_setopt($curlch,CURLOPT_POSTFIELDS, $PostData);
  curl_setopt($curlch, CURLOPT_RETURNTRANSFER, TRUE);

  //getting response from server
  $curlresponse = curl_exec($curlch);

  if (curl_errno($curlch))
  {
    // Fatal: display curl errors
    echo "Error " .  curl_errno($curlch) . ": " . curl_error($curlch) . "\n";
    return $NumFound;
  }

  //closing the curl
  curl_close($curlch);

  $response = json_decode($curlresponse);

  // print any errors
  if ($response->error)
  {
     echo $response->error . "\n";
  }

  /* Add tag or print */
if (is_array($response->results))
  foreach($response->results as $result)
  {
    $row = GetRawRow($result->sha1, $ToAntelink);
    if (!empty($row)){
    	$NumFound++;
    	$MasterFOSSarray[] = $row;
    }
  }

  return $NumFound;  
}


/**
 * @brief Query the Antelink public server and tag the results.
 * @param $ToAntelink array of pfile_fk, pfile_sha1, ufile_name records
 * @param $tag_pk 
 * @param $PrintOnly print the raw antelink data, do not update the db.  Used for debugging.
 * @parma $Verbose   print project name.
 * @return number of tagged files.
 **/
function QueryTag($ToAntelink, $tag_pk, $PrintOnly, $Verbose)
{
  global $PG_CONN;
  global $acmequeryurl;

  $numTagged = 0;

  /* construct array of arrays of name and sha1's */
  $files=array();
  foreach($ToAntelink as $row) 
  {
    $file['hash']=$row['pfile_sha1'];
    $file['name']=$row['ufile_name'];
    $files[]=$file;
  }
  $request['files']=$files;

  $PostData = json_encode($request);

  $curlch = curl_init($acmequeryurl);
  SetCurlArgs($curlch);

  curl_setopt($curlch, CURLOPT_POST, TRUE);
  curl_setopt($curlch,CURLOPT_POSTFIELDS, $PostData);
  curl_setopt($curlch, CURLOPT_RETURNTRANSFER, TRUE);

  //getting response from server
  $response = curl_exec($curlch);

  if (curl_errno($curlch))
  {
    // Fatal: display curl errors
    echo "Error " .  curl_errno($curlch) . ": " . curl_error($curlch) . "\n";
    return 0;
//    exit;
  }

  //closing the curl
  curl_close($curlch);

  $response = json_decode($response);
//echo "response\n";
//print_r($response);

  // print any errors
  if ($response->error)
  {
     echo $response->error . "\n";
  }

  /* Add tag or print */
if (is_array($response->results))
  foreach($response->results as $result)
  {
    $row = GetRawRow($result->sha1, $ToAntelink);

    if ($PrintOnly)
    {
     if (!empty($row)) print_r($row);
     // echo $row['ufile_name'] . "\n";
      print_r($result);
      continue;
    }

    foreach ($result->projects as $project)
    {
      /* check if acme_project already exists (check if the url is unique) */
      $url = pg_escape_string($PG_CONN, $project->url);
      $name = pg_escape_string($PG_CONN, $project->name);
      $acme_project_pk = '';
      $sql = "SELECT acme_project_pk from acme_project where url='$url' and project_name='$name'";
      $sqlresult = pg_query($PG_CONN, $sql);
      DBCheckResult($sqlresult, $sql, __FILE__, __LINE__);
      if (pg_num_rows($sqlresult) > 0)
      {
        $projrow = pg_fetch_assoc($sqlresult);
        $acme_project_pk = $projrow['acme_project_pk'];
      }
      pg_free_result($sqlresult);

      if (empty($acme_project_pk))
      {
        /* this is a new acme_project, so write the acme_project record */
        $acme_project_pk = writeacme_project($project, $Verbose);
      }

      /* write the acme_pfile record */
      writeacme_pfile($acme_project_pk, $row['pfile_pk']);

      /* Tag the pfile (update tag_file table) */
      /* There is no constraint preventing duplicate tags so do a precheck */
      $sql = "SELECT * from tag_file where pfile_fk='$row[pfile_pk]' and tag_fk='$tag_pk'";
      $sqlresult = pg_query($PG_CONN, $sql);
      DBCheckResult($sqlresult, $sql, __FILE__, __LINE__);
      if (pg_num_rows($sqlresult) == 0)
      {
        $sql = "insert into tag_file (tag_fk, pfile_fk, tag_file_date, tag_file_text) values ($tag_pk, '$row[pfile_pk]', now(), NULL)";
        $insresult = pg_query($PG_CONN, $sql);
        DBCheckResult($insresult, $sql, __FILE__, __LINE__);
        pg_free_result($insresult);
        $numTagged++;
      }
      pg_free_result($sqlresult);
    }
  }

  return $numTagged;  
}

/**
 * @brief Get the raw data row for this sha1
 * @param $sha1
 * @param $ToAntelink array of pfile_fk, pfile_sha1, ufile_name records
 **/
function GetRawRow($sha1, $ToAntelink)
{
  /* find the sha1 in $ToAntelink and print the ufile_name */
  foreach($ToAntelink as $row) 
  { 
    if (strcasecmp($row['pfile_sha1'], $sha1) == 0) return $row;
  }
  return '';
}


/**
 * @brief Set basic curl args
 * @param $ch  curl handle
 **/
function SetCurlArgs($ch)
{
  global $SysConf;
  curl_setopt($ch,CURLOPT_USERAGENT,'Curl-php');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  curl_setopt($ch,
              CURLOPT_HTTPHEADER, array("Content-Type:
               application/json; charset=utf-8","Accept:application/json,
               text/javascript, */*; q=0.01"));

  /* parse http_proxy server and port */
  $http_proxy = $SysConf['FOSSOLOGY']['http_proxy'];
  $ProxyServer = substr($http_proxy, 0, strrpos($http_proxy, ":"));
  $ProxyPort = substr(strrchr($http_proxy, ":"), 1);
  if (!empty($ProxyServer))
  {
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, TRUE);
    curl_setopt($ch, CURLOPT_PROXY, $ProxyServer);
    if (!empty($ProxyPort)) curl_setopt($ch, CURLOPT_PROXYPORT, $ProxyPort);
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
  }
}

/**
 * @brief Usage
 * @param $argc
 * @param $argv
 **/
function Usage($argc, $argv)
{
  echo "$argv[0] -v -p -u {upload_pk} -t {tag_pk}\n";
  echo "         -p prints out raw antepedia info, but do not update the db.\n";
  echo "         -v prints project found after inserting into db.\n";
}

/**
 * @brief write an acme_project record
 * @param $project
 * @param $Verbose print project name
 * @returns acme_project_pk
 **/
function writeacme_project($project, $Verbose)
{
  global $PG_CONN;

  $project_name = pg_escape_string($PG_CONN, $project->name);
  $url = pg_escape_string($PG_CONN, $project->url);
  $description = pg_escape_string($PG_CONN, $project->description);

  /* convert licenses array to pipe delimited list */
  $licenses = '';
  foreach($project->licenses as $license) 
  {
    if (!empty($licenses)) $licenses .= '|';
    $licenses .= pg_escape_string($PG_CONN, $license);
  }

  /* figure out if we have artefact or content data and pull release date an version out of their respective structs */
  if (!empty($project->artefacts))
  {
    $artefact = $project->artefacts[0];
    $projectDate = $artefact->releaseDate;
    $version = pg_escape_string($PG_CONN, $artefact->version);
  }
  else
  {
    $content = $project->contents[0];
    $projectDate = $content->releaseDate;
    $version = pg_escape_string($PG_CONN, $content->revision);
  }

  /* convert unix time to date m/d/yyyy  
   * Watch out for time stamps in milliseconds
   */
  if ($projectDate > 20000000000) $projectDate = $projectDate / 1000;  // convert to seconds if necessary
  $releasedate = date("Ymd", $projectDate);

  if ($Verbose) echo "Found project: $project_name\n";

  /* insert the data */
  $sql = "insert into acme_project (project_name, url, description, licenses, releasedate, version) 
              values ('$project_name', '$url', '$description', '$licenses', '$releasedate', '$version')";
  $InsResult = pg_query($PG_CONN, $sql);
  DBCheckResult($InsResult, $sql, __FILE__, __LINE__);
  pg_free_result($InsResult);

  /* retrieve and return the primary key */
  $sql = "select acme_project_pk from acme_project where project_name='$project_name' and url='$url' and description='$description' and licenses='$licenses' and releasedate='$releasedate' and version='$version' ";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  return $row['acme_project_pk'];
}

/**
 * @brief write an acme_pfile record
 * @param $acme_project_pk
 * @param $pfile_pk
 **/
function writeacme_pfile($acme_project_pk, $pfile_pk)
{
  global $PG_CONN;

  /* insert the data */
  $sql = "insert into acme_pfile (pfile_fk, acme_project_fk) values ($pfile_pk, $acme_project_pk)";
  // ignore errors (this is a prototype).  Errors are almost certainly from a duplicate insertion
  @$InsResult = pg_query($PG_CONN, $sql);
}
?>

