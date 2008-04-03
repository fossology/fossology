<?php
/***********************************************************
 db_postgres.h.php
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

/**
 * run a query without erroring if the query fails
 *
 * returns what pg_query() returns
 */
function db_queryx($sql)
{
    global $_pg_conn;
    // echo "<pre>$sql</pre>";
    $result = @pg_query($_pg_conn, $sql);

    return $result;
}

/**
 * run a query, return result, emit error if fails
 *
 * returns what pg_query() returns
 */
function db_query($sql)
{
    global $_pg_conn;
    // echo "<pre>$sql</pre>";
    if (!($result = pg_query($_pg_conn, $sql))) {
	echo "<pre>"; debug_print_backtrace(); echo "</pre>\n";
	die("db_query($sql): " . pg_result_error($result));
    }

    return $result;
}

/**
 * return the single value from the given query
 *
 * Assumes the query returns a single value and does not check.
 * example: $nufiles = db_query1("select count(*) from ufile");
 */
function db_query1($sql)
{
    $result = db_query($sql);

    $row = pg_fetch_row($result);
    pg_free_result($result);
    return $row[0];
}

/**
 * return an associative array from the given query
 *
 * Assumes the query returns a single row and does not check.
 */
function db_query1rec($sql)
{
    $result = db_query($sql);

    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    return $row;
}

/**
 * return an array of associative arrays from the passed sql
 *
 * return value is same format as from pg_fetch_all()
 */
function db_queryall($sql)
{
    $result = db_query($sql);

    $rows = pg_fetch_all($result);
    if (!is_array($rows)) $rows = array();

    pg_free_result($result);
    return $rows;
}

/**
 * quote data values as needed for producing good SQL
 *
 * Pass this function a table name ($table) and an associative array
 * ($rec) and it will return an associative array with appropriate
 * quotes for producing an SQL query.  ONLY WORKS if every entry in
 * $rec matches a column in the given table, so it is quite frequently
 * useless for joined queries.
 */
function db_convert($table, $rec)
{
    global $_pg_conn;

    if (!$crec = pg_convert($_pg_conn, $table, $rec)) {
//	echo "<pre>db_convert-1($table):"; var_dump($rec);
	$metadata = pg_meta_data($_pg_conn, $table);
//	echo "\nmetadata:"; print_r($metadata); echo "\n";
	foreach ($rec as $n => $v) {
	    $a = array($n => $v);
	    $b = pg_convert($_pg_conn, $table, $a);
//	    echo "cvt($n => $v): ", $b ? $b[$n] : 'false', "\n";
	}
//	echo "<pre>db_convert-2:"; var_dump($crec); echo "</pre>";
//	die();
    }
    return $crec;
}

/**
 * construct an SQL AND clause from passed name/value array
 *
 * set $convert=0 (default is 1) to prohibit this function from trying
 * to db_convert() its values
 */
function db_and($table, $rec, $convert=1)
{
    global $uaquote;
    // echo "<br>db_and($table, $rec)\n";
    $s = "";
    if ($convert)
	$rec = db_convert($table, $rec);
    foreach ($rec as $name => $value) {
	if ($value == "NULL") {
	    $s .= "$and$name IS NULL";
	} else {
	    $s .= "$and$name = $value";
	}
	$and = ' AND ';
    }

    return $s;
}

/**
 * find a database record based on a name/value associative array
 *
 * this is a slightly more refined version of db_query1rec() which uses
 * an associative array and table name rather than a raw SQL query.  It
 * calls db_convert();
 */
function db_find1($table, $record)
{
    global $_pg_conn;
    $sql = "SELECT * FROM $table WHERE " . db_and($table, $record)
    		. " LIMIT 1";

    $result = pg_query($_pg_conn, $sql) or die("db_find1($sql): " . pg_result_error($result));

    $rec = (pg_num_rows($result) == 1 ? pg_fetch_array($result, 0, PGSQL_ASSOC) : false);

    pg_free_result($result);
    return $rec;
}

