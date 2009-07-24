<?php
require_once "lib/openauth.php";

/**
 *  given the $_SERVER[REQUEST_URI], determine the real uri 
 *  using DirectoryIndex
 *
 *  Return:
 *     ..NOEXIST    if the url doesn't exist
 *     {uri}        if the uri exists
 */
function geturi()
{
    global $mime_type;

    $uri = $_SERVER["REQUEST_URI"];
    $uri_info = apache_lookup_uri($uri);
    $uri = $uri_info->uri;
    
//print "uri_info:<br><pre>";
//print_r ($uri_info);
//print "</pre>";
    // if this is a directory, make sure there is a trailing slash
    $lastchar = $uri{strlen($uri)-1};
    if (strstr($uri_info->content_type, "directory") &&
        ($lastchar != '/'))
        $uri .= '/';
    
    if (file_exists($uri_info->filename)) 
        $tmime_type = $uri_info->content_type;
    else
        return "..NOEXIST";

    $uuri = $uri_info->unparsed_uri;
    $lastchar = $uuri{strlen($uuri)-1};
    if ($lastchar == '/')
       $mime_type = "text/html";
    else
       $mime_type = $tmime_type;

//print "mime_type = $mime_type<br>";
    return $uri;
}

//////////////////   main   ////////////////////
$mime_type = "text/html";
session_start();

// user must authenticate
$pemdir = "file:///etc/spas/openauth/";
$pempath = $pemdir . $_SERVER["SERVER_NAME"];
auth($pempath);

// read "access" authorization file
// "access" file: white space is ignored, only the first field is used
$access_dir = $_SERVER["DOCUMENT_ROOT"] . $_SERVER["REQUEST_URI"];
$access_dir = rtrim($access_dir, "/");
if (!is_dir($access_dir)) $access_dir = dirname($access_dir);

$access_filepath = $access_dir . "/access";

// if access doesn't exist, continue looking for an access file up the
// parent hierarchy.  If no access file exists, then grant the user access
// (authentication is good enough).
$lastslash = 0;
while (!file_exists($access_filepath))
{
//print "access_dir: $access_dir<br>";
//print "access_filepath: $access_filepath<hr>";
    $prev = $lastslash;
    $lastslash = strrpos($access_dir, "/");
    if ($lastslash === false) break;
    if ($prev == $lastslash)
    {   // this should never happen
        print "infinite loop terminated, prevslash=$prev, lastslash=$lastslash<br>";
        print "access_dir: $access_dir<br>";
        print "access_filepath: $access_filepath<br>";
        phpinfo();
        exit();
    }

    $access_dir = substr($access_dir, 0, $lastslash);
    $access_filepath = $access_dir . "/access";
    if ($access_dir == $_SERVER["DOCUMENT_ROOT"]) break;
}

if (file_exists($access_filepath))
{
$access_array = file($access_filepath);
foreach ($access_array as $key =>$line)
{
    sscanf($line, "%s", $access_array[$key]);
}

// is sea in access_array?
if (!in_array($_SESSION["sea"], $access_array))
{
    print "Sorry - you don't have access to this directory";
    exit();
}
}

$uri = geturi();
switch($uri)
{
    case "..INDEX":
          echo "sorry no directory index is allowed";
          break;
    case "..NOEXIST":
          print "Sorry, the URL you are requesting:<br>";
          print "{$uri_info->uri} does not exist<br>";
          break;
    default:
          header("Content-type: $mime_type");
          virtual($uri);
}

?>
