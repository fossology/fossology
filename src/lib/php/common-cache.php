<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
***********************************************************/

/**
 * \file common-cache.php
 * \brief General purpose classes used for fossology report cache.
 *
 * \package common-cache
 *
 * \version "$Id: common-cache.php $"
 *
 */

/**
 * \brief This function is used by Output()
 * to see if the requested report is in the report cache.
 * If it is, the report is returned as a string.
 * Else, there is an empty return.
 * By convention, this should be called with _SERVER[REQUEST_URI].
 * However, any data may be put into the cache by any key.
 * This function also purges the cache of unused items.
 *
 * \param $CacheKey - cashekey, can get cashevalue throuth cashedkey 
 *
 * \return return null when cashe is off for this user, else return value for this key 
 *
 * \remark UserCacheStat: Does user want cache on?
 *                0 Don't know
 *                1 Cache is on
 *                2 Cache is off for this user
 *                $UserCacheStat = 0;  // default, don't know
 */

function ReportCacheGet($CacheKey)
{
  global $PG_CONN;
  global $UserCacheStat;

  /* Purge old entries ~ 1/500 of the times this fcn is called */
  if ( rand(1,500) == 1)
  {
    ReportCachePurgeByDate(" now() - interval '365 days'");
  }

  /** Check if user has cache turned off by default it is on for everyone.
   * If a record does not exist for this user, then the cache is on default
   * is used.
   */
  if ($UserCacheStat == 0)
  {
    $sql = "SELECT cache_on FROM report_cache_user WHERE user_fk='$_SESSION[UserId]';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (!empty($row['cache_on']) && ($result['cache_on'] == 'N'))
    {
      $UserCacheStat = 2;
      return;  /* cache is off for this user */
    }
  }

  $EscKey = pg_escape_string($CacheKey);

  // update time last accessed
  $sql = "UPDATE report_cache SET report_cache_tla = now() WHERE report_cache_key='$EscKey';";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  // Get the cached data
  $sql = "SELECT report_cache_value FROM report_cache WHERE report_cache_key='$EscKey';";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $cashedvalue = $row['report_cache_value'];
  pg_free_result($result);
  return $cashedvalue;
} // ReportCacheGet()


/**
 * \brief This function is used to write a record
 * to the report cache.  If the record already exists, update it.
 * 
 * \param $CacheKey - cashekey
 * \param $CacheValue - cashevalue
 */
function ReportCachePut($CacheKey, $CacheValue)
{
  global $PG_CONN;
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
  if (array_key_exists("upload", $ParsedURI))
  $Upload = $ParsedURI['upload'];
  else
  if (array_key_exists("item", $ParsedURI))
  {
    $sql = "SELECT upload_fk FROM uploadtree WHERE uploadtree_pk='$ParsedURI[item]';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    $row = pg_fetch_assoc($result);
    $Upload = $row['upload_fk'];
    pg_free_result($result);
  }
  if (empty($Upload)) $Upload = "Null";

  $sql = "INSERT INTO report_cache (report_cache_key, report_cache_value, report_cache_uploadfk)
                           VALUES ('$EscKey', '$EscValue', $Upload);";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
  $PGError = pg_last_error($PG_CONN);
  /* If duplicate key, do an update, else report the error */
  if (strpos($PGError, "uplicate") > 0)
  {
    $sql = "UPDATE report_cache SET report_cache_value = '$EscValue', report_cache_tla=now() WHERE report_cache_key = '$EscKey';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
  }
} // ReportCacheInit()

/**
 * \brief Purge all records from the report cache.
 */
function ReportCachePurgeAll()
{
  global $PG_CONN;
  $sql = "DELETE FROM report_cache;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
} // ReportCachePurgeAll()

/**
 * \brief Purge from the report cache records
 * that have been accessed previous to $PurgeDate.
 * 
 * \param $PurgeDate - format: YYYY-MM-DD HH:MM:SS (or some portion thereof).
 */
function ReportCachePurgeByDate($PurgeDate)
{
  global $PG_CONN;
  $sql = "DELETE FROM report_cache WHERE report_cache_tla < $PurgeDate;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
} // ReportCachePurgeByDate()

/**
 * \brief Purge from the report cache
 * records for upload $UploadPK.
 * 
 * \param $UploadPK - upload id
 */
function ReportCachePurgeByUpload($UploadPK)
{
  global $PG_CONN;
  $sql = "DELETE FROM report_cache WHERE report_cache_uploadfk = $UploadPK;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
} // ReportCachePurgeByUpload()

/**
 * \brief Purge from the report cache the
 * record with $CacheKey
 *
 * \param $CacheKey  can get cashevalue throuth cashedkey
 * 
 * \return error msg
 */
function ReportCachePurgeByKey($CacheKey)
{
  global $PG_CONN;
  $ParsedURI = array();
  $EscKey = pg_escape_string($CacheKey);
  parse_str($EscKey, $ParsedURI);

  $sql = "DELETE FROM report_cache WHERE report_cache_key = '$EscKey';";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $Err = pg_last_error($PG_CONN);
  pg_free_result($result);

  return $Err;
} // ReportCachePurgeByKey()

?>
