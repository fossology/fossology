<?php
/***********************************************************
 jobs.h.php
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
require_once("db_postgres.h.php");


/**
 * return a list of jq's which are ready to run.
 *
 * Return value is an associative array indexed by jq_pk with all the
 * fields from job+jq+jobdepends.  This is
 * used by old_showjobs() which is currently unused, and should be
 * replaced by the query in the current showjobs() anyway even if the
 * layout etc from the old version is used.
 */
function get_readyjqs()
{
    $readyjobs = db_queryall("
    SELECT DISTINCT(jobqueue.*), job.* FROM jobqueue
	LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk 
	LEFT JOIN jobqueue AS depends 
	    ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
	LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk 
    WHERE 
	jobqueue.jq_starttime IS NULL 
	AND ( 
	    (depends.jq_endtime IS NOT NULL AND
		    (depends.jq_end_bits & jobdepends.jdep_depends_bits) != 0 )
	    OR jobdepends.jdep_jq_depends_fk IS NULL
	) 
    ");

    foreach ($readyjobs as $job) {
	$readylist[$job['jq_pk']] = $job;
    }
    return $readylist;
}

/**
 * return a list of job which are ready to run.
 *
 * Return value is an associative array indexed by job_pk with all the
 * fields from job+jq+jobdepends.  This is
 * used by old_showjobs() which is currently unused, and should be
 * replaced by the query in the current showjobs() anyway even if the
 * layout etc from the old version is used.
 */
function get_readyjobs()
{
    $readyjobs = db_queryall("
    SELECT DISTINCT(job.job_pk) FROM jobqueue
	LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk 
	LEFT JOIN jobqueue AS depends 
	    ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
	LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk 
    WHERE 
	jobqueue.jq_starttime IS NULL 
	AND ( 
	    (depends.jq_endtime IS NOT NULL AND
		    (depends.jq_end_bits & jobdepends.jdep_depends_bits) != 0 )
	    OR jobdepends.jdep_jq_depends_fk IS NULL
	) 
    ");

    foreach ($readyjobs as $job) {
	$readylist[$job['job_pk']] = $job;
    }
    return $readylist;
}

/**
 * return a list of jq's which could run at some point.
 *
 * Return value is an associative array indexed by jq_pk with all the
 * fields from job+jq+jobdepends.  This is
 * used by old_showjobs() which is currently unused, and should be
 * replaced by the query in the current showjobs() anyway even if the
 * layout etc from the old version is used.
 */
function get_activejqs()
{
    $jobs = db_queryall("
    SELECT DISTINCT(jobqueue.jq_pk) FROM jobqueue
	LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk 
	LEFT JOIN jobqueue AS depends 
	    ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
	LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk 
    WHERE 
	$where
	jobqueue.jq_starttime IS NULL 
	AND ( 
	    (depends.jq_pk IS NOT NULL AND depends.jq_endtime IS NULL)
	    OR
	    (depends.jq_endtime IS NOT NULL AND
		    (depends.jq_end_bits & jobdepends.jdep_depends_bits) != 0 )
	    OR jobdepends.jdep_jq_depends_fk IS NULL
	) 
    ");

    foreach ($jobs as $job) {
	$readylist[$job['jq_pk']] = $job;
    }
    return $readylist;
}

/**
 * return a list of jobs which could run or are running
 *
 * Return value is an associative array indexed by jq_pk with all the
 * fields from job+jq+jobdepends.  This is
 * used by old_showjobs() which is currently unused, and should be
 * replaced by the query in the current showjobs() anyway even if the
 * layout etc from the old version is used.
 */
function get_activeandrunningjobs($upload_fk)
{
    $upseg = "job.job_upload_fk = $upload_fk AND ";

    $jobs = db_queryall("
    SELECT DISTINCT(job.job_pk), job.* FROM jobqueue
	LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk 
	LEFT JOIN jobqueue AS depends 
	    ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
	LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk 
    WHERE 
	$upseg
	(
	    jobqueue.jq_starttime IS NULL 
	    AND ( 
		(depends.jq_pk IS NOT NULL AND depends.jq_endtime IS NULL)
		OR
		(depends.jq_endtime IS NOT NULL AND
			(depends.jq_end_bits & jobdepends.jdep_depends_bits) != 0 )
		OR jobdepends.jdep_jq_depends_fk IS NULL
	    ) 
	    OR
	    ( 
		jobqueue.jq_starttime IS NOT NULL 
		AND
		jobqueue.jq_endtime IS NULL 
	    )
	)
    ");

    foreach ($jobs as $job) {
	$readylist[$job['job_pk']] = $job;
    }
    return $readylist;
}


/**
 * Used both to color code jobs and print the color code legend
 *
 * With a blank $type, prints the HTML for the job coloring legend
 *
 * If a $type is given, the corresponding color is returned.
 * @param string type job type such as ready, running, finished...
 */
function colorcode($type='')
{
    static $cc = array (
	'queued' => '#ffff66',
	'ready' => '#99ff99',
	'scheduled' => '#ff99cc',
	'finished' => '#cccccc',
	'blocked' => '#ff9933',
	'failed' => '#ff3333'
    );

    $cclegend = array (
	'queued' => $cc['queued'],
	'ready' => $cc['ready'],
	'scheduled' => $cc['scheduled'],
	'finished' => $cc['finished'],
	'blocked' => $cc['blocked'],
	'failed' => $cc['failed']
    );

    if ($type == '') {
        echo '<br><table border=0><tr>';
	foreach ($cclegend as $type => $color) {
	    echo "<td bgcolor=$color>$type</td>";
	}
	echo '</table>';
    } else {
        return $cc[$type];
    }
}

/**
 * query the job queue
 *
 * This replaces most or all the get_*jobs() functions and is used by
 * the new showjobs() code.  The return is an array of jobs together
 * with their status as separate columns: ready, couldrun, blocked, running
 * NOTE you can also use %ready, %couldrun, %blocked, and %running in
 * your $select and $where (no need in $orderbylmit, use ready etc there)
 * as boolean values.
 *
 * The default values are suitable for, and used by, showjobs()
 *
 * @param string $select a different SQL SELECT than the default.  It
 * is augmented by the status columns
 * @param string $where a different SQL WHERE
 * @param string $orderbylimit a different bit of SQL to be placed in the
 * query where ORDER BY and LIMIT are usually placed.
 */
function jobquery($select='', $where='', $orderbylimit='')
{
    // these allow substitution of things like %ready with the corresponding
    // SQL, making it possible for the caller to use %ready in their $where
    // clause for example
    static $statustags = array( '%ready', '%couldrun', '%blocked', '%scheduled');
    static $statustext = array(
        '(jobqueue.jq_starttime IS NULL
            AND (
                (depends.jq_endtime IS NOT NULL AND
                    (depends.jq_end_bits & jobdepends.jdep_depends_bits) != 0 )
                OR jobdepends.jdep_jq_depends_fk IS NULL
            )
         )',
        '(jobqueue.jq_starttime IS NULL
            AND (
                (depends.jq_pk IS NOT NULL AND depends.jq_endtime IS NULL)
                OR
                (depends.jq_endtime IS NOT NULL AND
                    (depends.jq_end_bits & jobdepends.jdep_depends_bits) != 0 )
                OR jobdepends.jdep_jq_depends_fk IS NULL
            )
        )',
        '(jobqueue.jq_starttime IS NULL
            AND (
                (depends.jq_pk IS NOT NULL AND 
                depends.jq_endtime IS NOT NULL AND
                    (depends.jq_end_bits & jobdepends.jdep_depends_bits) = 0 )
                OR jobdepends.jdep_jq_depends_fk IS NULL
            )
        )',
        '(
            jobqueue.jq_starttime IS NOT NULL
            AND
            jobqueue.jq_endtime IS NULL
        )');

    if ($select == '') $select='job.job_pk,
	    job.job_name,
	    job.job_upload_fk,
	    ufile.ufile_name,
        pfile.pfile_size,
	    job.job_queued,
	    jobqueue.jq_pk,
	    jobqueue.jq_type,
	    date_trunc(\'second\', jobqueue.jq_starttime) AS jq_starttime,
	    date_trunc(\'second\', jobqueue.jq_endtime) AS jq_endtime,
	    jobqueue.jq_end_bits,
        jobqueue.jq_elapsedtime,
        jobqueue.jq_processedtime,
        jobqueue.jq_itemsprocessed,
	    jobdepends.jdep_jq_depends_fk,
	    jobdepends.jdep_depends_bits';
    if ($where == '') $where='true';
    if ($orderbylimit == '')
	$orderbylimit='ORDER BY jq_pk DESC';
    $sql = "
      SELECT  
	    %couldrun AS couldrun,
	    %blocked AS blocked,
	    %ready AS ready,
	    %scheduled AS scheduled,
	    $select
        FROM jobqueue
	    LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
	    LEFT JOIN jobqueue AS depends
		ON depends.jq_pk = jobdepends.jdep_jq_depends_fk
	    LEFT JOIN job ON jobqueue.jq_job_fk = job.job_pk
	    LEFT JOIN upload ON upload_pk = job.job_upload_fk
	    LEFT JOIN ufile ON ufile_pk = upload.ufile_fk
	    LEFT JOIN pfile ON pfile_pk = ufile.pfile_fk
	WHERE $where
	$orderbylimit";

    $sql = str_replace($statustags, $statustext, $sql);
    // echo "<pre>$sql</pre>";
    return db_queryall($sql);
}


/**
 * determine which jobs are "interesting"
 *
 * return an associative array indexed by jq_pk where interesting
 * jq_pk's are set to 1
 */
function jdepchain(&$interesting, $j, $dep)
{
    if (is_array($dep[$j])) foreach (array_keys($dep[$j]) as $dj) {
	$interesting[$dj] = 1;
	jdepchain($interesting, $dj, $dep);
    }
}


/**
 * Display information about a single job
 *
 * Given a job_pk, display information about the job and its steps (jq's).
 * This is only used in the old showjobs() code -- CURRENTLY UNUSED
 */
function showjob($job)
{
    global $xjq, $xjob;

    $jrec = db_find1("job", array("job_pk" => $job));

    $ready = get_readyjqs();
    $active = get_activejqs();

    $alljqs = db_query(
    	"SELECT * FROM jobqueue WHERE jq_job_fk = $job");

    $rowcolor = $rowcolor ? '' : ' bgcolor=#ffccff';
    echo "<form method=post>\n";
    obj('job', '', 'all jobs');
    echo "<table border=0><tr style='background:white;'>\n";
    echo "<tr$rowcolor>\n";
    echo "<td></td>\n";
    echo "<th>jq</th>\n";
    echo "<th>type</th>\n";
    echo "<th>start</th>\n";
    echo "<th>end</th>\n";
    echo "<th>endbits</th>\n";
    echo "<th>depends</th>\n";
    echo "</tr>\n";

    while ($jq = pg_fetch_array($alljqs)) {
	$jqid = $jq['jq_pk'];
	$rowcolor = $rowcolor ? '' : ' bgcolor=#ffccff';
	if (!empty($jq['jq_starttime']) && empty($jq['jq_endtime'])) {
	    $jcolor = ' bgcolor=' . colorcode('scheduled');
	} else if ($ready[$jqid]) {
	    $jcolor = ' bgcolor=' . colorcode('ready');
	} else if ($active[$jqid]) {
	    $jcolor = ' bgcolor=' . colorcode('could run');
	} else {
	    $jcolor = ' bgcolor=' . colorcode('finished');
	}

	echo "<tr $rowcolor>";
	echo "<td valign=top bgcolor=pink>
		<input type=checkbox name=jq[$jqid] value=1 unchecked></td>\n";
	echo "<td$jcolor valign=top>";
	obj('jq', $jqid, $jqid);
	echo "</td>\n";
	echo "<td valign=top>", $jq['jq_type'], "</td>\n";
	echo "<td valign=top>", $jq['jq_starttime'], "</td>\n";
	echo "<td valign=top>", $jq['jq_endtime'], "</td>\n";
	echo "<td valign=top>", $jq['jq_endbits'], "</td>\n";
	echo "<td valign=top>";
	$deps = db_queryall("SELECT * FROM jobdepends WHERE jdep_jq_fk = $jqid");
	$br = "";
	foreach ($deps as $dep) {
	    echo $br; $br = "<br>";
	    $j = $dep['jdep_jq_depends_fk'];
	    $b = $dep['jdep_depends_bits'];
	    obj('jq', $j, $j);
	}
	echo "</td>\n";
	echo "</tr>\n";
    }
    // echo "<tr bgcolor=pink><td></td>
    	// <td colspan=8>
    	// <input type=submit name=submit[deletejq] value='Delete Without Asking'>
	// </td></tr>\n";
    echo "</table>\n</form>\n";

    colorcode();

    echo "<h3>Job $job</h3>\n";
    echo "<table border=1>\n";
    foreach ($jrec as $n => $v) {
        echo "<tr><th align=left valign=top>$n</th>\n";
	echo "<td>$v</td>\n";
    }
    echo "</table>\n";
}


/**
 * display the job table
 *
 * The jfilter GET parameter determines whether to show only
 * interesting jobs (jfilter = 0 or missing) or all jobs (jfilter = 1)
 *
 * This is a GEEK DISPLAY of jobs and unlikely to be the preferred
 * mode for communicating with real users.
 */
function showjobs()
{
    $recs = jobquery('', '', 'ORDER BY jq_pk ASC');

    /* flag the "interesting" jobs */
    foreach ($recs as $jq) 
    {
	    $jq_pk = intval($jq['jq_pk']);
        if (empty($jq['jq_endtime']) || $jq['jq_end_bits'] != '1') 
        {
	        $interesting[$jq_pk] = 1;
	    }
	    $dep[$jq_pk][$jq['jdep_jq_depends_fk']] = 1;
	    $rdep[$jq['jdep_jq_depends_fk']][$jq_pk] = 1;
        $statdep[$jq_pk] = $jq['jq_itemsprocessed'];
    }

    $numjobs = 0;
    /* flag the dependency chains associated with "interesting" jobs */
    if (is_array($interesting)) foreach ($interesting as $jq_pk => $dummy) {
	jdepchain($interesting, $jq_pk, $dep);
	jdepchain($interesting, $jq_pk, $rdep);
    $numjobs++;
    }

    $jfilter = intval($_GET['jfilter']); unset($_GET['jfilter']);
    echo "<div align=right>";
    if ($jfilter == 1) 
    {
	    $url = myname('jfilter=1');
        echo "<a href='$url'> Update </a> | ";
	    $url = myname('jfilter=0');
        echo "showing job history | <a href='$url'>show job queue</a>";
    } 
    else 
    {
    	$url = myname('jfilter=0');
        echo "<a href='$url'> Update </a> | ";
	    $url = myname('jfilter=1');
        echo "<a href='$url'>show job history</a> | showing job queue";
    }
    echo "</div\n>";

    colorcode();
    echo "$numjobs job(s) shown<hr>";

    echo '<table cellpadding=0 style="font-size:smaller;" border=0>';

    $headcolor = "#D2FFC4";
    echo '<tr>';
    echo "<th bgcolor='$headcolor'>Job/Depends on</th>";
    echo "<th bgcolor=$headcolor>Agent</th>";
    echo "<th bgcolor=$headcolor>Status</th>";
    echo "<th></th>";
    echo "<th bgcolor=$headcolor>Time Job<br>Was Submitted</th>";
    echo "<th bgcolor=$headcolor>Agent<br>End Time</th>";
    echo "<th></th>";
    echo "</tr>";

    $newgroup = false;
    foreach ($recs as $jq) {
        $job_pk = intval($jq['job_pk']);
	$jq_pk = intval($jq['jq_pk']);
	if ($jfilter == 0 && !$interesting[$jq_pk]) continue;

	$un = $jq['ufile_name'];
    if ($un != $oldun)
    {
        $oldun = $un;
        //echo "<tr><td colspan=11 bgcolor='#ECF4FF'>&nbsp;</td></tr>";
        // print border seperating job groups
        echo "<tr><td colspan=11 bgcolor='#ECF4FF'><hr></td></tr>";
        $newgroup = true;
    }

	if ($job_pk != $oldjob_pk) 
    {
	    $u = intval($jq['job_upload_fk']);
	    echo "<tr bgcolor='#cccccc'><td style='border: 1px solid black;'>
	     <a href=" . myname("o=job.$job_pk") . ">", $jq['job_name'], "</a>
	     </td>
	     <td style='border: 1px solid black;' colspan=3>
	     <a href=" . myname("g=$u") . ">$un</a>";
        if ($jq['pfile_size']  && $newgroup) 
        {
            $sql = "select count(*) from uploadtree where upload_fk=$jq[job_upload_fk]";
            $filescount = db_query1($sql);
            $num = number_format($jq['pfile_size']);
            echo "<br>$num bytes";
            $num = number_format($filescount);
            echo "<br>$num files";
            $newgroup = false;
        }
	    echo "</td>\n";
        $starttime = substr($jq['job_queued'],0,16);
	    echo "<td style='border: 1px solid black;'>{$starttime}</td>";
	    $oldjob_pk = $job_pk;
	}
	$jqurl = myname("o=jq.$jq_pk");
	if ($jq['scheduled'] == 't') {
	    $jcolor = colorcode('scheduled');
	} else if ($jq['ready'] == 't') {
	    $jcolor = colorcode('ready');
	} else if ($jq['couldrun'] == 't') {
	    $jcolor = colorcode('could run');
	} else if ($jq['blocked'] == 't') {
	    $jcolor = colorcode('blocked');
	} else if ($jq['jq_end_bits'] & 2) {
	    $jcolor = colorcode('failed');
	} else {
	    $jcolor = colorcode('finished');
	}
	$endcolor = '';
	if ($jq['jq_end_bits'] != "1" && $jq['jq_end_bits'] != "0")
		$endcolor=" bgcolor=$jcolor";
	echo "<tr>
	    <td bgcolor='$jcolor'><a href=$jqurl>$jq_pk</a>";
        if (!empty($jq['jdep_jq_depends_fk']))
   	        echo "/ {$jq['jdep_jq_depends_fk']}";
    echo "
        </td>
	    <td bgcolor='$jcolor'>{$jq['jq_type']}</td>";

    $num = number_format($jq['jq_itemsprocessed']);
    echo "<td> $num items";

    // skip the status if the depenency reports 0 items processed 
    // or this jq depends on another and that one report 0 items processed
        if (empty($jq['jdep_jq_depends_fk']))
        {
            printf("<br>&nbsp;elapsed: %s", secs2dhms($jq[jq_elapsedtime]));
            printf("<br>run time: %s", secs2dhms($jq[jq_processedtime]));
        }
        else
        {
            // jq has a dependency. Check if it reports 0 items processed
            $depfk = $jq[jdep_jq_depends_fk];
            if (($statdep[$depfk] == 0) || ($jq['jq_itemsprocessed'] == 0))
                echo " &nbsp; ";
            else
            {
                printf("<br>&nbsp;elapsed: %s", secs2dhms($jq[jq_elapsedtime]));
                printf("<br>run time: %s</td>", secs2dhms($jq[jq_processedtime]));
            }
        }
        echo "</td>";


    $starttime = substr($jq['jq_starttime'],0,16);
    $starttime = " ";
    $endtime = substr($jq['jq_endtime'],0,16);
    echo "
	    <td>$starttime</td>
	    <td> &nbsp; &nbsp; </td>
	    <td>$endtime</td>
	    <td> &nbsp; &nbsp; </td>
	    \n";

	    echo "</tr>\n";
    }
    echo '</table>';

    // printrecs($recs, 'horizontal');
}


/**
 * show a jobqueue entry
 *
 */
function showjq($jqid)
{
    $jqid = intval($jqid);
    $jq = db_queryall("SELECT *, job.* FROM jobqueue
    			LEFT JOIN job ON job.job_pk = jobqueue.jq_job_fk
			WHERE jobqueue.jq_pk = $jqid");
    $jq = $jq[0];

    obj('job', '', 'all jobs');
    echo "<table border=1>\n";
    if (!empty($jq))
      {
      foreach ($jq as $name => $value) {
	echo "<tr><th align=left valign=top>$name</th>\n";
	if ($name == 'jq_job_fk' || $name == 'job_pk') {
	    echo "<td valign=top>";
	    obj('job', $value, $value);
	    echo "</td></tr>\n";
	} else if ($name == 'jq_pk') {
	    echo "<td valign=top>";
	    obj('jq', $value, $value);
	    echo "</td></tr>\n";
	} else {
	    echo "<td valign=top>$value</td></tr>\n";
	}
      }
    }
    echo "<tr><th valign=top align=left>depends</th><td valign=top>";
    $deps = db_queryall("SELECT * FROM jobdepends WHERE jdep_jq_fk = $jqid");
    $br = "";
    foreach ($deps as $dep) {
	echo $br; $br = "<br>";
	$j = $dep['jdep_jq_depends_fk'];
	$b = $dep['jdep_depends_bits'];
	obj('jq', $j, $j);
	echo " ($b)\n";
    }
    echo "</td>\n";
    echo "</table>";
}


/**
 * delete a list of jobs
 *
 * CURRENTLY UNUSED. This implemented the function of deleting jobs which
 * is available through the oldshowjobs() GUI.  It deletes all the
 * stuff associated with a job_pk from jobs, jobdepends, and jobqueue
 */
function job_delete($jlist)
{
    // echo "<pre>job_delete()"; print_r($jlist); echo "</pre>";

    foreach ($jlist as $job) {
	$jqlist = db_queryall("SELECT * FROM jobqueue WHERE jq_job_fk = $job");
	if (is_array($jqlist) && count($jqlist) > 0) {
	    $or = "";
	    $orlist = "";
	    foreach ($jqlist as $jq) {
		$orlist .= $or . "jdep_jq_fk = " . $jq['jq_pk'];
		$or = " OR ";
	    }
	    db_query("DELETE FROM jobdepends WHERE $orlist");
	    $orlist = str_replace('jdep_jq_fk', 'jdep_jq_depends_fk', $orlist);
	    db_query("DELETE FROM jobdepends WHERE $orlist");
	}
	db_query("DELETE FROM jobqueue WHERE jq_job_fk = $job");
	db_query("DELETE FROM job WHERE job_pk = $job");
    }
}


/**
 * create a new job table entry
 *
 * @param integer $upload_pk upload (upload_pk) upon which this job operates
 * @param string $name name of the job for user purposes, unpack etc
 */
function job_create($upload_pk, $name)
{
    global $uid;
    if (empty($upload_pk)) 
        log_writedie("fatal", "bad function input, no upload_pk", "job_create");

    $job = db_insert('job',
		    array('job_upload_fk' => $upload_pk,
		    	'job_submitter' => $uid,
			'job_queued' => date("Y-m-d H:i:s"),
			'job_email_notify' => $uid,
			'job_name' => $name));
    return $job;
}

/**
 * set up one jq to be dependent on another
 *
 * @param integer $job a jq_pk
 * @param integer $dependson the jq_pk of the job that $job depends on
 * @param integer $bits (defaults to 1) matches to jobqueue.jq_endbits with
 * an inadequate algorithm -- intended to implement logic like "run this
 * if the previous job fails, or succeeds, or whatever
 */
function job_depends($job, $dependson, $bits=1)
{
    if (!empty($dependson))
	db_insert('jobdepends', array(
	    'jdep_jq_fk' => $job,
	    'jdep_jq_depends_fk' => $dependson,
	    'jdep_depends_bits' => $bits), 'none');
}

/**
 * create a jobqueue entry
 *
 * @param integer $job job_pk of the associated user-visible job
 * @param string $type type of this job step aka bsam, wget
 * BUG!! should probably be an index into the agent table
 */
function jq_create($job, $type, $args, $depends='', $pfile='', $repeat='no')
{
    $x = array('jq_job_fk' => $job,
	    'jq_type' => $type,
	    'jq_repeat' => $repeat,
	    'jq_args' => $args
	    );
	    
    if (!empty($pfile)) 
	$x['jq_runonpfile'] = $pfile;

    $jq = db_insert('jobqueue', $x, 'jobqueue_jq_pk_seq');

    if (empty($jq)) {
        die("jq is empty in jq_create($job, $ype)");
    }
    job_depends($jq, $depends, 1);

    return $jq;
}

/**
 *  Get the status of unpack agent processing an upload
 *  return the status based on $rtn
 *
 *  @param integer $upload_pk
 *  @param string  $rtn  "string" return status as string
 *                       "array" return vals as array
 */
function job_status_unpack($upload_pk, $rtn="string")
{
   $sql = "select count(*) from uploadtree where upload_fk=$upload_pk";
    $filesunpacked = db_query1($sql);

    if ($rtn == "array")
    {
        $status = array(0=>$filesunpacked);
    }
    else
        $status = "$filesunpacked unpacked ";
    return $status;
}



/**
 * create jobs for all the default agents that run after unpack
 *
 * @param integer $upload_pk associated with job
 * @param integer $unpack_jq_pk  jq_pk of unpack
 */
function job_create_defaults($upload_pk, $unpack_jq_pk='')
{
    $job = job_create($upload_pk, 'License');
    $jq = job_create_license($job, $upload_pk, $unpack_jq_pk);

    $job = job_create($upload_pk, 'Default Meta Agents');
    $jq = job_create_pkgmeta($job, $upload_pk, $unpack_jq_pk);
    $jq = job_create_mimetype($job, $upload_pk, $unpack_jq_pk);

    // specagent depends on mimetype agent
    $jq = job_create_specagent($job, $upload_pk, $jq);   
} 


/**
 * create a wget job with associated dependencies and jobqueue entries
 *
 * @param integer $upload_fk upload (upload_pk) associated with job
 * @param string $tmpfilepath path of wget output file
 * @param string $url URL from which to wget the user's data file
 */
function job_create_wget($upload_fk, $tmpfilepath, $url)
{
    $job = job_create($upload_fk, 'wget');

    $jq = jq_create($job, 'wget', "$upload_fk $tmpfilepath $url");

    return $jq;
}

/**
 * create an unpack job with associated dependencies and jobqueue entries
 *
 * @param integer $upload_fk upload associated with job
 * @param string $name unusued I think
 * @param integer $depends a jq_pk on which this job depends, defaults to none
 */
function job_create_unpack($upload_fk, $name, $depends='')
{
//    $unpackarg = "SELECT ufile.*,
//		    pfile.pfile_sha1 || '.' || pfile.pfile_md5
//		    	|| '.' || pfile.pfile_size AS pfile,
 //           $upload_fk
//		    FROM ufile
//		JOIN pfile ON ufile.pfile_fk = pfile.pfile_pk
//		WHERE ufile.ufile_pk = $upload_fk;";
    $unpackarg = "SELECT 
		    pfile.pfile_sha1 || '.' || pfile.pfile_md5
		    	|| '.' || pfile.pfile_size AS pfile,
            upload.upload_pk, ufile_pk, pfile_fk
		    FROM upload, ufile, pfile
            WHERE ufile.pfile_fk = pfile.pfile_pk
              AND upload.upload_pk = $upload_fk
              AND ufile.ufile_pk = upload.ufile_fk";

    $job = job_create($upload_fk, 'unpack');
    $jq = jq_create($job, 'unpack', $unpackarg, $depends, 'pfile', 'no');

    return $jq;
}


/**
 * create a license job with associated dependencies and jobqueue entries
 *
 * @param integer $upload_pk associated with job
 * @param integer $depends a jq_pk on which this job depends, defaults to none
 */
function job_create_license($job, $upload_pk, $depends='')
{
    // select all the pfiles associated with an upload_pk that haven't already
    // had license run on them

    $filterargs = "SELECT DISTINCT(pfile_pk) as Akey,
          pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
          FROM uploadtree
          INNER JOIN ufile ON uploadtree.ufile_fk=ufile.ufile_pk
          INNER JOIN pfile ON ufile.pfile_fk = pfile.pfile_pk
          LEFT JOIN agent_lic_status ON
              agent_lic_status.pfile_fk = pfile.pfile_pk
          WHERE upload_fk = $upload_pk
          AND agent_lic_status.pfile_fk IS NULL
          AND ufile.pfile_fk IS NOT NULL
          AND (ufile.ufile_mode & (1<<29)) = 0
		    LIMIT 5000;";

    $licensesargs = "SELECT DISTINCT(pfile_pk) as Akey,
			pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
		    FROM uploadtree
            INNER JOIN ufile ON uploadtree.ufile_fk=ufile.ufile_pk
            INNER JOIN pfile ON ufile.pfile_fk = pfile.pfile_pk
		    INNER JOIN agent_lic_status
		    	ON agent_lic_status.pfile_fk = pfile.pfile_pk
		    LEFT JOIN agent_lic_meta
		    	ON pfile.pfile_pk = agent_lic_meta.pfile_fk
		    WHERE agent_lic_status.processed IS FALSE
		    AND agent_lic_meta.pfile_fk IS NULL
		    AND ufile.pfile_fk IS NOT NULL
		    AND (ufile.ufile_mode & (1<<29)) = 0
		    AND upload_fk = $upload_pk
		    LIMIT 5000;";

    $fcleanargs = "SELECT DISTINCT(pfile_pk) as Akey, 
			pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
		    FROM uploadtree
            INNER JOIN ufile ON uploadtree.ufile_fk=ufile.ufile_pk
            INNER JOIN pfile ON ufile.pfile_fk = pfile.pfile_pk
		    INNER JOIN agent_lic_status
		    	ON agent_lic_status.pfile_fk = pfile.pfile_pk
		    WHERE agent_lic_status.processed IS TRUE
		    AND agent_lic_status.inrepository IS TRUE
		    AND upload_fk = $upload_pk
		    AND (ufile.ufile_mode & (1<<29)) = 0
		    LIMIT 5000;";

    $job = job_create($upload_pk, 'license');
    $jq = jq_create($job, 'filter_license', $filterargs, $depends, 'a', 'yes');
    $jq = jq_create($job, 'license', $licensesargs, $jq, 'a', 'yes');
    $jq = jq_create($job, 'filter_clean', $fcleanargs, $jq, 'a', 'yes');

    return $jq;
}

/**
 *  Get the status of license agent processing an upload
 *  return the status based on $rtn
 *
 *  @param integer $upload_pk
 *  @param string  $rtn  "string" return status as string
 *                       "array" return vals as array
 */
function job_status_license($upload_pk, $rtn="string")
{
//    $sql = "select count(*) from lic_progress where upload_fk=$upload_pk";
//    $toprocess = db_query1($sql);
    $sql = "select count(*) from lic_progress where upload_fk=$upload_pk and processed=true";
    $filesprocessed = db_query1($sql);

    if ($rtn == "array")
    {
        $status = array("processed"=>$filesprocessed, "to process"=>$toprocess);
    }
    else
        $status = "$filesprocessed analyzed ";
//        $status = "$filesprocessed/$toprocess analyzed ";
    return $status;
}

/**
 *  Get the status of filter_license agent processing an upload
 *  return the status based on $rtn
 *
 *  @param integer $upload_pk
 *  @param string  $rtn  "string" return status as string
 *                       "array" return vals as array
 */
function job_status_filter_license($upload_pk, $rtn="string")
{
    $sql = "select count(*) from lic_progress where upload_fk=$upload_pk";
    $filtered = db_query1($sql);

    if ($rtn == "array")
    {
        $status = array("processed"=>$filtered);
    }
    else
        $status = "$filtered filtered ";
    return $status;
}

/**
 *  Get the status of filter_clean agent processing an upload
 *  return the status based on $rtn
 *
 *  @param integer $upload_pk
 *  @param string  $rtn  "string" return status as string
 *                       "array" return vals as array
 */
function job_status_filter_clean($upload_pk, $rtn="string")
{
//    $sql = "select count(*) from lic_progress where upload_fk=$upload_pk and processed=true and inrepository=true";
//    $inrepo = db_query1($sql);
    $sql = "select count(*) from lic_progress where upload_fk=$upload_pk and processed=true and inrepository=false";
    $notinrepo = db_query1($sql);
//    $tot = $inrepo + $notinrepo;

    if ($rtn == "array")
    {
        $status = array("inrepo"=>$inrepo, "notinrepo"=>$notinrepo);
    }
    else
        $status = "$notinrepo cleaned ";
//        $status = "$notinrepo/$tot cleaned ";
    return $status;
}


/**
 * create a pkgmeta job with associated dependencies and jobqueue entries
 *
 * @param integer $job associated with job
 * @param integer $upload_pk associated with job
 * @param integer $depends a jq_pk on which this job depends, defaults to none
 */
function job_create_pkgmeta($job, $upload_pk, $depends='')
{
    // look up the pkgmetageta keys
    $pkgmetagetta_id = db_query1("select key_pk from key where key_name='pkgmeta'");
    if (empty($pkgmetagetta_id))
    {
        // this key needed to be preinstalled by the install db load file
	    $msg = "Fatal error: Table: key, key_name='pkgmeta' was not preloaded during install";
        log_write("fatal", $msg, "job_create_license", "key",null,null,true);
        exit();
    }
    
    // get all the pfiles that need processing
    // first find the 'pkgmeta' key id
    $processed_pk = db_query1("select key_pk from key where key_name='Processed' and key_parent_fk=$pkgmetagetta_id");
    if (empty($processed_pk))
    {
        // this key needed to be preinstalled by the install db load file
	    $msg = "Fatal error: Table: key, key_name='Processed' was not preloaded during install";
        log_write("fatal", $msg, "job_create_license", "key",null,null,true);
        exit();
    }
   
    $pkgmetagettaargs = "SELECT DISTINCT(pfile_pk) as Akey, 
			pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
		    FROM uptreeup where upload_fk = $upload_pk  
            EXCEPT
            SELECT DISTINCT(pfile_pk) as Akey, 
			pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
		    FROM uptreeattrib 
            WHERE upload_fk = $upload_pk 
                 and attrib_key_fk=$processed_pk
            LIMIT 5000";
    $jq = jq_create($job, 'pkgmetagetta', $pkgmetagettaargs, $depends, 'a', 'yes');

    return $jq;
}

/**
 *  Get the status of metageta agent processing an upload
 *  return the status based on $rtn
 *
 *  @param integer $upload_pk
 *  @param string  $rtn  "string" return status as string
 *                       "array" return vals as array
 */
function job_status_pkgmeta($upload_pk, $rtn="print")
{
    $pkgmetagetta_id = db_query1("select key_pk from key where key_name='pkgmeta'");
    $sql = "select count(distinct(pfile_pk)) from uptreeatkey 
            where upload_fk=$upload_pk
                  and key_parent_fk=$pkgmetagetta_id";
    $pkgs = db_query1($sql);

    if ($rtn == "array")
    {
        $status = array("packages"=>$pkgs);
    }
    else
        $status = "$pkgs recorded";
    return $status;
}


/**
 * queue a specagent job
 *
 * @param integer $job associated with job
 * @param integer $upload_pk associated with job
 * @param integer $depends a jq_pk on which this job depends, defaults to none
 */
function job_create_specagent($job, $upload_pk, $depends='')
{
    // get the spec file mimetype key
    $mimetype_name = 'application/x-rpm-spec';
    $sql = "select mimetype_pk from mimetype where mimetype_name='$mimetype_name'";
    $mimetype_pk = db_query1($sql);
    if (empty($mimetype_pk))
    {
        // if the mimetype doesn't exist, create it
        $sql1 = "insert into mimetype (mimetype_name) values ('$mimetype_name');";
        db_query($sql1);
        $mimetype_pk = db_query1($sql);
    }

    // get the specfile processed key
    $sql = "select key_pk from keyagent where key_name='Processed' and agent_name='specagent'";
    $processed_id = db_query1($sql);

    $specargs  = "SELECT DISTINCT(pfile_pk) as Akey,
           pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
           FROM uptreeup
           WHERE pfile_mimetypefk=$mimetype_pk
                 AND upload_fk = $upload_pk
            EXCEPT
            SELECT DISTINCT(pfile_pk) as Akey, 
			pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
		    FROM uptreeattrib 
           WHERE pfile_mimetypefk=$mimetype_pk
                 AND upload_fk = $upload_pk
                 and attrib_key_fk=$processed_id
           LIMIT 5000";

    $jq = jq_create($job, 'specagent', $specargs, $depends, 'a', 'yes');

    return $jq;
}

/**
 *  Get the status of specagent agent processing an upload
 *  For an upload:
 *     "processed" Number of spec files processed
 *     "total"     Total number of spec files
 *  return the status based on $rtn
 *
 *  @param integer $upload_pk
 *  @param string  $rtn  "string" return status as string
 *                       "array" return vals as array
 */
function job_status_specagent($upload_pk, $rtn="print")
{
    // get the spec file mimetype key
    $mimetype_name = 'application/x-rpm-spec';
    $mimetype_pk = db_query1("select mimetype_pk from mimetype where mimetype_name='$mimetype_name'");
    if (empty($mimetype_pk))
    {
        // if mimetype doesn't exist, then agent has never run
        if ($rtn == "array")
        {
            $status = array("processed"=>0, "total"=>0);
        }
        else
            $status = "0/0 processed ";
        return $status;
    }

    // how many total spec files are there to process for this upload?
    $sql = "select count(*) from uptreeup where pfile_mimetypefk=$mimetype_pk and upload_fk=$upload_pk";
    $total = db_query1($sql);

    // now do a bunch of work to find out how many have been processed.
    // 1) get the specagent agent_pk
    $specagent_pk = db_query1("select agent_pk from agent where agent_name='specagent'");
    if (empty($specagent_pk))
    {
        // if empty then spec agent has never run
        if ($rtn == "array")
        {
            $status = array("processed"=>0, "total"=>0);
        }
        else
            $status = "0/0 processed ";
        return $status;
    }

    // 2) get the specagent key id
    $specagent_key = db_query1("select key_pk from key where key_parent_fk=0 and key_agent_fk=$specagent_pk");
    if (empty($specagent_key))
    {
        // spec agent has run but never processed
        if ($rtn == "array")
        {
            $status = array("processed"=>0, "total"=>0);
        }
        else
            $status = "0/0 processed ";
        return $status;
    }

    // 3) get the specagent Processed key id
    $processed_key_pk = db_query1("select key_pk from key where key_name='Processed' and key_parent_fk=$specagent_key");
    if (empty($processed_key_pk))
    {
        // spec agent has run but never processed
        if ($rtn == "array")
        {
            $status = array("processed"=>0, "total"=>0);
        }
        else
            $status = "0/0 processed ";
        return $status;
    }

    // 4) get number of specs processed
        $sql = "select count(distinct(pfile_pk)) from uptreeattrib where upload_fk=$upload_pk 
                and attrib_key_fk=$processed_key_pk";
        $numprocessed = db_query1($sql);
        if ($rtn == "array")
        {
            $status = array("processed"=>$numprocessed, "total"=>$total);
        }
        else
            $status = "$numprocessed/$total processed ";
        return $status;
}

/**
 * create a mimetype job with associated dependencies and jobqueue entries
 *
 * @param integer $job associated with job
 * @param integer $upload_pk associated with job
 * @param integer $depends a jq_pk on which this job depends, defaults to none
 */
function job_create_mimetype($job, $upload_pk, $depends='')
{

    $mimetypeargs  = "SELECT DISTINCT(pfile_pk) as Akey,
           pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS A
           FROM uploadtree
           INNER JOIN ufile ON uploadtree.ufile_fk=ufile.ufile_pk
           INNER JOIN pfile ON ufile.pfile_fk = pfile.pfile_pk
           WHERE pfile_mimetypefk is NULL
                 AND upload_fk = $upload_pk
           LIMIT 5000";

    $jq = jq_create($job, 'mimetype', $mimetypeargs, $depends, 'a', 'yes');

    return $jq;
}

/**
 *  Get the status of mimetype agent processing an upload
 *  return the status based on $rtn
 *
 *  @param integer $upload_pk
 *  @param string  $rtn  "string" return status as string
 *                       "array" return vals as array
 */
function job_status_mimetype($upload_pk, $rtn="print")
{
        $sql = "select count(distinct(pfile_pk)) from uptreeup where upload_fk=$upload_pk
                and pfile_mimetypefk is not null";
        $processed = db_query1($sql);
        $sql = "select count(distinct(pfile_pk)) from uptreeup where upload_fk=$upload_pk";
        $total = db_query1($sql);
        if ($rtn == "array")
        {
            $status = array("processed"=>$processed, "total"=>$total);
        }
        else
            $status = "$processed/$total processed ";
        return $status;
}

/**
 * create a wc job with associated dependencies and jobqueue entries
 *
 * @param integer $proj project (ufile_pk) associated with job
 * @param integer $depends a jq_pk on which this job depends, defaults to none
 */
function job_create_wc($proj, $depends='')
{
    echo "<br>job_create_wc($proj, $depends)\n";

    $wcarg = "SELECT DISTINCT(pfile_pk), 
	    pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfile
	    FROM containers
		INNER JOIN ufile ON ufile_container_fk = contained_fk
		INNER JOIN pfile ON pfile_pk = pfile_fk
		LEFT JOIN agent_wc ON agent_wc.pfile_fk = pfile.pfile_pk
	    WHERE container_fk = $proj
		AND ufile.pfile_fk IS NOT NULL
		AND ( ufile_mode & (1<<29)) = 0
		AND agent_wc.pfile_fk IS NULL
		LIMIT 1000;";

    $job = job_create($proj, 'wc');
    $jq = jq_create($job, 'wc-pfiles', $wcarg, $depends, 'pfile', 'yes');

    return $jq;
}


?>
