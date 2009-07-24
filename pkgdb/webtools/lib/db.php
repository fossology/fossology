<?php
// mysql error codes
$mysql_error_code = array("ER_DUP_KEY" => 1061, 
                          "ER_DUP_ENTRY" => 1062);

// connect to db and select a database
function db_connect($dbuser, $dbpwd, $dbname, $dbserver="localhost:3306")
{
   $db = mysql_connect($dbserver, $dbuser, $dbpwd)
       or die("Could not connect to database $dbname: " . mysql_error());
   mysql_select_db($dbname, $db)
       or die('Could not open database io_card '.mysql_error());

// 4-6-2004 causes apache segfault and 5 sessions to start on ldl (php 4.1.3)
   //session_start();

   // because include virtual x.php can't retrieve cookies, 
   // see if the sessid was passed via GET/POST
//   $sessid = $_REQUEST[$cookiename];
//   if (!$sessid) $sessid = $_COOKIE[$cookiename];

//   if (!$sessid)
//   {
//      $sessid = rand();
//      $exptime = time() + (60*60*24*30);   // 30 days
//      setcookie($cookiename, $sessid, $exptime, $cookiepath);
//   }

}


// test if table exists
// Return true/false
function table_exists($table)
{
   $exists = mysql_query("select count(*) from $table limit 1");
   if ($exists) return true;
   return false;
}


// return number of rows in a table
// where is in the form "where x=y"
function get_rowcount($tablename, $where="")
{
   $sql = "Select count(*) from $tablename $where";
   $result = mysql_query($sql)
               or die("get_rowcount($sql) Invalid query: ".mysql_error());
   $tablerows = mysql_result($result,0);
   mysql_free_result($result);
   return $tablerows;
}


// return a single row (assoc array) 
// where is an optional sql where clause:
//   $where == ""
//   $where == "where a=b"
function select_row($tablename, $where="", $cols="*")
{
   $sql = "Select $cols from $tablename $where";
   $result = mysql_query($sql)
               or die("selectrow() Invalid query: $sql<br>".mysql_error());
   $row = mysql_fetch_assoc($result);
   if (!$row) $row = array("");

   return $row;
}


// return a single column value (eg. box_name from box where box_id=5)
// colval must not be empty, if it is, return ""
// $where = "where a=b ..."
function select_col($tablename, $rtncol, $where)
{
   $sql = "Select $rtncol from $tablename $where";
   $result = mysql_query($sql)
               or die("select_col($sql) Invalid query: ".mysql_error());
   if (mysql_num_rows($result) > 0)
       $val = mysql_result($result,0);
   else
       $val = "";
   mysql_free_result($result);
   return $val;
}


// get the allowed values in an enum or set field
function get_set_values($table, $column)
{
   $result = mysql_query("describe $table $column")
               or die("get_set_values() Invalid query: ".mysql_error());
   $row = mysql_fetch_assoc($result);

   $str = $row['Type'];
   mysql_free_result($result);

   // str looks like "set('val1','val2')"
   // get rid of everything before the first and after the last parens
   $leftpos = strpos($str, "(");
   $rightpos = strpos($str, ")");
   $str = substr($str, $leftpos+2, $rightpos - $leftpos - 3);
   $str = str_replace("','", "|", $str);
   $valarray = explode("|", $str);
   return $valarray;
}

// very handy function to build where clauses
function build_where(&$init, &$where, $subq, $op='and')
{
//print "subq: $subq<br>";
    if ($init == false)
    {
         $init = true;
         $where = " where " . $subq;
    }
    else
         $where .= " $op "  . $subq;
}


