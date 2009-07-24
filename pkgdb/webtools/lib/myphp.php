<?php 
//   functions to help out php                                                                                               
// Use this function to escape data from a get/post/cookie that is going into a db
function escslashes($str)
{
   if (!get_magic_quotes_gpc()) $str = addslashes($str);
   return ($str);
}


//  Function to parse lines like a=b
//  Return array with array[0]=key, array[1]=value
function get_line($line)
{
    $line = trim($line);
    if ($line == "") return 0;
    if ($line[0] == "#") return 0;
    $eqpos = strpos($line, "=");
    if ($eqpos !== FALSE)
    {
       $kwd  = trim(substr($line, 0, $eqpos));
       $value = trim(substr($line, $eqpos+1));
       return array($kwd, $value);
    }
    return 0;
}
                                                                                        
//  use this instead of parse_ini_file() to parse .cfg files since
//  parse_ini_file() has a problem with special characters like <>"
//  if keyword is set, only return that keyword value pair (and return).
//  if keyword is zero, return all keyword, value pairs (assoc array).
function parse_ini($file, $keyword=0)
{
    $farray = array();
    $lines = file($file);
    foreach ($lines as $line) {
       $pline = get_line($line);
       if (!is_array($pline)) continue;
       if (($keyword) && ($pline[0] == $keyword)) {
          $farray[$pline[0]] = $pline[1];
          return $farray;
       }
       $farray[$pline[0]] = $pline[1];
    }
    return $farray;
}

                                                                                
// convert an array's values to a string of comma seperated values
// array[0] = "one";
// array[1] = "two";
// returns "one, two"
function arr2str($arr)
{
   $str = "";
   $first = true;
                                                                                
   foreach ($arr as $item)
   {
      if ($first)
      {
         $str = $item;
         $first = false;
      }
      else
      {
         $str .= ','.$item;
      }
   }
   return $str;
}


// return the variable or blank if it doesn't exist
function valorblank($key, $array)
{
    if (array_key_exists($key, $array)) return $array[$key];
    return "";
}


/**  Evaluate a php file named fname and store it as a string
  *  Use the array $valarray to pass variables to fname
  */
function phpfile2str($fname, $valarray)
{
    ob_start();
    require $fname;
    $str = ob_get_clean();
    return $str;
}

?>