/**
 * insert a new record into a table based on a name/value assoc array
 * 
 * returns the ID of the newly-inserted record (usually)
 *
 * @param string $seq the name of the sequence variable aka the primary
 * autoincrementing key for this table.  Defaults to a reasonable value
 * but sometimes must be overridden.  Set to 'none' to avoid the ID
 * logic altogether.
 *
 * calls db_convert()
 */
function db_insert($table, $record, $seq="")
{
    global $_pg_conn;

    if ($seq == "") $seq = "{$table}_{$table}_pk_seq";

    $sql1 = "INSERT INTO $table (";
    $sql2 = ') VALUES (';
    $comma = '';

    $record = db_convert($table, $record);
    foreach ($record as $name => $value) {
        $sql1 .= "$comma$name";
	$sql2 .= "$comma$value";
	$comma = ', ';
    }

    $sql = $sql1 . $sql2 . ')';
    $result = pg_query($_pg_conn, $sql);
    if (!$result) 
    {
        echo "<pre>"; debug_print_backtrace(); echo "</pre>\n";
        die("db_insert($sql) failed");
    }

    if (pg_affected_rows($result) != 1) {
        die("db_insert($sql) failed");
    }

    $md = pg_meta_data($_pg_conn, $table);
    if ($seq != 'none') {
	$id = db_query1("SELECT currval('$seq')");
    } else {
        $id = "";
    }
    return $id;
}

/**
 * change table records, simlar to db_insert()
 *
 * @param string $table table name
 * @param assoc $where assoc array of what to match
 * @param assoc $record assoc array of new name/values for matched record(s)
 */
function db_update($table, $where, $record)
{
    global $_pg_conn;
    $record = db_convert($table, $record);
    $sql = "UPDATE \"$table\" SET";
    $comma = '';

    foreach ($record as $name => $value) {
        $sql .= "$comma\"$name\" = $value";
	$comma = ', ';
    }

    $sql .= ' WHERE ' . db_and($table, $where);

    $result = pg_query($_pg_conn, $sql) or die("db_update($sql): " . pg_result_error($result));

    return $rec;
}

$_pg_conn = false;

/**
 * connect to the DB and initialize a few things if it is a fresh DB
 *
 * If this is a fresh database, projects for the root and Attic nodes
 * are inserted so that their IDs can be forced to 1 and 2 respectively.
 *
 * @param string $path path name of the file containing database connection
 * data.  The default value is the only one used presently.
 */
function db_init($path)
{
    global $_pg_conn, $DATADIR, $LIBEXECDIR, $PROJECT ;

    if (empty($path)) $path="$DATADIR/dbconnect/$PROJECT";
    $_pg_conn = pg_connect(str_replace(";", " ", file_get_contents($path)));
}

/**
 * return a pfile table record matching an ID (pfile_pk)
 *
 * this probably fails awkwardly if the record does not exist
 */
function db_id2pfile($id)
{
    $result = db_query("SELECT * FROM pfile WHERE pfile_pk = $id LIMIT 1");

    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    return $row;
}

/**

 * return a ufile table record matching an ID (ufile_pk)
 *
 * Dies with a stack backtrace if the record does not exist.  Not the best
 * idea for production perhaps, but handy for debugging.
 */
function db_id2ufile($id)
{
    $result = db_query("SELECT * FROM ufile WHERE ufile_pk = $id LIMIT 1");
    if (!$result) {
        echo "<pre>"; debug_print_backtrace(); echo "</pre>\n";
	die("ufile lookup ($id) failed");
    }

    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    return $row;
}

/**
 * return a uploadtree table record matching an ID (uploadtree_pk)
 *
 * Dies with a stack backtrace if the record does not exist.  Not the best
 * idea for production perhaps, but handy for debugging.
 */
function db_id2uploadtree($id)
{
    $result = db_query("SELECT * FROM uploadtree WHERE uploadtree_pk = $id");
    if (!$result) {
        echo "<pre>"; debug_print_backtrace(); echo "</pre>\n";
	die("db_id2uploadtree lookup ($id) failed");
    }

    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    return $row;
}

