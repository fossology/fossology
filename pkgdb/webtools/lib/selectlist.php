<?php
// This file contains the functions to write selection types of inputs.
                                                                                
// this is probably the most useful <select> function here.
// feed it an array from get_colsarray().
// print a select option list but use an array as the variables
// $useval = true to use the array value as the option value
// $useval = false to use the array key as the option value
function print_select_array($array, $name, $useval, $initval, $blankval=false)
{
   printf("<select name='%s'>", $name);
   if ($blankval) printf("<option value=''>\n" );

   foreach (array_keys($array) as $akey)
   {
      $val = $useval ? $array[$akey] : $akey;

      if ($val == $initval)
         printf("<option value='%s' selected>%s\n", $val, $array[$akey]);
      else
         printf("<option value='%s'>%s\n", $val, $array[$akey]);
   }
   print "</select>";
}

// multiselect version of print_select_array
function print_mselect_array($array, $name, $useval, $checked_array, $blankval=false, $cols=5)
{
   printf("<select name='%s' multiple size=$cols>", $name);
   if ($blankval) printf("<option value=''>\n" );
                                                                                
   foreach (array_keys($array) as $akey)
   {
      $val = $useval ? $array[$akey] : $akey;
                                                                                
      if (array_key_exists($val, $checked_array))
         printf("<option value='%s' selected>%s\n", $val, $array[$akey]);
      else
         printf("<option value='%s'>%s\n", $val, $array[$akey]);
   }
   print "</select>";
}


// print a select list based on table values
// the list item name is the column name ($col), and the value is the column value
function print_selectrows($table, $col, $errortag)
{
   $result = mysql_query("select distinct $col from $table order by $col")
               or die($errortag. mysql_error());
   $num_fields = mysql_num_fields($result);
   printf("<select name='%s'>", $col);
   printf("<option value=''>\n");
   while ($row = mysql_fetch_row($result))
   {
      printf("<option value='%s'>%s\n", $row[0], $row[0]);
   }
   print "</select>";
   mysql_free_result($result);
}

// same as print_selectrows except option value ($colval) can be a different
// column than the displayed option
// and an initial value can be specified
//$blankval true means to add a blank choice to the beginning of the choicelist
//$prefix is prefixed to the field name
function print_selectrows2($table, $col, $colval, $initval, $errortag, $blankval=false,
                           $prefix="")
{
   $result = mysql_query("select $col, $colval from $table order by $col")
               or die($errortag." ". mysql_error());
   $num_fields = mysql_num_fields($result);
   printf("<select name='%s%s'>", $prefix, $colval);

   if ($blankval) printf("<option value=''>\n" );

   while ($row = mysql_fetch_row($result))
   {
      if ($row[1] == $initval)
         printf("<option value='%s' selected>%s\n", $row[1], $row[0]);
      else
         printf("<option value='%s'>%s\n", $row[1], $row[0]);
   }
   print "</select>";
   mysql_free_result($result);
}


// same as input_text but a select option list based on a field set/enum vals
function input_optionlist($display, $table, $column, $initval,  $edit)
{
   $valarray = get_set_values($table, $column);
   input_optionarray($display, $valarray, $column, $initval, true, $edit);
}

// same as input_optionlist but you supply the array to use
// $display    First table field
// $valarray   Assoc array to use in the option list
// $column     List Name
// $initval    Initial list value
// $useval     true to use the array value as the option value
//             false to use the array key as the option value
// $edit       If true, write hidden field with old value
function input_optionarray($display, $valarray, $column, $initval, $useval, $edit,
                           $spaces=0, $blankval=false, $prefix="")
{
   printf("<tr>");
   if ($spaces)
   {
      print("<td>");
      for ($i=0; $i<$spaces; $i++) print "&nbsp;";
      print("</td>");
   }
   printf("<td ><b>$display</b></td>");

   printf("<td>");
   print_select_array($valarray, $prefix.$column, $useval, $initval, $blankval);
   printf("</td>");

   $field_def = select_col("field_def", "field_definition", "where field_name='$column'");
   $size = min(strlen($field_def)*8, 500);
   printf("<td width='$size'>%s</td>", $field_def);
   
   printf("</tr>");

   if ($edit) // save old values
      printf("<input type='hidden' name='%s' value='%s'>", "old".$prefix.$column, $initval);
}

