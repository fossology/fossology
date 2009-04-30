<?php
/***********************************************************
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
 ***********************************************************/
/**
 * General purpose classes used for fossology report cache.
 *
 * @package common-cache
 *
 * @version "$Id: common-cache.php $"
 *
 */

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

  /***********************************************************
   ReportCacheGet(): This function is used by Output()
   to see if the requested report is in the report cache.
   If it is, the report is returned as a string.
   Else, there is an empty return.
   By convention, this should be called with _SERVER[REQUEST_URI].
   However, any data may be put into the cache by any key.
   This function also purges the cache of unused items.
   ***********************************************************/
/* UserCacheStat: Does user want cache on?
 *                0 Don't know
 *                1 Cache is on
 *                2 Cache is off for this user
 */
$UserCacheStat = 0;  // default, don't know

  function ReportCacheGet($CacheKey)
  {
    global $DB;
    global $UserCacheStat;

    /* Purge old entries ~ 1/100 of the times this fcn is called */
    if ( rand(1,100) == 1)
    {
      ReportCachePurgeByDate(" now() - interval '10 days'");
    }

    /* Check if user has cache turned off by default it is on for everyone.
     * If a record does not exist for this user, then the cache is on default
     * is used.
     */
    if ($UserCacheStat == 0)
    {
      $sql = "SELECT cache_on FROM report_cache_user WHERE user_fk='$_SESSION[UserId]'";
      $Result = $DB->Action($sql);
      if (!empty($Result[0]['cache_on']) && ($Result[0]['cache_on'] == 'N'))
      {
        $UserCacheStat = 2;
        return;  /* cache is off for this user */
      }
    }

    $EscKey = pg_escape_string($CacheKey);

    // update time last accessed
    $Result = $DB->Action("UPDATE report_cache SET report_cache_tla = now() WHERE report_cache_key='$EscKey'",
                          $PGError);

    // Get the cached data
    $Result = $DB->Action("SELECT report_cache_value FROM report_cache WHERE report_cache_key='$EscKey'");

    return $Result[0]['report_cache_value'];
  } // ReportCacheGet()


  /***********************************************************
   ReportCachePut(): This function is used to write a record 
   to the report cache.  If the record already exists, update 
   it.
   ***********************************************************/
  function ReportCachePut($CacheKey, $CacheValue)
  {
    global $DB;
    global $UserCacheStat;

    /* Check if user has cache turned off
     * CacheUserStat is set in ReportCacheGet
     * If it isn't, it is safe to fallback to the default
     * behavior (cache is on).
     */
    if ($UserCacheStat == 2) 
    {
      return;
    }

    $EscKey = pg_escape_string($CacheKey);
    $EscValue = pg_escape_string($CacheValue);
    
    /* Parse the key.  If the key is a uri  */
    /* look for upload =>, if not found, look for item => */
    /* in order to get the upload key */
    $ParsedURI = array();
    parse_str($EscKey, $ParsedURI);
    /* use 'upload= ' to define the upload in the cache key */
    if (!empty($ParsedURI['upload'])) 
      $Upload = $ParsedURI['upload'];
    else
      if (empty($Upload) and (!empty($ParsedURI['item'])))
      {
        $Result = $DB->Action("SELECT upload_fk FROM uploadtree WHERE uploadtree_pk='$ParsedURI[item]'");
        $Upload = $Result['upload_fk'];
      }

    $Result = $DB->Action("INSERT INTO report_cache (report_cache_key, report_cache_value, report_cache_uploadfk) 
                           VALUES ('$EscKey', '$EscValue', '$Upload')",
                          $PGError);
    
    /* If duplicate key, do an update, else report the error */
    if (strpos($PGError, "duplicate") >> 0)
      $Result = $DB->Action("UPDATE report_cache SET report_cache_value = '$EscValue', report_cache_tla=now() WHERE report_cache_key = '$EscKey'",
                            $PGError);
    if ($PGError) echo "UPDATE: $PGError";
  } // ReportCacheInit()

  /***********************************************************
   ReportCachePurgeAll(): Purge all records from the report cache.
   ***********************************************************/
  function ReportCachePurgeAll()
  {
    global $DB;

    $Result = $DB->Action("DELETE FROM report_cache;");
  } // ReportCachePurgeByDate()

  /***********************************************************
   ReportCachePurgeByDate(): Purge from the report cache records
   that have been accessed previous to $PurgeDate.
   $PurgeDate format: YYYY-MM-DD HH:MM:SS (or some portion thereof).
   ***********************************************************/
  function ReportCachePurgeByDate($PurgeDate)
  {
    global $DB;

    $Result = $DB->Action("DELETE FROM report_cache WHERE report_cache_tla < $PurgeDate");
  } // ReportCachePurgeByDate()

  /***********************************************************
   ReportCachePurgeByUpload(): Purge from the report cache 
   records for upload $UploadPK.
   ***********************************************************/
  function ReportCachePurgeByUpload($UploadPK)
  {
    global $DB;

    $Result = $DB->Action("DELETE FROM report_cache WHERE report_cache_uploadfk = $UploadPK");
  } // ReportCachePurgeByUpload()

  /***********************************************************
   ReportCachePurgeByKey(): Purge from the report cache the
   record with $CacheKey
   ***********************************************************/
  function ReportCachePurgeByKey($CacheKey)
  {
    global $DB;
    $ParsedURI = array();
    $EscKey = pg_escape_string($CacheKey);
    parse_str($EscKey, $ParsedURI);

    $Result = $DB->Action("DELETE FROM report_cache WHERE report_cache_key = '$EscKey'",$Err);
    return $Err;
  } // ReportCachePurgeByKey()

?>
