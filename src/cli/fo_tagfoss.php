#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/


/** @file This file is a quick hack to read all the pfiles in an upload and tag those
 *  that the Antelink public server identifies as FOSS files.
 *  As is it isn't ready for prime time but needs to solve an immediate
 *  problem.  I'll improve this at a future date but wanted to check it in
 *  because others my find it useful (with minor modificaitons).
 */

// $DATAROOTDIR and $PROJECT come from Makefile
//require_once "$DATAROOTDIR/$PROJECT/lib/php/bootstrap.php";
require_once "/usr/local/share/fossology/lib/php/bootstrap.php";

$SysConf = array();  // fo system configuration variables
$PG_CONN = 0;   // Database connection

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap();

/* Initialize global system configuration variables $SysConfig[] */
ConfigInit($SYSCONFDIR, $SysConf);

// Maximum number of sha1's to send to antelink in a single batch
$MaxSend = 500;

/*  -p  -u {upload_pk} -t {tag_pk}
 *  -u and -t are manditory
 */
$Options = getopt("pt:u:");
if ( array_key_exists('t', $Options)
     && array_key_exists('u', $Options)
   ) {
  $Missing = array_key_exists('m', $Options) ? true : false;
  $tag_pk = $Options['t'];
  $upload_pk = $Options['u'];
} else {
  echo "Fatal: Missing parameter\n";
  Usage($argc, $argv);
  exit -1;
}

if ( array_key_exists('p', $Options)) {
  $PrintOnly = true;
} else {
  $PrintOnly = false;
}

$sql = "select distinct(pfile_fk), pfile_sha1, ufile_name from uploadtree,pfile where upload_fk='$upload_pk' and pfile_pk=pfile_fk";
$result = pg_query($PG_CONN, $sql);
DBCheckResult($result, $sql, __FILE__, __LINE__);
if (pg_num_rows($result) == 0) {
  echo "Empty upload_pk $upload_pk\n";
  exit;
}

/* loop through each row accumulating groups of $MaxSend files (sha1's) to send to antelink */
$ToAntelink = array();
$TaggedFileCount = 0;
$TotalFileCount = 0;
while ($row = pg_fetch_assoc($result)) {
  $TotalFileCount ++;
  $ToAntelink[] = $row;
  if (count($ToAntelink) >= $MaxSend) {
    $TaggedFileCount += QueryTag($ToAntelink, $tag_pk, $PrintOnly);
    $ToAntelink = array();
  }
}

if (count($ToAntelink)) {
  $TaggedFileCount += QueryTag($ToAntelink, $tag_pk, $PrintOnly);
}

echo "$TaggedFileCount files tagged out of $TotalFileCount files.\n";

return (0);

/**************************** functions  ************************************/
function Usage($argc, $argv)
{
  echo "$argv[0] -p -u {upload_pk} -t {tag_pk}\n";
  echo "         -p means to only print out filenames to be tagged, but do not update the db.\n";
}


/**
 * @brief Query the Antelink public server and tag the results.
 * @param $ToAntelink array of pfile_fk, pfile_sha1, ufile_name records
 * @param $tag_pk
 * @param $PrintOnly print the ufile_name, do not update the db.  Used for debugging.
 * @return number of tagged files.
 **/
function QueryTag($ToAntelink, $tag_pk, $PrintOnly)
{
  global $PG_CONN;
  global $SysConf;
  $AntepediaServer = "https://api.antepedia.com/acme/v3/bquery/";

  /* parse http_proxy server and port */
  $http_proxy = $SysConf['FOSSOLOGY']['http_proxy'];
  $ProxyServer = substr($http_proxy, 0, strrpos($http_proxy, ":"));
  $ProxyPort = substr(strrchr($http_proxy, ":"), 1);

  /* construct array of just sha1's */
  $sha1array = array();
  foreach ($ToAntelink as $row) {
    $sha1array[] = $row['pfile_sha1'];
  }
  $PostData = json_encode($sha1array);

  $curlch = curl_init($AntepediaServer);
  //turning off the server and peer verification(TrustManager Concept).
  curl_setopt($curlch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($curlch, CURLOPT_SSL_VERIFYHOST, 2);

  curl_setopt($curlch, CURLOPT_POST, TRUE);
  curl_setopt($curlch,CURLOPT_POSTFIELDS, $PostData);
  curl_setopt($curlch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($curlch,CURLOPT_USERAGENT,'Curl-php');

  if (! empty($ProxyServer)) {
    curl_setopt($curlch, CURLOPT_HTTPPROXYTUNNEL, TRUE);
    curl_setopt($curlch, CURLOPT_PROXY, $ProxyServer);
    if (! empty($ProxyPort)) {
      curl_setopt($curlch, CURLOPT_PROXYPORT, $ProxyPort);
    }
    curl_setopt($curlch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
  }

  curl_setopt($curlch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'charset=utf-8',
    'Accept:application/json, text/javascript, */*; q=0.01'
  ));

  //getting response from server
  $response = curl_exec($curlch);

  if (curl_errno($curlch)) {
    // Fatal: display curl errors
    echo "Error " .  curl_errno($curlch) . ": " . curl_error($curlch);
    exit;
  }

  //closing the curl
  curl_close($curlch);

  $response = json_decode($response);

  // print any errors
  if ($response->error) {
    echo $response->error . "\n";
  }

  //echo "response\n";
  //print_r($response);
  /* Add tag or print */
  foreach ($response->results as $result) {
    $row = GetRawRow($result->sha1, $ToAntelink);

    if ($PrintOnly) {
      echo $row['ufile_name'] . "\n";
      continue;
    }

    /* Tag the pfile (update tag_file table) */
    /* There is no constraint preventing duplicate tags so do a precheck */
    $sql = "SELECT * from tag_file where pfile_fk='$row[pfile_fk]' and tag_fk='$tag_pk'";
    $sqlresult = pg_query($PG_CONN, $sql);
    DBCheckResult($sqlresult, $sql, __FILE__, __LINE__);
    if (pg_num_rows($sqlresult) == 0) {
      $sql = "insert into tag_file (tag_fk, pfile_fk, tag_file_date, tag_file_text) values ($tag_pk, '$row[pfile_fk]', now(), NULL)";
      $InsResult = pg_query($PG_CONN, $sql);
      DBCheckResult($InsResult, $sql, __FILE__, __LINE__);
    }
    pg_free_result($sqlresult);
  }

  return;
}

/**
 * @brief Get the raw data row for this sha1
 * @param $sha1
 * @param $ToAntelink array of pfile_fk, pfile_sha1, ufile_name records
 **/
function GetRawRow($sha1, $ToAntelink)
{
  /* find the sha1 in $ToAntelink and print the ufile_name */
  foreach ($ToAntelink as $row) {
    if (strcasecmp($row['pfile_sha1'], $sha1) == 0) {
      return $row;
    }
  }
}

