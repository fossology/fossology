<?php
/***********************************************************
 common.h.php
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

// Constants
require_once("constants.h.php");
require_once("db_postgres.h.php");
require_once("jobs.h.php");
require_once("log.h.php");

// Log types, used in log table
$LOGTYPE = array("debug"=>0, "warning"=>1, "error"=>2, "fatal"=>3);

// init the database
db_init('');

    // make sure there is at least one user
    // this is a stopgap until real users are implemented
    // A db contraint insures that all users have a root folder
    // if there is a user defined - use that to get the root folder
    $folder_pk = db_query1("select root_folder_fk from users limit 1");

    // if there is no folder_pk, then there are no users.  So create a user and a folder.
    // This can happen on a newly initiated database.

    if (empty($folder_pk))
    {
        $ins = array(
                     'folder_name' => 'Repository Directory',
                     'folder_desc' => 'Repository Root'
                    );
        $folder_pk = db_insert('folder', $ins, "folder_folder_pk_seq");
        $ins = array(
                     'user_name' => "fossology",
                     'root_folder_fk' => $folder_pk
                    );
        db_insert('users', $ins, 'none');
    }


// init globals

// Initialize table_enum
$TABLEENUM = table2assoc("table_enum", "table_name", "table_enum");


/**
 * Remove pfile table entries which are unreferenced by ufiles
 *
 * This function can be slow...
 */
function removeorphanpfiles()
{
    $tables = array("agent_lic_meta", "agent_lic_status", "agent_wc");
    // XXX when the DB is large, may want to select into a temp table
    $select = "(select pfile_pk from pfile
		    left join ufile on ufile.pfile_fk = pfile.pfile_pk
	            where ufile_pk is null)";

    foreach ($tables as $table) {
        db_query("delete from $table where pfile_fk in $select");
    }
    db_query("delete from pfile where pfile_pk in $select");
}

/**
 * Is the given ufile a container?
 */
function uis_container($urec)
{
    return ($urec['ufile_mode'] & (1<<29)) != 0;
}

/**
 * Is the given ufile a project?
 * NEED TO REDO WITH UPLOAD SCHEMA - this should be obsolete
 */
function uis_proj($urec)
{
    return ($urec['ufile_mode'] & (1<<27)) != 0;
}

/**
 * Is the given ufile a project with an attached(uploaded) file?
 * NEED TO REDO WITH UPLOAD SCHEMA - this should be obsolete
 */
function uis_fileproj($urec)
{
    return ($urec['ufile_mode'] & (1<<27)) != 0
		&& ($urec['pfile_fk'] || $urec['pfile_pk']);
}

/**
 * OBSOLETE
 * Is the given ufile a replica?
 */
function uis_replica($urec)
{
    return ($urec['ufile_mode'] & (1<<26)) != 0;
}

/**
 * Is the given ufile an unpacker-generated artifact?
 */
function uis_artifact($urec)
{
    return ($urec['ufile_mode'] & (1<<28)) != 0;
}

/**
 * Is the given ufile a directory?
 */
function uis_dir($urec)
{
    return ($urec['ufile_mode'] & 040000) != 0;
}

/**
 * may not handle replicas, used only by utree2table()
 *
 * NEED TO REDO WITH UPLOAD SCHEMA
 * This function walks a ufile tree in parallel using a dynamic OR
 * expression.  The number of terms in the OR expression is limited
 * to 1000 for speed based on cursory testing with different numbers.
 */
