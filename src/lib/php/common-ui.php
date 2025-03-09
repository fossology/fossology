<?php
/*
 SPDX-FileCopyrightText: © 2009-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2017 Siemens AG
 SPDX-FileCopyrightText: © 2020 Robert Bosch GmbH
 SPDX-FileCopyrightText: © Dineshkumar Devarajan <Devarajan.Dineshkumar@in.bosch.com>

 SPDX-License-Identifier: LGPL-2.1-only
*/

use Symfony\Component\HttpFoundation\Session\Session;

/**
 * \file
 * \brief Common function for UI operations
 */

/**
 * \brief Build a single choice select pulldown
 *
 * \param array $KeyValArray Assoc array.  Use key/val pairs for list
 * \param string $SLName Select list name (default is "unnamed"),
 * \param string $SelectedVal Initially selected value or key, depends on $SelElt
 * \param bool $FirstEmpty True if the list starts off with an empty choice (default is false)
 * \param bool $SelElt True (default) if $SelectedVal is a value False if $SelectedVal is a key
 * \param string $Options Optional.  Options to add to the select statement.
 * For example, "id=myid onclick= ..."
 * \param bool $ReturnKey True (default) return the Key as value, if False return the Value
 *
 * \return A string of select HTML
 */
function Array2SingleSelect($KeyValArray, $SLName="unnamed", $SelectedVal= "",
$FirstEmpty=false, $SelElt=true, $Options="", $ReturnKey=true)
{
  $str ="\n<select name='$SLName' $Options>\n";
  if ($FirstEmpty == true) {
    $str .= "<option value='' > </option>\n";
  }
  foreach ($KeyValArray as $key => $val) {
    if ($SelElt == true) {
      $SELECTED = ($val == $SelectedVal) ? "SELECTED" : "";
    } else {
      $SELECTED = ($key == $SelectedVal) ? "SELECTED" : "";
    }
    if ($ReturnKey == true) {
      $str .= "<option value='$key' $SELECTED>".htmlentities($val, ENT_QUOTES)."</option>\n";
    } else {
      $str .= "<option value='$val' $SELECTED>".htmlentities($val, ENT_QUOTES)."</option>\n";
    }
  }
  $str .= "</select>";
  return $str;
}


/**
 * \brief Write message to stdout and die.
 *
 * \param string $msg    Message to write
 * \param string $filenm File name (__FILE__)
 * \param int    $lineno Line number of the caller (__LINE__)
 *
 * \return None, prints error, file, and line number, then exits(1)
 * \sa debugbacktrace()
 */
function Fatal($msg, $filenm, $lineno)
{
  echo "<hr>FATAL error, File: $filenm, Line number: $lineno<br>";
  echo "$msg<hr>";
  debugbacktrace();
  exit(1);
}

/**
 * \brief Debug back trace
 */
function debugbacktrace()
{
  echo "<pre>";
  debug_print_backtrace();
  echo "</pre>";
}

/**
 * @brief Print debug message
 * @param mixed  $val   Variable to be printed
 * @param string $title Title of the variable
 */
function debugprint($val, $title)
{
  echo $title, "<pre>";
  print_r($val);
  echo "</pre>";
}

/**
 * \brief Translate a byte number to a proper type, xxx bytes to xxx B/KB/MB/GB/TB/PB
 * \param int $bytes Bytes to be converted
 */
function HumanSize( $bytes )
{
  foreach (array('B','KB','MB','GB','TB') as $unit) {
    if ($bytes < 1024) {
      return(round($bytes, 2) .' '. $unit);
    }
    $bytes /= 1024;
  }
  return(round($bytes, 2) . ' PB');
}

/**
 * @brief Convert DateInterval to Human readable format
 *
 * If DateInterval is more than 1 year, then return years, else return months,
 * else return days. If duration is less than 1 day, then return hours and
 * minutes.
 * @param DateInterval $duration Duration to convert
 * @return string Formatted duration
 */
function HumanDuration(DateInterval $duration): string
{
  $humanDuration = "";
  if ($duration->y > 0) {
    $humanDuration .= $duration->y . " y";
  } elseif ($duration->m > 0) {
    $humanDuration .= $duration->m . " m";
  } elseif ($duration->days > 0) {
    $humanDuration .= $duration->days . " d";
  } else {
    $humanDuration .= $duration->h . "h " . $duration->i . "m";
  }
  return $humanDuration;
}

/**
 * \brief Get File Extension (text after last period)
 *
 * \param string $fname File name
 *
 * \return The file extension of the specified file name
 */
function GetFileExt($fname)
{
  $extpos = strrpos($fname, '.') + 1;
  return strtolower(substr($fname, $extpos));
}


/**
 * \brief Get the value from a array(map)
 *
 * \param mixed $Key Key to look
 * \param array $Arr Within the Array, you can get value according to the key
 *
 * \return An array value, or "" if the array key does not exist
 */