//$blankval true means to add a blank choice to the beginning of the choicelist.
//$prefix is used to prefix an entity name (useful for resolving namespace conflicts).
// same as input_optionlist but a select option list on cols that aren't set/enum
function input_optionlist2($table, $col, $colval, $initval,  $edit, 
                           $display, $errortag, 
                           $spaces=0, $blankval=false, $prefix="")
{
   printf("<tr>");
   if ($spaces)
   {
      print("<td>");
      for ($i=0; $i<$spaces; $i++) print "&nbsp;";
      print("</td>");
   }
   printf("<td ><b>$display</b></td><td>");
   print_selectrows2($table, $col, $colval, $initval, $errortag, $blankval, $prefix);
   printf("</td></tr>");
   if ($edit) // save old values
      printf("<input type='hidden' name='%s' value='%s'>", "old".$prefix.$colval, $initval);
}

// todo: clean up all these darn optionlist functions.  This one is my latest attempt.
// Input field is displayed in a TABLE ROW
//   display : text to use in first field (first row field)
//   table   : table to select option list data from
//   listname: value for the option list NAME (name returned by POST)
//   valcol  : name of the column to get VALUE data (_POST[NAME] = VALUE)
//   txtarray: array of the names of columns to use as the display text for each option
//   initval : initial value of valcol
//   where   : any where clause.  In the form: "where ...."
//   errmsg  : text to display with any errors
function input_optionlist3($display, $table, $listname, $valcol, $txtarray, $initval, 
                           $where, $errmsg)
{
   if (!txtarray or !$valcol or !$txtarray or (count($txtarray) < 1)) return;

   $collist = arr2str($txtarray);

   printf("<tr><td ><b>$display</b></td><td>");
   
   $sql = "select  distinct $valcol , $collist from $table order by $collist";
   $result = mysql_query($sql) or die($errmsg." ". mysql_error());

   printf("<select name='%s'>", $listname);

   while ($row = mysql_fetch_row($result))
   {
      // combine cols into a single string
      $num_fields = mysql_num_fields($result);
      $str = "";
      $first = true;
      // start at col 1 to skip valcol
      for ($i=1; $i < $num_fields; $i++)  
      {
         if ($first) 
         {
            $str = $row[$i];
            $first = false;
         }
         else
            $str .= ', '.$row[$i];
      }

      if ($row[0] == $initval)
         printf("<option value='%s' selected>%s\n", $row[0], $str);
      else
         printf("<option value='%s'>%s\n", $row[0], $str);
   }
   print "</select>";
   mysql_free_result($result);

   printf("</td></tr>");
   printf("<input type='hidden' name='%s' value='%s'>", "old".$valcol, $initval);
}


// print a table of either radio buttons or checkboxes
// iname is "str", that becomes the name of the radio btn.
// Checkboxes are named str[$data_array]
// If data_array is array("one", "two", "three")
// then checked_array is array("two", "three")
function print_checkradio_table($data_array, $checked_array, $max_cols,
                        $inputtype="checkbox", $iname)
{
//print "<hr>checked array:<pre>";
//print_r($checked_array);
//print "</pre><hr>";
    echo "<table border=0 >\n";
    echo "<TR valign=top>";
                                                                                
    $i = 0;
    foreach ($data_array as $inputval)
    {
       echo "<TD>";
       if ($inputtype ==  "radio")
          $tclause = "name='$iname' value=\"$inputval\"";
       if ($inputtype ==  "checkbox")
          $tclause = "name=\"{$iname}[$inputval]\" ";
       if (array_key_exists($inputval, $checked_array))
           $checked = "CHECKED";
       else
           $checked = "";
                                                                                
       printf("<input type=$inputtype $tclause $checked>%s",
              $inputval);
       echo "</TD>";
       $i += 1;
                                                                                
       if (($i % $max_cols) == 0)
       {
           echo "</tr>";
           echo "<tr valign=top>";
       }
    }
       echo "</TR>";
    echo "</table>\n";
}


?>
