<?php
/***********************************************************
 leftnav.php
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
require_once("webcommon.h.php");

/**
 * walk the folder tree for a user emitting javascript for the navigation bar
 * Links are either going to be to an upload_pk or a folder_pk
 *
 * @param int $parent_folder_fk 
 */
function navtree(&$JSunique, $parent_folder_fk)
{
    global $uid, $isadmin, $all, $fid;
    $maxlen = 30;
    $JSparent = $JSunique;

    if (empty($parent_folder_fk))  // tree node must be a folder
    {
        log_write("fatal", "No parent folder", "navtree");
        print "fatal program error, no parent folder $parent_folder_fk, navtree";
        exit();
    } 
    
    $sql = "select * from leftnav where parent=$parent_folder_fk order by name";
    $rows = db_queryall($sql);

    foreach ($rows as $row) 
    {
        $JSunique++;
        if ($row['foldercontents_mode'] == 1)
        {   // child is a folder
//debugprint_r("folder", $row);
            $url = trim(myname("f=$row[folder_pk]&amp;g=$row[upload_pk]&fid=$fid", 'rightframe.php'));
            $pname = trim(dottrim($row['name'], $maxlen));
            $textover = addslashes($row['description']);
	    	echo "d.add($JSunique, $JSparent, '$pname','$url', '$textover', 'basefrm', 'img/folder.gif')\n";
            navtree($JSunique, $row['folder_pk']);
        }
        else
        {   // child is an upload
// Showing uploads in the left nav can get very slow if there are lots of them.  If you want to see them
// uncomment this block.
      //      $url = trim(myname("f=$row[folder_pk]&amp;g=$row[upload_pk]&fid=$fid", 'rightframe.php'));
      //      $pname = trim(dottrim($row['name'], $maxlen));
      //      $textover = addslashes($row['description']);
	  //  	echo "d.add($JSunique, $JSparent, '$pname','$url','$textover', 'basefrm')\n";
        }
    }

    return ;
}


/**
 * entry function to build the javascript for the left navigation bar
 */
function buildleftnavjs($username="")
{
    global $uid, $isadmin, $all, $fid;
    $JSparent = -1;
    $JSunique = 0;
    $maxlen = 30;

    // build nav tree for all users
    if (!empty($username))
        $where = "where user_name='$username'";
    else
        $where = "";
    $sql = "select root_folder_fk from users $where order by user_pk asc limit 1";
    $root_folder_fk = db_query1($sql);
    $root_folder_name = db_query1("select folder_name from folder where folder_pk=$root_folder_fk");

    // initialize the tree with the root folder
    $pname = trim(dottrim($root_folder_name, $maxlen));
	$url = trim(myname("f=$root_folder_fk&fid=$fid", 'rightframe.php'));
	echo "d.add($JSunique, $JSparent, '$pname','$url', 'Root folder', 'basefrm')\n";

    // start the tree with the user's root folder
    navtree($JSunique, $root_folder_fk);

//    if ($isadmin && $all) {
//	    $JSroot = uploadtree(array('ufile_pk' => 1, 'ufile_name' => 'root'));
//    } else {
//	    $JSroot = uploadtree(userproj($uid));
//     }

    if ($isadmin) 
    {
		echo "d.add(999990,0,'Show Jobs','./rightframe.php?o=job.&fid=$fid', 'Show agent queue', 'basefrm','img/question.gif')\n";
    }

}


/////////////////////   MAIN   ///////////////////////////
$all = trim($_GET['all']) + 0;
$fid = intval($_GET['fid']);

?>
<html>

<head>
	<link rel="StyleSheet" href="dtree.css" type="text/css" />
	<script type="text/javascript" src="dtree.js"></script>

</head>

<body>

<!-- search form 
<form action=rightframe.php method=get target=basefrm>
<input name=search value='Search' type=submit>
<input type=hidden name=fid value=<?php global $fid; echo $fid; ?>>
<input type=hidden name=a value=search>
<input name=q size=20>
</form>
<hr>
-->

<!-- dynamic tree from www.destroydrop.com/javascript/tree/ -->
<div class="dtree">
	<p><a href="javascript: d.openAll();">open all</a> | <a href="javascript: d.closeAll();">close all</a></p>

	<script type="text/javascript">
		<!--

		d = new dTree('d');

<?php
buildleftnavjs();
?>

		document.write(d);

		//-->
	</script>

</div>

<p>
<font size=-1>
<a href='about.html' target=basefrm> About The FOSSology Project</a>
</font>
</body>
</html>
