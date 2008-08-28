create role fossy with createdb login password 'fossy';
--
-- PostgreSQL database dump
--

SET client_encoding = 'SQL_ASCII';
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: fossology; Type: DATABASE; Schema: -; Owner: fossy
--

CREATE DATABASE fossology WITH TEMPLATE = template0 ENCODING = 'SQL_ASCII';


ALTER DATABASE fossology OWNER TO fossy;

\connect fossology
CREATE LANGUAGE plpgsql;

SET client_encoding = 'SQL_ASCII';
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: postgres
--

COMMENT ON SCHEMA public IS 'Standard public schema';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: agent; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE agent (
    agent_pk serial NOT NULL,
    agent_name character varying(32) NOT NULL,
    agent_rev character varying(32),
    agent_desc character varying(255),
    agent_id integer NOT NULL
);


ALTER TABLE public.agent OWNER TO fossy;

--
-- Name: TABLE agent; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE agent IS 'Defines each agent used in the system.  
A new agent record should be defined any time an agent changes in a way that alters the  output given the same input.';


--
-- Name: COLUMN agent.agent_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent.agent_pk IS 'Primary key';


--
-- Name: COLUMN agent.agent_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent.agent_name IS 'display name';


--
-- Name: COLUMN agent.agent_rev; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent.agent_rev IS 'revision string';


--
-- Name: COLUMN agent.agent_desc; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent.agent_desc IS 'short description';


--
-- Name: COLUMN agent.agent_id; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent.agent_id IS 'agent ID (agents will have same ID regardless of revision)';


--
-- Name: agent_lic_meta; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE agent_lic_meta (
    pfile_fk integer NOT NULL,
    tok_pfile integer,
    tok_match integer,
    version character varying(32),
    phrase_text character varying(256),
    tok_pfile_start integer,
    tok_pfile_end integer,
    tok_license_start integer,
    tok_license_end integer,
    tok_license integer,
    lic_fk integer,
    pfile_path character varying,
    license_path character varying
);


ALTER TABLE public.agent_lic_meta OWNER TO fossy;

--
-- Name: TABLE agent_lic_meta; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE agent_lic_meta IS 'License analysis results';


--
-- Name: COLUMN agent_lic_meta.tok_pfile; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.tok_pfile IS '# Tokens found in pfile';


--
-- Name: COLUMN agent_lic_meta.tok_match; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.tok_match IS '# tokens from match';


--
-- Name: COLUMN agent_lic_meta.version; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.version IS 'Version of license agent - this should be replaced by a agent_fk';


--
-- Name: COLUMN agent_lic_meta.phrase_text; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.phrase_text IS 'Section of the file containing the license.';


--
-- Name: COLUMN agent_lic_meta.tok_pfile_start; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.tok_pfile_start IS 'Offset of starting byte in the pfile (for display)';


--
-- Name: COLUMN agent_lic_meta.tok_pfile_end; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.tok_pfile_end IS 'Offset of ending byte in the pfile (for display)';


--
-- Name: COLUMN agent_lic_meta.tok_license_start; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.tok_license_start IS 'byte offset in license of start';


--
-- Name: COLUMN agent_lic_meta.tok_license_end; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.tok_license_end IS 'byte offset in license of end';


--
-- Name: COLUMN agent_lic_meta.tok_license; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.tok_license IS '# tokens found in license';


--
-- Name: COLUMN agent_lic_meta.lic_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.lic_fk IS 'Link to the agent_lic_raw table';


--
-- Name: COLUMN agent_lic_meta.pfile_path; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.pfile_path IS 'The file offsets for the best match path';


--
-- Name: COLUMN agent_lic_meta.license_path; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_meta.license_path IS 'The file offsets for the best match in the license';


--
-- Name: agent_lic_phrases; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE agent_lic_phrases (
    phrase_group character varying NOT NULL,
    phrase_match character varying NOT NULL,
    phrase_unique integer
);


ALTER TABLE public.agent_lic_phrases OWNER TO fossy;

--
-- Name: TABLE agent_lic_phrases; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE agent_lic_phrases IS 'Use this list to group phrases. If the match string exists in the phrase (case-insensitive), then mark it as being part of this group.';


--
-- Name: COLUMN agent_lic_phrases.phrase_unique; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_phrases.phrase_unique IS 'The unique value to add to the license table';


--
-- Name: agent_lic_raw; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE agent_lic_raw (
    lic_pk serial NOT NULL,
    lic_name text NOT NULL,
    lic_unique text NOT NULL,
    lic_text text,
    lic_version integer DEFAULT 1 NOT NULL,
    lic_section text,
    lic_id integer,
    lic_name_full text,
    lic_url text,
    lic_date timestamp with time zone
);


ALTER TABLE public.agent_lic_raw OWNER TO fossy;

--
-- Name: TABLE agent_lic_raw; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE agent_lic_raw IS 'Raw licenses used for license analysis';


--
-- Name: COLUMN agent_lic_raw.lic_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_raw.lic_pk IS 'License primary key.  Uniquely identifies a license section.';


--
-- Name: COLUMN agent_lic_raw.lic_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_raw.lic_name IS 'Common license name (typically abbreviation like GPLv2)';


--
-- Name: COLUMN agent_lic_raw.lic_unique; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_raw.lic_unique IS 'A unique identifier for the license (used to remove duplicate entries)';


--
-- Name: COLUMN agent_lic_raw.lic_text; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_raw.lic_text IS 'The actual license text';


--
-- Name: COLUMN agent_lic_raw.lic_version; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_raw.lic_version IS 'Analysis code version (bsam/filter)';


--
-- Name: COLUMN agent_lic_raw.lic_section; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_raw.lic_section IS 'Section identifier';


--
-- Name: COLUMN agent_lic_raw.lic_id; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_raw.lic_id IS 'uniquely identifies a license (s opposed to a license section)';


--
-- Name: COLUMN agent_lic_raw.lic_name_full; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_raw.lic_name_full IS 'Full text license name';


--
-- Name: COLUMN agent_lic_raw.lic_url; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_raw.lic_url IS 'URL origin of the license';