/**
 * return info (upload, uploadtree, ufile) from an uploadtree_pk
 *
 * Dies with a stack backtrace if the record does not exist.  Not the best
 * idea for production perhaps, but handy for debugging.
 */
function db_uploadtree2info($uploadtree_pk)
{
    $sql = "select uploadtree.*, upload.*, ufile.* 
            from uploadtree, upload, ufile 
            where uploadtree_pk=$uploadtree_pk
                  and uploadtree.ufile_fk=ufile.ufile_pk 
                  and uploadtree.upload_fk=upload.upload_pk ";
//debugprint_r("sql in uploadtree2info", $sql);
    $result = db_query($sql);
    if (!$result) {
        echo "<pre>"; debug_print_backtrace(); echo "</pre>\n";
	die("db_id2uploadtree ($sq) failed");
    }

    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    return $row;
}

/**
 * return a upload table record matching an ID (upload_pk)
 *
 * Dies with a stack backtrace if the record does not exist.  Not the best
 * idea for production perhaps, but handy for debugging.
 */
function db_id2upload($id)
{
    $result = db_query("SELECT * FROM upload, ufile WHERE upload_pk = $id 
                        and upload.ufile_fk=ufile.ufile_pk");
    if (!$result) log_writedie("fatal", "no upload rec for id $id", db_id2upload);

    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    return $row;
}

/**
 * return a folder table record matching an ID (folder_pk)
 *
 * Dies with a stack backtrace if the record does not exist.  Not the best
 * idea for production perhaps, but handy for debugging.
 */
function db_id2folder($id)
{
    $result = db_query("SELECT * FROM folder WHERE folder_pk = $id ");
    if (!$result) log_writedie("fatal", "no folder for id $id", db_id2folder);

    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    return $row;
}



/**
 * Return a list of child records for a given uploadtree id and user
 * Not recursive.
 *
 * @param int $user_fk        optional user id, if null, all users are selected
 * @param int $upload_fk      optional upload head node for the records returned
 *                           If null, then all the top level upload recs are returned.
 * @param bool $artifacts     Return artifacts if true
 *
 * Returns an assoc array of uploadtree, upload, ufile records
 */
function db_uploadtree_children($user_fk="", $uploadtree_fk="", $artifacts=false)
{
    // base sql to build
    $sql = "select uploadtree.*, upload.*, ufile.* 
            from uploadtree, upload, ufile 
            where uploadtree.ufile_fk=ufile.ufile_pk 
                  and uploadtree.upload_fk=upload.upload_pk ";

    if (empty($uploadtree_fk))  // all children
        $sql .= " and uploadtree.parent is null ";
    else   // all children for uploadtree_fk
    {
        // get the upload rec for this upload tree - should probably pass this in
        // as an optional arg since some callers already have it.
        $sql_upload = "select upload_fk from uploadtree 
                       where uploadtree_pk=$uploadtree_fk
                       limit 1";
        $upload_fk = db_query1($sql_upload);

        $sql .= " and uploadtree.parent=$uploadtree_fk
                  and uploadtree.upload_fk=$upload_fk
                    and upload_pk=$upload_fk ";
    }

    // if user_fk is null, select all the uploads
    // qualify selection by user id
    if (!empty($user_fk)) $sql .= " and upload.upload_userid=$user_fk ";

    $rows = db_queryall($sql);

    // The above query gets all the children of an upload (non recursive).
    // So, if the caller does not want to see artifacts we have to
    // traverse artifact containers as if they weren't there.
    if (!$artifacts)
    {
        $nonartrows = array();
        foreach ($rows as $key => $row)
        {
            if (uis_artifact($row))
            {
                // replace artifact
                uploadtree2nonartifact($row['uploadtree_pk'], $nonartrows);
                unset($rows[$key]);
            }   
        }   
        // merge $nonartrows into $rows
        $rows = array_merge($rows, $nonartrows);
    }   

    return $rows;
}


/**
 * Return a list of child records for a given upload id and user
 * Not recursive but if the only child is a container artifact, then that
 * container is also scanned.
 *
 * @param int $user_fk        optional user id, if null, all users are selected
 * @param int $upload_fk      optional upload head node for the records returned
 *                           If null, then all the top level upload recs are returned.
 * @param bool $artifacts     Return artifacts if true
 *
 * Returns an assoc array of uploadtree, upload, ufile records
 */
function db_upload_children($user_fk="", $upload_fk="", $artifacts=false)
{
    // find the uploadtree id for this upload_fk
    $sql = "select * from uploadtree where parent is null ";
    if (!empty($upload_fk)) $sql .= " and upload_fk=$upload_fk";

    $head = db_queryall($sql);
    $numrows = count($head);
    if ($numrows == 0)
    {
        // upload hasn't been unpacked
        return 0;
    }
    if ($numrows != 1) 
    {
        log_write("warning", "one row expected $numrows returned: ".$sql, "db_upload_children");
        // delete the upload
        del_upload($upload_fk);
        return 0;
    }

    $uploadrec = $head[0];;

    // upload should never point to an artifact
    if (uis_artifact($uploadrec))
        log_writedie("fatal", "upload points to artifact", "db_upload_children");

    // base sql to build
    $sql = "select uploadtree.*, upload.*, ufile.* 
            from uploadtree, upload, ufile 
            where uploadtree.ufile_fk=ufile.ufile_pk 
                  and uploadtree.upload_fk=upload.upload_pk ";

    if (empty($upload_fk))  // all children
        $sql .= " and uploadtree.parent is null ";
    else   // all children for upload_fk
        $sql .= " and uploadtree.parent=$uploadrec[uploadtree_pk]
                  and uploadtree.upload_fk=$upload_fk
                    and upload_pk=$upload_fk ";

    // if user_fk is null, select all the uploads
    // qualify selection by user id
    if (!empty($user_fk)) $sql .= " and upload.upload_userid=$user_fk ";

    $rows = db_queryall($sql);

    // The above query gets all the children of an upload (non recursive).
    // So, if the caller does not want to see artifacts we have to
    // traverse artifact containers as if they weren't there.
    if (!$artifacts)
    {
        $nonartrows = array();
        foreach ($rows as $key => $row)
        {
            if (uis_artifact($row))
            {
                // replace artifact
                uploadtree2nonartifact($row['uploadtree_pk'], $nonartrows);
                unset($rows[$key]);
            }
        }
        // merge $nonartrows into $rows
        $rows = array_merge($rows, $nonartrows);
    }

    return $rows;
}


/**
 * Return a list of child records for a given folder_pk
 * Not recursive.
 *
 * @param int $user_fk        optional user id, if null, all users are selected
 * @param int $folder_fk      optional folder head node for the records returned
 *                           If null, then all the top level folder recs are returned.
 * @param bool $artifacts     Return artifacts if true
 *
 * Returns an assoc array containing folder, uploadtree, upload, and ufile record
 * info for each child.
 */
function db_folder_children($user_fk="", $folder_fk="", $artifacts)
{
    if ($artifacts)
        $artwhere = 'and ((ufile.ufile.mode & (1<<28)) != 0)';
    else
        $artwhere = '';

    // qualify selection by user id
    if (!empty($user_fk)) $userwhere = " and upload.upload_userid=$user_fk ";

    // create an array with all the folder id's 
    if (empty($folder_fk))
    {
        // select all the folders and get the children of all the folders.
        $sql = "select folder_pk from folder";
// yikes - this isn't right
        $folders = db_queryall($sql);
    }
    else
    {
        $folders = array();
        $folders[] = $folder_fk;
    }

    $rows = array();
    // get the children of each folder
    foreach ($folders as $folder)
    {
        $sql = "select * from foldercontents where parent_fk=$folder";
        $foldercontents = db_queryall($sql);
        foreach ($foldercontents as $fcontent)
        {
            if (($fcontent['foldercontents_mode'] & (1<<0)) > 0)
            {   // child is a folder
                $sql = "select * from folder where folder_pk=$fcontent[child_id]";
                $tmp = db_query1rec($sql);
                if (!empty($tmp)) $rows[] = $tmp;
            }
            else
            if (($fcontent['foldercontents_mode'] & (1<<1)) > 0)
            {   // child is an upload
                $tmp = db_id2upload($fcontent['child_id']);
                if (!empty($tmp)) $rows[] = $tmp;
            }
            else
            if (($fcontent['foldercontents_mode'] & (1<<2)) > 0)
            {  // child is an uploadtree
//                $tmp = db_uploadtree_children($user_fk, $fcontent['child_id'], $artifacts);
                $tmp = db_uploadtree2info($fcontent['child_id']);
                if (!empty($tmp)) $rows[] = $tmp;
            }
        }
    }

    reset($rows);
    return $rows;
}


/**
 * return the foldercontents of $folder_fk
 * Note that foldercontents are non-recursive
 *
 * @param array parent folder_fk
 *
 * returned array contains all the fields from table foldercontents
 */
//function db_proj2projs($p)
function db_folderchildren($folder_fk)
{
    if (empty($folder_fk)) die("error missing parameter: db_proj2projs()");

    $rows = db_queryall("SELECT * from foldercontents
			WHERE parent_fk=$folder_fk");

    return $rows;
}


/**
 * create a new folder under a given parent folder
 *
 * @param integer $parentfolder (required) parent folder (folder_pk)
 * @param string $name (required) folder name
 * @param string $descr (optional) folder description
 *
 * NOTE: if createfolder is called, you do NOT need to call 
 *       createfoldercontents. As this routine inserts into the 
 *       foldercontents table.
 */
function createfolder($parentfolder, $name, $descr="")
{
    $ftemplate = array(
	    'folder_name' => $name,
	    'folder_desc' => $descr
    );
    
	$folder_pk = db_insert('folder', $ftemplate, "folder_folder_pk_seq");

	// Why not call createfoldercontents?  The code below is a dup of
	// that routine.
 
    $fctemplate = array(
         'parent_fk' => $parentfolder,
         'foldercontents_mode' => 1<<0,
         'child_id' => $folder_pk
    );

	$fc_pk = db_insert('foldercontents', $fctemplate, "foldercontents_foldercontents_pk_seq");

    return $fc_pk;
}


/**
 * create a new foldercontents under a given parent folder
 *
 * @param integer $parentfolder (required) parent folder (folder_pk)
 * @param string $childid (required) id depending on foldercontents_mode
 * @param string $mode (required) foldercontents_mode
 */
function createfoldercontents($parentfolder, $childid, $mode)
{
    $fctemplate = array(
         'parent_fk' => $parentfolder,
         'foldercontents_mode' => $mode,
         'child_id' => $childid
    );

	$fc_pk = db_insert('foldercontents', $fctemplate, "foldercontents_foldercontents_pk_seq");

    return $fc_pk;
}

/**
 * create a new upload and ufile record.  
 *
 * Note: Since this function creates records in two tables, the function name is misleading.
 * (the uploadtree rec is created by ununpack)
 *
 * @param integer $parent (required) parent folder (folder_pk) that this upload will 
 *                        be categorized under in the left navigation.
 * @param string $name (required) ufile.ufile_name.  For a wget, this is the name of the file wget gets.
 * @param string $descr (optional) upload.upload_desc description of the file
 * @param string $filename (required) upload.upload_filename 
                         if this is from wget, $filename is the url.  Otherwise, it is the 
                         package name, like gcc-4.2.0, if a package, or the filename if not a pkg.
 * @param string $mode (required) upload.uploadmode: gold, wget, upload, discovery
 * @param string $creator (optional) uploader user id (leave empty until this is implemented)
 * @param string $ctime (optional) upload creation time in SQL-compatible format
 *
 * Returns the new upload_pk or -1 if this is a duplicate upload
 */
function createuploadrec($parent, $name, $descr="", $filename="", $mode=0, $creator="License", $ctime="")
{
    // make sure this isn't a duplicate upload (upload to parent folder with same filename)
    $sql = "select count(*) from leftnav where parent=$parent and name='$filename'";
    $count = db_query1($sql);
    if ($count) return -1;

    if (empty($ctime)) $ctime = date("Y-m-d H:i:s");

    $ufilerec = array(
        'ufile_container_fk' => $parent,  // obsolete
	    'ufile_name' => $name,
	    'ufile_mode' => (1<<27),
	    'ufile_ts' => $ctime,
	    'ufile_mtime' => $ctime
    );

    $uploadrec = array(
        'upload_desc' => $descr,
   	    'upload_filename' => $filename,
	    'upload_userid' => $creator,
	    'upload_mode' => $mode
    );

    $ufile_pk = db_insert('ufile', $ufilerec, 'ufile_ufile_pk_seq');
    $uploadrec['ufile_fk'] = $ufile_pk;
    $id = db_insert('upload', $uploadrec, 'upload_upload_pk_seq');

    // foldercontents_mode is an upload
    $foldercontents_mode = 1<<1;
    createfoldercontents($parent, $id, $foldercontents_mode);
    return ($id);
}

/**
 * Given an uploadtree_pk, return an array defining its file hierarchy.
 *
 * @param int  $uploadtree_fk  uploadtree id
 * @param bool $artifacts      true to include artifacts
 */
function uploadtree2patha( $uploadtree_fk, $artifacts=false)
{
    // returned array
    $patha = array();

    if ($artifacts)
        $artwhere = 'and ((ufile.ufile.mode & (1<<28)) != 0)';
    else
        $artwhere = '';

    // build the array from the given node to the top and then reverse it so
    // it goes top down before returning it.
    do
    {
        // get the uploadtree record
        $sql = "select ufile.*, upload.*, uploadtree.* from ufile, upload, uploadtree
                where uploadtree_pk=$uploadtree_fk
                  and uploadtree.ufile_fk=ufile.ufile_pk
                  and uploadtree.upload_fk=upload.upload_pk";
        $uploadtree = db_query1rec($sql);

        // go up the tree ignoring artifacts
        if (($artifacts == true) or (!uis_artifact($uploadtree))) $patha[] = $uploadtree;

        // set next record to look at
        $uploadtree_fk = $uploadtree['parent'];
//debugprint_r("patha", $patha);
        
    } while (!empty($uploadtree_fk));
    $pathb = array_reverse($patha, false);
    return $pathb;
}


/**
 *  Given an uploadtree_pk, descend the hierarchy and return all the non-artifacts
 *  immediatly below this node.
 *
 *  @param int $uploadtree_fk  uploadtree id
 *  @parm  mixed $nonartrows is an array of rows to return
 *
 *  The return rows consists of * uploadtree, upload, and ufile data.
 */
function uploadtree2nonartifact($uploadtree_fk, &$nonartrows)
{
    $sql = "select ufile.*, upload.*, uploadtree.* from ufile, upload, uploadtree
            where uploadtree.parent=$uploadtree_fk
              and uploadtree.ufile_fk=ufile.ufile_pk
              and uploadtree.upload_fk=upload.upload_pk";
    $childrows = db_queryall($sql);
    
    foreach ($childrows as $child)
    {
        // recurse on container artifacts
        if (uis_artifact($child) and uis_container($child))
            uploadtree2nonartifact($child['uploadtree_pk'], $nonartrows);
        else
        {
            // put non artifacts in result array $nonartrows
            $nonartrows[] = $child;
        }
    }

    return;
}


/**
 *  Given an uploadtree_pk, ASCEND the hierarchy and return the first non-artifact 
 *  found.
 *
 * @param int $uploadtree_fk  uploadtree id
 *
 * Return the first non-artifact above this node.  The return row consists of
 * uploadtree, upload, and ufile data.
 */
function uploadtree2nonartifactup($uploadtree_fk)
{
    do 
    {
        $sql = "select ufile.*, upload.*, uploadtree.* from ufile, upload, uploadtree
                where uploadtree.uploadtree_pk=$uploadtree_fk
                  and uploadtree.ufile_fk=ufile.ufile_pk
                  and uploadtree.upload_fk=upload.upload_pk";
        $rec = db_query1rec($sql);
        $uploadtree_fk = $rec['uploadtree_pk'];
    } while ($rec['ufile_mode'] & (1<<28));
    return $rec;
}


/**
 * Return an associative array from two table columns
 *
 * @param string $tablename (required) table name
 * @param string $keycol (required) name of table col that becomes the assoc array key
 * @param string $valcol (required) name of table col that becomes the assoc array value
 * @param string $clause (optional) sql clause to be appended after "from tablename"
 */
function table2assoc($tablename, $keycol, $valcol, $clause="")
{
    $assocarray = array();
    $sql = "select $keycol, $valcol from $tablename $clause";
    $result = db_query($sql);

    $rows = pg_fetch_all($result);
    if (is_array($rows)) 
    {
        foreach ($rows as $n => $lrec)
        {
            $assocarray[$lrec[$keycol]] = $lrec[$valcol];
        }
    }

    pg_free_result($result);
    return $assocarray;

}


/**
 * Return a list of uploadtree nodes from a $oinfo
 *
 * @param mixed array $oinfo info record on a particular folder, upload or uploadtree node
 */
function oo2uploadtreenodes($oinfo)
{
    $utnodes = array();

    if (!empty($oinfo['uploadtree_pk']))
    {
        // input is an uploadtree node
        $utnodes[] = $oinfo['uploadtree_pk'];
    }
    else
    if (!empty($oinfo['upload_pk']))
    {
        // input is an upload 
        $sql = "select uploadtree_pk from uploadtree 
                where upload_fk=$oinfo[upload_pk] and parent is NULL";
        $utnodes[] = db_query1($sql);
    }
    else
    if (!empty($oinfo['folder_pk']))
    {
        // input is a folder
        $user_fk = "";  // not implemented yet
        $artifacts  = true;
        $children = db_folder_children($user_fk, $oinfo['folder_pk'], $artifacts);
        foreach ($children as $child)
        {
            $utnodes[] = $child['uploadtree_pk'];
        }
    }
    else
        log_writedie("fatal", "bad input", "oo2uploadtreenodes");

    return $utnodes;
}


/**
 * recurse through uploadtree node (utn)
 * call tn_recursefcn($info, $fcnresult) at every node, results are put back in $fcnresult
 *
 * @param  int      $uploadtree_pk  node to start/continue search
 * @param  array   &$fcnresult       license records
 * @param  string   $utn_recursefcn function name
 * @param  string  &$psql           prepared sql that needs to be executed 
 *                                  with $uploadtree_pk as a parm
 * @param   array    $fcndata        supplementary data (assoc array) to pass to utn_recursefcn
 *
 * Return array, key is lic_fk, value is count (number of licenses with that lic_fk)
 */
function utn_recurse($uploadtree_pk, &$fcnresult, $utn_recursefcn, &$psql, $fcndata=array())
{
    $result = pg_execute($psql, array($uploadtree_pk));
    if (!$result)
    {
        log_write("fatal", "bad psql", "utn_recurse");
        die("error:".pg_result_error($result));
    }
    $children = pg_fetch_all($result);
    if (!children) die("error:".pg_result_error($result));

    if ((count($children) > 0) and (!empty($children)))
    {
        foreach ($children as $child)
        {
           $utn_recursefcn($child, $fcnresult, $fcndata);

           // is the child a container?
           if ($child['ufile_mode'] & (1<<29))
           {
               utn_recurse($child['uploadtree_pk'], $fcnresult, $utn_recursefcn, $psql, $fcndata);
           }
        }
    }

   return;
}



/**
 * search, put results in an info array
 *
 * @param  string      $searchstring  string to search for
 *
 * Return info array   assoc array of uploadtree, upload, ufile records
 */
function db_search($searchstring)
{
    // base sql to build
    $sql = "select uploadtree.*, upload.*, ufile.* 
            from uploadtree, upload, ufile 
            where ufile.ufile_name like '%searchstring%'
                  and ufile.ufile_pk=upload.ufile_fk
                  and ufile.ufile_pk=uploadtree.ufile_fk";

    $rows = db_queryall($sql);

    return $rows;
}

?>
