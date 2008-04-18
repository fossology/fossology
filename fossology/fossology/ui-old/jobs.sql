-- jobs.sql
-- Copyright (C) 2007 Hewlett-Packard Development Company, L.P. 
--
-- This program is free software; you can redistribute it and/or
-- modify it under the terms of the GNU General Public License
-- version 2 as published by the Free Software Foundation.
-- 
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
-- 
-- You should have received a copy of the GNU General Public License along
-- with this program; if not, write to the Free Software Foundation, Inc.,
-- 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

// list jobs which could be run now
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
ORDER BY job.job_priority DESC

// claim a job
UPDATE jobqueue SET jq_starttime = now() WHERE jq_pk = ??ID??

// finish a job
UPDATE jobqueue
    SET jq_endtime = now(), jq_end_bits = 1, jq_endtext (if appropriate)
    WHERE jq_pk = ??ID??

// what User jobs are running?
SELECT DISTINCT(jq_pk) FROM job
    LEFT JOIN jobqueue ON job.job_pk = jobqueue.jq_job_fk
WHERE
    jobqueue.jq_starttime IS NOT NULL
    AND 
    jobqueue.jq_endtime IS NULL
    
// list pfiles which need wc processing, pfile concat
SELECT DISTINCT(pfile_pk),
	pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS run_on_pfile
	FROM pfile
	INNER JOIN ufile ON ufile.pfile_fk = pfile.pfile_pk
	LEFT JOIN agent_wc ON agent_wc.pfile_fk = pfile.pfile_pk
	WHERE ufile.ufile_proj_fk = 9
	AND agent_wc.pfile_fk IS NULL
	AND ufile.pfile_fk IS NOT NULL
	AND ( ufile.ufile_mode & (1<<29)) = 0 
	LIMIT 10
	;

// how many distinct pfiles need wc processing?
SELECT COUNT(DISTINCT(pfile_pk)) FROM pfile
	INNER JOIN ufile ON ufile.pfile_fk = pfile.pfile_pk
	LEFT JOIN agent_wc ON agent_wc.pfile_fk = pfile.pfile_pk
	WHERE ufile.ufile_proj_fk = 9
	AND agent_wc.pfile_fk IS NULL
	AND ufile.pfile_fk IS NOT NULL
	AND ( ufile.ufile_mode & (1<<29)) = 0 
	;

// how many ufiles need wc processing?
SELECT COUNT(*) FROM ufile
	LEFT JOIN agent_wc ON agent_wc.pfile_fk = ufile.pfile_fk
	WHERE ufile.ufile_proj_fk = 9
	AND agent_wc.pfile_fk IS NULL
	AND ufile.pfile_fk IS NOT NULL
	AND ( ufile.ufile_mode & (1<<29)) = 0 
	;


// update/join example
update ufile set gid = 999 from
(select distinct(ufile_container_fk) as x from ufile where ufile_proj_fk = 280) as foo
where foo.x = ufile.ufile_pk

// cute way to look at a set of u/p files
select (ufile_mode & (1<<29)) != 0 as C,
	(ufile_mode & (1<<28)) != 0 as A,
	(ufile_mode & (1<<27)) != 0 as P,
	(ufile_mode & (1<<26)) != 0 as R,
	(ufile_mode & (1<<14)) != 0 as D,
	ufile_pk, ufile_container_fk, ufile_name from ufile
	where ufile_container_fk = 282 order by ufile_container_fk

// which pfiles are seen in the most projects (nproj) or at all (nufile)?
select pfile_pk, count(distinct(proj.ufile_pk)) as nproj,
	    count(distinct(ufile.ufile_pk)) as nufile
	from pfile
        left join ufile on ufile.pfile_fk = pfile.pfile_pk
        left join containers on contained_fk = ufile.ufile_container_fk
        left join ufile as proj on proj.ufile_pk = container_fk
        where (proj.ufile_mode & (1<<27)) != 0
        group by pfile.pfile_pk
        order by nufile desc limit 10;