--
-- Name: COLUMN agent_lic_raw.lic_date; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_raw.lic_date IS 'when license text was captured';


--
-- Name: agent_lic_status; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE agent_lic_status (
    pfile_fk integer NOT NULL,
    processed boolean DEFAULT false NOT NULL,
    inrepository boolean DEFAULT false NOT NULL
);


ALTER TABLE public.agent_lic_status OWNER TO fossy;

--
-- Name: TABLE agent_lic_status; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE agent_lic_status IS 'List of license analysis status.';


--
-- Name: COLUMN agent_lic_status.processed; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_status.processed IS 'Has this file been processed by the license agent?';


--
-- Name: COLUMN agent_lic_status.inrepository; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_lic_status.inrepository IS 'Is the cache file in the repository?';


--
-- Name: agent_wc; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE agent_wc (
    pfile_fk integer,
    wc_words integer,
    wc_lines integer
);


ALTER TABLE public.agent_wc OWNER TO fossy;

--
-- Name: TABLE agent_wc; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE agent_wc IS 'Table populated by WC agent.  
This is a test/learning agent  only.';


--
-- Name: COLUMN agent_wc.pfile_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_wc.pfile_fk IS 'pfile key';


--
-- Name: COLUMN agent_wc.wc_words; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_wc.wc_words IS 'wc words';


--
-- Name: COLUMN agent_wc.wc_lines; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN agent_wc.wc_lines IS 'wc lines';


--
-- Name: attrib; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE attrib (
    attrib_pk serial NOT NULL,
    attrib_key_fk integer NOT NULL,
    attrib_value text,
    pfile_fk integer NOT NULL
);


ALTER TABLE public.attrib OWNER TO fossy;

--
-- Name: TABLE attrib; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE attrib IS 'attribute table.  Relates keys to key values.';


--
-- Name: COLUMN attrib.attrib_key_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN attrib.attrib_key_fk IS 'attribute key reference';


--
-- Name: COLUMN attrib.attrib_value; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN attrib.attrib_value IS 'attribute value';


--
-- Name: COLUMN attrib.pfile_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN attrib.pfile_fk IS 'key of pfile record that attribute refers to';


--
-- Name: folder; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE folder (
    folder_pk serial NOT NULL,
    folder_name text NOT NULL,
    folder_desc text,
    folder_perm integer
);


ALTER TABLE public.folder OWNER TO fossy;

--
-- Name: TABLE folder; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE folder IS 'Define a user folder.  Folder construction is totally arbitrary and is defined by the user.  
Contents can be other folders, uploads and uploadtree records.';


--
-- Name: COLUMN folder.folder_perm; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN folder.folder_perm IS 'future permission';


--
-- Name: foldercontents; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE foldercontents (
    foldercontents_pk serial NOT NULL,
    parent_fk integer NOT NULL,
    foldercontents_mode integer NOT NULL,
    child_id integer NOT NULL
);


ALTER TABLE public.foldercontents OWNER TO fossy;

--
-- Name: TABLE foldercontents; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE foldercontents IS 'Folder contents may be another folder, an upload_fk, or an uploadtree_fk.  This is user definable.';


--
-- Name: COLUMN foldercontents.parent_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN foldercontents.parent_fk IS 'parent folder_fk';


--
-- Name: COLUMN foldercontents.foldercontents_mode; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN foldercontents.foldercontents_mode IS '1<<0 child is folder_fk, 1<<1 child is upload_fk, 1<<2 child is an uploadtree_fk';


--
-- Name: job; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE job (
    job_pk serial NOT NULL,
    job_ufile_fk integer,
    job_submitter character varying(64) NOT NULL,
    job_queued timestamp with time zone,
    job_priority integer DEFAULT 0 NOT NULL,
    job_email_notify text,
    job_name text,
    job_upload_fk integer
);


ALTER TABLE public.job OWNER TO fossy;

--
-- Name: TABLE job; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE job IS 'Job info, one entry per user-meaningful job not per step';


--
-- Name: COLUMN job.job_ufile_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN job.job_ufile_fk IS 'ufile associated with job';


--
-- Name: COLUMN job.job_submitter; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN job.job_submitter IS 'job submitter name';


--
-- Name: COLUMN job.job_email_notify; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN job.job_email_notify IS 'list of emails to notify on completion (format?)';


--
-- Name: COLUMN job.job_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN job.job_name IS 'what to call this job, for the user';


--
-- Name: COLUMN job.job_upload_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN job.job_upload_fk IS 'upload this job applies to';


--
-- Name: jobdepends; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE jobdepends (
    jdep_jq_fk integer,
    jdep_jq_depends_fk integer,
    jdep_depends_bits smallint
);


ALTER TABLE public.jobdepends OWNER TO fossy;

--
-- Name: COLUMN jobdepends.jdep_depends_bits; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobdepends.jdep_depends_bits IS 'This is a mask to use with jobqueue.jq_end_bits. If jq_end_bits & jdepdepends_bits != 0, then this job can be scheduled.';


--
-- Name: jobqueue; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE jobqueue (
    jq_pk serial NOT NULL,
    jq_job_fk integer NOT NULL,
    jq_type text NOT NULL,
    jq_args text,
    jq_length integer DEFAULT 0 NOT NULL,
    jq_progress integer DEFAULT 0 NOT NULL,
    jq_starttime timestamp with time zone,
    jq_endtime timestamp with time zone,
    jq_endurl text,
    jq_endtext text,
    jq_end_bits smallint DEFAULT 0 NOT NULL,
    jq_sqlprogress text,
    jq_schedinfo text,
    jq_repeat character(3) DEFAULT 'no'::bpchar NOT NULL,
    jq_runonpfile character(12),
    jq_units character varying(32),
    jq_statustime timestamp with time zone,
    jq_elapsedtime integer DEFAULT 0,
    jq_processedtime integer DEFAULT 0,
    jq_itemsprocessed integer DEFAULT 0
);


ALTER TABLE public.jobqueue OWNER TO fossy;

