<?php
// input a text area, save old value as old.fname
function input_textarea($fname, $value, $rows=5, $cols=50 )
{
   printf("<textarea name='$fname' cols='$cols' rows='$rows'>");
   printf("%s</textarea>", $value);
   printf("<input type='hidden' name='%s' value='%s'>", "old".$fname, 
           htmlentities($value, ENT_QUOTES));
}

// input text, save old value as old.fname
function input_text($fname, $value, $size=5)
{
   printf("<input type=text name='%s' value='%s' size='%s'>",
           $fname, 
           htmlentities($value, ENT_QUOTES), $size);

   printf("<input type='hidden' name='%s' value='%s'>", "old".$fname, 
           htmlentities($value, ENT_QUOTES));
}

// Print a common three field table row: label, value, optional help
// This accepts an array for the value because that is typically where
// the value comes from.  This way, this fcn can do the valorblank()
// instead of the caller.
// NOTE: the valarray MUST contain $valarray["thisarrayname"]=the array name
function myinput_textrow($label, $valarray, $fname, $help="&nbsp;", $cols=32)
{
    $varrayname = $valarray["thisarrayname"];
    print "<tr><td>$label</td><td align=left>";
    $initval = stripslashes(valorblank($fname, $valarray));
    input_text("{$varrayname}[$fname]", $initval, $cols);
    print "</td><td align=left>$help</td></tr>";
}

function myinput_textarea($label, $valarray, $fname, $rows=5, $cols=50)
{
    $varrayname = $valarray["thisarrayname"];
    print "<tr><td valign='top'>$label</td><td colspan=2>";
    $initval = stripslashes(valorblank($fname, $valarray));
    input_textarea("{$varrayname}[$fname]", $initval, $rows, $cols);
    print "</td></tr>";
}
?>