// find the defined length of a column
// return 0 if there is no length
function get_col_len($table, $colname)
{
   $result = mysql_query("describe $table $column")
               or die("get_col_len() Invalid query: ".mysql_error());
   $row = mysql_fetch_assoc($result);

   $str = $row['Type'];
   mysql_free_result($result);

   // for columns with lenghts, $str looks like:
   //     "varchar(255)" or "int(10) unsigned
   // get rid of everything except what is in between the parens
   $leftpos = strpos($str, "(");
   $rightpos = strpos($str, ")");
   if (!$leftpos OR !$rightpos) return 0;

   $str = substr($str, $leftpos+1, $rightpos - $leftpos - 1);
   return $str;
}
                                                                                

//////////////////////////////////////////////////////////////////////
// return an array of all the values in a column
function getcolvals($table, $colname, $where="")
{
    $newarray = array();
    $sql = "select $colname from $table $where group by $colname order by $colname";
    $result = mysql_query($sql)
               or die("getcolvals($sql) Invalid query: ".mysql_error());
    while ($row = mysql_fetch_assoc($result)) $newarray[] = $row[$colname];
    mysql_free_result($result);
    return $newarray;
}

                                                                                
// return an associative array of column values
// where the key is $keycol, and the columns are in $valcolarray
// $valcolarray is an array of column names
// $where is a complete (or empty) sql WHERE clause ("where box_id = 3")
// (eg. all box_id => box_name, box_codename  from box)
// Typical usage:
//    $colsarray = array("codename", "box_name");
//    $valarray = get_colsarray("box", "box_id", $colsarray, "order by codename");
// Then use the output in print_select_array()
function get_colsarray($tablename, $keycol, $valcolarray, $where)
{
   $valcols = arr2str($valcolarray);
   $valarray = array();
                                                                                
   // col 0 must be key, cols 1... are values
   $sql = "Select $keycol, $valcols from $tablename ".$where;
   $result = mysql_query($sql)
               or die("get_colsarray() Invalid query $sql: ".mysql_error());
   $num_fields = mysql_num_fields($result);
                                                                                
   while ($row = mysql_fetch_row($result))
   {
      $valstr = $row[1];  // first value
      for ($i=2; $i < $num_fields; $i++)
      {
          if (strlen($row[$i]) > 1)
              $valstr .= ", " . $row[$i];  // remaining values
      }
//print "<hr>valarray[$row[0]] = $valstr<hr>";
      $valarray[$row[0]] = $valstr;
   }
   mysql_free_result($result);
   return $valarray;
}


// update a table row
// array key is the column name and the value is the column value
// return empty string on success and error msg on error
function update_row($tablename, $varray, $where)
{
    $setclause = "";
    foreach ($varray as $name => $value)
    {
        $val = escslashes($value);
        $setclause .= "," . $name . "= \"" . $val . "\"";
    }
    // remove initial comma
    $setclause = substr($setclause, 1);

    // update row
    $sql = sprintf("update $tablename set %s %s", $setclause, $where);
    $result = mysql_query($sql);
    if (!$result)
        $msg = "Update sql error: $sql<br>".mysql_error();
    else
        $msg = "";
    return $msg;
}


// convert an input time like 8:32 pm or 20:32 (see strtotime) to a db time (HH:MM:SS)
// an empty intime converts to 00:00:00
function input2dbtime($intime="", $inarray=array(), $inarraykey="")
{
    if (empty($intime)) 
    {
        if (array_key_exists($inarraykey, $inarray)) $intime = $inarray[$inarraykey];
    }
    if (empty($intime)) 
        return "00:00:00";
    else
        return date("G:i:s", strtotime($intime));
}


// convert an input date like 11/10 or nov 10 (see strtotime) to a db date (YYYY-MM-DD)
// pass this either a date string or an array and key
function input2dbdate($indate="", $inarray=array(), $inarraykey="")
{
    if (empty($indate)) 
    {
        if (array_key_exists($inarraykey, $inarray)) $indate = $inarray[$inarraykey];
    }
    if (empty($indate)) 
        return "0000-00-00";
    else
        return date("Y-m-d", strtotime($indate));
}
?>