--
-- Name: TABLE jobqueue; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE jobqueue IS 'queue of steps required to accomplish user-meaningful jobs';


--
-- Name: COLUMN jobqueue.jq_job_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_job_fk IS 'which user job this step supports';


--
-- Name: COLUMN jobqueue.jq_type; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_type IS 'text agent name - THIS SHOULD BE REPLACED BY agent_fk';


--
-- Name: COLUMN jobqueue.jq_args; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_args IS 'arbitrary text understood by the agent';


--
-- Name: COLUMN jobqueue.jq_length; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_length IS 'number of "items" to process (file size in K for example) or NULL/0 if unknown';


--
-- Name: COLUMN jobqueue.jq_progress; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_progress IS '# items processed in same units as jq_length';


--
-- Name: COLUMN jobqueue.jq_starttime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_starttime IS 'set this field to indicate job is in progress';


--
-- Name: COLUMN jobqueue.jq_endtime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_endtime IS 'set this when job completed for whatever reason';


--
-- Name: COLUMN jobqueue.jq_endurl; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_endurl IS 'optional URL associated with end of this job, error report for example';


--
-- Name: COLUMN jobqueue.jq_end_bits; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_end_bits IS 'bitmask(ok=0x1, fail=0x2, nonfatal=0x4)';


--
-- Name: COLUMN jobqueue.jq_sqlprogress; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_sqlprogress IS 'if set, an SQL query which returns a single value in same units as jq_length whereupon jq_progress is unusued';


--
-- Name: COLUMN jobqueue.jq_schedinfo; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_schedinfo IS 'Field for information storage by job scheduler(s) presumably useful for cleanup after failures too';


--
-- Name: COLUMN jobqueue.jq_repeat; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_repeat IS 'yes means re-queue this job until the query returns empty.  only meaningful when jq_runonpfile is set';


--
-- Name: COLUMN jobqueue.jq_runonpfile; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_runonpfile IS 'when set, scheduler runs the jq_arg as sql and sends each row to the host indicated by the jq_runonpfile column (a pfile name)';


--
-- Name: COLUMN jobqueue.jq_units; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_units IS 'the units name of jq_progress and jq_length';


--
-- Name: COLUMN jobqueue.jq_statustime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_statustime IS 'last time the agent gave a progress update, even when progress didn''t change';


--
-- Name: COLUMN jobqueue.jq_elapsedtime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_elapsedtime IS 'Elapsed secs working on jq';


--
-- Name: COLUMN jobqueue.jq_processedtime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_processedtime IS 'sum of process times (secs) from all agents';


--
-- Name: COLUMN jobqueue.jq_itemsprocessed; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN jobqueue.jq_itemsprocessed IS 'sum of files processed by all agents';


--
-- Name: key; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE "key" (
    key_pk serial NOT NULL,
    key_name character varying(32) NOT NULL,
    key_desc character varying(255),
    key_parent_fk integer DEFAULT 0 NOT NULL,
    key_agent_fk integer NOT NULL
);


ALTER TABLE public."key" OWNER TO fossy;

--
-- Name: TABLE "key"; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE "key" IS 'Define each attribute key.  This helps make the attribute data self documenting.';


--
-- Name: COLUMN "key".key_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN "key".key_name IS 'displayed key name';


--
-- Name: COLUMN "key".key_desc; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN "key".key_desc IS 'brief description of the key';


--
-- Name: COLUMN "key".key_parent_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN "key".key_parent_fk IS 'parent key_pk (keys may be nested), 0 if no parent, 0 is used instead of null beause of unique constraint';


--
-- Name: COLUMN "key".key_agent_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN "key".key_agent_fk IS 'agent id to use to populate this data type';


--
-- Name: keyagent; Type: VIEW; Schema: public; Owner: fossy
--

CREATE VIEW keyagent AS
    SELECT "key".key_pk, "key".key_name, "key".key_desc, "key".key_parent_fk, "key".key_agent_fk, agent.agent_pk, agent.agent_name, agent.agent_rev, agent.agent_desc, agent.agent_id FROM "key", agent WHERE (agent.agent_pk = "key".key_agent_fk);


ALTER TABLE public.keyagent OWNER TO fossy;

--
-- Name: VIEW keyagent; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON VIEW keyagent IS 'key, agent join';


SET default_with_oids = true;

--
-- Name: ufile; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE ufile (
    ufile_pk serial NOT NULL,
    ufile_name text NOT NULL,
    ufile_mode integer,
    ufile_mtime timestamp without time zone,
    pfile_fk integer,
    ufile_ts timestamp with time zone DEFAULT now(),
    ufile_container_fk integer NOT NULL,
    gid integer,
    gname text,
    ouid integer,
    oname text
);


ALTER TABLE public.ufile OWNER TO fossy;

--
-- Name: COLUMN ufile.ufile_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN ufile.ufile_name IS 'filename';


--
-- Name: COLUMN ufile.ufile_mode; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN ufile.ufile_mode IS 'container=1<<29, artifact=1<<28, project=1<<27, replica(same pfile)=1<<26, directory=1<<18 (directory doesn''t seem to be used), note: wget and POST names are NOT considered artifacts.';


--
-- Name: COLUMN ufile.ufile_ts; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN ufile.ufile_ts IS 'timestamp ufile record was last modified';


--
-- Name: COLUMN ufile.ufile_container_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN ufile.ufile_container_fk IS 'OBSOLETE parent';


--
-- Name: COLUMN ufile.gid; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN ufile.gid IS 'group id from unpack';


--
-- Name: COLUMN ufile.gname; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN ufile.gname IS 'group name from unpack';


--
-- Name: COLUMN ufile.ouid; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN ufile.ouid IS 'file UID from unpack';


--
-- Name: COLUMN ufile.oname; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN ufile.oname IS 'owner name from unpack';


SET default_with_oids = false;

--
-- Name: upload; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE upload (
    upload_pk serial NOT NULL,
    upload_desc text,
    upload_filename text NOT NULL,
    upload_userid text,
    upload_mode integer NOT NULL,
    upload_ts timestamp with time zone DEFAULT now() NOT NULL,
    ufile_fk integer NOT NULL
);


