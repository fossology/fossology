<?php
/***********************************************************
 Copyright (C) 2009-2012 Hewlett-Packard Development Company, L.P.

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
 * \file common-ui.php
 */

/**
 * \brief Build a single choice select pulldown
 *
 * \param $KeyValArray - Assoc array.  Use key/val pairs for list
 * \param $SLName - Select list name (default is "unnamed"),
 * \param $SelectedVal - Initially selected value or key, depends on $SelElt
 * \param $FirstEmpty - True if the list starts off with an empty choice (default is false)
 * \param $SelElt - True (default) if $SelectedVal is a value False if $SelectedVal is a key
 * \param $Options - Optional.  Options to add to the select statment.
 * For example, "id=myid onclick= ..."
 *
 * \return a string of select html 
 */
function Array2SingleSelect($KeyValArray, $SLName="unnamed", $SelectedVal= "",
$FirstEmpty=false, $SelElt=true, $Options="")
{
  $str ="\n<select name='$SLName' $Options>\n";
  if ($FirstEmpty) $str .= "<option value='' > \n";
  foreach ($KeyValArray as $key => $val)
  {
    if ($SelElt == true)
    $SELECTED = ($val == $SelectedVal) ? "SELECTED" : "";
    else
    $SELECTED = ($key == $SelectedVal) ? "SELECTED" : "";
    $str .= "<option value='$key' $SELECTED>$val\n";
  }
  $str .= "</select>";
  return $str;
}


/**
 * \brief Write message to stdout and die.
 *
 * \param $msg - Message to write
 * \param $filenm - File name (__FILE__)
 * \param $lineno - Line number of the caller (__LINE__)
 *
 * \return None, prints error, file, and line number, then exits(1)
 */
function Fatal($msg, $filenm, $lineno)
{
  echo "<hr>FATAL error, File: $filenm, Line number: $lineno<br>";
  echo "$msg<hr>";
  debugbacktrace();
  exit(1);
}

/**
 * \brief debug back trace
 */
function debugbacktrace()
{
  echo "<pre>";
  debug_print_backtrace();
  echo "</pre>";
}

/**
 * \brief print debug message
 */
function debugprint($val, $title)
{
  echo $title, "<pre>";
  print_r($val);
  echo "</pre>";
}

/**
 * \brief transelate a byte number to a proper type, xxx bytes to xxx B/KB/MB/GB/TB
 */
function HumanSize( $bytes )
{
  $types = array( 'B', 'KB', 'MB', 'GB', 'TB' );
  for( $i = 0; $bytes >= 1024 && $i < ( count( $types ) -1 ); $bytes /= 1024, $i++ );
  return( round( $bytes, 2 ) . " " . $types[$i] );
}
 
/**
 * \brief get File Extension (text after last period)
 *
 * \param $fname - file name
 * 
 * \return the file extension of the specified file name
 */
function GetFileExt($fname)
{
  $extpos = strrpos($fname, '.') + 1;
  $extension = strtolower(substr($fname, $extpos, strlen($fname) - $extpos));
  return $extension;
}


/**
 * \brief get the value from a array(map)
 *
 * \param $Key - key 
 * \param $Arr - Whithin the Array, you can get value according to the key
 *
 * \return an array value, or "" if the array key does not exist
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
 * \brief get host list
 *
 * \return return HTML of the host name tree
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
 * \brief send the download file to the user
 *
 * \param $path - file path
 * \param $name - file name
 * 
 * \return True on success, error message on failure.
 */
function DownloadFile($path, $name)
{
  $regfile = file_exists($path);
  if (!$regfile) return _("File does not exist");

  $regfile = is_file($path);
  if (!$regfile) return _("Not a regular file");

  $connstat = connection_status();
  if ($connstat != 0) return _("Lost connection.");

  session_write_close();
  ob_end_clean();
  //    header("Cache-Control: no-store, no-cache, must-revalidate");
  //    header("Cache-Control: post-check=0, pre-check=0", false);
  //    header("Pragma: no-cache");
  header("Expires: ".gmdate("D, d M Y H:i:s", mktime(date("H")+2, date("i"), date("s"), date("m"), date("d"), date("Y")))." GMT");
  header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
  header('Content-Description: File Transfer');
  header("Content-Type: application/octet-stream");
  header("Content-Length: ".(string)(filesize($path)));
  header("Content-Disposition: attachment; filename=$name");
  header("Content-Transfer-Encoding: binary\n");

  /* read/write in chunks to optimize large file downloads */
  if ($file = fopen($path, 'rb'))
  {
    while(!feof($file) and (connection_status()==0))
    {
      print(fread($file, 1024*8));
      flush();
    }
    fclose($file);
  }
  if ((connection_status()==0) and !connection_aborted()) return True;
  return _("Lost connection.");
}


/**
 * \brief Get the uploadtree table name for this upload_pk
 *        If upload_pk does not exist, return "uploadtree".
 *
 * \param $upload_pk
 * 
 * \return uploadtree table name
 */
function GetUploadtreeTableName($upload_pk)
{
  $upload_rec = GetSingleRec("upload", "where upload_pk='$upload_pk'");
  if (empty($upload_rec['uploadtree_tablename'])) return "uploadtree";
  else return $upload_rec['uploadtree_tablename'];
}
?>
