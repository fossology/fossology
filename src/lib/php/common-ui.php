<?php
/***********************************************************
 Copyright (C) 2009-2014 Hewlett-Packard Development Company, L.P.

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
  if ($FirstEmpty == true) $str .= "<option value='' > </option>\n";

  foreach ($KeyValArray as $key => $val)
  {
    if ($SelElt == true)
    $SELECTED = ($val == $SelectedVal) ? "SELECTED" : "";
    else
    $SELECTED = ($key == $SelectedVal) ? "SELECTED" : "";
    $str .= "<option value='$key' $SELECTED>$val</option>\n";
  }
  $str .= "</select>";
  return $str;
}


/**
 * \brief  Use two columns in a table to create an array key => val
 * \param $keycol key column
 * \param $valcol can be a comma separated list of value columns
 * \param $tablename
 * \param $separator is used to separate values if there are multiple columns
 * \param $where is an optional where clause eg "where a=b", "order by x", ...
 */
function Table2Array($keycol, $valcol, $tablename, $separator=" ", $where="")
{
  global $PG_CONN;

  $valarray = explode(",", $valcol);
  $RetArray = array();
  $sql = "select $keycol, $valcol from $tablename $where";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  if (pg_num_rows($result) > 0)
  {
    while ($row = pg_fetch_assoc($result))
    {
      $newval = "";
      foreach ($valarray as $sqlcolname)
      {
        if (!empty($newval)) $newval .= $separator;
        $newval .= $row[trim($sqlcolname)];
      }
      $RetArray[$row[$keycol]] = $newval;
    }
  }
  pg_free_result($result);
  return $RetArray;
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
 * \brief translate a byte number to a proper type, xxx bytes to xxx B/KB/MB/GB/TB/PB
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
 * \brief get File Extension (text after last period)
 *
 * \param $fname - file name
 * 
 * \return the file extension of the specified file name
 */
function GetFileExt($fname)
{
  $extpos = strrpos($fname, '.') + 1;
  $extension = strtolower(substr($fname, $extpos));
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
 * \brief send a string to a user as a download file
 *
 * \param $text - text to download as file
 * \param $name - file name
 * \param $contentType - download file Content-Type
 * 
 * \return True on success, error message on failure.
 */
function DownloadString2File($text, $name, $contentType)
{
  $connstat = connection_status();
  if ($connstat != 0) return _("Lost connection.");

  session_write_close();
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
 * \param $upload_pk
 * 
 * \return uploadtree table name
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
 * \brief get Upload Name thourgh upload id
 * 
 * \param $upload_id - upload ID
 *
 * \return upload name
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
 * \brief get upload id through uploadtreeid
 *
 * \param $uploadtreeid - uploadtree id
 *
 * \return return upload id
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
 * \brief get 1st uploadtree id through upload id
 *
 * \param $upload - upload id
 *
 * \return return 1st uploadtree id 
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

/**
 * \brief execute a shell command
 *
 * \param $cmd - command to execute
 *
 * \return command results
 */
function DoCmd($Cmd)
{
  $Fin = popen($Cmd,"r");

  /* Read results */
  $Buf = "";
  while(!feof($Fin)) $Buf .= fread($Fin,8192);
  pclose($Fin);
  return $Buf;
}

?>