function GetArrayVal($Key, $Arr)
{
  if (! is_array($Arr)) {
    return "";
  }
  if (array_key_exists($Key, $Arr)) {
    return ($Arr[$Key]);
  } else {
    return "";
  }
}

/**
 * \brief Get host list
 *
 * \return Return HTML of the host name tree
 */
function HostListOption()
{
  global $SysConf;
  $options = "";
  $i = 0;
  foreach ($SysConf['HOSTS'] as $key => $value) {
    $options .= "<option value='$key' SELECTED> $key </option>\n";
    $i ++;
  }
  if (1 == $i) {
    return ""; // if only have one host, does not display
  }
  return $options;
}

/**
 * \brief Send a string to a user as a download file
 *
 * \param string $text Text to download as file
 * \param string $name File name
 * \param string $contentType Download file Content-Type
 *
 * \return True on success, error message on failure.
 */
function DownloadString2File($text, $name, $contentType)
{
  $connstat = connection_status();
  if ($connstat != 0) {
    return _("Lost connection.");
  }

  $session = new Session();
  $session->save();

  ob_end_clean();
  header("Expires: ".gmdate("D, d M Y H:i:s", mktime(date("H")+2, date("i"), date("s"), date("m"), date("d"), date("Y")))." GMT");
  header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
  header('Content-Description: File Transfer');
  header("Content-Type: $contentType");
  header("Content-Length: ".(string)(strlen($text)));
  header("Content-Disposition: attachment; filename=\"$name\"");
  header("Content-Transfer-Encoding: binary\n");

  echo $text;
  if ((connection_status() == 0) and ! connection_aborted()) {
    return true;
  }
  return _("Lost connection.");
}


/**
 * \brief Get the uploadtree table name for this upload_pk
 *        If upload_pk does not exist, return "uploadtree".
 *
 * \param int $upload_pk
 *
 * \return Uploadtree table name
 */
function GetUploadtreeTableName($upload_pk)
{
  if (! empty($upload_pk)) {
    $upload_rec = GetSingleRec("upload", "where upload_pk='$upload_pk'");
    if (! empty($upload_rec['uploadtree_tablename'])) {
      return $upload_rec['uploadtree_tablename'];
    }
  }
  return "uploadtree";
}

/**
 * \brief Get Upload Name through upload id
 *
 * \param int $upload_id Upload ID
 *
 * \return Upload name, "" if upload does not exists
 */
function GetUploadName($upload_pk)
{
  if (empty($upload_pk)) {
    return "";
  }
  $upload_rec = GetSingleRec("upload", "where upload_pk='$upload_pk'");
  $upload_filename = $upload_rec['upload_filename'];
  if (empty($upload_filename)) {
    return "";
  } else {
    return $upload_filename;
  }
}

/**
 * \brief Get upload id through uploadtreeid
 *
 * \param int $uploadtreeid Uploadtree id
 *
 * \return Upload id
 */
function GetUploadID($uploadtreeid)
{
  if (empty($uploadtreeid)) {
    return "";
  }
  $upload_rec = GetSingleRec("uploadtree", "where uploadtree_pk=$uploadtreeid");
  $uploadid = $upload_rec['upload_fk'];
  if (empty($uploadid)) {
    return "";
  } else {
    return $uploadid;
  }
}

/**
 * \brief Get 1st uploadtree id through upload id
 *
 * \param int $upload Upload id
 *
 * \return 1st uploadtree id
 */
function Get1stUploadtreeID($upload)
{
  global $PG_CONN;
  if (empty($upload)) {
    return "";
  }
  $sql = "SELECT max(uploadtree_pk) from uploadtree where upload_fk = $upload and parent is null;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $uploadtree_id = $row['max'];
  pg_free_result($result);
  return $uploadtree_id;
}

/**
 * \brief Convert the server time to browser time
 * \param time $server_time to be converted
 */
function Convert2BrowserTime($server_time)
{
  if (empty($server_time)) {
    throw new \InvalidArgumentException('Server time cannot be empty');
  }

  $server_timezone = date_default_timezone_get();

  try {
    $browser_time = new \DateTime($server_time, new \DateTimeZone($server_timezone));

    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['timezone'])) {
      $tz = $_SESSION['timezone'];
      if (in_array($tz, timezone_identifiers_list(), true)) {
        $browser_time->setTimezone(new \DateTimeZone($tz));
      } else {
        throw new \UnexpectedValueException("Invalid timezone in session: {$tz}");
      }
    }

    return $browser_time->format('Y-m-d H:i:s');
  } catch (\Exception $e) {
    $ts = strtotime($server_time);
    if ($ts === false) {
      throw new \InvalidArgumentException(
        "Unparseable server time: {$server_time}",
        0,
        $e
      );
    }

    return date('Y-m-d H:i:s', $ts);
  }
}
