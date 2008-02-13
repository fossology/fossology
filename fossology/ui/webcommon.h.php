<?php
/***********************************************************
 webcommon.h.php
 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
***********************************************************/
require_once("pathinclude.h.php");
require_once("jobs.h.php");
require_once("common.h.php");

/**
 * shortcut for trim($_GET[$name])
 */
function get($name)
{
    $v = trim($_GET[$name]);
}

/**
 * create a form submit button
 *
 * @param string $label text to display on button
 * @param string $action action to take on button press, see handle()
 * @param string $value object to be operated upon by the action
 */
function submit($label, $action, $value)
{
    echo "<input type=submit name='a[$action.$value]' value='$label'>\n";
}

/**
 * draw the normal set of tabs highlighting the active one, $activetab
 *
 * @param string $activetab name of the active tab, see the code
 */
function drawtabs($activetab)
{
    $tabs = array("browse" => "browse",
    			"lic" => "licenses",
    			// "taint" => "kernel taint",
    			"prop" => "properties",
    			"op" => "operations",
    			// "wc" => "wc",
			);
		    // border-bottom:solid black 1px;
    echo "<div style='width:100%;
			padding: 0 0 0 2px;
			border-bottom: 1px solid black;
    			margin:0px;'>";

    echo "<span style='padding:0 20px 0 0;'></span>";
    foreach ($tabs as $tab => $text) {
	$color = ($activetab == $tab) ? "#ffff99" : "#cccccc";
	$bb = ($activetab == $tab) ? "#ffff99" : "black";
	echo "<span style='padding:0 10px 0 10px;
		    margin: 0;
		    border-width: 2px 2px 1px 2px;
		    border-style: outset outset solid outset;
		    border-bottom-color: $bb;
		    background:$color;
		    '>";
	$url = myname("t=$tab");
	echo "<a href=$url style='text-decoration:none'>$text</a>";
	echo "</span>";

	echo "<span style='padding-right: 10px;'></span>\n";
    }
    echo "<span align=right>
    	<a target=_top href='",
		myname("t=$activetab&fid=", "."),
		"' style='font-size:smaller;text-decoration:none;'>link to this page</a></span>\n";
    echo "</div>";
}


/**
 * display the normal project header yellow box with tabs
 *
 * Does not include the display of the path to the ufile being displayed.
 * See printpathbox() for that.
 * @param integer $uploadtree uploadtree record for the project to display
 * @param string $activetab active tab name.  no tabs displayed if empty (default)
 * @param integer $div defaults to 1, use 0 to suppress the yellow box
 */
function showuploadheader($info, $activetab="", $div=1)
{
    if ($div) 
    {
	    if ($activetab) {
	    drawtabs($activetab);
	    echo "<div style='background:#ffff99;
			    width: 100%;
			    padding:0 0 0 2px;
			    margin: 0;
			    border-style: none solid solid solid;
			    border-width: 0 1px 1px 1px;
			    border-color: black;
			    '>\n";
	    } else {
	    echo "<div style='background:#ffff99;border:solid 1px;padding:4px'>\n";
	    }
    }

    if (!empty($info) && (array_key_exists('upload_ts', $info)))
    {
        $displaydate = explode('.', $info['upload_ts']);
        echo "<br><i>{$info['upload_desc']} (Added to repository {$displaydate[0]}\n";
        echo ")</i><br>\n";
    }

    if (!empty($info) && (array_key_exists('upload_pk', $info)))
        $jobs = get_activeandrunningjobs($info['upload_pk']);

    if (is_array($jobs) && count($jobs) > 0) 
    {
//	    echo "<br><ul><b>Active jobs</b> ";
//    	foreach ($jobs as $j) 
//        {
//    	    echo "<li>",$j['job_name'], " queued: ", $j['job_queued'], "</li>";
//    	}
//        echo "</ul>";
	    echo "<table border=1 rules=none frame=box><th>Active job</th><th>Queued</th> ";
    	foreach ($jobs as $j) 
        {
    	    echo "<tr><td>$j[job_name]</td><td> $j[job_queued] </td></tr>";
    	}
        echo "</table>";
    }
}