function HOLDurecurse($ulist, $table)
{
    $n = count($ulist);
    $nn = 1000;

    // echo "<br>", $n, "\n"; flush();
    for ($i = 0; $i < $n; $i += $nn) {
	$or = "("; $orlist = "";
	$k = $i + $nn;
	if ($k >= $n) $k = $n;
	// if ($i > 0) echo "<br>**$i-$k ($n)\n";
	for ($j = $i; $j < $k; $j++) {
	    $u = $ulist[$j]['ufile_pk'];
	    if (uis_replica($ulist[$j])) {
		$pfile = $ulist[$j]['pfile_fk'];
		$orlist .= "{$or}(pfile_fk = $pfile AND (ufile_mode & (1<<26)) = 0)";
	    } else {
		$orlist .= "{$or}ufile_container_fk = $u";
	    }
	    $or = " OR ";
	}
	// echo "<br>*", $j, "/", $n, "\n"; flush();
	$orlist .= ")";
	//echo $orlist, "\n";
	$parent = $urec['ufile_pk'];
	db_query("INSERT INTO $table SELECT ufile_pk as ufile_fk, pfile_fk
		    FROM ufile
		    WHERE $orlist
		    AND (ufile_mode & (1<<29)) = 0");
	$dirlist = db_queryall("SELECT ufile_pk, ufile_mode, pfile_fk  FROM ufile
		    WHERE $orlist
		    AND (ufile_mode & (1<<29)) != 0");
	if (is_array($dirlist) && count($dirlist) > 0)
	    urecurse($dirlist, $table);
    }
}

/**
 * walk a ufile tree placing ufile and pfile IDs in a new table
 *
 * a two-column table, TEMPORARY by default, is created with the
 * name "utreeXXX" where XXX is the ufile ID passed in ($u).  $u
 * may be either a simple ufile ID or a ufile record from which the
 * ID will be extracted.
 *
 * The columns are ufile_fk and pfile_fk corresponding to all the
 * ufiles and pfiles referenced by the $u and all its children.
 *
 * THIS FUNCTION IS CURRENTLY UNUSUED AND MAY NOT HANDLE REPLICAS
 */
function utree2table($u, $temp="TEMPORARY") {
    if (is_array($u)) {
	$urec = $u;
    } else {
	$urec = db_find1("ufile", array("ufile_pk" => $u));
    }
    $u = $urec['ufile_pk'];
    $table = "utree$u";
    if (db_queryx("CREATE $temp TABLE $table (ufile_fk integer, pfile_fk integer);")) {

	if (uis_container($urec)) {
	    urecurse(array($urec), $table);
	} else {
	    db_query("INSERT INTO $table (ufile_fk, pfile_fk) VALUES ({$urec['ufile_pk']}, {$urec['pfile_fk']})");
	}
    }

    return $table;
}

/**
 * return a randomly-generated UUID
 *
 * Spawn the uuidgen(1) to produce a random, unpredictable UUID
 * handy for temporary file names and such.
 */
function uuid()
{
    exec("uuidgen -r", $tmp);
    $uniqid = $tmp[0];
    empty($uniqid) && die ("uuidgen failed or missing or not in path");
    return $uniqid;
}

/**
 * Find 1 project given the name/values in $record.  OBSOLETE!!!
 *
 * This only needs to be kept around so the OSRB files portal bulk
 * load code in load.php can be re-written at some point.
 */
function db_find1proj($record)
{
    die("db_find1proj()");
    if (isset($record['projtree_parent'])) {
	$parent = " AND projtree_parent =" . $record['projtree_parent'];
	unset($record['projtree_parent']);
    }
    // echo "<pre>find";print_r($record);echo "</pre>";
    $result = db_query($s="SELECT *, projtree.* FROM proj
			    LEFT JOIN projtree ON projtree.projtree_child = proj.proj_pk
			    WHERE " . db_and('proj', $record) . $parent
			    . " LIMIT 1");
    $rec = (pg_num_rows($result) == 1 ? pg_fetch_array($result, 0, PGSQL_ASSOC) : false);

    pg_free_result($result);
    return $rec;
}


/**
 * return the pfile ID for the named pfile (sha1.md5.length), creating the pfile record if necessary.
 *
 */
function findorcreatepfile($pfilename)
{
    list($sha1, $md5, $len) = explode('.', $pfilename);

    $x['pfile_md5'] = $md5;
    $x['pfile_sha1'] = $sha1;
    $x['pfile_size'] = $len;

    if (!($rec = db_find1('pfile', $x))) {
        $id = db_insert('pfile', $x);
        $rec['pfile_pk'] = $id;
    }

    return $rec['pfile_pk'];
}

/**
 * OBSOLETE -- used only by load.php -- preserve until load.php is rewritten
 */
function findorcreateufile($name, $projid, $pfileid)
{
    die("findorcreateufile($name, $projid, $pfileid)");
    $x['ufile_name'] = $name;
    $x['pfile_fk'] = $pfileid;
    $x['ufile_proj_fk'] = $projid;


    if (!($rec = db_find1('ufile', $x))) {
        $x['ufile_mtime'] = 'now()';
        $x['oname'] = $fdata['name-uid'];
        $id = db_insert('ufile', $x);
        $rec['ufile_pk'] = $id;
    }
 
    return $rec['ufile_pk'];
}

/**
 * given an attribute name, return its ID
 *
 * Attribute names are like path names using . instead of /, so
 * a.b means an attribute named 'b' which is a child of the attribute
 * named 'a' which is a child of nothing.  If the attribute names
 * do not yet exist, they are created.  The return value is to be
 * used to match the attr.attr_pk field.
 *
 * BUG: the other
 * fields of the key table (key_agent_fk) are neither
 * matched nor returned.  NOTE consider requiring the agent name+version
 * as the initial part of the attribute name or give up on the agent_fk
 * perhaps.
 *
 * BUG: attribute names actually reside in the key table
 * @param string $name attribute name
 */
function attr2id($name)
{
    static $attr2id = array();

    $id = $attr2id[$name];
    if (empty($id)) {
        $comps = explode('.', $name);

	$path = "";
	$dot = "";
	$lastid = $id = 0;
	foreach ($comps as $comp) {
	    $path .= $dot . $comp;
	    $dot = '.';
	    $id = db_query1("SELECT key_pk FROM key
	    			WHERE key_name = '$comp'
				AND key_parent_fk = $id");
	    if (empty($id)) {
		$id = db_insert('key', array('key_name' => $comp,
					  'key_desc' => 'automatic insert',
					  'key_agent_fk' => 0,
					  'key_parent_fk' => $lastid));
	    }
	    $lastid = $attr2id[$path] = $id;
	}
    }

    return $attr2id[$name];
}

/**
 * Return the value of the named attribute referring to a data item
 *
 * @param string $name attribute name
 * @param integer $pk primary key (ID) of a data item
 */
function attr_get($name, $pk)
{
    if (empty($name) or empty($pk)) return 0;
    $id = attr2id($name);
    if (empty($id)) return 0;
    $val = db_query1("SELECT attrib_value FROM attrib
    				WHERE attrib_key_fk = $id
				AND pfile_fk = $pk");
    return $val;
}

/**
 * Set the named attribute for a data item to a specific value
 *
 * @param string $name attribute name
 * @param integer $pk primary key (ID) of a data item
 * @param string $val new value for $pk.$name
 */
function attr_set($name, $pk, $val)
{
    $id = attr2id($name);
    $aid = db_query1("SELECT attrib_pk FROM attrib
    				WHERE attrib_key_fk = $id
				AND pfile_fk = $pk");
    if (empty($aid)) {
        db_query("INSERT INTO attrib
			  (attrib_key_fk, attrib_value, pfile_fk)
			  VALUES ($id, '$val', $pk)");
    } else {
        db_query("UPDATE attrib
			  SET attrib_value = '$val'
			    WHERE attrib_key_fk = $id
			    AND pfile_fk = $pk");
    }
}

/**
 * Delete all attributes of a given name for a data item
 *
 * @param string $name attribute name
 * @param integer $pk primary key (ID) of a data item
 */
function attr_unset($name, $pk)
{
    $id = attr2id($name);
    db_query("DELETE FROM attrib
    				WHERE attrib_key_fk = $id
				AND pfile_fk = $pk");
}

/**
 * used by flatten()
 * NEED TO REDO WITH UPLOAD SCHEMA
 */
function rflatten($containers, $c="")
{
    if (uis_replica($c)) {
	$c = db_query1rec("SELECT * FROM ufile
			    WHERE pfile_fk = {$c['pfile_fk']}
			    AND (ufile_mode & (1<<26)) = 0");
    }
    $cid = $c['ufile_pk'];

    foreach ($containers as $container) {
	db_queryx("INSERT INTO containers (container_fk, contained_fk)
		VALUES($container, $cid)");
    }
    db_queryx("INSERT INTO containers (container_fk, contained_fk)
		VALUES($container, $container)");

    $containers[] = $cid;

    $result = db_query("SELECT * FROM ufile
    			WHERE ufile_container_fk = $cid
			AND (ufile_mode & (1<<29)) != 0");

    while ($row = pg_fetch_assoc($result)) {
        rflatten($containers, $row);
    }

    pg_free_result($result);
}

/**
 * walk a ufile tree inserting tree-flattening entries in to container table
 *
 * This is mostly used by the flatten command-line progrem which is used
 * as the last agent the chain of agents which accomplish an unpacking.
 */
function flatten($container)
{
    $c = db_id2ufile($container);
    // Only flatten if ufile is a container
    if (uis_container($c)) {
	rflatten(array($container), $c);
    }
}



/*
 * Shorten a name by putting elipsis in front, if necessary
 *
 */
function dottrim($str, $maxlen, $escit=true)
{
    if ($escit) $pname = str_replace("'", "\\'", $str);
    if (($len = strlen($pname)) > $maxlen)
    {
        $short = "..."; 
        $pname = $short . substr($pname, $len - $maxlen);
    }
    return $pname;
}

?>
