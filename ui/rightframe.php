<?php
/***********************************************************
 rightframe.php
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
require_once("webcommon.h.php");
require_once("jobs.h.php");
require_once("del.h.php");

/*************************** DEBUG CODE
if ($_GET["die"]) {
    echo "<pre>";
    echo "GET: "; print_r($_GET);
    echo "\nPOST: "; print_r($_POST);
    die();
}
 ***************************/

/**
 * Most or all of the functions which go into the right-hand frame in the
 * current repo browser.  Routines which are more likely to be useful from
 * multiple web applications are in webcommon.h.php
 */

/**
 * GUI HANDLER to move project $u to new parent project $parent
 *
 * several steps are involved in the interaction to move a project
 * via the GUI.  This is the handler for the final step.
 *
 * BUG: this routine does no error checking thus you can move ufiles
 * which are not projects and maybe other bad things.
 *
 * @param ufile_pk $u
 * @param ufile_pk $parent
 */
function projmove($u, $parent)
{
    global $fid, $activetab;

    // die( "projmove($srcp, $type, $dest, $copyflag)");

    if (empty($_POST['cancel'])) {
	if ($parent != $u)
	    db_query("UPDATE ufile SET ufile_container_fk = $parent WHERE
		    ufile_pk = $u");
    }

    // remove cookie
    setcookie($fid, $activetab);
    goto('', '', true);
}


/**
 * emit the 'add a file' HTML form
 *
 * @param ufile_pk $p (project) folder to which file will be added
 */
function addfile_form($info)
{
    popup_link();
//    showprojheader($p, '', 1);
    showuploadheader($info, $activetab="", $div=1);

    echo "</div>\n";
    ?>
	<form method='POST' enctype='multipart/form-data'>
	<h1>Upload a File (4 steps)</h1>
	<table width="90%" align="center" cellspacing="0" cellpadding="3" border="1">
    <!-- 700M limit for uploads -->
    <input type="hidden" name="MAX_FILE_SIZE" value="734003200">
	<tr>
	<th valign=top align=left>Step 1<br>Upload file</th>
	<td>
	<i>Option A</i> -  from My Computer:<br>
	<font size="1">700MB limit; If your file is larger than 700MB you must use "Option B - from the Internet" below.</font><br>
	<input type='file' name='file' size=60><br>
	<br>
	<i>Option B</i> -  from the Internet:<br>

	<font size="1">Example, "ftp://ftp.hp.com/directory/file.zip" or "http://www.hp.com/directory/file.zip"</font><br>
	<font size="1">HTTP, HTTPS, and anonymous FTP are all permitted.</font><br>	
	<input type='text' name='url' size=60><br> 
	</td>
	<tr>
	<td valign="top"><b>Step 2<br>Additional information</b></td>
	<td>
	Description (optional)
	<br><input type=text name=descr size=40>
	<br> Viewable file name (optional)
	<br><input type=text name=name size=40>

	</td>
	</tr>
	<tr>
	<td valign="top"><b>Step 3<br>Request reports</b></td> 
	<td>
    Check which reports to produce for this file: (optional)<br>
	<input type=checkbox checked name=license value=1>
	<?php popup_link("License Analysis", "../help.html#lic"); ?><br>

	</td>
	</tr>
	<tr>
	<td valign="top"><b>Step 4<br>Begin upload</td>
    <?php
	echo "<td>";
	submit('Begin Upload', 'upload', $p);
	echo "</tr></table>";
}


/**
 * GUI HANDLER for adding a file-project
 *
 * User has either specified a URL or done a form-based file upload.
 * Create a fileproj to hold the new file and schedule an unpack
 * job, and wget and license jobs if appropriate.
 *
 * BUG? When a URL is specified there is a period of time between
 * when the fileproj is created and the wget agent completes when
 * the fileproj has no pfile attached yet.  This makes it appear
 * like a normal folder and the GUI may allow creating subfolders within
 * it which is a very bad thing.
 *
 * @param ufile_pk $p project (folder) to parent the user's new file-proj
 */
function upload($parent)
{
    global $uid, $VARDATADIR, $AGENTDIR;

    $upurl = trim($_POST['url']);
    $file = $_POST['file'];
    $name = trim($_POST['name']);
    $descr = trim($_POST['descr']);
    $jq = "";
    @mkdir($VARDATADIR, 0777);

    if (!empty($upurl)) 
    {
	    if (empty($name)) $name = basename($upurl);
    	if (empty($name)) $name = $upurl;
        $mode = 1<<3;
    	$upload_fk = createuploadrec($parent["folder_pk"], $name, $descr, $upurl, $mode);
        if ($upload_fk < 0)
        {
            echo "Duplicate upload is not allowed.";
            return;
        }

    	// tmp upload file
    	$tmpfile = $VARDATADIR . "/" . uuid();

    	$jq = job_create_wget($upload_fk, $tmpfile, $upurl);
    	$jq = job_create_unpack($upload_fk, $upurl, $jq);
    	job_create_defaults($upload_fk, $jq);
    }
    else
    if ($_FILES['file']['error'] == UPLOAD_ERROR_OK
    		&& is_uploaded_file($_FILES['file']['tmp_name'])) 
    {
	    $f = $_FILES['file'];
	
    	if ($f['error'] == UPLOAD_ERROR_OK && is_uploaded_file($f['tmp_name'])) 
    	{
    	    $tmp = $VARDATADIR . "/" . uuid();

    	    move_uploaded_file($f['tmp_name'], $tmp);
    	    chmod($tmp, 0666);

    	    if (empty($name)) $name = $f['name'];

            $mode = 1<<2;
    	    $upload_fk = createuploadrec($parent["folder_pk"], $name, $descr, $f['name'], $mode);
            if ($upload_fk < 0)
            {
                echo "Duplicate upload is not allowed.";
                return;
            }

    	    $lastline = exec("$AGENTDIR/webgoldimport {$upload_fk} $tmp '{$f['name']}' 2>&1", $out, $e);
            if ($e)
            {
                $execerror = "exec error: ".$lastline." output: ".$out;
                log_write("debug", $execerror, "rightframe.upload");
            }

    	    $jq = job_create_unpack($upload_fk, $f['name']);
    	} 
        else 
        {
	        $fdata->append($myfile, "upload", "FAILED");
	    }

	    job_create_defaults($upload_fk, $jq);

    }
    else
    {
        $uploaderror = sprintf("file upload error: %d", $_FILES['file']['error']);
        log_write("debug", $uploaderror, "rightframe.upload");
    }

    goto("o=u.{$upload_fk}.$parent", '', true);
}

/**
 * calculate the percent license match from an agent_lic_meta record
 */
function lic_match($lrec)
{
    $total = max($lrec['tok_license'], $lrec['tok_pfile']);
    $match = $lrec['tok_match'] / $total;
    return (int)(100 * $match + 0.5);
}

/**
 * display a non-container ufile
 *
 * displays a non-container file's header info and possible other
 * info depending on the active tab.  Calls showfilecontents() to
 * display file contents, optionally with license highlighting
 * @param mixed record $urec is a combo of ufile, and upload recs
 * @param string $activetab (optional)
 */
function showufile($urec, $activetab="")
{
    $fname = $urec['ufile_name'];
    if ($urec['pfile_fk']) $prec = db_id2pfile($urec['pfile_fk']);
    if ($activetab == 'prop') 
    {
	     printrec($urec, "horizontal");
     	if ($prec) printrec($prec, "horizontal");
    }

    if ($prec) 
    {
        $pfurl = myname("o=pf." . $urec['pfile_fk'], "rightframe.php/{$urec['ufile_name']}");
        echo "(<a href='$pfurl'>download</a>)<br>\n";
        if ($activetab == 'lic') 
        {
            $lrecs = db_queryall(
		           	"SELECT tok_pfile, tok_match, version, phrase_text,
           				tok_pfile_start, tok_pfile_end,
           				tok_license_start, tok_license_end, tok_license,
           				lic_name, lic_pk, pfile_path, license_path FROM agent_lic_meta
           			LEFT JOIN agent_lic_raw
           			    ON agent_lic_meta.lic_fk = agent_lic_raw.lic_pk
           			WHERE pfile_fk = {$urec['pfile_fk']}
           			ORDER BY tok_pfile_start");
            // printrecs($lrecs, "horizontal");
	        if (is_array($lrecs) && count($lrecs) > 0) 
            {
                echo "<center><table>
                      <tr>
                      <th>Match %</th>
                      <th></th><th></th>
                      <th align=left>License</th>
                      </tr>\n";

                foreach ($lrecs as $n => $lrec) 
                {
                    $match = lic_match($lrec);  // calculate the license match percentage
                    $color = colorlist($n);     // get the next color to use (from circular list)
                    $name  = $lrec['lic_name'];
		    $name = strrchr($name,'/');
		    if ($name) $name=substr($name,1);
		    else $name  = $lrec['lic_name'];
                    if (!empty($lrec['phrase_text'])) 
                    {
                        $name = $lrec['phrase_text'];
                    } 
		    $name = str_replace("&","&amp;",$name);
		    $name = str_replace("<","&lt;",$name);
		    $name = str_replace(">","&gt;",$name);
                    echo "<tr bgcolor='$color'><td align=right>$match</td>\n
                          <td><a href='#$n' style='color:black'>view</a></td>
                          <td>";
                    if (empty($lrec['phrase_text'])) obj('lic', $lrec['lic_pk'], 'ref');
                    echo "</td>
                          <td>$name</td>
                          </tr>\n";
                }
                echo "</table></center>\n";
            }

            if ($lrecs) showfilecontents($prec, $lrecs);
        } 
        else 
        {
            showfilecontents($prec);
        }
    }
}

/**
 * display a license's text
 *
 * Pretty stupid function which prints the license text from the
 * agent_lic_raw table.
 *
 * @param lic_pk $lic license ID
 */
function showlic($lic) {
    $lic = db_query1rec("SELECT * FROM agent_lic_raw WHERE lic_pk = $lic");
    $name = $lic['lic_name'];
    $name = strrchr($name,'/');
    if ($name) $name=substr($name,1);
    else $name  = $lic['lic_name'];

    if ($lic['phrase_text']) echo "<h1>{$name}</h1>\n";
    else echo "<h1>{$name}: {$lic['phrase_text']}</h1>\n";
    echo "<pre>";
    echo htmlentities($lic['lic_text']);
}

/**
 * if $u is a replica tree, return the master tree's $u
 *
 * @param ufile_record $u
 */
function unreplicate($u) {
    if (uis_replica($u)) {
	$u = db_query1rec("SELECT * FROM ufile
			    WHERE pfile_fk = {$u['pfile_fk']}
			    ORDER BY ufile_pk
			    LIMIT 1");
	if (empty($u)) {
	    die ("ERROR! replica with no master found!");
	}
    }
    return $u;
}

/**
 * main handler for displaying requested data in the right frame, typically file data
 *
 * @param string $otype   (required)  Obj type, either "upload", "uploadtree", or "folder"
 * @param int    $opk     (required)  Obj primary key (upload_pk, or uploadtree_pk or folder_pk)
 * @param string $activetab (optional) Tab name "lic", "browse, "op", ...
 */
function showu($otype, $opk, $activetab="")
{
    global $lastmsg;

    $showlic = trim($_GET['showlic']); unset($_GET['showlic']);

    echo "<form method=post>\n";
    printpathbox($otype, $opk, $activetab );

    $oinfo = oo2info($otype, $opk);
    
//    if ($otype != "uploadtree")
    if (uis_container($oinfo) or ($otype == "folder"))
    {
	    switch ($activetab) 
        {
           	case 'op':
          	    if (uis_proj($origuploadree)) 
                {
	           	    echo "<br>"; submit('Move...', 'move', $origuploadree['ufile_pk']);
// echo "<div align=right>"; submit('DELETE IMMEDIATELY', 'delete', $ufile_fk); echo "</div>";
    	        }
                if (!empty($oinfo['folder_pk']))
                {
               	 	echo "<hr>"; 
                    submit('Upload File...', "add", $oinfo['ufile_pk']);

		            // Add Folder needs to be in a separate <form> because
                    // sometimes people enter the text and hit [return] and this
                    // needs to map to the New Folder submit button, not one
                    // of the other buttons!
                    echo "</form><hr>";
                    if (!empty($lastmsg)) 
                    {
                        echo "<b> $lastmsg </b>";
                    }
                    echo "<form name='addfolder' method=post>\n";
                    echo "<table bgcolor=#FFBBBB>";
                    echo "<TR><TH colspan=2 align=center>Create New Folder</TH></TR>";

                    echo "<TR><TD>Name</TD>";
                    echo "<TD><input type=text name='foldername' value=''></TD></TR>";

                    echo "<TR><TD>Description</TD>";
                    echo "<td><input type=text name='description' size=50 value=''></td></tr>\n";

                    echo "<TR><TD colspan=2 align=center>";
                    submit(' New Folder &rarr; ', "newfolder", $oinfo['folder_pk']);
                    echo "</TD></TR>";
                    print "</table>";
                    print "</form>";

                    echo "<hr>";

                    if (!empty($oinfo["folder_name"]))
                    {
                        // general form
                        echo "<form name='ops' method=post>\n";
                        echo "<br>";
                        submit(" DELETE Folder: $oinfo[folder_name]", "cfdelete", $oinfo['folder_pk']);
                        print "</form>";
                    }
                }
                else  // upload is selected in left nav
                {
                    // general form
                    if (!empty($oinfo["ufile_name"]))
                    {
                        echo "<form name='ops' method=post>\n";
                        echo "<br>";
                        submit(" DELETE File: $oinfo[ufile_name]", "cudelete", $oinfo['upload_pk']);
                        print "</form>";
                    }
                }
                break;
	        case 'lic':
	            lic_summary_u($oinfo, $showlic);
            default:
                if (uis_container($oinfo) or ($otype == "folder"))
                    showdir($otype, $opk, "", $activetab);
                else
                    showufile($oinfo, $activetab);
    	}
	}
    else
    {   // not a container
        showufile($oinfo, $activetab);
    }
    echo "</form>\n";
}


/*
 * Display repository text file with license matches shaded in color
 *
 * Can the same text bytes be identified with more than one license - No.
 * Can multiple identified licenses overlap? - No.
 *
 * @param string $prec (required) pfile record array
 * @param array $licrecs  agent_lic_meta  record
 */
function showtextcoloredlicenses($prec, $licrecs)
{
//debugprint_r("prec", $prec);
//debugprint_r("licrecs", $licrecs);
    $context = 2048; $halfcontext = $context / 2;
    // use small context sizes for more convenient testing...
    // $context = 100; $halfcontext = $context / 2;

    $f = repopen($prec) or die ("showtextcoloredlicenses: Cannot open file $prec");
    $here = 0;
    $licensererun = false;
    foreach ($licrecs as $n => $l) 
    {
        // loop through each pfile_path 
//debugprint_r("l", $l);
        if (empty($l['pfile_path']))
        {
            $licensererun = true;
            echo "Sorry, your license metadata is out of date.  Please rerun analysis.<br>";
        }
        else
        {
            $rangearray = explode(",", $l['pfile_path']);
            foreach($rangearray as $byterange)
            {
                $byterangearray = explode("-", $byterange);
                $start = $byterangearray[0];
                if (array_key_exists(1, $byterangearray))
                    $end = $byterangearray[1];
                else
                    $end = $start;
            
        	if ($start - $here > $context) 
            {
	            if ($n > 0) 
                {
		            $s = fread($f, $halfcontext);
            		echo htmlspecialchars($s);
    	        	echo "[...]\n</pre><pre style='background:#ffffff;'>";
    	        }
    	        echo "[...]<hr>";
    	        fseek($f, $start - $halfcontext);
    	        $s = fread($f, $halfcontext);
    	        echo htmlspecialchars($s);
    	    } 
            else 
            {
	            while (($here = ftell($f)) < $start) 
                {
    		        $s = fread($f, $start - $here);
            		echo htmlspecialchars($s);
	            }
	        }
        	// echo "<b>XXXXX-", ftell($f), "-XXXXX</b>";
        	$color = colorlist($n);
        	echo "<span style='background:$color'><a name=$n>";
        	while (($here = ftell($f)) < $end) 
            {
        	    $s = fread($f, $end - $here);
        	    echo htmlspecialchars($s);
        	}
	        echo "</a></span>";
    	    // echo "<b>XXXXX-", ftell($f), "-XXXXX</b>";
            }
        }
    }

    if ($licensererun == false)
    {
        $end += $halfcontext;
        while (!feof($f) && ftell($f) < $end) 
        {
            $s = fread($f, $halfcontext);
            echo htmlspecialchars($s);
        }
        if (!feof($f)) 
        {
        	echo "[...]<hr>\n";
        }
    }
    repclose($f);
}

define("BYTESPERLINE", 80);

function bindump($f, $start, $length, $pad='both')
{
    $lstart = $start; $lend = $start + $length;

    if ($pad == 'both' || $pad == 'start') {
	$lstart = BYTESPERLINE * (int)($start / BYTESPERLINE);
    }
    if ($pad == 'both' || $pad == 'end') {
	$lend = BYTESPERLINE * (int)(($start + $length + BYTESPERLINE - 1) / BYTESPERLINE);
    }

    //echo "<b>bindump($start, $length, $pad, $lstart, $lend)</b>";

    fseek($f, $lstart);
    while (!feof($f) && ($here = ftell($f)) < $lend) {
        $s = fread($f, $lend - $here);
	$n = strlen($s);
	// XXX this can be done faster with str_replace
	// XXX this is ASCII specific -- what's the right way?
	for ($i = 0; $i < $n; $i++) {
	    if (($here + $i) % BYTESPERLINE == 0) {
		printf("\n<span style='background:white;'>%05d </span>", $here + $i);
	    }
	    $byte = ord($c = $s[$i]);
	    if ($byte < 32 || $byte > 126) {
		$c = '&bull;';
	    } else if ($c == '<') {
		$c = '&lt;';
	    } else if ($c == '&') {
		$c = '&amp;';
	    }
	    echo $c;
	}
	$here += $i;
    }

    return $here;
}

function showbinarycoloredlicenses($prec, $licrecs)
{
    $context = BYTESPERLINE * 40; $halfcontext = $context / 2;
    // use small context sizes for more convenient testing...
    $context = BYTESPERLINE * 10; $halfcontext = $context / 2;

    echo "<pre><span style='background:#ffffff'>";
    $f = repopen($prec) or die ("showbinarycoloredlicenses: Cannot open file");
    $here = 0;
    $licensererun = false;
    foreach ($licrecs as $n => $l) 
    {
        if (empty($l['pfile_path']))
        {
            $licensererun = true;
            echo "Sorry, your license metadata is out of date.  Please rerun license analysis.<br>";
        }
        else
        {
            // loop through each pfile_path 
            $rangearray = explode(",", $l['pfile_path']);
            foreach($rangearray as $byterange)
            {
                $byterangearray = explode("-", $byterange);
                $start = $byterangearray[0];
                if (array_key_exists(1, $byterangearray))
                    $end = $byterangearray[1];
                else
                    $end = $start;
    
        	    if ($start - $here > $context) 
                {
	                if ($n > 0) 
                    {
		                $here = bindump($f, $here, $halfcontext, 'end');
	                }
        	        echo "\n<span style='background:white;'>[...]<hr></span>";
        	        $here = bindump($f, $start - $halfcontext, $halfcontext, 'start');
        	    } 
                else 
                {
	                $here = bindump($f, $here, $start - $here, '');
	            }
  
        	    // echo "<b>XXXXX-", ftell($f), "-XXXXX</b>";
        	    $color = colorlist($n);
        	    echo "<span style='background:$color'><a name=$n>";
        	    $here = bindump($f, $start, $end - $start, '');
        	    echo "</a></span>";
        	    // echo "<b>XXXXX-", ftell($f), "-XXXXX</b>";
            }
        }
    }

    bindump($f, $here, $halfcontext, 'end');
    if ($end < $prec['pfile_size'] - 1) {
	echo "\n<span style='background:white;'>[...]<hr></span>";
    }
    echo "</span></pre>\n";

    repclose($f);
}

/**
 * displays a file's contents
 *
 * Grab the file's type and make a decision whether to display it as a
 * binary file or a text file.  If license records are presented, add the
 * color highlighting and such.
 *
 * BUG: currently this function only shows the first 4k of a binary
 * file, but will happily send an arbitrary-length text file to the
 * user's browser, possibly crashing it.
 *
 * BUG: there is no license highlighting for binary files (yet)
 *
 * @param pfile_record $prec
 * @param licence_records $licrecs (optional) list of records from agent_lic_meta
 */
function showfilecontents($prec, $licrecs="")
{
    global $LIBEXECDIR;
//debugprint_r("prec", $prec);
//debugprint_r("licrecs", $licrecs);

    $opath = reppath($prec);
    $path = trim($opath);
    unset($out);
    
    // make sure file exists
    if (file_exists($path) == false)
    {
        echo "<p>File is not available.  Perhaps it is waiting to be processed.<p>";
        return;
    }
    
    exec("file --brief $path", $out);
    $file = trim($out[0]);
    
    if (strpos($file, " text") !== false || strncmp($file, "PGP arm", 7) == 0) 
    {
        echo "<pre><span style='background:#ffffff;'><hr>";
	    if ($licrecs) 
        {
	        showtextcoloredlicenses($prec, $licrecs);
	    } 
        else 
        {
	        repcat($prec);
            //$p = reppath($prec);
            //passthru("dd if=$p bs=10240 count=1 | sed -e 's/&/\&amp;/' -e 's/</\&lt;/'");
    	}
    	echo "</span></pre>";
    } 
    else 
    {
	    if ($licrecs) 
        {
	        showbinarycoloredlicenses($prec, $licrecs);
	    } 
        else 
        {
	        $f = repopen($prec) or die ("showfilecontents: Cannot open file");
	        echo "<pre><span style='background:#ffffff;'>";
	        bindump($f, 0, 10240);
	        echo "</span></pre>";
	        repclose($f);
	    }
    }
}


/**
 * Sort comparison function for info array
 */
function infocmp($a, $b)
{
    if (array_key_exists("ufile_name", $a))
    {
        $a1 = $a['ufile_name'];
        $b1 = $b['ufile_name'];
    }
    else if (array_key_exists("upload_name", $a))
    {
        $a1 = $a['upload_filename'];
        $b1 = $b['upload_filename'];
    }
    else if (array_key_exists("folder_name", $a))
    {
        $a1 = $a['folder_name'];
        $b1 = $b['folder_name'];
    }
    return strnatcasecmp($a1, $b1);
}


/**
 * display a ufile which is a directory
 *
 * @param mixed $u ufile record or ufile_pk
 * @param string $otype $opk data type ("folder", "upload", ...)
 * @param string $opk   folder_pk
 * @param string $where (optional) currently unused
 * @param string $activetab (optional)
 */
function showdir($otype, $opk, $where="", $activetab="")
{
//debugprint_r("showdir:", "otype=$otype, opk=$opk, where=$where, activetab=$activetab");
    $nfiles = 0;
    $user_fk = 0;
    if ($activetab == 'prop') 
        $artifacts = true;
    else
        $artifacts = false;

    // get children of $opk
    switch($otype)
    {
        case "folder":
            $info = db_folder_children($user_fk, $opk, $artifacts);
            break;
        case "upload":
            $info = db_upload_children($user_fk, $opk, $artifacts);
            break;
        case "uploadtree":
            $info = db_uploadtree_children($user_fk, $opk, $artifacts);
            break;
        case "search":
            $info = db_search($where);
            break;
        default:
            log_writedie("fatal", "bad input, otype = $otype", "showdir");
    }
//debugprint_r("info:", $info);

    if (!empty($info))
    {
        $nfiles = count($info);
	    echo '<pre>';
    	if ($activetab == 'prop') 
        {
	        echo '<b>CADRPF  mode';
    	    echo '       size';
    	    echo '          u';
    	    echo '          p';
    	    echo '  lines';
    	    echo '  words';
    	    echo ' lic';
    	    echo "</b>\n";
    	}

        // sort $info by name
        usort($info, "infocmp");

        foreach ($info as $file)
        {
//debugprint_r("file: ", $file);
	        $uploadtree_pk = $file['uploadtree_pk'];
	        if ($activetab == 'prop') 
            {
		        echo uis_container($file) ? "c" : "-";
        		echo uis_artifact($file) ? "a" : "-";
        		echo uis_dir($file) ? "d" : "-";
        		echo uis_replica($file) ? "r" : "-";
        		echo uis_proj($file) ? "p" : "-";
        		echo uis_fileproj($file) ? "f" : "-";
        		printf(" %06o", 0177777 & $file['ufile_mode']);
        		printf(" %10s", $file['pfile_size']);
        		printf(" %10d", $file['ufile_pk']);
        		printf(" %10d", $file['pfile_fk']);
        		if ($file['wc_words'])
        		    printf(" %6d", $file['wc_words']);
        		else
        		    echo '      -';
        		if ($file['wc_lines'])
        		    printf(" %6d", $file['wc_lines']);
        		else
        		    echo '      -';
        
        		unset ($nlic);
        		if ($file['pfile_fk']) 
                {
    		        $nlic = db_query1(
        		    		"SELECT COUNT(*) FROM agent_lic_meta WHERE
        		    		 pfile_fk = {$file['pfile_fk']}");
		        }
    		    if ($nlic) 
                {
    		        printf(" %3d", $nlic);
        		} 
                else 
                {
        		    echo '   -';
        		}
    		    echo ' ';
	        }

            if (!empty($file['upload_pk']))
            {
	            $uname = $file['ufile_name'];
                // if there is no uploadtree_pk, then this is an upload
                // else it is a dir/file in the upload
                if (empty($file['uploadtree_pk']))
                    $url = myname("g={$file[upload_pk]}", "rightframe.php/$n");
                else
                    $url = myname("h={$file[uploadtree_pk]}", "rightframe.php/$n");

                if (uis_container($file)) 
    		        echo "<a href=$url><b>$uname/</b></a>";
    	        else
    		        echo "<a href=$url>$uname</a>";

    	        if ($file['pfile_pk'])  // DOWNLOAD DISABLED (NAK)
                {
	                $pf = myname("p={$file['pfile_fk']}", "rightframe.php/$n");
       	    	    echo " (<a href='$pf'>download</a>)";
    	        }

                // don't print description/added to repo for directories inside an upload
                if (empty($file['parent']))
                {
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                    submit(" DELETE Upload: $uname", "cudelete", $file['upload_pk']);
                   // echo "\n";
                    if (empty($file['upload_desc']))
                        $desc = "No description";
                    else
                        $desc = $file['upload_desc'];
		        	echo "    <i>", $desc, "</i>\n";
	                echo "    <i>Added to repository on: ";
    		    	echo substr($file['upload_ts'],0,19), "</i>\n";
                }
            }
            else  // is this a folder?
            if (!empty($file['folder_pk']))
            {
                $pf = myname("f={$file['folder_pk']}", "rightframe.php/$n");
       	        echo "<a href='$pf'><b>$file[folder_name]</b></a> ";
                echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
       	        //echo "<a href='$pf'>(Delete folder $file[folder_name])</a> ";
                submit(" DELETE Folder: $file[folder_name]", "cfdelete", $file['folder_pk']);
       	        echo " \n";
                if (empty($file['folder_desc']))
                    $desc = "No description";
                else
                    $desc = $file['folder_desc'];
		    	echo "    <i>", $desc, "</i>\n";
            }
	        
	    }
        echo "</pre>";
    }
    else
    {
        echo "<p><b>No Files</b></p>";
    }

    return $nfiles;
}


function lichisto_cmp($a, $b)
{
    if ($a <= $b) return 1;
    if ($a == $b) return 0;
    return -1;
}

// info array passed in, sort on pctmatch then ufile_name
function liclist_cmp($a, $b)
{
    if ($a['pctmatch'] > $b['pctmatch']) return -1;
    if ($a['pctmatch'] < $b['pctmatch']) return 1;
    return strcasecmp($a['ufile_name'], $b['ufile_name']);
}


function liccount($info, &$fcnresult)
{
    if (!empty($info['lic_fk'])) $fcnresult[$info['lic_fk']]++;
}


function licget($info, &$fcnresult, $fcndata)
{
    global $licpk2id;

    if (empty($licpk2id))
            $licpk2id = table2assoc("agent_lic_raw", "lic_pk", "lic_id");
    
        if ($licpk2id[$info['lic_fk']] == $fcndata['lic_id']) $fcnresult[] = $info;
}


/**
 * show a license summary for a container or a license-specific page of links
 *
 * When $showlic is blank, displays a license summary for the given ufile
 * and if appropriate, filter and bsam progress bars (probably not appropriate
 * for production) or a form button to request a license report.
 *
 * BUG: the attribute used to record if a license report has completed is
 * placed on a ufile which may not be the one passed in the URL in cases
 * where the URL refers to an artifact.  This is only really a problem
 * when cleaning up license runs during Nealk's testing, but could be
 * a problem later at some point.  It also points out the fact that
 * we may want every ufile which is a container to carry the attribute(s).
 *
 * When $showlic isn't blank, display a list of files containing that
 * license within the current $u.
 *
 * BUG: no path names displayed
 *
 * @param mixed $u ufile record or ufile_pk
 * @param string $showlic license name for which to show a page of links.
 * NOTE!! this should almost definitely be a lic_pk now but isn't
 */
function lic_summary_u($oinfo, $showlic)
{
    // Start query timer
    $t1 = microtime(true);

    $ufile_pk = $oinfo['ufile_pk'];

    $allcount = "SELECT COUNT(*) as a,
    			COUNT(agent_lic_status.pfile_fk) as b,
			SUM(CASE WHEN agent_lic_status.processed IS TRUE THEN 1 END) as c
		    FROM containers
		    INNER JOIN ufile ON ufile_container_fk = contained_fk
		    LEFT JOIN agent_lic_status
		        ON agent_lic_status.pfile_fk = ufile.pfile_fk
		    WHERE container_fk = $ufile_pk
		    AND ufile.pfile_fk IS NOT NULL
		    AND NOT ((ufile.ufile_mode & (1<<29)) <> 0)
		    ";

    // whether the user asked for licenses on a folder, upload, or uploadtree node,
    // the request can be converted to a list of uploadtree nodes.  Do it.
    $uploadtree_nodes = oo2uploadtreenodes($oinfo);

    // query to recurse through uploadtree
    $lics = array(); 

    // some useful arrays
    $licnames = array();
    $lic_ids = table2assoc("agent_lic_raw", "lic_pk", "lic_id");
    $lic_names = table2assoc("agent_lic_raw", "lic_id", "lic_name");
    $lic_id2name = table2assoc("agent_lic_raw", "lic_name", "lic_id");

    $licq_join = "licq_join";
    // find all the children of this parent uploadtree id
    $psql = "select ufile_name, ufile_mode, lic_fk, uploadtree_pk,
                    tok_match, tok_license, tok_pfile, phrase_text
                from ufile inner join uploadtree on uploadtree.parent=$1 
                     and uploadtree.ufile_fk=ufile.ufile_pk 
                left outer join agent_lic_meta on agent_lic_meta.pfile_fk=ufile.pfile_fk
            ";

    $result = pg_prepare($licq_join, $psql);

    // if $showlic is empty, then show a license histogram
    if (empty($showlic) )
    {

        // get all the license records for all the nodes
        foreach ($uploadtree_nodes as $node)
        {
            // result is in $lics
            utn_recurse($node, $lics, "liccount", $licq_join);
        }

	    $lictotal = attr_get('ui.license.total', $oinfo['ufile_pk']);
    	// XXX the ufile where the attr is checked is different than
    	// the one the user is using when there are artifacts.
if (false)  // needs to be updated not to use defunct containers table
    	if (empty($lictotal) and ($ufile_pk>0)) 
        {
    	    $r = db_query1rec($allcount);
    	    $total = $r['a'];
    	    $fn = $r['b'];
    	    $nn = $r['c'];

	        if (1) 
            { // XXX
//		        submit('Request License Report', 'license', $ufile_pk);
	        }

    	    if (!empty($total) && ($fn < $total || $nn < $total)) 
            {
    		    $fx = (int)(100 * $fn / $total + 0.5);
        		if ($fx == 100 && $fn < $total) $fx = 99;
        		$nx = (int)(100 * $nn / $total + 0.5);
        		if ($nx == 100 && $nn < $total) $nx = 99;
        		echo "
                    <table border=0><tr><td>filter: $fn/$total ($fx)
                    </td>
                    </td><td>bsam: $nn/$total ($nx)
                    </td>
                    </tr><tr>
                    <td>
                    <div style='height:10px;width:100px;border:solid 1px black;'>
                    <div style='height:10px;width:{$fx}px;background:#cc9966'>
                    </div>
                    </div>
                    <td>
                    <div style='height:10px;width:100px;border:solid 1px black;'>
                    <div style='height:10px;width:{$nx}px;background:#cc9966'>
                    </div>
                    </div>
                    </td>
                    </tr></table>";
            } 
            else 
            {
	            attr_set('ui.license.total', $oinfo['ufile_pk'], $total);
	        }
        }

	    if (is_array($lics) && count($lics) > 0) 
        {
            echo "<br><table border=0>\n";
            echo "<tr><th>Count</th><th align=left>License</th></tr>\n";

            // $lics key is lic_pk (id's license+section), and value is the count
            // convert this to make the key a lic_name instead of a lic_pk
            // so that the array can be sorted
            $totlicenses = 0;
            foreach ($lics as $lic_pk=>$count) 
            {
                $licid = $lic_ids[$lic_pk];
                $licnames[$lic_names[$licid]] += $count;
                $totlicenses += $count;
            }
            uasort($licnames, "lichisto_cmp");

            foreach ($licnames as $name=>$count) 
            {
                $lic_id = $lic_id2name[$name];
                $url = myname("showlic=$lic_id");
		$printname = strrchr($name,'/');
		if ($printname) $printname=substr($printname,1);
		else $printname = $name;
                echo "<tr><td align=right>$count </td><td><a href='$url' style='text-decoration:none'>$printname</a></td></tr>\n";
            }
            echo "</table>";

            echo "<br>$totlicenses total licenses in ".count($licnames)." license categories found";
            $intext = 'in';
        }
        else
        {
            echo "<p>No licenses found.</p>";
        }
    } 
    else  // show a list of files with the $showlic (==lic_id) license
    {
        // get the files
        foreach ($uploadtree_nodes as $node)
        {
            // result is in $lics
            utn_recurse($node, $lics, "licget", $licq_join, array("lic_id"=>$showlic));
        }

        // order the output by % match and then ufile name
        // start by putting % match into the result array
        foreach ($lics as $key=>$lic)
        {
            $match = lic_match($lic);   // calculate % match
            $lics[$key]['pctmatch'] = $match;
        }

        // now sort by % match, then ufile name
        uasort($lics, "liclist_cmp");

//debugprint_r("sortedlics", $lics);

        $oldname = '';
        $olduploadtree_fk = 0;
        $lic_pk2name = table2assoc("agent_lic_raw", "lic_pk", "lic_name");

        foreach ($lics as $lic)
        {
            $lic_pk = $lic['lic_fk'];
            $uploadtree_pk = $lic['uploadtree_pk'];

            $name = $lic_pk2name[$lic_pk];
	        $name = strrchr($name,'/');
    	    if ($name) $name=substr($name,1);
    	    else $name  = $lic_pk2name[$lic_pk];
    	    if (!empty($lic["phrase_text"])) $name .= ': ' . $lic["phrase_text"];
    	    $name = str_replace("&","&amp;",$name);
    	    $name = str_replace("<","&lt;",$name);
    	    $name = str_replace(">","&gt;",$name);
            if ($name != $oldname) 
            {
        	    if (!empty($oldname)) echo "</ol>\n";
        	    $oldname = $name;
        	    echo "<p>License: <span style='background:yellow'>$name</span></p><ol>";
            }

//print <<<toend
// <a href="javascript:void(0);" onmouseover="return overlib('This is an ordinary popup.');" onmouseout="return nd();">here</a>
//toend;
            if ($uploadtree_pk != $olduploadtree_pk)
            {
                $patha = uploadtree2patha($uploadtree_pk);
                $path = pathinfo2text($patha);
                echo "<li>$lic[pctmatch]% <a href=" . myname("h=$uploadtree_pk") . 
                     ">{$lic['ufile_name']}</a>  &nbsp;&nbsp;", $path,
                     "</li>\n";
                $olduploadtree_pk = $uploadtree_pk;
            }
	    }
	    echo "</ol>\n";
        $intext = '';
    }
    $t2 = microtime(true);

    printf(" %s  %.2f elapsed seconds", $intext, $t2 - $t1);
    return;
}

/**
 * display the results of a 'search'
 *
 * This is unlikely to be the final preferred search but is something to
 * start with.
 *
 * BUG: no path names displayed, which refers to another BUG that we
 * haven't decided how to handle files with multiple parent paths
 * which come from ufile reuse.
 */
function showsearch($searchstring, $activetab)
{
    if (empty($searchstring)) {
        echo "Error, empty search string\n";
	exit;
    }

    echo "<h3>Search Results for '$searchstring'</h3>\n";
    // echo "<pre>$where</pre>\n";

    showdir("search", "", $searchstring, $activetab);
}

/**
 * main dispatcher for all the stuff which happens in the right-hand frame
 *
 * The logic just below this function follows a complex path to turn
 * COOKIE, POST, and URL/GET information into parameters for this
 * function.  In normal browsing the most important bit of information
 * is the GET 'o' parameter, for example o=u.1234 where 'u' is the
 * object 'o's type, in this case a ufile ID, and 1234 is the object's
 * value.  Given no further information from POST or COOKIE, this
 * will result in the action=show<type> (action=showu), a blank $aobj,
 * and $obj=1234
 * 
 * GET parms:
 *   fid Frame ID
 *   all 1 = show all users (defunct)
 *   p   Parent ufile (defunct, now upload.ufile_fk)
 *   o   options (defunct, changed to individual get parms)
 *   u   ufile_pk
 *   p   pfile_pk
 *   h   uploadtree pk
 *   f   folder_pk
 *   g   upload_pk
 *   t   new tab (browse, lic, prop, op)
 *
 * Moving a project required two operands, the project and its new
 * parent.  These are deciphered by combining both the COOKIE and
 * the GET parameters.
 *
 * As usual with such applications, all this handling is a bit tricky
 * and fiddly -- don't be surprised when things don't seem to work right.
 *
 * @param string $action action requested, often not explicit but synthesized
 * from $obj type which is encoded in the URL
 * @param string $aobj when URL, POST, or COOKIE demands a specific $action
 * it can also refer to a specific object to manipulate which becomes $aobj
 * @param string $obj the primary object to operate upon.
 */
function handle($action, $aobj, $obj)
{
    global $fid, $activetab, $lastmsg;

//debugprint_r("action", $action); debugprint_r("aobj", $aobj); debugprint_r("obj", $obj);

    // die ("handle($action, $aobj, $obj)\n");
    $oarray = getotk();
//debugprint_r("oarray", $oarray);

    switch ($action) 
    {
        case 't':	// new tab
	        $activetab = $aobj;
        	setcookie($fid, $activetab);
        	goto();
        	break;
        case 'move':  // move folder
    	    setcookie($fid, "$activetab.projmove.$obj");
        	echo "<br><br><br><br><br>\n";
        	echo "&larr; <b>Select destination folder</b>";
        	echo "<form method=post><input type=submit name=cancel value='Cancel'></form>\n";
        	break;
        case 'upload':   // upload a file
		    foreach ($oarray as $objtype => $objpk) 
            {
                $obj = oo2info($objtype, $objpk);
        	    upload($obj);
                break;
            }
        	break;
        case 'add':      // add an upload to a folder
		    foreach ($oarray as $objtype => $objpk) 
            {
                $obj = oo2info($objtype, $objpk);
        	    addfile_form($obj);
                break;
            }
        	break;
        case 'cfdelete':   // confirm delete a folder (recursive)
        	del_cfolder($aobj);
        	break;
        case 'cudelete':   // confirm delete an upload
        	del_cupload($aobj);
        	break;
        case 'fdelete':   // delete a folder (recursive)
        	del_folder($aobj);
            goto('', '', true);
        	break;
        case 'udelete':   // delete an upload
        	del_upload($aobj);
            goto('', '', true);
        	break;
        case 'newfolder':  // create a new folder
            $lastmsg = "";

            $foldername = trim($_POST['foldername']);
            $description = trim($_POST['description']);

            // check that folder name is html escaped and is not a dup and is not blank
            if (empty($foldername))
            {
                $lastmsg = "Empty folder name - folder not created.";
            }
            if (empty($oarray['folder']))  $lastmsg = "  No parent folder. ";
 
            if (empty($lastmsg))
            {
                // make sure this isn't a duplicate folder
                $sql = "select count(*) from folder, foldercontents 
                           where folder_name='$foldername'
                             and child_id=folder_pk
                             and foldercontents_mode=1 and parent_fk='$oarray[folder]'";
                $dup = db_query1($sql);
                if ($dup == 0)
                    createfolder($oarray['folder'], $foldername, $description);
                else
                    $lastmsg = "Duplicate folder name.  &nbsp;Duplicate not created.";
            }
if (!empty($lastmsg))
print $lastmsg. "<p>Click your browser's Back button to continue.</p>";
else
            goto('', '', true);
        	break;
        case 'license':    // schedule a license job
		    foreach ($oarray as $objtype => $objpk) 
            {
                $obj = oo2info($objtype, $objpk);
                break;
            }
            $upload_pk = intval($obj['upload_pk']);
            $job = job_create($upload_pk, 'License');
        	job_create_license($job, $upload_pk);
        	break;
        case 'wc':      // schedule a wc job - depends on obsolete containers.container_fk
        	job_create_wc($obj);
        	break;
        case 'jobdelete':    // delete a job  not currently used
        	foreach ($_POST['j'] as $jid => $dummy) {
        	    $jlist[] = intval($jid);
        	}
        	if (count($jlist) > 0)
    	    job_delete($jlist);
        	// showjobs();
        	goto();
        	break;
        case 'show':     // main display of files, folders, licenses, etc in right frame
        case 'showu':
            // $oarray[upload] = upload_pk
            // OR
            // $oarray[uploadtree] = uploadtree_pk
            //debugprint_r("oarray", $oarray);
            if (array_key_exists("upload", $oarray)) 
            {
                $otype = "upload";
                $opk = intval($oarray["upload"]);
            }
            else
            if (array_key_exists("uploadtree", $oarray)) 
            {
                $otype = "uploadtree";
                $opk = intval($oarray["uploadtree"]);
            }
            else
            if (array_key_exists("folder", $oarray))
            {
                $otype = "folder";
                $opk = intval($oarray["folder"]);
            }
            else
            {
//                log_write("error", "show invalid input: $oarray", "handle");
//                echo "<hr>bad input to handle:<br> ";
//                debugprint_r("action", $action);
//                debugprint_r("aobj", $aobj);
//                debugprint_r("obj", $obj);
//                echo "<hr>";
            }

            if (empty($aobj)) $aobj="browse";
            if (empty($otype)) $otype = "folder";
            if (empty($opk)) $opk = intval(db_query1("select root_folder_fk from users limit 1"));
            // showu(table, pk, active tab)
            showu($otype, $opk, $aobj);
        	break;
        case 'showjob':    // display jobs
        	if (empty($obj))
        	    showjobs();
        	else
        	    showjob(intval($obj));  // $obj is the job_pk
        	break;
        case 'showjq':     // display job queue  $obj is the job queue id (jobqueue.jq_pk)
        	showjq(intval($obj));
        	break;
        case 'showpf':    // display a pfile
            header("content-type: application/octet-stream");
        	$path = reppath(db_id2pfile($obj));
        	readfile($path);
        	break;
        case 'search':    // search
        	$searchfor = trim($_GET['q']);
        	showsearch($searchfor, $activetab);
        	break;
        case 'showlic':    // show license text, obj is lic_pk
            showlic($obj);
        	break;
        default:
            echo "handle: unknown action= $action ";
    }
    exit;
}

//////////////////  MAIN logic for right frame ////////////////////////
//phpinfo(); exit();

//echo "<head>";
//echo "</html>";
echo "<body>";

$lastmsg="";
//debugprint_r("_REQUEST", $_REQUEST);

////////////////// tabs, fid logic
$fid = intval($_GET['fid']);
if (empty($fid)) {
    $fid = '_osdefault';
}

// echo "<pre>COOKIE:"; print_r($_COOKIE); echo "\nGET: "; print_r($_GET); echo "</pre>";
list($activetab, $action, $aobj) = explode('.', $_COOKIE[$fid]);
if (empty($activetab)) $activetab = 'browse';
$activetab = explode('.', $_COOKIE[$fid]);

///////////////////

//print "activetab: $activetab, action: $action, aobj: $aobj";

list($otype, $gobj, $gobj2) = $o = explode('.', trim($_GET['o']));

if (empty($action)) 
{
    // actions from submit button second priority
//debugprint_r("_POST", $_POST);
    if (is_array($_POST['a'])) foreach ($_POST['a'] as $x => $dummy) {
	list($action, $aobj) = explode('.', $x);
//echo "newaction is $action<br>";
	break;
    }
}

//if (empty($action)) 
//{
//    // actions from GET third priority.
//    list($action, $aobj) = explode('.', trim($_GET['a']));
//}

unset($_GET['a']);
unset($_POST['a']);

if (empty($aobj))
{
    $aobj = $_GET['t'];
}

if ($_GET['search'])
{
    $action = "search";
} if (empty($action)) { $action = 'show' . $otype; } 
//debugprint_r("action", $action);
handle($action, $aobj, $gobj);
echo "</body>";