ALTER TABLE public.upload OWNER TO fossy;

--
-- Name: TABLE upload; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE upload IS 'Information about (gold) files added to the repo.';


--
-- Name: COLUMN upload.upload_desc; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN upload.upload_desc IS 'description of file';


--
-- Name: COLUMN upload.upload_filename; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN upload.upload_filename IS 'file name';


--
-- Name: COLUMN upload.upload_userid; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN upload.upload_userid IS 'who uploaded this file FUTURE';


--
-- Name: COLUMN upload.upload_mode; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN upload.upload_mode IS '1 upload complete, 1<<1 gold (devel only), 1<<2 wget, 1<<3 web upload, 1<<4 discovery ; 1<<5 ununpack complete without fatal errors';


--
-- Name: COLUMN upload.upload_ts; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN upload.upload_ts IS 'record (upload) creation time';


--
-- Name: COLUMN upload.ufile_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN upload.ufile_fk IS 'needed by ununpack ONLY, others should use uploadtree_pk';


--
-- Name: leftnav; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW leftnav AS
    SELECT folder.folder_pk, folder.folder_name AS name, folder.folder_desc AS description, foldercontents.parent_fk AS parent, foldercontents.foldercontents_mode, NULL::"unknown" AS ts, NULL::"unknown" AS upload_pk, NULL::"unknown" AS ufile_pk, NULL::"unknown" AS pfile_fk, NULL::"unknown" AS ufile_mode FROM folder, foldercontents WHERE ((foldercontents.foldercontents_mode = 1) AND (foldercontents.child_id = folder.folder_pk)) UNION ALL SELECT NULL::"unknown" AS folder_pk, ufile.ufile_name AS name, upload.upload_desc AS description, foldercontents.parent_fk AS parent, foldercontents.foldercontents_mode, upload.upload_ts AS ts, upload.upload_pk, ufile.ufile_pk, ufile.pfile_fk, ufile.ufile_mode FROM ufile, upload, foldercontents WHERE (((upload.ufile_fk = ufile.ufile_pk) AND (foldercontents.foldercontents_mode = 2)) AND (foldercontents.child_id = upload.upload_pk));


ALTER TABLE public.leftnav OWNER TO postgres;

--
-- Name: lic_1295; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE lic_1295 (
    uploadtree_pk integer,
    ufile_fk integer,
    parent integer,
    upload_fk integer,
    pfile_pk integer,
    pfile_md5 character(32),
    pfile_sha1 character(40),
    pfile_size bigint,
    pfile_mimetypefk integer,
    pfile_usecount integer
);


ALTER TABLE public.lic_1295 OWNER TO fossy;

--
-- Name: uploadtree; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE uploadtree (
    uploadtree_pk serial NOT NULL,
    ufile_fk integer NOT NULL,
    parent integer,
    upload_fk integer NOT NULL
);


ALTER TABLE public.uploadtree OWNER TO fossy;

--
-- Name: TABLE uploadtree; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE uploadtree IS 'Define the entire ufile tree for a given upload.
This is the navigation path for upload contents.  ';


--
-- Name: COLUMN uploadtree.parent; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN uploadtree.parent IS 'uploadtree parent';


--
-- Name: COLUMN uploadtree.upload_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN uploadtree.upload_fk IS 'original uploaded file';


--
-- Name: lic_progress; Type: VIEW; Schema: public; Owner: fossy
--

CREATE VIEW lic_progress AS
    SELECT uploadtree.uploadtree_pk, uploadtree.ufile_fk, uploadtree.parent, uploadtree.upload_fk, ufile.ufile_pk, ufile.ufile_name, ufile.ufile_mode, ufile.ufile_mtime, ufile.pfile_fk, ufile.ufile_ts, agent_lic_status.processed, agent_lic_status.inrepository FROM uploadtree, ufile, agent_lic_status WHERE ((uploadtree.ufile_fk = ufile.ufile_pk) AND (agent_lic_status.pfile_fk = ufile.pfile_fk));


ALTER TABLE public.lic_progress OWNER TO fossy;

--
-- Name: VIEW lic_progress; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON VIEW lic_progress IS 'track license agent progress by upload';


--
-- Name: log; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE log (
    log_table_enum integer NOT NULL,
    log_rec_fk integer,
    log_type integer NOT NULL,
    log_message text NOT NULL,
    log_date timestamp with time zone DEFAULT now() NOT NULL,
    log_pk serial NOT NULL,
    log_jq_fk integer,
    log_logger text
);


ALTER TABLE public.log OWNER TO fossy;

--
-- Name: TABLE log; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE log IS 'Errors, warnings, and processing logs.';


--
-- Name: COLUMN log.log_table_enum; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN log.log_table_enum IS 'enum see table_enum';


--
-- Name: COLUMN log.log_rec_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN log.log_rec_fk IS 'Record identifier';


--
-- Name: COLUMN log.log_type; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN log.log_type IS 'Type of log record: 0=debug, 1=warning, 2=error, 3=fatal';


--
-- Name: COLUMN log.log_message; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN log.log_message IS 'Message text';


--
-- Name: COLUMN log.log_date; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN log.log_date IS 'Date log was added';


--
-- Name: COLUMN log.log_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN log.log_pk IS 'PK for log';


--
-- Name: COLUMN log.log_jq_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN log.log_jq_fk IS 'Link to job that created this log entry.';


--
-- Name: COLUMN log.log_logger; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN log.log_logger IS 'logger (function, script name, etc)  when table_enum is unknown ';


--
-- Name: mimetype; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE mimetype (
    mimetype_pk serial NOT NULL,
    mimetype_name text NOT NULL
);


ALTER TABLE public.mimetype OWNER TO fossy;

--
-- Name: TABLE mimetype; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE mimetype IS 'Define mime types for containers';