/**
 * unused experimental obsolete function to attempt to choose
 * a ufile parent path appropriately.
 */
function u2projparent($u, $proj) {
    if (empty($proj)) die ("u2projparent($u, empty)");

    $pf = $u['pfile_fk'];
    if (empty($pf)) $pf = $u['pfile_pk'];
    // I am perhaps an unpacked container so could have multiple parents
    if (0 && uis_container($u) && uis_replica($u) && $pf) {
	$parent = db_queryall("SELECT ufile.*, containers.* FROM ufile
	    LEFT JOIN containers ON ufile_container_fk = contained_fk
	    WHERE pfile_fk = $pf
	    AND container_fk = $proj");
	printrecs($parent); die("die");
    } else {
	$parent = db_id2ufile($u['ufile_container_fk']);
    }
    return $parent;
}

/**
 * print the normal yellow box with tabs, project info, and ufile path
 *
 * @param string $otype  type of primary key (folder, upload, uploadtree)
 * @param int    $opk    primary key of type $otype
 * @param string $activetab active tab name.  no tab displayed if empty
 */
function printpathbox($otype, $opk, $activetab="" )
{
    $url = '';

    // Note: the order of the case statements must be from the most specific to the most general
    switch($otype)
    {
        case "uploadtree":
            if ($activetab == 'prop') 
                $artifacts = true;
            else
                $artifacts = false;
            $info = uploadtree2patha($opk, $artifacts);
//            debugprint_r("tree:", $info);
            $url = pathinfo2url($info);
            break;
        case "upload":
            $info = db_id2upload($opk);
//            debugprint_r("upload:", $info);
            $url = "<a href=" . myname("f=$info[folder_pk]&g=$info[upload_pk]") . ">$info[upload_filename]</a>";
            break;
        case "folder":
            $info = db_id2folder($opk);
//            debugprint_r("folder:", $info);
            $url = "<a href=" . myname("f=$info[folder_pk]") . ">$info[folder_name]</a>";
            break;
        default:
            log_writedie("fatal", "bad input, otype = $otype", "printpathbox");
    }

    $info = oo2info($otype, $opk);
    showuploadheader($info, $activetab, 1 );

    if (!empty($url))
	echo "$url";

    echo "</div>\n";
}


/**
 * Turn an  OO (Obj Type and Obj primary key) into an oinfo type (assoc array with everything it can 
 * find from upload, uploadtree and ufile tables.  Folders only return folder info.
 */
function oo2info($otype, $opk)
{
    $opk = intval($opk);
    switch($otype)
    {
        case "uploadtree":
            $sql = "select ufile.*, upload.*, uploadtree.* from ufile, upload, uploadtree
                    where uploadtree_pk=$opk
                      and uploadtree.ufile_fk=ufile.ufile_pk
                      and uploadtree.upload_fk=upload.upload_pk";
            break;
        case "upload":
            $sql = "select ufile.*, upload.*  from ufile, upload
                    where upload_pk=$opk
                      and upload.ufile_fk=ufile.ufile_pk";
            break;
        case "folder":
            $sql = "select *  from folder where folder_pk=$opk";
            break;
        default:
            log_writedie("fatal", "bad input, otype = $otype", "oo2info");
    }
    $oinfo = db_query1rec($sql);
    return $oinfo;
}


/**
 * Turn a path info into an html string with each node linked to a ufiletree record
 *
 * @param array $pathinfo 
 */
function pathinfo2url($pathinfo)
{
//debugprint_r("pathinfo", $pathinfo);

   $url = "";
   foreach($pathinfo as $info)
   {
       $url .= "<a href=". myname("h=$info[uploadtree_pk]") . ">". $info["ufile_name"] . "</a>";
       if (uis_container($info)) $url .= "/";
   }
   return $url;
}

/**
 * show an array of database records in an HTML table
 *
 * @param array $recs array of associative arrays as from db_queryall()
 * @param string $layout set to "vertical" if column names should be displayed in a vertical column, "horizontal" if they should be displayed in the top row, defaults to vertical
 */
function printrecs($recs, $layout="vertical")
{
    if (!is_array($recs) || count($recs) == 0) return;

    if ($layout == "vertical") {
	echo "<table border=1 cellspacing=0 cellpadding=0>\n";
	foreach ($recs as $r1) {
	    break;
	}
	foreach ($r1 as $r1n => $r1v) {
	    echo "<tr><th>$r1n</th>";
	    foreach ($recs as $rec) {
		echo "<td>", htmlspecialchars($rec[$r1n]), "</td>\n";
	    }
	    echo "</tr>\n";
	}
	echo "</table>\n";
    } else {
        echo "<table border=1 cellspacing=0 cellpadding=0>\n";
	echo "<tr>";
	foreach ($recs as $r1) {
	    foreach ($r1 as $r1n => $r1v) {
		echo "<th>$r1n</th>";
	    }
	    break;
	}
	echo "</tr>\n";
	foreach ($recs as $rec) {
	    echo "<tr>";
	    foreach ($r1 as $r1n => $r1v) {
		$v = htmlspecialchars($rec[$r1n]);
		if ($v == "") $v = "&nbsp;";
		echo "<td valign=top>$v</td>\n";
	    }
	    echo "</tr>\n";
	}
	echo "</table>\n";
    }
}

/**
 * show one database record in an HTML table
 *
 * @param array $rec associative array as from db_queryrec()
 * @param string $layout set to "vertical" if column names should be displayed in a vertical column, "horizontal" if they should be displayed in the top row, defaults to vertical
 */
function printrec($rec, $layout="vertical")
{
    if (!is_array($rec) || count($rec) == 0) return;

    if ($layout == "vertical") {
	echo "<table border=1 cellspacing=0 cellpadding=0>\n";
        foreach ($rec as $n => $v) {
	    $v = htmlspecialchars($v);
	    echo "<tr><th align=right valign=top>$n</th><td align=top>$v</td></tr>\n";
	}
	echo "</table>\n";
    } else {
        echo "<table border=1 cellspacing=0 cellpadding=0>\n";
	echo "<tr>";
        foreach ($rec as $n => $v) {
	    echo "<th>$n</th>";
	}
	echo "</tr>\n<tr>";
        foreach ($rec as $n => $v) {
	    $v = htmlspecialchars($v);
	    if ($v == "") $v = "&nbsp;";
	    echo "<td>$v</td>\n";
	}
	echo "</tr>\n";
	echo "</table>\n";
    }
}

/**
 * return path to pfile contents given pfile record and repository section
 *
 * executes the command reppath so isn't terribly fast
 * @param array $prec pfile record from database
 * @param string $repo file repository slice, defaults to "files"
 */
function reppath($prec, $repo="files")
{
    global $LIBEXECDIR;

    if (is_array($prec)) {
	$pname = $prec['pfile_sha1'];
	$pname .= "." . $prec['pfile_md5'];
	$pname .= "." . $prec['pfile_size'];
    } else {
        $pname = $prec;
    }

    exec("$LIBEXECDIR/reppath $repo $pname", $path);

    return $path[0];
}

/**
 * open a file in the repository
 *
 * returns a file handle from fopen() which should be checked by caller
 * @param array $prec pfile record
 * @param string $repo optional, defaults to "files"
 * @param string $mode optional fopen mode, defaults to "r"
 */
function repopen($prec, $repo="files", $mode="r")
{
    if (is_array($prec)) {
	$pname = $prec['pfile_sha1'];
	$pname .= "." . $prec['pfile_md5'];
	$pname .= "." . $prec['pfile_size'];
    } else {
        $pname = $prec;
    }

//log_write("debug", "pname is $pname", "repopen");
    $path = reppath($pname, $repo);
    $cat = fopen($path, $mode);
    return $cat;
}

/**
 * close a file opened with repopen()
 */
function repclose($cat)
{
    fclose($cat);
}

/**
 * send an unprocessed pfile to the user's web browser
 *
 * @param array $prec pfile record
 * @param string $repo optional, defaults to "files"
 */
function repcat($prec, $repo="files")
{
    $pname = $prec['pfile_sha1'];
    $pname .= "." . $prec['pfile_md5'];
    $pname .= "." . $prec['pfile_size'];

    $cat = repopen($pname, $repo);
    fpassthru($cat);
    repclose($cat);
}

/**
 * display a binary file
 * 
 * @param array $prec pfile record
 * @param string $repo optional, defaults to "files"
 */
function repcatb($prec, $repo="files")
{
    $path = reppath($prec, $repo);

    $cat = popen("dd if=$path bs=4k count=1 | hexdump -C  | sed -e 's/&/&amp;/g' -e 's/</&lt;/g'", "r");
    fpassthru($cat);
    pclose($cat);
}

/**
 * return decent HTML colors
 *
 * given a small, usually incrementing integer, $n, return an HTML color
 * of the form #xxxxxx which can be used as a background with black text
 * and which is distinguishable from colors returned by adjacent values of $n
 */
function colorlist($n)
{
    static $x = array ("#ffff66",
			"#ffccff",
    			"#ffcccc", 
			"#ccffff",
			"#ccffcc",
			"#ccccff",
    			"#cccc99",
			"#cc99cc",
			"#cc9999",
			"#99cccc",
			"#99cc99",
			"#9999cc",
			"#999966",
			"#996666",
			"#666699",
			"#669999",
			"#9999cc",
			"#99cccc",
			"#ccccff");

    return $x[$n % 12];
}

/******
function orphanpfiles()
{
    $result = db_query("SELECT * FROM pfile
    				LEFT JOIN ufile ON ufile.pfile_fk = pfile_pk
				WHERE ufile_pk IS NULL");

    header("content-type: text/plain");
    echo "Orphan Pfiles\n\n";
    while ($row = pg_fetch_array($result)) {
        echo $row['pfile_pk'], "\t",
		$row['pfile_sha1'], ".",
		$row['pfile_md5'], ".",
		$row['pfile_size'], "\n";
    }

    pg_free_result($result);
}
*****/

function popup_link($text="", $url="") {
    if (empty($url)) {
?>
<SCRIPT LANGUAGE="JavaScript">
<!-- Idea by:  Nic Wolfe (Nic@TimelapseProductions.com) -->
<!-- Web URL:  http://fineline.xs.mw -->

<!-- This script and many more are available free online at -->
<!-- The JavaScript Source!! http://javascript.internet.com -->

<!-- Begin
function popUp(URL) {
day = new Date();
id = day.getTime();
eval("page" + id + " = window.open(URL, '" + id + "', 'toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=1,width=400,height=400,left = 600,top = 300');");
}
// End -->
</script>

<?php
    } else {
        echo "<a href=\"javascript:popUp('$url')\">$text</a>";
    }
}


/**
 * url referring to this page with optional changes to GET args and basename
 *
 * Returns a relative URL which can be used to refer to the current page
 * including GET args.  Both the page name and GET args may be changed.
 * @param string $getargs optional URL-style string name1=value1&name2=value2(etc)
 *   changes or sets all occurances of name1 and name2 in the current page's
 *   GET arguments to value1 and value2.  If a value is blank, the correspond
 *   name is removed from the current page's GET arguments.
 * @param string $myname usually the base name of the current page is used
 *   in the relative URL.  Use optional $myname to get another behavior.
 */
function myname($getargs="", $myname="")
{
    if (empty($myname))
	$myname = basename($_SERVER['SCRIPT_NAME']);
    $tmp = $_GET;
    if (!empty($getargs)) {
	foreach (explode('&', $getargs) as $nv) {
	    list($n, $v) = explode('=', $nv);
	    if ($v == "") {
		unset($tmp[$n]);
	    } else {
		$tmp[$n] = $v;
	    }
	}
    }
    $connect = '?';
    foreach ($tmp as $n => $v) {
	$myname .= "$connect$n=$v";
	$connect = '&';
    }

    return $myname;
}

/**
 * emit an HTML reference to another "object" handled by the current page
 *
 * For example, obj('u', '888', 'foo') emits &lt;a href=XXX>foo</a> where
 * XXX is a link to the current page with o=u.888 (a reference to ufile 888)
 * instead of the current page's object
 */
function obj($type, $v, $text)
{
    echo "<a href='";
    echo myname("o=$type.$v");
    echo "'>$text</a>";
}

/**
 * reload this page or another page from an HTML body with javascript
 *
 * You can use this even if HTTP headers have already been emitted, and
 * it is easier to use than header("location: foo") because relative
 * links are allowed and changes may be made.
 * @param string $getargs optional, see myname()
 * @param string $myname optional, see myname()
 * @param boolean $nav optional, default=false, set to true to reload left
 *   left navigation frame
 */
function goto($getargs="", $myname="", $nav=false)
{
    echo "<script language=javascript>";
    if ($nav) echo "window.parent.treeframe.location.reload();";
    echo "window.location = '" . myname($getargs, $myname) . "';
		</script>";
    exit();
}

/**
 * replace an assoc array such as $_GET[] with it's unquoted form
 *
 * if PHP inserts backslash characters into an array, remove them.
 * @param array@ $a array which is modified in place such as $_GET[]
 */
function unslash(&$a) {
    if (!get_magic_quotes_gpc())
        return;

    if (!is_array($a)) {
        $a = stripslashes($a);
        return;
    }

    $keys = array_keys($a);
    foreach ($keys as $key) {
        unslash($a[$key], $html);
    }
}

/**
 * remove PHP-added slashes from GET, POST, and COOKIE arrays
 */
function unslash_gpc() {
    $tmp = array(&$_GET, &$_POST, &$_COOKIE);
    unslash($tmp);
}

unslash_gpc();
$uid = 'user@hp.com';
$isadmin = true;

/**
 * Print a variable (of any type, including arrays)
 * Use log functions if  you want  logging
 */
function debugprint_r($label, $var)
{
     echo "<pre>$label: "; print_r($var); echo "</pre>";
     flush();
}


/** 
 * Get the object type ("upload", "uploadtree", "folder") and the
 * object primary key (upload_pk, etc)
 * from the _GET args
 */
function getotk()
{
    $retvals = array();
    if (array_key_exists('h', $_GET)) $retvals["uploadtree"] = intval($_GET['h']);
    else if (array_key_exists('g', $_GET)) $retvals["upload"] = intval($_GET['g']);
    else if (array_key_exists('f', $_GET)) $retvals["folder"] = intval($_GET['f']);
    return $retvals;
}


/**
 * Multiple selection pull down
 * @param mixed array  $sdata text to display
 * @param string       $field name
 * @param boolean      $useval true to use array value as the option value,
 *                             false array key is option value
 * @param mixed array  $checked_sdata data to init as checked
 * @param boolean      $blankval true to have a blank as an option value
 * @param int          $fsize - field size in number of characters
 */
function print_mselect_array($sdata, $name, $useval, $checked_sdata, $blankval=false, $fsize= 15)
{
   printf("<select name='%s' multiple size=$fsize>", $name);
   if ($blankval) printf("<option value=''>\n" );

   foreach (array_keys($sdata) as $akey)
   {
      $val = $useval ? $sdata[$akey] : $akey;

      if (in_array($val, $checked_sdata))
         printf("<option value='%s' selected>%s\n", $val, $sdata[$akey]);
      else
         printf("<option value='%s'>%s\n", $val, $sdata[$akey]);
   }
   print "</select>";
}


/**
 *  Convert seconds to days, hours, minutes, seconds, in the form nnd:nnh:nnm:nns
 *  Days, hours, minutes are only returned if non zero.
 *  @param int     $secs  seconds
 */
function secs2dhms($secs)
{
    $days = (int)($secs / (24*60*60));
    if ($days) $outtime = "{$days}d";
    $secs -= (int)($days * (24*60*60));
    $hrs = (int)($secs / (60*60));
    if ($hrs) $outtime .= "{$hrs}h";
    $secs -= (int)($hrs * (60*60));
    $min = (int)($secs / 60);
    if ($min) $outtime .= "{$min}m";
    $secs -= (int)($min * 60);
    $outtime .= "{$secs}s";
//    return("{$days}d; {$hrs}h; {$min}m; {$secs}s");
    return ($outtime);
}
?>
