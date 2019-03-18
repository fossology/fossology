<?php
/***********************************************************
 Copyright (C) 2009-2014 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2017, Siemens AG

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
  if ($FirstEmpty == true) $str .= "<option value='' > </option>\n";

  foreach ($KeyValArray as $key => $val)
  {
    if ($SelElt == true)
    $SELECTED = ($val == $SelectedVal) ? "SELECTED" : "";
    else
    $SELECTED = ($key == $SelectedVal) ? "SELECTED" : "";
    if ($ReturnKey == true)
    $str .= "<option value='$key' $SELECTED>".htmlentities($val, ENT_QUOTES)."</option>\n";
    else
    $str .= "<option value='$val' $SELECTED>".htmlentities($val, ENT_QUOTES)."</option>\n";
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
  foreach(array('B','KB','MB','GB','TB') as $unit){
    if ($bytes < 1024)
    {
      return(round($bytes, 2) .' '. $unit);
    }
    $bytes /= 1024;
  }
  return(round($bytes, 2) . ' PB');
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
  $extension = strtolower(substr($fname, $extpos));
  return $extension;
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
  if (!is_array($Arr)) return "";
  if (array_key_exists($Key, $Arr))
  return ($Arr[$Key]);
  else
  return "";
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
  foreach($SysConf['HOSTS'] as $key=>$value)
  {
    $options .= "<option value='$key' SELECTED> $key </option>\n";
    $i++;
  }
  if (1 == $i) return ""; // if only have one host, does not display
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
  if ($connstat != 0) return _("Lost connection.");

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
  if ((connection_status()==0) and !connection_aborted()) return True;
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
  if (!empty($upload_pk))
  {
    $upload_rec = GetSingleRec("upload", "where upload_pk='$upload_pk'");
    if (!empty($upload_rec['uploadtree_tablename']))
    {
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
  if (empty($upload_pk)) return "";
  $upload_rec = GetSingleRec("upload", "where upload_pk='$upload_pk'");
  $upload_filename = $upload_rec['upload_filename'];
  if (empty($upload_filename)) return "";
  else return $upload_filename;
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
  if (empty($uploadtreeid)) return "";
  $upload_rec = GetSingleRec("uploadtree", "where uploadtree_pk=$uploadtreeid");
  $uploadid = $upload_rec['upload_fk'];
  if (empty($uploadid)) return "" ;
  else return $uploadid;
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
  if (empty($upload)) return "";
  $sql = "SELECT max(uploadtree_pk) from uploadtree where upload_fk = $upload and parent is null;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $uploadtree_id = $row['max'];
  pg_free_result($result);
  return $uploadtree_id;
}