--
-- Name: pfile; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE pfile (
    pfile_pk serial NOT NULL,
    pfile_md5 character(32) NOT NULL,
    pfile_sha1 character(40) NOT NULL,
    pfile_size bigint NOT NULL,
    pfile_mimetypefk integer,
    pfile_usecount integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.pfile OWNER TO fossy;

--
-- Name: TABLE pfile; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE pfile IS 'physical files';


--
-- Name: COLUMN pfile.pfile_mimetypefk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN pfile.pfile_mimetypefk IS 'NULL is treated as application/octet-stream';


--
-- Name: proj; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE proj (
    ufile_fk integer NOT NULL,
    proj_desc text NOT NULL,
    proj_origin text NOT NULL,
    proj_creator text
);


ALTER TABLE public.proj OWNER TO fossy;

--
-- Name: COLUMN proj.proj_creator; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN proj.proj_creator IS 'who created this project';


--
-- Name: scheduler_status; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE scheduler_status (
    unique_scheduler text NOT NULL,
    agent_number integer NOT NULL,
    agent_attrib text NOT NULL,
    agent_status text NOT NULL,
    agent_status_date timestamp without time zone,
    record_update timestamp without time zone DEFAULT now() NOT NULL,
    agent_host text,
    agent_fk integer,
    agent_param text
);


ALTER TABLE public.scheduler_status OWNER TO fossy;

--
-- Name: TABLE scheduler_status; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE scheduler_status IS 'The status of the scheduler.';


--
-- Name: COLUMN scheduler_status.unique_scheduler; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN scheduler_status.unique_scheduler IS 'unique id for the scheduler: gethostid()+getpid() for the scheduler';


--
-- Name: COLUMN scheduler_status.agent_number; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN scheduler_status.agent_number IS 'unique id for the agent based on the scheduler';


--
-- Name: COLUMN scheduler_status.agent_attrib; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN scheduler_status.agent_attrib IS 'attributes for the agent (type, host, etc.)';


--
-- Name: COLUMN scheduler_status.agent_status; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN scheduler_status.agent_status IS 'run-time status of the agent';


--
-- Name: COLUMN scheduler_status.agent_status_date; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN scheduler_status.agent_status_date IS 'time of the last status change for the agent';


--
-- Name: COLUMN scheduler_status.record_update; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN scheduler_status.record_update IS 'time this record was last updated (scheduler updates at least once a minute so old entries indicate a dead scheduler)';


--
-- Name: COLUMN scheduler_status.agent_host; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN scheduler_status.agent_host IS 'The host class where this job runs (value from the host= scheduler.conf field)';


--
-- Name: COLUMN scheduler_status.agent_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN scheduler_status.agent_fk IS 'Linking scheduler.conf "agent=" to agent table.';


--
-- Name: COLUMN scheduler_status.agent_param; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN scheduler_status.agent_param IS 'The last parameters sent to the agent (for debugging)';


--
-- Name: sqlagentproc; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE sqlagentproc (
    sap_pk serial NOT NULL,
    sap_name text NOT NULL,
    sap_proc text NOT NULL,
    agent_fk integer NOT NULL,
    sap_version integer DEFAULT 1 NOT NULL
);


ALTER TABLE public.sqlagentproc OWNER TO fossy;

--
-- Name: TABLE sqlagentproc; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE sqlagentproc IS 'sql procedures for agents';


--
-- Name: COLUMN sqlagentproc.sap_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN sqlagentproc.sap_name IS 'procedure name';


--
-- Name: COLUMN sqlagentproc.sap_proc; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN sqlagentproc.sap_proc IS 'sql stmts';


--
-- Name: COLUMN sqlagentproc.agent_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN sqlagentproc.agent_fk IS 'agent foreign key';


--
-- Name: COLUMN sqlagentproc.sap_version; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN sqlagentproc.sap_version IS 'procedure version';


--
-- Name: table_enum; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE table_enum (
    table_pk serial NOT NULL,
    table_name character varying NOT NULL,
    table_enum integer NOT NULL
);


ALTER TABLE public.table_enum OWNER TO fossy;

--
-- Name: TABLE table_enum; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE table_enum IS 'enum of tables';


--
-- Name: uplicense; Type: VIEW; Schema: public; Owner: fossy
--

CREATE VIEW uplicense AS
    SELECT uploadtree.uploadtree_pk, uploadtree.ufile_fk, uploadtree.parent, uploadtree.upload_fk, ufile.ufile_name, agent_lic_meta.pfile_fk, agent_lic_meta.tok_pfile, agent_lic_meta.tok_match, agent_lic_meta.version, agent_lic_meta.phrase_text, agent_lic_meta.tok_pfile_start, agent_lic_meta.tok_pfile_end, agent_lic_meta.tok_license_start, agent_lic_meta.tok_license_end, agent_lic_meta.tok_license, agent_lic_meta.lic_fk, agent_lic_meta.pfile_path, agent_lic_meta.license_path, agent_lic_raw.lic_pk, agent_lic_raw.lic_name, agent_lic_raw.lic_unique, agent_lic_raw.lic_text, agent_lic_raw.lic_version, agent_lic_raw.lic_section, agent_lic_raw.lic_id FROM uploadtree, ufile, agent_lic_meta, agent_lic_raw WHERE (((uploadtree.ufile_fk = ufile.ufile_pk) AND (ufile.pfile_fk = agent_lic_meta.pfile_fk)) AND (agent_lic_meta.lic_fk = agent_lic_raw.lic_pk));


ALTER TABLE public.uplicense OWNER TO fossy;

--
-- Name: uptreeatkey; Type: VIEW; Schema: public; Owner: fossy
--

CREATE VIEW uptreeatkey AS
    SELECT uploadtree.uploadtree_pk, uploadtree.ufile_fk, uploadtree.parent, uploadtree.upload_fk, ufile.ufile_pk, ufile.ufile_name, ufile.ufile_mode, ufile.ufile_mtime, ufile.pfile_fk, ufile.ufile_ts, ufile.ufile_container_fk, ufile.gid, ufile.gname, ufile.ouid, ufile.oname, pfile.pfile_pk, pfile.pfile_md5, pfile.pfile_sha1, pfile.pfile_size, pfile.pfile_mimetypefk, pfile.pfile_usecount, attrib.attrib_pk, attrib.attrib_key_fk, attrib.attrib_value, "key".key_pk, "key".key_name, "key".key_desc, "key".key_parent_fk, "key".key_agent_fk FROM uploadtree, ufile, pfile, attrib, "key" WHERE ((((uploadtree.ufile_fk = ufile.ufile_pk) AND (pfile.pfile_pk = ufile.pfile_fk)) AND (attrib.pfile_fk = ufile.pfile_fk)) AND (attrib.attrib_key_fk = "key".key_pk));


ALTER TABLE public.uptreeatkey OWNER TO fossy;

--
-- Name: VIEW uptreeatkey; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON VIEW uptreeatkey IS 'join uploadtree, ufile, pfile, attrib, and key';


--
-- Name: uptreeattrib; Type: VIEW; Schema: public; Owner: fossy
--

CREATE VIEW uptreeattrib AS
    SELECT uploadtree.uploadtree_pk, uploadtree.ufile_fk, uploadtree.parent, uploadtree.upload_fk, ufile.ufile_pk, ufile.ufile_name, ufile.ufile_mode, ufile.ufile_mtime, ufile.pfile_fk, ufile.ufile_ts, ufile.ufile_container_fk, ufile.gid, ufile.gname, ufile.ouid, ufile.oname, pfile.pfile_pk, pfile.pfile_md5, pfile.pfile_sha1, pfile.pfile_size, pfile.pfile_mimetypefk, pfile.pfile_usecount, attrib.attrib_pk, attrib.attrib_key_fk, attrib.attrib_value FROM uploadtree, ufile, pfile, attrib WHERE (((uploadtree.ufile_fk = ufile.ufile_pk) AND (pfile.pfile_pk = ufile.pfile_fk)) AND (attrib.pfile_fk = ufile.pfile_fk));


ALTER TABLE public.uptreeattrib OWNER TO fossy;

--
-- Name: VIEW uptreeattrib; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON VIEW uptreeattrib IS 'join uploadtree, ufile, pfile, attrib';


--
-- Name: uptreeup; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW uptreeup AS
    SELECT uploadtree.uploadtree_pk, uploadtree.ufile_fk, uploadtree.parent, uploadtree.upload_fk, ufile.ufile_pk, ufile.ufile_name, ufile.ufile_mode, ufile.ufile_mtime, ufile.pfile_fk, ufile.ufile_ts, ufile.ufile_container_fk, ufile.gid, ufile.gname, ufile.ouid, ufile.oname, pfile.pfile_pk, pfile.pfile_md5, pfile.pfile_sha1, pfile.pfile_size, pfile.pfile_mimetypefk, pfile.pfile_usecount FROM uploadtree, ufile, pfile WHERE ((uploadtree.ufile_fk = ufile.ufile_pk) AND (pfile.pfile_pk = ufile.pfile_fk));


ALTER TABLE public.uptreeup OWNER TO postgres;

--
-- Name: VIEW uptreeup; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON VIEW uptreeup IS 'joined uploadtree, ufile and pfile';


--
-- Name: users; Type: TABLE; Schema: public; Owner: fossy; Tablespace: 
--

CREATE TABLE users (
    user_pk serial NOT NULL,
    user_name text NOT NULL,
    root_folder_fk integer NOT NULL
);


ALTER TABLE public.users OWNER TO fossy;

--
-- Name: TABLE users; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE users IS 'FOSSology user table';


--
-- Name: COLUMN users.root_folder_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN users.root_folder_fk IS 'root folder for this user';


--
-- Name: agent_id; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE agent ALTER COLUMN agent_id SET DEFAULT currval('agent_agent_pk_seq'::regclass);


--
-- Name: No duplicates; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY agent_lic_meta
    ADD CONSTRAINT "No duplicates" UNIQUE (pfile_fk, tok_pfile, tok_match, version, tok_pfile_start, tok_pfile_end, tok_license_start, tok_license_end, tok_license, lic_fk);


--
-- Name: One unique per software version; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY agent_lic_raw
    ADD CONSTRAINT "One unique per software version" UNIQUE (lic_unique, lic_version);


--
-- Name: agent_lic_phrases_phrase_group_key; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY agent_lic_phrases
    ADD CONSTRAINT agent_lic_phrases_phrase_group_key UNIQUE (phrase_group, phrase_match);


--
-- Name: agent_lic_status_pfile_fk_key; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY agent_lic_status
    ADD CONSTRAINT agent_lic_status_pfile_fk_key UNIQUE (pfile_fk);


--
-- Name: agent_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY agent
    ADD CONSTRAINT agent_pkey PRIMARY KEY (agent_pk);


--
-- Name: agent_unique_name_rev; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY agent
    ADD CONSTRAINT agent_unique_name_rev UNIQUE (agent_name, agent_rev);


--
-- Name: agent_wc_pfile_fk_key; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY agent_wc
    ADD CONSTRAINT agent_wc_pfile_fk_key UNIQUE (pfile_fk);


--
-- Name: attrib_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY attrib
    ADD CONSTRAINT attrib_pkey PRIMARY KEY (attrib_pk);


--
-- Name: dirmodemask; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY mimetype
    ADD CONSTRAINT dirmodemask UNIQUE (mimetype_name);


--
-- Name: folder_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY folder
    ADD CONSTRAINT folder_pkey PRIMARY KEY (folder_pk);


--
-- Name: foldercontents_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY foldercontents
    ADD CONSTRAINT foldercontents_pkey PRIMARY KEY (foldercontents_pk);


--
-- Name: job_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY job
    ADD CONSTRAINT job_pkey PRIMARY KEY (job_pk);


--
-- Name: jobqueue_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY jobqueue
    ADD CONSTRAINT jobqueue_pkey PRIMARY KEY (jq_pk);


--
-- Name: key_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY "key"
    ADD CONSTRAINT key_pkey PRIMARY KEY (key_pk);


--
-- Name: md5_sha1_size; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY pfile
    ADD CONSTRAINT md5_sha1_size UNIQUE (pfile_md5, pfile_sha1, pfile_size);


--
-- Name: mimetype_pk; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY mimetype
    ADD CONSTRAINT mimetype_pk PRIMARY KEY (mimetype_pk);


--
-- Name: name_parent; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY "key"
    ADD CONSTRAINT name_parent UNIQUE (key_name, key_parent_fk);


--
-- Name: pfile_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY pfile
    ADD CONSTRAINT pfile_pkey PRIMARY KEY (pfile_pk);


--
-- Name: proj_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY proj
    ADD CONSTRAINT proj_pkey PRIMARY KEY (ufile_fk);


--
-- Name: scheduler_status_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY scheduler_status
    ADD CONSTRAINT scheduler_status_pkey PRIMARY KEY (unique_scheduler, agent_number);


--
-- Name: sqlagentproc_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY sqlagentproc
    ADD CONSTRAINT sqlagentproc_pkey PRIMARY KEY (sap_pk);


--
-- Name: table_enum_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY table_enum
    ADD CONSTRAINT table_enum_pkey PRIMARY KEY (table_pk);


--
-- Name: ufile_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY ufile
    ADD CONSTRAINT ufile_pkey PRIMARY KEY (ufile_pk);


--
-- Name: ufile_rel_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY uploadtree
    ADD CONSTRAINT ufile_rel_pkey PRIMARY KEY (uploadtree_pk);


--
-- Name: upload_pkey_idx; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY upload
    ADD CONSTRAINT upload_pkey_idx PRIMARY KEY (upload_pk);


--
-- Name: uploadtree_unique; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY uploadtree
    ADD CONSTRAINT uploadtree_unique UNIQUE (ufile_fk, parent, upload_fk);


--
-- Name: user_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT user_pkey PRIMARY KEY (user_pk);


--
-- Name: user_user_name_key; Type: CONSTRAINT; Schema: public; Owner: fossy; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT user_user_name_key UNIQUE (user_name);


--
-- Name: attrib_key_fk; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX attrib_key_fk ON attrib USING btree (attrib_key_fk);


--
-- Name: attribpfile_fk; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX attribpfile_fk ON attrib USING btree (pfile_fk);


--
-- Name: inrepository_idx; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX inrepository_idx ON agent_lic_status USING btree (inrepository);


--
-- Name: lic_1295pfile_pk; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX lic_1295pfile_pk ON lic_1295 USING btree (pfile_pk);


--
-- Name: lic_fk_btree; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX lic_fk_btree ON agent_lic_meta USING btree (lic_fk);


--
-- Name: pfile_fk; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX pfile_fk ON ufile USING hash (pfile_fk);


--
-- Name: pfile_fk_idx; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX pfile_fk_idx ON agent_lic_meta USING btree (pfile_fk);

ALTER TABLE agent_lic_meta CLUSTER ON pfile_fk_idx;


--
-- Name: pfile_mimetypefk_idx; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX pfile_mimetypefk_idx ON pfile USING btree (pfile_mimetypefk);


--
-- Name: phrases_unique; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE UNIQUE INDEX phrases_unique ON agent_lic_phrases USING btree (phrase_group, phrase_match);


--
-- Name: processed_idx; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX processed_idx ON agent_lic_status USING btree (processed);

ALTER TABLE agent_lic_status CLUSTER ON processed_idx;


--
-- Name: projtree_parent_idx; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX projtree_parent_idx ON uploadtree USING btree (parent);


--
-- Name: projtree_projid_idx; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX projtree_projid_idx ON uploadtree USING btree (upload_fk);


--
-- Name: projtree_ufile_idx; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX projtree_ufile_idx ON uploadtree USING btree (ufile_fk);


--
-- Name: ufile_mode_index; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX ufile_mode_index ON ufile USING btree (ufile_mode) WHERE ((ufile_mode & (1 << 29)) <> 0);


--
-- Name: ufile_mode_not_container; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX ufile_mode_not_container ON ufile USING btree (ufile_mode) WHERE ((ufile_mode & (1 << 29)) = 0);


--
-- Name: ufile_name; Type: INDEX; Schema: public; Owner: fossy; Tablespace: 
--

CREATE INDEX ufile_name ON ufile USING btree (ufile_name);


--
-- Name: agent_lic_meta_pfile_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY agent_lic_meta
    ADD CONSTRAINT agent_lic_meta_pfile_fk_fkey FOREIGN KEY (pfile_fk) REFERENCES pfile(pfile_pk);


--
-- Name: agent_lic_status_pfile_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY agent_lic_status
    ADD CONSTRAINT agent_lic_status_pfile_fk_fkey FOREIGN KEY (pfile_fk) REFERENCES pfile(pfile_pk);


--
-- Name: attrib_attrib_key_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY attrib
    ADD CONSTRAINT attrib_attrib_key_fk_fkey FOREIGN KEY (attrib_key_fk) REFERENCES "key"(key_pk);


--
-- Name: attrib_pfile_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY attrib
    ADD CONSTRAINT attrib_pfile_fk_fkey FOREIGN KEY (pfile_fk) REFERENCES pfile(pfile_pk);


--
-- Name: job_job_upload_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY job
    ADD CONSTRAINT job_job_upload_fk_fkey FOREIGN KEY (job_upload_fk) REFERENCES upload(upload_pk);


--
-- Name: jobdepends_jdep_jq_depends_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY jobdepends
    ADD CONSTRAINT jobdepends_jdep_jq_depends_fk_fkey FOREIGN KEY (jdep_jq_depends_fk) REFERENCES jobqueue(jq_pk);


--
-- Name: jobdepends_jdep_jq_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY jobdepends
    ADD CONSTRAINT jobdepends_jdep_jq_fk_fkey FOREIGN KEY (jdep_jq_fk) REFERENCES jobqueue(jq_pk);


--
-- Name: jobqueue_jq_job_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY jobqueue
    ADD CONSTRAINT jobqueue_jq_job_fk_fkey FOREIGN KEY (jq_job_fk) REFERENCES job(job_pk);


--
-- Name: key_key_agent_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY "key"
    ADD CONSTRAINT key_key_agent_fk_fkey FOREIGN KEY (key_agent_fk) REFERENCES agent(agent_pk);


--
-- Name: mimetype_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY pfile
    ADD CONSTRAINT mimetype_fk FOREIGN KEY (pfile_mimetypefk) REFERENCES mimetype(mimetype_pk);


--
-- Name: pfile_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY ufile
    ADD CONSTRAINT pfile_fk FOREIGN KEY (pfile_fk) REFERENCES pfile(pfile_pk);


--
-- Name: pfile_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY agent_wc
    ADD CONSTRAINT pfile_fk FOREIGN KEY (pfile_fk) REFERENCES pfile(pfile_pk);


--
-- Name: ufile; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY upload
    ADD CONSTRAINT ufile FOREIGN KEY (ufile_fk) REFERENCES ufile(ufile_pk);


--
-- Name: uploadtree_ufilefk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY uploadtree
    ADD CONSTRAINT uploadtree_ufilefk FOREIGN KEY (ufile_fk) REFERENCES ufile(ufile_pk) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: uploadtree_uploadfk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY uploadtree
    ADD CONSTRAINT uploadtree_uploadfk FOREIGN KEY (upload_fk) REFERENCES upload(upload_pk) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO fossy;


--
-- Name: keyagent; Type: ACL; Schema: public; Owner: fossy
--

REVOKE ALL ON TABLE keyagent FROM PUBLIC;
REVOKE ALL ON TABLE keyagent FROM fossy;
GRANT ALL ON TABLE keyagent TO fossy;
GRANT SELECT ON TABLE keyagent TO PUBLIC;


--
-- Name: leftnav; Type: ACL; Schema: public; Owner: postgres
--

REVOKE ALL ON TABLE leftnav FROM PUBLIC;
REVOKE ALL ON TABLE leftnav FROM postgres;
GRANT ALL ON TABLE leftnav TO postgres;
GRANT SELECT ON TABLE leftnav TO PUBLIC;


--
-- Name: uploadtree; Type: ACL; Schema: public; Owner: fossy
--

REVOKE ALL ON TABLE uploadtree FROM PUBLIC;
REVOKE ALL ON TABLE uploadtree FROM fossy;
GRANT ALL ON TABLE uploadtree TO fossy;


--
-- Name: lic_progress; Type: ACL; Schema: public; Owner: fossy
--

REVOKE ALL ON TABLE lic_progress FROM PUBLIC;
REVOKE ALL ON TABLE lic_progress FROM fossy;
GRANT ALL ON TABLE lic_progress TO fossy;
GRANT SELECT ON TABLE lic_progress TO PUBLIC;


--
-- Name: table_enum; Type: ACL; Schema: public; Owner: fossy
--

REVOKE ALL ON TABLE table_enum FROM PUBLIC;
REVOKE ALL ON TABLE table_enum FROM fossy;
GRANT ALL ON TABLE table_enum TO fossy;


--
-- Name: uptreeatkey; Type: ACL; Schema: public; Owner: fossy
--

REVOKE ALL ON TABLE uptreeatkey FROM PUBLIC;
REVOKE ALL ON TABLE uptreeatkey FROM fossy;
GRANT ALL ON TABLE uptreeatkey TO fossy;
GRANT SELECT ON TABLE uptreeatkey TO PUBLIC;


--
-- Name: uptreeattrib; Type: ACL; Schema: public; Owner: fossy
--

REVOKE ALL ON TABLE uptreeattrib FROM PUBLIC;
REVOKE ALL ON TABLE uptreeattrib FROM fossy;
GRANT ALL ON TABLE uptreeattrib TO fossy;
GRANT SELECT ON TABLE uptreeattrib TO PUBLIC;


--
-- Name: uptreeup; Type: ACL; Schema: public; Owner: postgres
--

REVOKE ALL ON TABLE uptreeup FROM PUBLIC;
REVOKE ALL ON TABLE uptreeup FROM postgres;
GRANT ALL ON TABLE uptreeup TO postgres;
GRANT SELECT ON TABLE uptreeup TO PUBLIC;


--
-- PostgreSQL database dump complete
--

INSERT INTO folder VALUES (1, 'Software Repository', '', 0);
INSERT INTO users VALUES (1, 'Default User', 1);
INSERT INTO agent (agent_pk,agent_rev,agent_name,agent_desc,agent_id) VALUES (0,'Unknown', '', 'No agent - needed for key definition',0);
INSERT INTO table_enum VALUES (1, 'agent', 1);
INSERT INTO table_enum VALUES (2, 'agent_lic_meta', 2);
INSERT INTO table_enum VALUES (3, 'agent_lic_status', 3);
INSERT INTO table_enum VALUES (4, 'agent_lic_raw', 4);
INSERT INTO table_enum VALUES (5, 'agent_lic_phrases', 5);
INSERT INTO table_enum VALUES (6, 'agent_pmccabe', 6);
INSERT INTO table_enum VALUES (7, 'agent_wc', 7);
INSERT INTO table_enum VALUES (8, 'attrib', 8);
INSERT INTO table_enum VALUES (9, 'containers', 9);
INSERT INTO table_enum VALUES (10, 'job', 10);
INSERT INTO table_enum VALUES (11, 'jobdepends', 11);
INSERT INTO table_enum VALUES (12, 'jobqueue', 12);
INSERT INTO table_enum VALUES (13, 'key', 13);
INSERT INTO table_enum VALUES (14, 'mimetype', 14);
INSERT INTO table_enum VALUES (15, 'pfile', 15);
INSERT INTO table_enum VALUES (16, 'proj', 16);
INSERT INTO table_enum VALUES (17, 'ufile', 17);
INSERT INTO table_enum VALUES (18, 'Unknown', -1);
INSERT INTO table_enum VALUES (20, 'upload', 18);
INSERT INTO table_enum VALUES (21, 'uploadtree', 19);
INSERT INTO table_enum VALUES (22, 'users', 20);
INSERT INTO table_enum VALUES (23, 'folder', 21);
INSERT INTO table_enum VALUES (24, 'foldercontents', 22);
