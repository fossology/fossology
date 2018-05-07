--
-- PostgreSQL database dump
--

-- Dumped from database version 9.6.8
-- Dumped by pg_dump version 9.6.8

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: getitemparent(integer); Type: FUNCTION; Schema: public; Owner: fossy
--

CREATE FUNCTION public.getitemparent(itemid integer) RETURNS integer
    LANGUAGE sql STABLE STRICT
    AS $_$
    WITH RECURSIVE file_tree(uploadtree_pk, parent, jump, path, cycle) AS (
        SELECT ut.uploadtree_pk, ut.parent,
          true,
          ARRAY[ut.uploadtree_pk],
          false
        FROM uploadtree ut
        WHERE ut.uploadtree_pk = $1
      UNION ALL
        SELECT ut.uploadtree_pk, ut.parent,
          ut.ufile_mode & (1<<28) != 0,
          path || ut.uploadtree_pk,
        ut.uploadtree_pk = ANY(path)
        FROM uploadtree ut, file_tree ft
        WHERE ut.uploadtree_pk = ft.parent AND jump AND NOT cycle
      )
   SELECT uploadtree_pk from file_tree ft WHERE NOT jump
   $_$;


ALTER FUNCTION public.getitemparent(itemid integer) OWNER TO fossy;

--
-- Name: uploadtree_uploadtree_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.uploadtree_uploadtree_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.uploadtree_uploadtree_pk_seq OWNER TO fossy;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: uploadtree; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.uploadtree (
    uploadtree_pk integer DEFAULT nextval('public.uploadtree_uploadtree_pk_seq'::regclass) NOT NULL,
    parent integer,
    realparent integer,
    upload_fk integer NOT NULL,
    pfile_fk integer,
    ufile_mode integer,
    lft integer,
    rgt integer,
    ufile_name text
);


ALTER TABLE public.uploadtree OWNER TO fossy;

--
-- Name: COLUMN uploadtree.parent; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.uploadtree.parent IS 'uploadtree parent';


--
-- Name: COLUMN uploadtree.realparent; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.uploadtree.realparent IS 'uploadtree non-artifact parent';


--
-- Name: COLUMN uploadtree.upload_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.uploadtree.upload_fk IS 'original uploaded file';


--
-- Name: COLUMN uploadtree.ufile_mode; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.uploadtree.ufile_mode IS 'container=1<<29, artifact=1<<28, project=1<<27, replica(same pfile)=1<<26, package<<25,directory=1<<18 (directory doesn''t seem to be used), note: wget and POST names are NOT considered artifacts.  Can tell directories as containers with no pfile.';


--
-- Name: COLUMN uploadtree.lft; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.uploadtree.lft IS 'nested set left edge';


--
-- Name: COLUMN uploadtree.rgt; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.uploadtree.rgt IS 'nested set right edge';


--
-- Name: COLUMN uploadtree.ufile_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.uploadtree.ufile_name IS 'file name';


--
-- Name: uploadtree2path(integer); Type: FUNCTION; Schema: public; Owner: fossy
--

CREATE FUNCTION public.uploadtree2path(uploadtree_pk_in integer) RETURNS SETOF public.uploadtree
    LANGUAGE plpgsql
    AS $$
    DECLARE
      UTrec   uploadtree;
      UTpk    integer;
      sql     varchar;
    BEGIN
      UTpk := uploadtree_pk_in;
      WHILE UTpk > 0 LOOP
        sql := 'select * from uploadtree where uploadtree_pk=' || UTpk;
        execute sql into UTrec;
        IF ((UTrec.ufile_mode & (1<<28)) = 0) THEN RETURN NEXT UTrec; END IF;
        UTpk := UTrec.parent;
      END LOOP;
      RETURN;
    END;
    $$;


ALTER FUNCTION public.uploadtree2path(uploadtree_pk_in integer) OWNER TO fossy;

--
-- Name: agent_agent_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.agent_agent_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.agent_agent_pk_seq OWNER TO fossy;

--
-- Name: agent; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.agent (
    agent_pk integer DEFAULT nextval('public.agent_agent_pk_seq'::regclass) NOT NULL,
    agent_name character varying(32) NOT NULL,
    agent_rev character varying(32),
    agent_desc character varying(255),
    agent_enabled boolean DEFAULT true,
    agent_parms text,
    agent_ts timestamp with time zone DEFAULT now()
);


ALTER TABLE public.agent OWNER TO fossy;

--
-- Name: COLUMN agent.agent_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent.agent_pk IS 'Primary key';


--
-- Name: COLUMN agent.agent_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent.agent_name IS 'display name';


--
-- Name: COLUMN agent.agent_rev; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent.agent_rev IS 'revision string';


--
-- Name: COLUMN agent.agent_desc; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent.agent_desc IS 'short description';


--
-- Name: COLUMN agent.agent_enabled; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent.agent_enabled IS 'true to enable, false to disable';


--
-- Name: COLUMN agent.agent_parms; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent.agent_parms IS 'agent parsable parameters';


--
-- Name: COLUMN agent.agent_ts; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent.agent_ts IS 'time record was added';


--
-- Name: agent_runstatus_ars_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.agent_runstatus_ars_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.agent_runstatus_ars_pk_seq OWNER TO fossy;

--
-- Name: agent_runstatus; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.agent_runstatus (
    ars_pk integer DEFAULT nextval('public.agent_runstatus_ars_pk_seq'::regclass) NOT NULL,
    agent_fk integer NOT NULL,
    upload_fk integer NOT NULL,
    ars_complete boolean DEFAULT false NOT NULL,
    ars_status text,
    ars_starttime timestamp with time zone DEFAULT now() NOT NULL,
    ars_endtime timestamp with time zone
);


ALTER TABLE public.agent_runstatus OWNER TO fossy;

--
-- Name: COLUMN agent_runstatus.ars_complete; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent_runstatus.ars_complete IS 'true if the agent has completed with success (i.e. results are available), false if the agent has not completed running on this upload or the results are not usable (due to errors)';


--
-- Name: COLUMN agent_runstatus.ars_status; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent_runstatus.ars_status IS 'status of run - on error exit, record error msg if possible';


--
-- Name: COLUMN agent_runstatus.ars_starttime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent_runstatus.ars_starttime IS 'time record was added.';


--
-- Name: COLUMN agent_runstatus.ars_endtime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent_runstatus.ars_endtime IS 'time when agent completed';


--
-- Name: agent_wc; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.agent_wc (
    pfile_fk integer,
    wc_words integer,
    wc_lines integer
);


ALTER TABLE public.agent_wc OWNER TO fossy;

--
-- Name: COLUMN agent_wc.pfile_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent_wc.pfile_fk IS 'pfile key';


--
-- Name: COLUMN agent_wc.wc_words; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent_wc.wc_words IS 'wc words';


--
-- Name: COLUMN agent_wc.wc_lines; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.agent_wc.wc_lines IS 'wc lines';


--
-- Name: nomos_ars_ars_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.nomos_ars_ars_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.nomos_ars_ars_pk_seq OWNER TO fossy;

--
-- Name: ars_master; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.ars_master (
    ars_pk integer DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass) NOT NULL,
    agent_fk integer NOT NULL,
    upload_fk integer NOT NULL,
    ars_success boolean DEFAULT false NOT NULL,
    ars_status text,
    ars_starttime timestamp with time zone DEFAULT now() NOT NULL,
    ars_endtime timestamp with time zone
);


ALTER TABLE public.ars_master OWNER TO fossy;

--
-- Name: COLUMN ars_master.agent_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.ars_master.agent_fk IS 'nomos agent pk';


--
-- Name: COLUMN ars_master.ars_success; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.ars_master.ars_success IS 'true if the scan completed successfully.  false if the scan in not complete or completed with errors';


--
-- Name: COLUMN ars_master.ars_status; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.ars_master.ars_status IS 'scan completion message (error msg, or "success")';


--
-- Name: COLUMN ars_master.ars_starttime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.ars_master.ars_starttime IS 'time scan started';


--
-- Name: COLUMN ars_master.ars_endtime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.ars_master.ars_endtime IS 'time scan completed';


--
-- Name: attachments_attachment_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.attachments_attachment_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.attachments_attachment_pk_seq OWNER TO fossy;

--
-- Name: attachments; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.attachments (
    attachment_pk integer DEFAULT nextval('public.attachments_attachment_pk_seq'::regclass) NOT NULL,
    type character(1) NOT NULL,
    text text NOT NULL,
    pfile_fk integer,
    server_fk integer
);


ALTER TABLE public.attachments OWNER TO fossy;

--
-- Name: COLUMN attachments.type; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.attachments.type IS '''c''=comment, ''f''=file';


--
-- Name: COLUMN attachments.text; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.attachments.text IS 'comment, or file name of uploaded file';


--
-- Name: COLUMN attachments.pfile_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.attachments.pfile_fk IS 'pfile_fk if type file';


--
-- Name: copyright_ct_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.copyright_ct_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.copyright_ct_pk_seq OWNER TO fossy;

--
-- Name: author; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.author (
    ct_pk bigint DEFAULT nextval('public.copyright_ct_pk_seq'::regclass) NOT NULL,
    agent_fk bigint NOT NULL,
    pfile_fk bigint NOT NULL,
    content text,
    hash text,
    type text,
    copy_startbyte integer,
    copy_endbyte integer,
    is_enabled boolean DEFAULT true
);


ALTER TABLE public.author OWNER TO fossy;

--
-- Name: COLUMN author.is_enabled; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.author.is_enabled IS 'true to enable, false to disable';


--
-- Name: bucket_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.bucket_ars (
    ars_pk integer DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass) NOT NULL,
    agent_fk integer NOT NULL,
    upload_fk integer NOT NULL,
    ars_success boolean DEFAULT false NOT NULL,
    ars_status text,
    ars_starttime timestamp with time zone DEFAULT now() NOT NULL,
    ars_endtime timestamp with time zone,
    nomosagent_fk integer,
    bucketpool_fk integer
);


ALTER TABLE public.bucket_ars OWNER TO fossy;

--
-- Name: bucket_bucket_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.bucket_bucket_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.bucket_bucket_pk_seq OWNER TO fossy;

--
-- Name: bucket_container_bf_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.bucket_container_bf_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.bucket_container_bf_pk_seq OWNER TO fossy;

--
-- Name: bucket_container; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.bucket_container (
    bucket_fk integer NOT NULL,
    agent_fk integer NOT NULL,
    uploadtree_fk integer,
    bf_pk integer DEFAULT nextval('public.bucket_container_bf_pk_seq'::regclass) NOT NULL,
    nomosagent_fk integer NOT NULL
);


ALTER TABLE public.bucket_container OWNER TO fossy;

--
-- Name: bucket_def; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.bucket_def (
    bucket_pk integer DEFAULT nextval('public.bucket_bucket_pk_seq'::regclass) NOT NULL,
    bucket_name text NOT NULL,
    bucket_color text DEFAULT 'yellow'::text NOT NULL,
    bucket_reportorder integer DEFAULT 50 NOT NULL,
    bucket_evalorder integer DEFAULT 50 NOT NULL,
    bucketpool_fk integer NOT NULL,
    bucket_type integer NOT NULL,
    bucket_regex text,
    bucket_filename text,
    stopon character(1) DEFAULT 'N'::character(1) NOT NULL,
    applies_to character(1) DEFAULT 1 NOT NULL
);


ALTER TABLE public.bucket_def OWNER TO fossy;

--
-- Name: COLUMN bucket_def.bucket_color; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucket_def.bucket_color IS 'color name or value.  eg "red" or "#fe0922", used to highlight reports, may also be ''url''';


--
-- Name: COLUMN bucket_def.bucket_reportorder; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucket_def.bucket_reportorder IS 'sort order (ascending) for reporting this bucket';


--
-- Name: COLUMN bucket_def.bucket_evalorder; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucket_def.bucket_evalorder IS 'sort order (ascending) for evaluating this bucket relative to the others in the pool.';


--
-- Name: COLUMN bucket_def.bucket_type; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucket_def.bucket_type IS '1=MATCH_EVERY (aka STR-FILE) licenses in file line all match licenses in test file, 2=MATCH_ONLY (aka COMP-FILE) test file licenses are all contained in this file, 3=REGEX, 4=EXEC, 5=REGEX-FILE, 99=Not in any other bucket';


--
-- Name: COLUMN bucket_def.bucket_regex; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucket_def.bucket_regex IS 'if type=3, this is the regex, else null';


--
-- Name: COLUMN bucket_def.bucket_filename; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucket_def.bucket_filename IS 'if type =1, 2, 4,5: this is the name of the file in /usr/local/share/fossology/{bucketpool name}';


--
-- Name: COLUMN bucket_def.stopon; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucket_def.stopon IS 'if ''N'' continue evaluating buckets even if this one matches.  If ''Y'' stop bucket processing loop if this matches';


--
-- Name: COLUMN bucket_def.applies_to; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucket_def.applies_to IS 'Bucket only applies to: ''1''=every file, ''2''=package';


--
-- Name: bucket_file_bf_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.bucket_file_bf_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.bucket_file_bf_pk_seq OWNER TO fossy;

--
-- Name: bucket_file; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.bucket_file (
    bf_pk integer DEFAULT nextval('public.bucket_file_bf_pk_seq'::regclass) NOT NULL,
    bucket_fk integer NOT NULL,
    pfile_fk integer,
    agent_fk integer NOT NULL,
    nomosagent_fk integer NOT NULL
);


ALTER TABLE public.bucket_file OWNER TO fossy;

--
-- Name: bucketpool_bucketpool_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.bucketpool_bucketpool_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.bucketpool_bucketpool_pk_seq OWNER TO fossy;

--
-- Name: bucketpool; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.bucketpool (
    bucketpool_pk integer DEFAULT nextval('public.bucketpool_bucketpool_pk_seq'::regclass) NOT NULL,
    bucketpool_name text NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    active character(1) DEFAULT 'Y'::character(1) NOT NULL,
    description text
);


ALTER TABLE public.bucketpool OWNER TO fossy;

--
-- Name: COLUMN bucketpool.bucketpool_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucketpool.bucketpool_name IS 'Descriptive name';


--
-- Name: COLUMN bucketpool.version; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucketpool.version IS '1, 2, 3, ...';


--
-- Name: COLUMN bucketpool.active; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucketpool.active IS 'N=inactive, Y=active';


--
-- Name: COLUMN bucketpool.description; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.bucketpool.description IS 'Optional bucketpool description';


--
-- Name: clearing_decision_clearing_decision_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.clearing_decision_clearing_decision_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.clearing_decision_clearing_decision_pk_seq OWNER TO fossy;

--
-- Name: clearing_decision; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.clearing_decision (
    clearing_decision_pk integer DEFAULT nextval('public.clearing_decision_clearing_decision_pk_seq'::regclass) NOT NULL,
    uploadtree_fk integer NOT NULL,
    pfile_fk integer NOT NULL,
    user_fk integer NOT NULL,
    group_fk integer,
    date_added timestamp with time zone DEFAULT now() NOT NULL,
    decision_type integer DEFAULT 0 NOT NULL,
    scope integer DEFAULT 1 NOT NULL
);


ALTER TABLE public.clearing_decision OWNER TO fossy;

--
-- Name: COLUMN clearing_decision.decision_type; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.clearing_decision.decision_type IS 'see Fossology/Lib/Data/DecisionTypes';


--
-- Name: COLUMN clearing_decision.scope; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.clearing_decision.scope IS 'see Fossology/Lib/Data/DecisonScopes';


--
-- Name: clearing_decision_event; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.clearing_decision_event (
    clearing_event_fk integer NOT NULL,
    clearing_decision_fk integer NOT NULL
);


ALTER TABLE public.clearing_decision_event OWNER TO fossy;

--
-- Name: clearing_decision_license_events_clearing_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.clearing_decision_license_events_clearing_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.clearing_decision_license_events_clearing_pk_seq OWNER TO fossy;

--
-- Name: clearing_event_clearing_event_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.clearing_event_clearing_event_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.clearing_event_clearing_event_pk_seq OWNER TO fossy;

--
-- Name: clearing_event; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.clearing_event (
    clearing_event_pk integer DEFAULT nextval('public.clearing_event_clearing_event_pk_seq'::regclass) NOT NULL,
    uploadtree_fk integer NOT NULL,
    rf_fk integer NOT NULL,
    removed boolean,
    user_fk integer NOT NULL,
    group_fk integer NOT NULL,
    job_fk integer,
    type_fk integer NOT NULL,
    comment text,
    reportinfo text,
    date_added timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE public.clearing_event OWNER TO fossy;

--
-- Name: COLUMN clearing_event.rf_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.clearing_event.rf_fk IS 'refer to license_ref* (not only license_ref)';


--
-- Name: COLUMN clearing_event.removed; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.clearing_event.removed IS 'true: add license, false: remove license, null: only change comment';


--
-- Name: COLUMN clearing_event.type_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.clearing_event.type_fk IS 'see Fossology/Lib/Data/LicenseEvent/ClearingEventTypes';


--
-- Name: COLUMN clearing_event.comment; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.clearing_event.comment IS 'User comment';


--
-- Name: COLUMN clearing_event.reportinfo; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.clearing_event.reportinfo IS 'public comment';


--
-- Name: copyright; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.copyright (
    ct_pk bigint DEFAULT nextval('public.copyright_ct_pk_seq'::regclass) NOT NULL,
    agent_fk bigint NOT NULL,
    pfile_fk bigint NOT NULL,
    content text,
    hash text,
    type text,
    copy_startbyte integer,
    copy_endbyte integer,
    is_enabled boolean DEFAULT true
);


ALTER TABLE public.copyright OWNER TO fossy;

--
-- Name: COLUMN copyright.is_enabled; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.copyright.is_enabled IS 'true to enable, false to disable';


--
-- Name: copyright_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.copyright_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.copyright_ars OWNER TO fossy;

--
-- Name: copyright_decision_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.copyright_decision_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.copyright_decision_pk_seq OWNER TO fossy;

--
-- Name: copyright_decision; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.copyright_decision (
    copyright_decision_pk bigint DEFAULT nextval('public.copyright_decision_pk_seq'::regclass) NOT NULL,
    user_fk bigint NOT NULL,
    pfile_fk bigint NOT NULL,
    clearing_decision_type_fk bigint NOT NULL,
    description text,
    textfinding text,
    comment text,
    is_enabled boolean DEFAULT true
);


ALTER TABLE public.copyright_decision OWNER TO fossy;

--
-- Name: COLUMN copyright_decision.is_enabled; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.copyright_decision.is_enabled IS 'true to enable, false to disable';


--
-- Name: decider_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.decider_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.decider_ars OWNER TO fossy;

--
-- Name: deciderjob_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.deciderjob_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.deciderjob_ars OWNER TO fossy;

--
-- Name: dep5_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.dep5_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.dep5_ars OWNER TO fossy;

--
-- Name: ecc_ct_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.ecc_ct_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.ecc_ct_pk_seq OWNER TO fossy;

--
-- Name: ecc; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.ecc (
    ct_pk bigint DEFAULT nextval('public.ecc_ct_pk_seq'::regclass) NOT NULL,
    agent_fk bigint NOT NULL,
    pfile_fk bigint NOT NULL,
    content text,
    hash text,
    type text,
    copy_startbyte integer,
    copy_endbyte integer,
    is_enabled boolean DEFAULT true
);


ALTER TABLE public.ecc OWNER TO fossy;

--
-- Name: COLUMN ecc.is_enabled; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.ecc.is_enabled IS 'true to enable, false to disable';


--
-- Name: ecc_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.ecc_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.ecc_ars OWNER TO fossy;

--
-- Name: ecc_decision_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.ecc_decision_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.ecc_decision_pk_seq OWNER TO fossy;

--
-- Name: ecc_decision; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.ecc_decision (
    copyright_decision_pk bigint DEFAULT nextval('public.ecc_decision_pk_seq'::regclass) NOT NULL,
    user_fk bigint NOT NULL,
    pfile_fk bigint NOT NULL,
    clearing_decision_type_fk bigint NOT NULL,
    description text,
    textfinding text,
    comment text,
    is_enabled boolean DEFAULT true
);


ALTER TABLE public.ecc_decision OWNER TO fossy;

--
-- Name: COLUMN ecc_decision.is_enabled; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.ecc_decision.is_enabled IS 'true to enable, false to disable';


--
-- Name: file_picker_file_picker_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.file_picker_file_picker_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.file_picker_file_picker_pk_seq OWNER TO fossy;

--
-- Name: file_picker; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.file_picker (
    file_picker_pk integer DEFAULT nextval('public.file_picker_file_picker_pk_seq'::regclass) NOT NULL,
    user_fk integer NOT NULL,
    uploadtree_fk1 integer NOT NULL,
    uploadtree_fk2 integer NOT NULL,
    last_access_date date NOT NULL
);


ALTER TABLE public.file_picker OWNER TO fossy;

--
-- Name: folder_folder_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.folder_folder_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.folder_folder_pk_seq OWNER TO fossy;

--
-- Name: folder; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.folder (
    folder_pk integer DEFAULT nextval('public.folder_folder_pk_seq'::regclass) NOT NULL,
    folder_name text NOT NULL,
    user_fk integer,
    folder_desc text,
    folder_perm integer
);


ALTER TABLE public.folder OWNER TO fossy;

--
-- Name: COLUMN folder.folder_perm; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.folder.folder_perm IS 'future permission';


--
-- Name: foldercontents_foldercontents_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.foldercontents_foldercontents_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.foldercontents_foldercontents_pk_seq OWNER TO fossy;

--
-- Name: foldercontents; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.foldercontents (
    foldercontents_pk integer DEFAULT nextval('public.foldercontents_foldercontents_pk_seq'::regclass) NOT NULL,
    parent_fk integer NOT NULL,
    foldercontents_mode integer NOT NULL,
    child_id integer NOT NULL
);


ALTER TABLE public.foldercontents OWNER TO fossy;

--
-- Name: COLUMN foldercontents.parent_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.foldercontents.parent_fk IS 'parent folder_fk';


--
-- Name: COLUMN foldercontents.foldercontents_mode; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.foldercontents.foldercontents_mode IS '1<<0 child is folder_fk, 1<<1 child is upload_fk, 1<<2 child is an uploadtree_fk';


--
-- Name: upload_upload_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.upload_upload_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.upload_upload_pk_seq OWNER TO fossy;

--
-- Name: upload; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.upload (
    upload_pk integer DEFAULT nextval('public.upload_upload_pk_seq'::regclass) NOT NULL,
    upload_desc text,
    upload_filename text NOT NULL,
    user_fk integer,
    upload_mode integer NOT NULL,
    upload_ts timestamp with time zone DEFAULT now() NOT NULL,
    pfile_fk integer,
    upload_origin text,
    uploadtree_tablename character varying(18) DEFAULT 'uploadtree_a'::character varying NOT NULL,
    expire_date date,
    expire_action character(1),
    public_perm integer
);


ALTER TABLE public.upload OWNER TO fossy;

--
-- Name: COLUMN upload.upload_desc; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.upload.upload_desc IS 'description of file';


--
-- Name: COLUMN upload.upload_filename; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.upload.upload_filename IS 'user visible upload name must be identical to the top uploadtree rec (parent = null) for this upload';


--
-- Name: COLUMN upload.user_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.upload.user_fk IS 'who uploaded this file FUTURE';


--
-- Name: COLUMN upload.upload_mode; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.upload.upload_mode IS '1 upload complete, 1<<1 gold (devel only), 1<<2 wget, 1<<3 web upload, 1<<4 discovery ; 1<<5 ununpack complete without fatal errors; 1<<6 adj2nest successful';


--
-- Name: COLUMN upload.upload_ts; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.upload.upload_ts IS 'record (upload) creation time';


--
-- Name: COLUMN upload.upload_origin; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.upload.upload_origin IS 'original filename or directory name or URL, depending on upload_mode';


--
-- Name: COLUMN upload.expire_date; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.upload.expire_date IS 'expiration date';


--
-- Name: COLUMN upload.expire_action; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.upload.expire_action IS '''a'' archive, ''d'' delete';


--
-- Name: COLUMN upload.public_perm; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.upload.public_perm IS 'permission PERM_NONE, PERM_READ, PERM_WRITE, PERM_ADMIN';


--
-- Name: folderlist; Type: VIEW; Schema: public; Owner: fossy
--

CREATE VIEW public.folderlist AS
 SELECT folder.folder_pk,
    folder.folder_name AS name,
    folder.folder_desc AS description,
    foldercontents.parent_fk AS parent,
    foldercontents.foldercontents_mode,
    NULL::timestamp with time zone AS ts,
    NULL::integer AS upload_pk,
    NULL::integer AS pfile_fk,
    NULL::integer AS ufile_mode
   FROM public.folder,
    public.foldercontents
  WHERE ((foldercontents.foldercontents_mode = 1) AND (foldercontents.child_id = folder.folder_pk))
UNION ALL
 SELECT NULL::integer AS folder_pk,
    uploadtree.ufile_name AS name,
    upload.upload_desc AS description,
    foldercontents.parent_fk AS parent,
    foldercontents.foldercontents_mode,
    upload.upload_ts AS ts,
    upload.upload_pk,
    uploadtree.pfile_fk,
    uploadtree.ufile_mode
   FROM ((public.upload
     JOIN public.uploadtree ON (((upload.upload_pk = uploadtree.upload_fk) AND (uploadtree.parent IS NULL))))
     JOIN public.foldercontents ON (((foldercontents.foldercontents_mode = 2) AND (foldercontents.child_id = upload.upload_pk))));


ALTER TABLE public.folderlist OWNER TO fossy;

--
-- Name: group_group_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.group_group_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.group_group_pk_seq OWNER TO fossy;

--
-- Name: group_user_member_group_user_member_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.group_user_member_group_user_member_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.group_user_member_group_user_member_pk_seq OWNER TO fossy;

--
-- Name: group_user_member; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.group_user_member (
    group_user_member_pk integer DEFAULT nextval('public.group_user_member_group_user_member_pk_seq'::regclass) NOT NULL,
    group_fk integer NOT NULL,
    user_fk integer NOT NULL,
    group_perm integer NOT NULL
);


ALTER TABLE public.group_user_member OWNER TO fossy;

--
-- Name: COLUMN group_user_member.group_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.group_user_member.group_fk IS 'Group user is a member of';


--
-- Name: COLUMN group_user_member.user_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.group_user_member.user_fk IS 'User foreign key';


--
-- Name: COLUMN group_user_member.group_perm; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.group_user_member.group_perm IS 'Permission: 0=user, 1=admin.  Only Admins can add/remove/assign permissions to users.';


--
-- Name: groups; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.groups (
    group_pk integer DEFAULT nextval('public.group_group_pk_seq'::regclass) NOT NULL,
    group_name character varying(64)
);


ALTER TABLE public.groups OWNER TO fossy;

--
-- Name: highlight; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.highlight (
    type character(2),
    start bigint,
    len bigint,
    fl_fk bigint,
    rf_start bigint,
    rf_len bigint
);


ALTER TABLE public.highlight OWNER TO fossy;

--
-- Name: COLUMN highlight.type; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.highlight.type IS 'Monk: full match ''M'', Diff ''M0'', DiffAdd ''M+'', DiffRemove ''M-'', DiffReplace ''MR''; Nomos License ''L''';


--
-- Name: highlight_bulk; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.highlight_bulk (
    clearing_event_fk bigint,
    lrb_fk bigint,
    start bigint,
    len bigint
);


ALTER TABLE public.highlight_bulk OWNER TO fossy;

--
-- Name: highlight_keyword; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.highlight_keyword (
    pfile_fk bigint,
    start bigint,
    len bigint
);


ALTER TABLE public.highlight_keyword OWNER TO fossy;

--
-- Name: job_job_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.job_job_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.job_job_pk_seq OWNER TO fossy;

--
-- Name: job; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.job (
    job_pk integer DEFAULT nextval('public.job_job_pk_seq'::regclass) NOT NULL,
    job_queued timestamp with time zone,
    job_priority integer DEFAULT 0 NOT NULL,
    job_email_notify text,
    job_name text,
    job_upload_fk integer,
    job_folder_fk integer,
    job_user_fk integer,
    job_group_fk integer
);


ALTER TABLE public.job OWNER TO fossy;

--
-- Name: COLUMN job.job_email_notify; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.job.job_email_notify IS 'list of emails to notify on completion (format?)';


--
-- Name: COLUMN job.job_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.job.job_name IS 'what to call this job, for the user';


--
-- Name: COLUMN job.job_upload_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.job.job_upload_fk IS 'upload this job applies to';


--
-- Name: COLUMN job.job_folder_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.job.job_folder_fk IS 'folder this job applies to';


--
-- Name: COLUMN job.job_user_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.job.job_user_fk IS 'Job submitted by this user';


--
-- Name: COLUMN job.job_group_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.job.job_group_fk IS 'Job submitted by this group';


--
-- Name: jobdepends; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.jobdepends (
    jdep_jq_fk integer,
    jdep_jq_depends_fk integer
);


ALTER TABLE public.jobdepends OWNER TO fossy;

--
-- Name: COLUMN jobdepends.jdep_jq_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobdepends.jdep_jq_fk IS 'jdep_jq_fk depends on jdep_jq_depends_fk';


--
-- Name: jobqueue_jq_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.jobqueue_jq_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.jobqueue_jq_pk_seq OWNER TO fossy;

--
-- Name: jobqueue; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.jobqueue (
    jq_pk integer DEFAULT nextval('public.jobqueue_jq_pk_seq'::regclass) NOT NULL,
    jq_job_fk integer NOT NULL,
    jq_type text NOT NULL,
    jq_args text,
    jq_starttime timestamp with time zone,
    jq_endtime timestamp with time zone,
    jq_endtext text,
    jq_end_bits smallint DEFAULT 0 NOT NULL,
    jq_schedinfo text,
    jq_itemsprocessed integer DEFAULT 0,
    jq_log text,
    jq_runonpfile text,
    jq_host text,
    jq_cmd_args character varying(1024)
);


ALTER TABLE public.jobqueue OWNER TO fossy;

--
-- Name: COLUMN jobqueue.jq_job_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_job_fk IS 'which user job this step supports';


--
-- Name: COLUMN jobqueue.jq_type; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_type IS 'agent.agent_name';


--
-- Name: COLUMN jobqueue.jq_args; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_args IS 'arbitrary text understood by the agent';


--
-- Name: COLUMN jobqueue.jq_starttime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_starttime IS 'set this field to indicate job is in progress';


--
-- Name: COLUMN jobqueue.jq_endtime; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_endtime IS 'set this when job completed for whatever reason';


--
-- Name: COLUMN jobqueue.jq_end_bits; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_end_bits IS 'bitmask(ok=0x1, fail=0x2, nonfatal=0x4)';


--
-- Name: COLUMN jobqueue.jq_schedinfo; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_schedinfo IS 'Field for information storage by job scheduler(s) presumably useful for cleanup after failures too';


--
-- Name: COLUMN jobqueue.jq_itemsprocessed; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_itemsprocessed IS 'sum of files processed by all agents';


--
-- Name: COLUMN jobqueue.jq_log; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_log IS 'Field that holds the name of the optional log file';


--
-- Name: COLUMN jobqueue.jq_runonpfile; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_runonpfile IS 'The column name for determining which host to run on';


--
-- Name: COLUMN jobqueue.jq_host; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_host IS 'Host to run job on.  May be null to let the scheduler decide.';


--
-- Name: COLUMN jobqueue.jq_cmd_args; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.jobqueue.jq_cmd_args IS 'command line arguments, i.e. "-ab -u 123 -c /my/dir"';


--
-- Name: license_ref_rf_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.license_ref_rf_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.license_ref_rf_pk_seq OWNER TO fossy;

--
-- Name: license_ref; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.license_ref (
    rf_pk bigint DEFAULT nextval('public.license_ref_rf_pk_seq'::regclass) NOT NULL,
    rf_shortname text NOT NULL,
    rf_text text NOT NULL,
    rf_url text,
    rf_add_date date,
    rf_copyleft bit(1),
    "rf_OSIapproved" bit(1),
    rf_fullname text,
    "rf_FSFfree" bit(1),
    "rf_GPLv2compatible" bit(1),
    "rf_GPLv3compatible" bit(1),
    rf_notes text,
    "rf_Fedora" text,
    marydone boolean DEFAULT false NOT NULL,
    rf_active boolean DEFAULT true NOT NULL,
    rf_text_updatable boolean DEFAULT false NOT NULL,
    rf_md5 character(32),
    rf_detector_type integer NOT NULL,
    rf_source text,
    rf_risk integer,
    rf_spdx_compatible boolean DEFAULT false NOT NULL
);


ALTER TABLE public.license_ref OWNER TO fossy;

--
-- Name: COLUMN license_ref.rf_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_pk IS 'Primary Key';


--
-- Name: COLUMN license_ref.rf_shortname; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_shortname IS 'GPL, APSL, MIT, ...';


--
-- Name: COLUMN license_ref.rf_text; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_text IS 'reference License text, or regex';


--
-- Name: COLUMN license_ref.rf_url; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_url IS 'URL of authoritative license text';


--
-- Name: COLUMN license_ref.rf_add_date; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_add_date IS 'Date License added to this table';


--
-- Name: COLUMN license_ref.rf_copyleft; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_copyleft IS 'Is license copyleft?';


--
-- Name: COLUMN license_ref."rf_OSIapproved"; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref."rf_OSIapproved" IS 'Is license OSI approved? ';


--
-- Name: COLUMN license_ref.rf_fullname; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_fullname IS 'GNU General Public License, Apple Public Source License, ...';


--
-- Name: COLUMN license_ref."rf_FSFfree"; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref."rf_FSFfree" IS 'Is license FSF free?';


--
-- Name: COLUMN license_ref."rf_GPLv2compatible"; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref."rf_GPLv2compatible" IS 'Is license GPL v2 compatible';


--
-- Name: COLUMN license_ref."rf_GPLv3compatible"; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref."rf_GPLv3compatible" IS 'Is license GPL v3 compatible';


--
-- Name: COLUMN license_ref.rf_notes; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_notes IS 'General notes (public)';


--
-- Name: COLUMN license_ref."rf_Fedora"; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref."rf_Fedora" IS '"Good", "Bad", "Unknown"';


--
-- Name: COLUMN license_ref.rf_active; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_active IS 'change this to false if you don''t want this reference license to be used in new analyses (does  not apply to nomos agent)';


--
-- Name: COLUMN license_ref.rf_text_updatable; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_text_updatable IS 'true if the license text can be updated (eg written by nomos)';


--
-- Name: COLUMN license_ref.rf_md5; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_md5 IS 'md5 of the license text, used to keep duplicates out of the system';


--
-- Name: COLUMN license_ref.rf_detector_type; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_detector_type IS '1 uses reference license, 2 nomos';


--
-- Name: COLUMN license_ref.rf_risk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_risk IS 'risk level';


--
-- Name: COLUMN license_ref.rf_spdx_compatible; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref.rf_spdx_compatible IS 'change this to true if you want the reference of license to be removed from spdx reports';


--
-- Name: license_candidate; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.license_candidate (
    group_fk bigint
)
INHERITS (public.license_ref);


ALTER TABLE public.license_candidate OWNER TO fossy;

--
-- Name: COLUMN license_candidate.group_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_candidate.group_fk IS 'group seeing this candidate';


--
-- Name: license_file_fl_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.license_file_fl_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.license_file_fl_pk_seq OWNER TO fossy;

--
-- Name: license_file; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.license_file (
    fl_pk bigint DEFAULT nextval('public.license_file_fl_pk_seq'::regclass) NOT NULL,
    rf_fk bigint,
    agent_fk integer NOT NULL,
    rf_match_pct integer,
    rf_timestamp timestamp with time zone DEFAULT now() NOT NULL,
    pfile_fk integer NOT NULL,
    server_fk integer DEFAULT 1 NOT NULL,
    fl_ref_start_byte integer,
    fl_ref_end_byte integer,
    fl_start_byte integer,
    fl_end_byte integer
);


ALTER TABLE public.license_file OWNER TO fossy;

--
-- Name: COLUMN license_file.fl_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_file.fl_pk IS 'Primary Key';


--
-- Name: COLUMN license_file.rf_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_file.rf_fk IS 'RefLicense fk, if NULL this file does not contain a license';


--
-- Name: COLUMN license_file.agent_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_file.agent_fk IS 'Agent fk that recorded this record';


--
-- Name: COLUMN license_file.rf_match_pct; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_file.rf_match_pct IS 'match pct 1-100';


--
-- Name: COLUMN license_file.rf_timestamp; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_file.rf_timestamp IS 'record add time';


--
-- Name: COLUMN license_file.fl_ref_start_byte; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_file.fl_ref_start_byte IS 'byte offset of match in reference license';


--
-- Name: COLUMN license_file.fl_ref_end_byte; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_file.fl_ref_end_byte IS 'byte offset of match end in reference license';


--
-- Name: COLUMN license_file.fl_start_byte; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_file.fl_start_byte IS 'byte offset of license match in file';


--
-- Name: COLUMN license_file.fl_end_byte; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_file.fl_end_byte IS 'byte offset of end of license match in file';


--
-- Name: license_file_ref; Type: VIEW; Schema: public; Owner: fossy
--

CREATE VIEW public.license_file_ref AS
 SELECT license_ref.rf_fullname,
    license_ref.rf_shortname,
    license_ref.rf_pk,
    license_file.fl_end_byte,
    license_file.rf_match_pct,
    license_file.rf_timestamp,
    license_file.fl_start_byte,
    license_file.fl_ref_end_byte,
    license_file.fl_ref_start_byte,
    license_file.fl_pk,
    license_file.agent_fk,
    license_file.pfile_fk
   FROM (public.license_file
     JOIN public.license_ref ON ((license_file.rf_fk = license_ref.rf_pk)));


ALTER TABLE public.license_file_ref OWNER TO fossy;

--
-- Name: license_map_license_map_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.license_map_license_map_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.license_map_license_map_pk_seq OWNER TO fossy;

--
-- Name: license_map; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.license_map (
    license_map_pk bigint DEFAULT nextval('public.license_map_license_map_pk_seq'::regclass) NOT NULL,
    rf_fk integer NOT NULL,
    rf_parent integer NOT NULL,
    usage integer
);


ALTER TABLE public.license_map OWNER TO fossy;

--
-- Name: COLUMN license_map.rf_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_map.rf_fk IS 'License';


--
-- Name: COLUMN license_map.rf_parent; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_map.rf_parent IS 'self or generalization';


--
-- Name: COLUMN license_map.usage; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_map.usage IS '0: license, 1: family';


--
-- Name: license_ref_bulk_lrb_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.license_ref_bulk_lrb_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.license_ref_bulk_lrb_pk_seq OWNER TO fossy;

--
-- Name: license_ref_bulk; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.license_ref_bulk (
    lrb_pk bigint DEFAULT nextval('public.license_ref_bulk_lrb_pk_seq'::regclass) NOT NULL,
    user_fk bigint NOT NULL,
    group_fk bigint,
    rf_text text NOT NULL,
    upload_fk bigint,
    uploadtree_fk bigint NOT NULL
);


ALTER TABLE public.license_ref_bulk OWNER TO fossy;

--
-- Name: COLUMN license_ref_bulk.lrb_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref_bulk.lrb_pk IS 'Primary Key';


--
-- Name: COLUMN license_ref_bulk.user_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref_bulk.user_fk IS 'user who made this bulk scan';


--
-- Name: COLUMN license_ref_bulk.group_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref_bulk.group_fk IS 'group id';


--
-- Name: COLUMN license_ref_bulk.rf_text; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref_bulk.rf_text IS 'text searched by nulk scan';


--
-- Name: COLUMN license_ref_bulk.upload_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref_bulk.upload_fk IS 'upload id';


--
-- Name: COLUMN license_ref_bulk.uploadtree_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_ref_bulk.uploadtree_fk IS 'file from which the decision was made';


--
-- Name: license_set_bulk; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.license_set_bulk (
    rf_fk bigint NOT NULL,
    removing boolean DEFAULT false NOT NULL,
    lrb_fk bigint NOT NULL
);


ALTER TABLE public.license_set_bulk OWNER TO fossy;

--
-- Name: COLUMN license_set_bulk.rf_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_set_bulk.rf_fk IS 'reference to license_ref* (not only license_ref)';


--
-- Name: COLUMN license_set_bulk.removing; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.license_set_bulk.removing IS 'true if removing, false if adding';


--
-- Name: mimetype_mimetype_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.mimetype_mimetype_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.mimetype_mimetype_pk_seq OWNER TO fossy;

--
-- Name: mimetype; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.mimetype (
    mimetype_pk integer DEFAULT nextval('public.mimetype_mimetype_pk_seq'::regclass) NOT NULL,
    mimetype_name text NOT NULL
);


ALTER TABLE public.mimetype OWNER TO fossy;

--
-- Name: mimetype_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.mimetype_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.mimetype_ars OWNER TO fossy;

--
-- Name: monk_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.monk_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.monk_ars OWNER TO fossy;

--
-- Name: nomos_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.nomos_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.nomos_ars OWNER TO fossy;

--
-- Name: obligation_candidate_map_om_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.obligation_candidate_map_om_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.obligation_candidate_map_om_pk_seq OWNER TO fossy;

--
-- Name: obligation_candidate_map; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.obligation_candidate_map (
    om_pk bigint DEFAULT nextval('public.obligation_candidate_map_om_pk_seq'::regclass) NOT NULL,
    ob_fk bigint NOT NULL,
    rf_fk bigint NOT NULL
);


ALTER TABLE public.obligation_candidate_map OWNER TO fossy;

--
-- Name: COLUMN obligation_candidate_map.om_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_candidate_map.om_pk IS 'Primary Key';


--
-- Name: COLUMN obligation_candidate_map.ob_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_candidate_map.ob_fk IS 'Obligation topic key as in obligation_ref';


--
-- Name: COLUMN obligation_candidate_map.rf_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_candidate_map.rf_fk IS 'Reference license key as in license_candidate';


--
-- Name: obligation_map_om_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.obligation_map_om_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.obligation_map_om_pk_seq OWNER TO fossy;

--
-- Name: obligation_map; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.obligation_map (
    om_pk bigint DEFAULT nextval('public.obligation_map_om_pk_seq'::regclass) NOT NULL,
    ob_fk bigint NOT NULL,
    rf_fk bigint NOT NULL
);


ALTER TABLE public.obligation_map OWNER TO fossy;

--
-- Name: COLUMN obligation_map.om_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_map.om_pk IS 'Primary Key';


--
-- Name: COLUMN obligation_map.ob_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_map.ob_fk IS 'Obligation topic key as in obligation_ref';


--
-- Name: COLUMN obligation_map.rf_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_map.rf_fk IS 'Reference license key as in rf_license';


--
-- Name: obligation_ref_ob_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.obligation_ref_ob_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.obligation_ref_ob_pk_seq OWNER TO fossy;

--
-- Name: obligation_ref; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.obligation_ref (
    ob_pk bigint DEFAULT nextval('public.obligation_ref_ob_pk_seq'::regclass) NOT NULL,
    ob_type text DEFAULT 'Obligation'::text NOT NULL,
    ob_topic text NOT NULL,
    ob_text text NOT NULL,
    ob_classification text,
    ob_modifications text,
    ob_comment text,
    ob_active boolean DEFAULT true NOT NULL,
    ob_text_updatable boolean DEFAULT false NOT NULL,
    ob_md5 character(32)
);


ALTER TABLE public.obligation_ref OWNER TO fossy;

--
-- Name: COLUMN obligation_ref.ob_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_ref.ob_pk IS 'Primary Key';


--
-- Name: COLUMN obligation_ref.ob_type; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_ref.ob_type IS 'Type of legal statement: obligation, restriction, risk or right';


--
-- Name: COLUMN obligation_ref.ob_topic; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_ref.ob_topic IS 'An arbitrary name for the obligation: include notices, copyleft effect, ...';


--
-- Name: COLUMN obligation_ref.ob_text; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_ref.ob_text IS 'The full text of the obligation';


--
-- Name: COLUMN obligation_ref.ob_classification; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_ref.ob_classification IS 'Level of attention this obligation should raise in the clearing process: green, white, yellow or red';


--
-- Name: COLUMN obligation_ref.ob_modifications; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_ref.ob_modifications IS 'True if the obligation applies on modified source code (mainly applies on copyleft licenses)';


--
-- Name: COLUMN obligation_ref.ob_comment; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_ref.ob_comment IS 'Optional comment';


--
-- Name: COLUMN obligation_ref.ob_active; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_ref.ob_active IS 'Change this to false if you don''t want this obligation to be used in new reports';


--
-- Name: COLUMN obligation_ref.ob_text_updatable; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_ref.ob_text_updatable IS 'True if the obligation text can be updated';


--
-- Name: COLUMN obligation_ref.ob_md5; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.obligation_ref.ob_md5 IS 'md5 of the obligation text, used to keep duplicates out of the system';


--
-- Name: package_package_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.package_package_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.package_package_pk_seq OWNER TO fossy;

--
-- Name: package; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.package (
    package_pk integer DEFAULT nextval('public.package_package_pk_seq'::regclass) NOT NULL,
    package_name text NOT NULL
);


ALTER TABLE public.package OWNER TO fossy;

--
-- Name: perm_upload_perm_upload_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.perm_upload_perm_upload_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.perm_upload_perm_upload_pk_seq OWNER TO fossy;

--
-- Name: perm_upload; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.perm_upload (
    perm_upload_pk integer DEFAULT nextval('public.perm_upload_perm_upload_pk_seq'::regclass) NOT NULL,
    perm integer NOT NULL,
    upload_fk integer NOT NULL,
    group_fk integer NOT NULL
);


ALTER TABLE public.perm_upload OWNER TO fossy;

--
-- Name: COLUMN perm_upload.perm; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.perm_upload.perm IS 'permission PERM_NONE, PERM_READ, PERM_WRITE, PERM_ADMIN';


--
-- Name: pfile_pfile_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.pfile_pfile_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.pfile_pfile_pk_seq OWNER TO fossy;

--
-- Name: pfile; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.pfile (
    pfile_pk integer DEFAULT nextval('public.pfile_pfile_pk_seq'::regclass) NOT NULL,
    pfile_md5 character(32) NOT NULL,
    pfile_sha1 character(40) NOT NULL,
    pfile_size bigint NOT NULL,
    pfile_mimetypefk integer
);


ALTER TABLE public.pfile OWNER TO fossy;

--
-- Name: COLUMN pfile.pfile_mimetypefk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pfile.pfile_mimetypefk IS 'NULL is treated as application/octet-stream';


--
-- Name: pkg_deb_pkg_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.pkg_deb_pkg_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.pkg_deb_pkg_pk_seq OWNER TO fossy;

--
-- Name: pkg_deb; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.pkg_deb (
    pkg_name text NOT NULL,
    pkg_arch text NOT NULL,
    version text NOT NULL,
    section text,
    priority text,
    installed_size integer,
    maintainer text NOT NULL,
    homepage text,
    source text,
    description text NOT NULL,
    pfile_fk integer NOT NULL,
    pkg_pk integer DEFAULT nextval('public.pkg_deb_pkg_pk_seq'::regclass) NOT NULL,
    summary text,
    format text,
    uploaders text,
    standards_version text
);


ALTER TABLE public.pkg_deb OWNER TO fossy;

--
-- Name: COLUMN pkg_deb.pkg_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.pkg_name IS 'the name of the package';


--
-- Name: COLUMN pkg_deb.pkg_arch; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.pkg_arch IS 'debian machine architecture';


--
-- Name: COLUMN pkg_deb.version; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.version IS 'the version number of a package';


--
-- Name: COLUMN pkg_deb.section; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.section IS 'this field specifies an application area into which the package has been classified';


--
-- Name: COLUMN pkg_deb.priority; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.priority IS 'this field represents how important that it is that the user have the package installed';


--
-- Name: COLUMN pkg_deb.installed_size; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.installed_size IS 'an estimate of the total amount of disk space required to install the named package';


--
-- Name: COLUMN pkg_deb.maintainer; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.maintainer IS 'the package maintainer''s name and email address';


--
-- Name: COLUMN pkg_deb.homepage; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.homepage IS 'the URL of the web site for this package';


--
-- Name: COLUMN pkg_deb.source; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.source IS 'this field identifies the source package name.';


--
-- Name: COLUMN pkg_deb.description; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.description IS 'a description of the binary package';


--
-- Name: COLUMN pkg_deb.pfile_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.pfile_fk IS 'key of pfile record that package refer to';


--
-- Name: COLUMN pkg_deb.pkg_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.pkg_pk IS 'package primary key';


--
-- Name: COLUMN pkg_deb.summary; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.summary IS 'short description';


--
-- Name: COLUMN pkg_deb.format; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.format IS 'specifies a format revision for the file';


--
-- Name: COLUMN pkg_deb.uploaders; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.uploaders IS 'names and email addresses of co-maintainers of the package';


--
-- Name: COLUMN pkg_deb.standards_version; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb.standards_version IS 'the most recent version of the standards';


--
-- Name: pkg_deb_req_req_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.pkg_deb_req_req_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.pkg_deb_req_req_pk_seq OWNER TO fossy;

--
-- Name: pkg_deb_req; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.pkg_deb_req (
    req_pk integer DEFAULT nextval('public.pkg_deb_req_req_pk_seq'::regclass) NOT NULL,
    pkg_fk integer NOT NULL,
    req_value text
);


ALTER TABLE public.pkg_deb_req OWNER TO fossy;

--
-- Name: COLUMN pkg_deb_req.req_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb_req.req_pk IS 'pkg debian require primary key';


--
-- Name: COLUMN pkg_deb_req.pkg_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb_req.pkg_fk IS 'key of pkg record that require refer to';


--
-- Name: COLUMN pkg_deb_req.req_value; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_deb_req.req_value IS 'pkg debian require';


--
-- Name: pkg_rpm_pkg_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.pkg_rpm_pkg_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.pkg_rpm_pkg_pk_seq OWNER TO fossy;

--
-- Name: pkg_rpm; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.pkg_rpm (
    pkg_name text NOT NULL,
    pkg_alias text,
    pkg_arch text NOT NULL,
    version text NOT NULL,
    rpm_filename text NOT NULL,
    license text NOT NULL,
    pkg_group text,
    packager text NOT NULL,
    release text NOT NULL,
    build_date text NOT NULL,
    vendor text NOT NULL,
    url text,
    source_rpm text,
    summary text,
    description text,
    pfile_fk integer NOT NULL,
    pkg_pk integer DEFAULT nextval('public.pkg_rpm_pkg_pk_seq'::regclass) NOT NULL
);


ALTER TABLE public.pkg_rpm OWNER TO fossy;

--
-- Name: COLUMN pkg_rpm.pkg_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.pkg_name IS 'the name of the package';


--
-- Name: COLUMN pkg_rpm.pkg_alias; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.pkg_alias IS 'the alias of the package';


--
-- Name: COLUMN pkg_rpm.pkg_arch; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.pkg_arch IS 'a shorthand name describing the type of computer hardware the packaged software is meant to run on';


--
-- Name: COLUMN pkg_rpm.version; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.version IS 'the version number of the software, as specified by the software''s original creator';


--
-- Name: COLUMN pkg_rpm.rpm_filename; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.rpm_filename IS 'filename of rpm';


--
-- Name: COLUMN pkg_rpm.license; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.license IS 'license type can be GPL, Freeware, Commercial, or other common type';


--
-- Name: COLUMN pkg_rpm.pkg_group; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.pkg_group IS 'categorize the software';


--
-- Name: COLUMN pkg_rpm.packager; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.packager IS 'the name and contact information for the individual responsible for building the package';


--
-- Name: COLUMN pkg_rpm.release; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.release IS 'the number of times a package consisting of this software has been packaged';


--
-- Name: COLUMN pkg_rpm.build_date; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.build_date IS 'the time the package was created';


--
-- Name: COLUMN pkg_rpm.vendor; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.vendor IS 'the organization responsible for building this package';


--
-- Name: COLUMN pkg_rpm.url; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.url IS 'url resource';


--
-- Name: COLUMN pkg_rpm.source_rpm; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.source_rpm IS 'the package file containing the source code and other files used to create the binary package file. the package is source rpm, this field will be NULL';


--
-- Name: COLUMN pkg_rpm.summary; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.summary IS 'a concise description of the packaged software';


--
-- Name: COLUMN pkg_rpm.description; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.description IS 'a verbose description of the packaged software';


--
-- Name: COLUMN pkg_rpm.pfile_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.pfile_fk IS 'key of pfile record that package refer to';


--
-- Name: COLUMN pkg_rpm.pkg_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm.pkg_pk IS 'package primary key';


--
-- Name: pkg_rpm_req_req_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.pkg_rpm_req_req_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.pkg_rpm_req_req_pk_seq OWNER TO fossy;

--
-- Name: pkg_rpm_req; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.pkg_rpm_req (
    pkg_fk integer NOT NULL,
    req_value text,
    req_pk integer DEFAULT nextval('public.pkg_rpm_req_req_pk_seq'::regclass) NOT NULL
);


ALTER TABLE public.pkg_rpm_req OWNER TO fossy;

--
-- Name: COLUMN pkg_rpm_req.pkg_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm_req.pkg_fk IS 'key of pkg record that require refer to';


--
-- Name: COLUMN pkg_rpm_req.req_value; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm_req.req_value IS 'pkg rpm require';


--
-- Name: COLUMN pkg_rpm_req.req_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.pkg_rpm_req.req_pk IS 'pkg rpm require primary key';


--
-- Name: pkgagent_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.pkgagent_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.pkgagent_ars OWNER TO fossy;

--
-- Name: readmeoss_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.readmeoss_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.readmeoss_ars OWNER TO fossy;

--
-- Name: report_cache_report_cache_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.report_cache_report_cache_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.report_cache_report_cache_pk_seq OWNER TO fossy;

--
-- Name: report_cache; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.report_cache (
    report_cache_pk integer DEFAULT nextval('public.report_cache_report_cache_pk_seq'::regclass) NOT NULL,
    report_cache_tla timestamp without time zone DEFAULT now() NOT NULL,
    report_cache_key text NOT NULL,
    report_cache_value text NOT NULL,
    report_cache_uploadfk integer
);


ALTER TABLE public.report_cache OWNER TO fossy;

--
-- Name: COLUMN report_cache.report_cache_tla; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.report_cache.report_cache_tla IS 'time last accessed';


--
-- Name: COLUMN report_cache.report_cache_key; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.report_cache.report_cache_key IS 'http GET args';


--
-- Name: COLUMN report_cache.report_cache_value; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.report_cache.report_cache_value IS 'report to output';


--
-- Name: report_cache_user_report_cache_user_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.report_cache_user_report_cache_user_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.report_cache_user_report_cache_user_pk_seq OWNER TO fossy;

--
-- Name: report_cache_user; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.report_cache_user (
    report_cache_user_pk integer DEFAULT nextval('public.report_cache_user_report_cache_user_pk_seq'::regclass) NOT NULL,
    user_fk integer NOT NULL,
    cache_on character(1) DEFAULT 'Y'::character(1)
);


ALTER TABLE public.report_cache_user OWNER TO fossy;

--
-- Name: report_info_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.report_info_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.report_info_pk_seq OWNER TO fossy;

--
-- Name: report_info; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.report_info (
    ri_pk integer DEFAULT nextval('public.report_info_pk_seq'::regclass) NOT NULL,
    upload_fk integer NOT NULL,
    ri_reviewed text DEFAULT 'NA'::text NOT NULL,
    ri_footer text DEFAULT 'Your Organization'::text NOT NULL,
    ri_report_rel text DEFAULT 'NA'::text NOT NULL,
    ri_community text DEFAULT 'NA'::text NOT NULL,
    ri_component text DEFAULT 'NA'::text NOT NULL,
    ri_version text DEFAULT 'NA'::text NOT NULL,
    ri_release_date text DEFAULT 'NA'::text NOT NULL,
    ri_sw360_link text DEFAULT 'NA'::text NOT NULL,
    ri_general_assesment text DEFAULT 'NA'::text NOT NULL,
    ri_ga_additional text DEFAULT 'NA'::text NOT NULL,
    ri_ga_risk text DEFAULT 'NA'::text NOT NULL,
    ri_ga_checkbox_selection text
);


ALTER TABLE public.report_info OWNER TO fossy;

--
-- Name: reportgen; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.reportgen (
    upload_fk integer NOT NULL,
    job_fk integer NOT NULL,
    filepath text
);


ALTER TABLE public.reportgen OWNER TO fossy;

--
-- Name: reportimport_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.reportimport_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.reportimport_ars OWNER TO fossy;

--
-- Name: reuser_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.reuser_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.reuser_ars OWNER TO fossy;

--
-- Name: runstat_up_agent; Type: VIEW; Schema: public; Owner: fossy
--

CREATE VIEW public.runstat_up_agent AS
 SELECT agent_runstatus.ars_status,
    agent_runstatus.ars_complete,
    agent_runstatus.ars_pk,
    agent_runstatus.ars_starttime AS ars_ts,
    agent_runstatus.agent_fk,
    agent.agent_desc,
    agent.agent_name,
    agent.agent_parms,
    agent.agent_enabled AS agent_enable,
    agent.agent_pk,
    agent.agent_ts,
    agent.agent_rev,
    upload.upload_desc,
    upload.upload_filename,
    upload.pfile_fk,
    upload.upload_pk
   FROM ((public.agent_runstatus
     JOIN public.agent ON ((agent_runstatus.agent_fk = agent.agent_pk)))
     JOIN public.upload ON ((agent_runstatus.upload_fk = upload.upload_pk)));


ALTER TABLE public.runstat_up_agent OWNER TO fossy;

--
-- Name: spdx2_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.spdx2_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.spdx2_ars OWNER TO fossy;

--
-- Name: spdx2tv_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.spdx2tv_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.spdx2tv_ars OWNER TO fossy;

--
-- Name: sysconfig; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.sysconfig (
    sysconfig_pk integer NOT NULL,
    variablename character varying(30) NOT NULL,
    conf_value text,
    ui_label character varying(60) NOT NULL,
    vartype integer NOT NULL,
    group_name character varying(20) NOT NULL,
    group_order integer,
    description text NOT NULL,
    validation_function character varying(40) DEFAULT NULL::character varying
);


ALTER TABLE public.sysconfig OWNER TO fossy;

--
-- Name: TABLE sysconfig; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON TABLE public.sysconfig IS 'System configuration values';


--
-- Name: COLUMN sysconfig.variablename; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.sysconfig.variablename IS 'Name of configuration variable';


--
-- Name: COLUMN sysconfig.conf_value; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.sysconfig.conf_value IS 'value of config variable';


--
-- Name: COLUMN sysconfig.ui_label; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.sysconfig.ui_label IS 'Label that appears on user interface to prompt for variable';


--
-- Name: COLUMN sysconfig.vartype; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.sysconfig.vartype IS 'variable type.  1=int, 2=text, 3=textarea';


--
-- Name: COLUMN sysconfig.group_name; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.sysconfig.group_name IS 'Name of this variables group in the user interface';


--
-- Name: COLUMN sysconfig.group_order; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.sysconfig.group_order IS 'The order this variable appears in the user interface group';


--
-- Name: COLUMN sysconfig.description; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.sysconfig.description IS 'Description of variable to document how/where the variable value is used.';


--
-- Name: COLUMN sysconfig.validation_function; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.sysconfig.validation_function IS 'Name of function to validate input. Not currently implemented.';


--
-- Name: sysconfig_sysconfig_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.sysconfig_sysconfig_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.sysconfig_sysconfig_pk_seq OWNER TO fossy;

--
-- Name: sysconfig_sysconfig_pk_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: fossy
--

ALTER SEQUENCE public.sysconfig_sysconfig_pk_seq OWNED BY public.sysconfig.sysconfig_pk;


--
-- Name: tags_tag_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.tags_tag_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.tags_tag_pk_seq OWNER TO fossy;

--
-- Name: tag; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.tag (
    tag_pk integer DEFAULT nextval('public.tags_tag_pk_seq'::regclass) NOT NULL,
    tag character varying(32) NOT NULL,
    tag_desc text
);


ALTER TABLE public.tag OWNER TO fossy;

--
-- Name: COLUMN tag.tag_desc; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.tag.tag_desc IS 'tag description';


--
-- Name: tags_file_tag_file_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.tags_file_tag_file_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.tags_file_tag_file_pk_seq OWNER TO fossy;

--
-- Name: tag_file; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.tag_file (
    tag_file_pk integer DEFAULT nextval('public.tags_file_tag_file_pk_seq'::regclass) NOT NULL,
    tag_fk integer NOT NULL,
    pfile_fk integer NOT NULL,
    tag_file_date timestamp with time zone NOT NULL,
    tag_file_text text
);


ALTER TABLE public.tag_file OWNER TO fossy;

--
-- Name: tag_manage_tag_manage_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.tag_manage_tag_manage_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.tag_manage_tag_manage_pk_seq OWNER TO fossy;

--
-- Name: tag_manage; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.tag_manage (
    tag_manage_pk integer DEFAULT nextval('public.tag_manage_tag_manage_pk_seq'::regclass) NOT NULL,
    upload_fk integer NOT NULL,
    is_disabled boolean
);


ALTER TABLE public.tag_manage OWNER TO fossy;

--
-- Name: COLUMN tag_manage.tag_manage_pk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.tag_manage.tag_manage_pk IS 'primary key';


--
-- Name: COLUMN tag_manage.upload_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.tag_manage.upload_fk IS 'upload id, foreign key';


--
-- Name: COLUMN tag_manage.is_disabled; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.tag_manage.is_disabled IS '1: disabled, 0: enabled';


--
-- Name: tags_uploadtree_tag_uploadtree_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.tags_uploadtree_tag_uploadtree_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.tags_uploadtree_tag_uploadtree_pk_seq OWNER TO fossy;

--
-- Name: tag_uploadtree; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.tag_uploadtree (
    tag_uploadtree_pk integer DEFAULT nextval('public.tags_uploadtree_tag_uploadtree_pk_seq'::regclass) NOT NULL,
    tag_fk integer NOT NULL,
    uploadtree_fk integer NOT NULL,
    tag_uploadtree_date timestamp with time zone NOT NULL,
    tag_uploadtree_text text
);


ALTER TABLE public.tag_uploadtree OWNER TO fossy;

--
-- Name: unifiedreport_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.unifiedreport_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.unifiedreport_ars OWNER TO fossy;

--
-- Name: ununpack_ars; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.ununpack_ars (
)
INHERITS (public.ars_master);


ALTER TABLE public.ununpack_ars OWNER TO fossy;

--
-- Name: upload_clearing; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.upload_clearing (
    upload_fk integer NOT NULL,
    group_fk integer NOT NULL,
    assignee integer DEFAULT 1,
    status_fk integer DEFAULT 1,
    status_comment text,
    priority double precision
);


ALTER TABLE public.upload_clearing OWNER TO fossy;

--
-- Name: COLUMN upload_clearing.group_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.upload_clearing.group_fk IS 'who is working at upload';


--
-- Name: upload_clearing_license; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.upload_clearing_license (
    upload_fk integer NOT NULL,
    group_fk integer NOT NULL,
    rf_fk bigint NOT NULL
);


ALTER TABLE public.upload_clearing_license OWNER TO fossy;

--
-- Name: upload_packages; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.upload_packages (
    package_fk integer NOT NULL,
    upload_fk integer NOT NULL
);


ALTER TABLE public.upload_packages OWNER TO fossy;

--
-- Name: upload_reuse; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.upload_reuse (
    upload_fk integer NOT NULL,
    reused_upload_fk integer NOT NULL,
    group_fk integer NOT NULL,
    reused_group_fk integer NOT NULL,
    reuse_mode integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.upload_reuse OWNER TO fossy;

--
-- Name: uploadtree_a; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.uploadtree_a (
)
INHERITS (public.uploadtree);


ALTER TABLE public.uploadtree_a OWNER TO fossy;

--
-- Name: uploadtree_tag_file_inner; Type: VIEW; Schema: public; Owner: fossy
--

CREATE VIEW public.uploadtree_tag_file_inner AS
 SELECT uploadtree.pfile_fk,
    uploadtree.uploadtree_pk,
    uploadtree.parent,
    uploadtree.upload_fk,
    uploadtree.ufile_mode,
    uploadtree.lft,
    uploadtree.rgt,
    uploadtree.ufile_name,
    tag_file.tag_file_pk,
    tag_file.tag_fk,
    tag_file.tag_file_date,
    tag_file.tag_file_text
   FROM (public.uploadtree
     JOIN public.tag_file USING (pfile_fk));


ALTER TABLE public.uploadtree_tag_file_inner OWNER TO fossy;

--
-- Name: users_user_pk_seq; Type: SEQUENCE; Schema: public; Owner: fossy
--

CREATE SEQUENCE public.users_user_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.users_user_pk_seq OWNER TO fossy;

--
-- Name: users; Type: TABLE; Schema: public; Owner: fossy
--

CREATE TABLE public.users (
    user_pk integer DEFAULT nextval('public.users_user_pk_seq'::regclass) NOT NULL,
    user_name text NOT NULL,
    root_folder_fk integer NOT NULL,
    user_desc text,
    user_seed text,
    user_pass text,
    user_perm integer,
    user_email text,
    email_notify character varying(1) DEFAULT 'y'::character varying,
    user_agent_list text,
    default_bucketpool_fk integer,
    ui_preference character varying DEFAULT 'simple'::character varying,
    new_upload_group_fk integer,
    new_upload_perm integer,
    group_fk integer
);


ALTER TABLE public.users OWNER TO fossy;

--
-- Name: COLUMN users.root_folder_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.users.root_folder_fk IS 'root folder for this user';


--
-- Name: COLUMN users.email_notify; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.users.email_notify IS 'Email notification flag';


--
-- Name: COLUMN users.user_agent_list; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.users.user_agent_list IS 'list of user agents to automatically run on upload';


--
-- Name: COLUMN users.ui_preference; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.users.ui_preference IS 'ui preference for the user, either simple or original';


--
-- Name: COLUMN users.new_upload_group_fk; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.users.new_upload_group_fk IS 'group given new_upload_perm on new uploads';


--
-- Name: COLUMN users.new_upload_perm; Type: COMMENT; Schema: public; Owner: fossy
--

COMMENT ON COLUMN public.users.new_upload_perm IS 'permission given to new_upload_group on new uploads';


--
-- Name: copyright_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.copyright_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: copyright_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.copyright_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: copyright_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.copyright_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: decider_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.decider_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: decider_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.decider_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: decider_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.decider_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: deciderjob_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.deciderjob_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: deciderjob_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.deciderjob_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: deciderjob_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.deciderjob_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: dep5_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.dep5_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: dep5_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.dep5_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: dep5_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.dep5_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: ecc_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ecc_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: ecc_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ecc_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: ecc_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ecc_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: license_candidate rf_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_candidate ALTER COLUMN rf_pk SET DEFAULT nextval('public.license_ref_rf_pk_seq'::regclass);


--
-- Name: license_candidate marydone; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_candidate ALTER COLUMN marydone SET DEFAULT false;


--
-- Name: license_candidate rf_active; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_candidate ALTER COLUMN rf_active SET DEFAULT true;


--
-- Name: license_candidate rf_text_updatable; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_candidate ALTER COLUMN rf_text_updatable SET DEFAULT false;


--
-- Name: license_candidate rf_spdx_compatible; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_candidate ALTER COLUMN rf_spdx_compatible SET DEFAULT false;


--
-- Name: mimetype_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.mimetype_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: mimetype_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.mimetype_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: mimetype_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.mimetype_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: monk_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.monk_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: monk_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.monk_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: monk_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.monk_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: nomos_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.nomos_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: nomos_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.nomos_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: nomos_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.nomos_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: pkgagent_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkgagent_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: pkgagent_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkgagent_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: pkgagent_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkgagent_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: readmeoss_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.readmeoss_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: readmeoss_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.readmeoss_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: readmeoss_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.readmeoss_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: reportimport_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.reportimport_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: reportimport_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.reportimport_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: reportimport_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.reportimport_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: reuser_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.reuser_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: reuser_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.reuser_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: reuser_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.reuser_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: spdx2_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.spdx2_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: spdx2_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.spdx2_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: spdx2_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.spdx2_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: spdx2tv_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.spdx2tv_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: spdx2tv_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.spdx2tv_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: spdx2tv_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.spdx2tv_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: sysconfig sysconfig_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.sysconfig ALTER COLUMN sysconfig_pk SET DEFAULT nextval('public.sysconfig_sysconfig_pk_seq'::regclass);


--
-- Name: unifiedreport_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.unifiedreport_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: unifiedreport_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.unifiedreport_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: unifiedreport_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.unifiedreport_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: ununpack_ars ars_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ununpack_ars ALTER COLUMN ars_pk SET DEFAULT nextval('public.nomos_ars_ars_pk_seq'::regclass);


--
-- Name: ununpack_ars ars_success; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ununpack_ars ALTER COLUMN ars_success SET DEFAULT false;


--
-- Name: ununpack_ars ars_starttime; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ununpack_ars ALTER COLUMN ars_starttime SET DEFAULT now();


--
-- Name: uploadtree_a uploadtree_pk; Type: DEFAULT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.uploadtree_a ALTER COLUMN uploadtree_pk SET DEFAULT nextval('public.uploadtree_uploadtree_pk_seq'::regclass);


--
-- Data for Name: agent; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.agent (agent_pk, agent_name, agent_rev, agent_desc, agent_enabled, agent_parms, agent_ts) FROM stdin;
2	nomos	"3.2.0-19-g4dd2b69".4dd2b6	License Scanner	t	\N	2018-04-04 17:20:45.793958+05:30
3	buckets	"3.2.0-19-g4dd2b69".4dd2b6	Bucket agent	t	\N	2018-04-04 17:20:55.602808+05:30
4	copyright	"3.2.0-19-g4dd2b69".4dd2b6	copyright agent	t	\N	2018-04-04 17:20:55.620844+05:30
5	adj2nest	"3.2.0-19-g4dd2b69".4dd2b6	Adj2nest Agent	t	\N	2018-04-04 17:20:55.642647+05:30
6	maintagent	"3.2.0-19-g4dd2b69".4dd2b6	Maintenance Agent	t	\N	2018-04-04 17:20:55.644447+05:30
7	mimetype	"3.2.0-19-g4dd2b69".4dd2b6	Determines mimetype for each file	t	\N	2018-04-04 17:20:55.67281+05:30
9	delagent	"3.2.0-19-g4dd2b69".4dd2b6	(null)	t	\N	2018-04-04 17:20:55.68096+05:30
10	ecc	"3.2.0-19-g4dd2b69".4dd2b6	ecc agent	t	\N	2018-04-04 17:20:55.719676+05:30
11	monkbulk	"3.2.0-19-g4dd2b69".4dd2b6	monkbulk agent	t	\N	2018-04-04 17:20:55.723514+05:30
12	wget_agent	"3.2.0-19-g4dd2b69".4dd2b6	Network downloader.  Uses wget(1).	t	\N	2018-04-04 17:20:55.736214+05:30
14	pkgagent	"3.2.0-19-g4dd2b69".4dd2b6	Pulls metadata out of RPM or DEBIAN packages	t	\N	2018-04-04 17:20:55.75246+05:30
15	spdx2	3.2.0-19-g4dd2b69.4dd2b6	spdx2 agent	t	\N	2018-04-04 17:20:56.019374+05:30
8	monk	"3.2.0-19-g4dd2b69".4dd2b6	monk agent	t	\N	2018-04-04 17:20:55.680456+05:30
16	deciderjob	3.2.0-19-g4dd2b69.4dd2b6	deciderjob agent	t	\N	2018-04-04 17:20:56.057758+05:30
17	spdx2tv	3.2.0-19-g4dd2b69.4dd2b6	spdx2tv agent	t	\N	2018-04-04 17:20:56.06535+05:30
18	reportImport	3.2.0-19-g4dd2b69.4dd2b6	reportImport agent	t	\N	2018-04-04 17:20:56.09177+05:30
19	decider	3.2.0-19-g4dd2b69.4dd2b6	decider agent	t	\N	2018-04-04 17:20:56.09699+05:30
20	unifiedreport	3.2.0-19-g4dd2b69.4dd2b6	unifiedreport agent	t	\N	2018-04-04 17:20:56.107096+05:30
21	readmeoss	3.2.0-19-g4dd2b69.4dd2b6	readmeoss agent	t	\N	2018-04-04 17:20:56.108133+05:30
22	reuser	3.2.0-19-g4dd2b69.4dd2b6	reuser agent	t	\N	2018-04-04 17:20:56.126005+05:30
23	dep5	3.2.0-19-g4dd2b69.4dd2b6	dep5 agent	t	\N	2018-04-04 17:20:56.151049+05:30
13	ununpack	"3.2.0-19-g4dd2b69".4dd2b6	Unpacks archives (iso, tar, etc)	t	\N	2018-04-04 17:20:55.736232+05:30
\.


--
-- Name: agent_agent_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.agent_agent_pk_seq', 23, true);


--
-- Data for Name: agent_runstatus; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.agent_runstatus (ars_pk, agent_fk, upload_fk, ars_complete, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Name: agent_runstatus_ars_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.agent_runstatus_ars_pk_seq', 1, true);


--
-- Data for Name: agent_wc; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.agent_wc (pfile_fk, wc_words, wc_lines) FROM stdin;
\.


--
-- Data for Name: ars_master; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.ars_master (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Data for Name: attachments; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.attachments (attachment_pk, type, text, pfile_fk, server_fk) FROM stdin;
\.


--
-- Name: attachments_attachment_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.attachments_attachment_pk_seq', 1, true);


--
-- Data for Name: author; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.author (ct_pk, agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte, is_enabled) FROM stdin;
4	4	5	contributors may be used to endorse or promote	1babb6972f13d5400aa0901acdd5e7f0	author	860	906	t
5	4	5	CONTRIBUTORS	98f07bc20cb66328be238119df96c490	author	1067	1079	t
6	4	5	CONTRIBUTORS BE LIABLE	771d4b66a62cdc6e1f4bd602ddf32400	author	1327	1349	t
15	4	4	authors commit to using it.	6fb7e374ba71269124d1948355a3a028	author	861	888	t
18	4	4	author to ask for permission.	cdc9bf294f219e9055c38644808eb95b	author	14289	14318	t
23	4	3	authors who decide to use it.	b00e37ce66268b188d10fbf046b44a93	author	934	963	t
25	4	3	authorized party saying it may be distributed under the terms of	78312666505455906646e3f94388d498	author	6201	6265	t
24	4	3	modified by someone else and passed on, the recipients should know	9cf0d2e77addde255ea09f5abd4e3bde	author	2703	2769	f
16	4	4	modified by someone else and passed on	10203b6b60982dc159be374712c021b4	author	2319	2357	f
17	4	4	authors	5d9449e8d8508c7ee2cf746c86e5dde3	author	2506	2513	f
\.


--
-- Data for Name: bucket_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.bucket_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime, nomosagent_fk, bucketpool_fk) FROM stdin;
\.


--
-- Name: bucket_bucket_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.bucket_bucket_pk_seq', 3, true);


--
-- Data for Name: bucket_container; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.bucket_container (bucket_fk, agent_fk, uploadtree_fk, bf_pk, nomosagent_fk) FROM stdin;
\.


--
-- Name: bucket_container_bf_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.bucket_container_bf_pk_seq', 1, true);


--
-- Data for Name: bucket_def; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.bucket_def (bucket_pk, bucket_name, bucket_color, bucket_reportorder, bucket_evalorder, bucketpool_fk, bucket_type, bucket_regex, bucket_filename, stopon, applies_to) FROM stdin;
2	GPL Licenses (Demo)	orange	50	50	2	3	(affero|gpl)	\N	N	f
3	non-gpl (Demo)	yellow	50	1000	2	99	\N	\N	N	f
\.


--
-- Data for Name: bucket_file; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.bucket_file (bf_pk, bucket_fk, pfile_fk, agent_fk, nomosagent_fk) FROM stdin;
\.


--
-- Name: bucket_file_bf_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.bucket_file_bf_pk_seq', 1, true);


--
-- Data for Name: bucketpool; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.bucketpool (bucketpool_pk, bucketpool_name, version, active, description) FROM stdin;
2	GPL Demo bucket pool	1	Y	Demonstration of a very simple GPL/non-gpl bucket pool
\.


--
-- Name: bucketpool_bucketpool_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.bucketpool_bucketpool_pk_seq', 2, true);


--
-- Data for Name: clearing_decision; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.clearing_decision (clearing_decision_pk, uploadtree_fk, pfile_fk, user_fk, group_fk, date_added, decision_type, scope) FROM stdin;
3	5	4	3	3	2018-04-04 17:21:36.246904+05:30	5	0
5	6	5	3	3	2018-04-04 17:22:03.212649+05:30	3	0
7	4	3	3	3	2018-04-04 17:22:17.286333+05:30	5	0
\.


--
-- Name: clearing_decision_clearing_decision_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.clearing_decision_clearing_decision_pk_seq', 7, true);


--
-- Data for Name: clearing_decision_event; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.clearing_decision_event (clearing_event_fk, clearing_decision_fk) FROM stdin;
2	3
3	5
4	7
\.


--
-- Name: clearing_decision_license_events_clearing_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.clearing_decision_license_events_clearing_pk_seq', 1, false);


--
-- Data for Name: clearing_event; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.clearing_event (clearing_event_pk, uploadtree_fk, rf_fk, removed, user_fk, group_fk, job_fk, type_fk, comment, reportinfo, date_added) FROM stdin;
2	5	210	f	3	3	\N	3			2018-04-04 17:21:36.246904+05:30
3	6	509	f	3	3	\N	3			2018-04-04 17:22:03.212649+05:30
4	4	348	f	3	3	\N	3			2018-04-04 17:22:17.286333+05:30
\.


--
-- Name: clearing_event_clearing_event_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.clearing_event_clearing_event_pk_seq', 4, true);


--
-- Data for Name: copyright; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.copyright (ct_pk, agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte, is_enabled) FROM stdin;
2	4	5	Copyright (c) 2002-2004 Sam Leffler, Errno Consulting, Atheros Communications, Inc. All rights reserved.\r	5a340f75e8988869cdce766449ed4e8d	statement	7	115	t
3	4	5	copyright notice, this list of conditions and the following NO WARRANTY'' disclaimer below (''Disclaimer''), without modification. 3. Redistributions in binary form must reproduce at minimum a disclaimer similar to the Disclaimer below and any redistribution must be conditioned upon including a substantially similar Disclaimer requirement for further binary redistribution.\r	6d58e6e3b0bde1ad59b38403e3746532	statement	375	774	t
7	4	4	Copyright © 2002 Affero Inc. 510 Third Street - Suite 225, San Francisco, CA 94107, USA	d7858d0ab9143c67759c149e6c65e3ef	statement	54	142	t
8	4	4	copyright (C) 1989, 1991 Free Software Foundation, Inc. made with their permission. Section 2(d) has been added to cover use of software over a computer network.	ddc8203e7036cc7bcede5b7240215238	statement	213	374	t
9	4	4	copyright the software, and 2) offer you this license which gives you legal permission to copy, distribute and/or modify the software.	9b667c8c384ff67da1d6b0cb8fd1a420	statement	2018	2153	t
10	4	4	copyright law: that is to say, a work containing the Program or a portion of it, either verbatim or with modifications and/or translated into another language. (Hereinafter, translation is included without limitation in the term "modification".) Each licensee is addressed as you".	5ace850a975d232827deab22e23d352c	statement	3360	3642	t
11	4	4	copyright notice and disclaimer of warranty; keep intact all the notices that refer to this License and to the absence of any warranty; and give any other recipients of the Program a copy of this License along with the Program.	6ff40bbbda9062dbb2f08d1ea169d789	statement	4229	4456	t
13	4	4	copyrighted by Affero, Inc., write to us; we sometimes make exceptions for this. Our decision will be guided by the two goals of preserving the free status of all derivatives of our free software and of promoting the sharing and reuse of software generally.	beeb26b47a30880f54b1f41951ad5c39	statement	14341	14598	t
19	4	3	Copyright (C) 1991, 1999 Free Software Foundation, Inc. 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA Everyone is permitted to copy and distribute verbatim copies of this license document, but changing it is not allowed.	90bde9ecc9caf4f9d3cfe7f19061d9a7	statement	72	310	t
20	4	3	copyright the library, and (2) we offer you this license, which gives you legal permission to copy, distribute and/or modify the library.	7d2560befbe9cd72fb7dc00128d407a1	statement	2430	2567	t
21	4	3	copyright law: thay of this License along with the Library.	06037a119e4ed2e8beed47d0f6e5dc40	statement	6769	6828	t
22	4	3	Copyright (C) <year> <name of author>	444c085e373d7e01b356550ce846cb71	statement	15448	15486	f
12	4	4	copyrighted interfaces, the	6429aca755804df3fd3f89291d59b969	statement	12848	12875	f
14	4	4	COPYRIGHT HOLDERS AND/OR OTHER PARTIES PROVIDE THE PROGRAM "AS IS" WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE. THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE PROGRAM IS WITH YOU. SHOULD THE PROGRAM PROVE DEFECTIVE, YOU ASSUME THE COST OF ALL NECESSARY SERVICING, REPAIR OR CORRECTION.	2be2a2f23f0df4519885433b8eb77754	statement	14790	15210	f
\.


--
-- Data for Name: copyright_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.copyright_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
3	4	2	t	\N	2018-04-04 17:21:13.582164+05:30	2018-04-04 17:21:13.712849+05:30
\.


--
-- Name: copyright_ct_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.copyright_ct_pk_seq', 25, true);


--
-- Data for Name: copyright_decision; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.copyright_decision (copyright_decision_pk, user_fk, pfile_fk, clearing_decision_type_fk, description, textfinding, comment, is_enabled) FROM stdin;
\.


--
-- Name: copyright_decision_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.copyright_decision_pk_seq', 1, true);


--
-- Data for Name: decider_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.decider_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Data for Name: deciderjob_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.deciderjob_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Data for Name: dep5_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.dep5_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Data for Name: ecc; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.ecc (ct_pk, agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte, is_enabled) FROM stdin;
2	10	5	\N	\N	\N	\N	\N	t
3	10	4	\N	\N	\N	\N	\N	t
4	10	3	\N	\N	\N	\N	\N	t
\.


--
-- Data for Name: ecc_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.ecc_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
7	10	2	t	\N	2018-04-04 17:21:13.637278+05:30	2018-04-04 17:21:13.705439+05:30
\.


--
-- Name: ecc_ct_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.ecc_ct_pk_seq', 4, true);


--
-- Data for Name: ecc_decision; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.ecc_decision (copyright_decision_pk, user_fk, pfile_fk, clearing_decision_type_fk, description, textfinding, comment, is_enabled) FROM stdin;
\.


--
-- Name: ecc_decision_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.ecc_decision_pk_seq', 1, true);


--
-- Data for Name: file_picker; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.file_picker (file_picker_pk, user_fk, uploadtree_fk1, uploadtree_fk2, last_access_date) FROM stdin;
\.


--
-- Name: file_picker_file_picker_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.file_picker_file_picker_pk_seq', 1, true);


--
-- Data for Name: folder; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.folder (folder_pk, folder_name, user_fk, folder_desc, folder_perm) FROM stdin;
1	Software Repository	\N	Top Folder	\N
3	Folder1	3		\N
\.


--
-- Name: folder_folder_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.folder_folder_pk_seq', 3, true);


--
-- Data for Name: foldercontents; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.foldercontents (foldercontents_pk, parent_fk, foldercontents_mode, child_id) FROM stdin;
2	1	0	0
4	1	1	3
3	3	2	2
\.


--
-- Name: foldercontents_foldercontents_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.foldercontents_foldercontents_pk_seq', 4, true);


--
-- Name: group_group_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.group_group_pk_seq', 3, true);


--
-- Data for Name: group_user_member; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.group_user_member (group_user_member_pk, group_fk, user_fk, group_perm) FROM stdin;
2	2	2	1
3	3	3	1
\.


--
-- Name: group_user_member_group_user_member_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.group_user_member_group_user_member_pk_seq', 3, true);


--
-- Data for Name: groups; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.groups (group_pk, group_name) FROM stdin;
2	Default User
3	fossy
\.


--
-- Data for Name: highlight; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.highlight (type, start, len, fl_fk, rf_start, rf_len) FROM stdin;
M 	0	15825	4	0	15918
L 	82	39	3	\N	\N
L 	1895	122	3	\N	\N
L 	15591	152	3	\N	\N
L 	15079	66	3	\N	\N
L 	15591	152	3	\N	\N
L 	15079	66	3	\N	\N
L 	43	26	3	\N	\N
L 	223	39	6	\N	\N
L 	0	29	6	\N	\N
L 	617	29	6	\N	\N
L 	3183	30	6	\N	\N
L 	13269	29	6	\N	\N
L 	617	101	6	\N	\N
L 	0	40	6	\N	\N
L 	121	80	7	\N	\N
\.


--
-- Data for Name: highlight_bulk; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.highlight_bulk (clearing_event_fk, lrb_fk, start, len) FROM stdin;
\.


--
-- Data for Name: highlight_keyword; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.highlight_keyword (pfile_fk, start, len) FROM stdin;
3	6078	9
3	72	9
3	82	3
3	2430	9
3	5223	2
3	6175	9
3	6769	9
3	7436	2
3	11239	9
3	11279	9
3	12641	2
3	15302	9
3	15448	9
3	15458	3
3	3857	6
3	6747	6
3	8968	6
3	9167	6
3	9354	6
3	9725	6
3	10248	6
3	10401	6
3	225	9
3	1345	9
3	1682	9
3	1834	9
3	1911	9
3	2530	9
3	2587	9
3	5682	9
3	6029	9
3	6235	9
3	6653	9
3	7107	9
3	8683	9
3	8732	9
3	8816	9
3	9552	9
3	10436	9
3	10815	9
3	11611	9
3	12854	9
3	12879	9
3	13403	9
3	13458	9
3	13935	9
3	14128	9
3	14191	9
3	14792	9
3	14914	9
3	14968	9
3	15533	9
3	97	13
3	695	13
3	899	13
3	1199	13
3	1366	13
3	4301	13
3	5168	13
3	14778	13
3	14880	13
3	15508	13
3	15649	13
3	28	6
3	261	6
3	427	6
3	500	6
3	627	6
3	771	6
3	806	6
3	1045	6
3	1084	6
3	1281	6
3	2479	6
3	3139	6
3	3207	6
3	3319	6
3	3422	6
3	3437	6
3	3476	6
3	3582	6
3	3604	6
3	3922	6
3	4049	6
3	4140	6
3	4176	6
3	4268	6
3	4452	6
3	4501	6
3	5022	6
3	5412	6
3	5984	6
3	6070	6
3	6293	6
3	6320	6
3	6336	6
3	6797	6
3	7482	6
3	7553	6
3	8619	6
3	9245	6
3	9516	6
3	11150	6
3	11191	6
3	11386	6
3	13726	6
3	14115	6
3	14843	6
3	15066	6
3	15621	6
3	15705	6
3	2950	6
3	3154	6
3	3200	6
3	14627	6
3	2510	10
3	5050	10
3	5197	10
3	8191	7
3	8405	7
3	8801	7
3	13209	7
3	13698	7
3	1440	11
3	8831	11
3	9772	11
3	11515	11
3	11812	11
3	5648	20
3	5995	20
3	2647	7
3	6931	7
3	15252	7
4	11171	9
4	14850	5
4	54	9
4	64	2
4	213	9
4	223	3
4	2018	9
4	3113	9
4	3360	9
4	4229	9
4	5232	2
4	5459	9
4	8357	2
4	12848	9
4	12885	9
4	14341	9
4	14790	9
4	15295	9
4	15420	7
4	15489	7
4	15817	7
4	3338	6
4	6460	6
4	7280	6
4	10221	6
4	14504	6
4	410	9
4	1175	9
4	1639	9
4	1716	9
4	2115	9
4	2636	9
4	2918	9
4	2991	9
4	3147	9
4	3675	9
4	4053	9
4	4731	9
4	5027	9
4	5582	9
4	6761	9
4	6809	9
4	6899	9
4	7264	9
4	7488	9
4	7592	9
4	7875	9
4	8154	9
4	8241	9
4	8426	9
4	8516	9
4	9031	9
4	9086	9
4	9303	9
4	9491	9
4	9660	9
4	9785	9
4	10191	9
4	10337	9
4	10499	9
4	10579	9
4	10721	9
4	11317	9
4	11467	9
4	11563	9
4	11754	9
4	12237	9
4	12383	9
4	12535	9
4	12750	9
4	12977	9
4	13036	9
4	13888	9
4	14237	9
4	15356	9
4	238	13
4	1038	13
4	1196	13
4	12223	13
4	13958	13
4	14523	13
4	10156	5
4	10879	5
4	22	6
4	149	6
4	205	6
4	445	6
4	510	6
4	639	6
4	785	6
4	962	6
4	1113	6
4	2065	6
4	2699	6
4	2814	6
4	2854	6
4	3030	6
4	3205	6
4	3611	6
4	3729	6
4	4321	6
4	4425	6
4	5142	6
4	5220	6
4	5677	6
4	6697	6
4	6954	6
4	6991	6
4	7562	6
4	9648	6
4	9723	6
4	9774	6
4	9873	6
4	9956	6
4	9984	6
4	10095	6
4	10301	6
4	10435	6
4	10677	6
4	10703	6
4	10969	6
4	11234	6
4	11294	6
4	11388	6
4	11523	6
4	11712	6
4	12289	6
4	12586	6
4	12730	6
4	12936	6
4	13127	6
4	13197	6
4	13291	6
4	13554	6
4	13795	6
4	14004	6
4	14144	6
4	14640	6
4	2592	6
4	2692	6
4	2799	6
4	11040	6
4	11100	6
4	11516	6
4	12072	6
4	12834	6
4	285	10
4	2095	10
4	6969	10
4	10167	10
4	14307	10
4	5823	7
4	6484	7
4	10071	7
4	15235	7
4	1270	11
4	1899	11
4	6414	11
4	7848	11
4	8222	11
4	8451	11
4	8661	11
4	8812	11
4	9019	11
4	9449	11
4	9511	11
4	10754	10
4	2884	20
4	2957	20
4	10465	20
4	10771	20
4	14081	20
4	2267	7
4	4264	7
4	4355	7
4	4557	7
4	5506	7
4	5551	7
4	14603	7
4	14677	7
4	14865	7
4	14959	7
5	1085	5
5	7	9
5	17	3
5	375	9
5	818	9
5	1045	9
5	1306	9
5	1799	9
5	1394	7
5	1779	7
5	123	9
5	324	9
5	525	9
5	638	9
5	760	9
5	1580	10
5	1619	10
5	979	10
5	727	7
5	147	17
5	341	11
5	446	7
5	1000	7
5	1120	7
5	1178	7
\.


--
-- Data for Name: job; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.job (job_pk, job_queued, job_priority, job_email_notify, job_name, job_upload_fk, job_folder_fk, job_user_fk, job_group_fk) FROM stdin;
2	2018-04-04 17:21:11.369731+05:30	0	\N	3files.tar	2	\N	3	3
\.


--
-- Name: job_job_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.job_job_pk_seq', 2, true);


--
-- Data for Name: jobdepends; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.jobdepends (jdep_jq_fk, jdep_jq_depends_fk) FROM stdin;
3	2
4	3
5	3
6	3
7	3
8	3
9	3
\.


--
-- Data for Name: jobqueue; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.jobqueue (jq_pk, jq_job_fk, jq_type, jq_args, jq_starttime, jq_endtime, jq_endtext, jq_end_bits, jq_schedinfo, jq_itemsprocessed, jq_log, jq_runonpfile, jq_host, jq_cmd_args) FROM stdin;
2	2	ununpack	2	2018-04-04 17:21:11.393462+05:30	2018-04-04 17:21:12.490537+05:30	Completed	1	\N	5	\N	\N	\N	\N
3	2	adj2nest	2	2018-04-04 17:21:12.518178+05:30	2018-04-04 17:21:12.525097+05:30	Completed	1	\N	5	\N	\N	\N	\N
9	2	pkgagent	2	2018-04-04 17:21:13.556484+05:30	2018-04-04 17:21:13.632396+05:30	Completed	1	\N	0	\N	\N	\N	\N
5	2	ecc	2	2018-04-04 17:21:13.567223+05:30	2018-04-04 17:21:13.71996+05:30	Completed	1	\N	3	\N	\N	\N	\N
4	2	copyright	2	2018-04-04 17:21:13.553632+05:30	2018-04-04 17:21:13.721083+05:30	Completed	1	\N	3	\N	\N	\N	\N
6	2	mimetype	2	2018-04-04 17:21:13.585168+05:30	2018-04-04 17:21:13.722074+05:30	Completed	1	\N	3	\N	\N	\N	\N
7	2	monk	2	2018-04-04 17:21:13.580305+05:30	2018-04-04 17:21:14.132024+05:30	Completed	1	\N	3	\N	\N	\N	\N
8	2	nomos	2	2018-04-04 17:21:13.565313+05:30	2018-04-04 17:21:14.406481+05:30	Completed	1	\N	3	\N	\N	\N	\N
\.


--
-- Name: jobqueue_jq_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.jobqueue_jq_pk_seq', 9, true);


--
-- Data for Name: license_candidate; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.license_candidate (rf_pk, rf_shortname, rf_text, rf_url, rf_add_date, rf_copyleft, "rf_OSIapproved", rf_fullname, "rf_FSFfree", "rf_GPLv2compatible", "rf_GPLv3compatible", rf_notes, "rf_Fedora", marydone, rf_active, rf_text_updatable, rf_md5, rf_detector_type, rf_source, rf_risk, rf_spdx_compatible, group_fk) FROM stdin;
\.


--
-- Data for Name: license_file; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.license_file (fl_pk, rf_fk, agent_fk, rf_match_pct, rf_timestamp, pfile_fk, server_fk, fl_ref_start_byte, fl_ref_end_byte, fl_start_byte, fl_end_byte) FROM stdin;
2	\N	8	\N	2018-04-04 17:21:13.81153+05:30	5	1	\N	\N	\N	\N
3	348	2	\N	2018-04-04 17:21:14.020939+05:30	3	1	\N	\N	\N	\N
4	210	8	100	2018-04-04 17:21:14.041756+05:30	4	1	\N	\N	\N	\N
5	\N	8	\N	2018-04-04 17:21:14.123258+05:30	3	1	\N	\N	\N	\N
6	210	2	\N	2018-04-04 17:21:14.343015+05:30	4	1	\N	\N	\N	\N
7	509	2	\N	2018-04-04 17:21:14.400845+05:30	5	1	\N	\N	\N	\N
\.


--
-- Name: license_file_fl_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.license_file_fl_pk_seq', 7, true);


--
-- Data for Name: license_map; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.license_map (license_map_pk, rf_fk, rf_parent, usage) FROM stdin;
\.


--
-- Name: license_map_license_map_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.license_map_license_map_pk_seq', 1, true);


--
-- Data for Name: license_ref; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.license_ref (rf_pk, rf_shortname, rf_text, rf_url, rf_add_date, rf_copyleft, "rf_OSIapproved", rf_fullname, "rf_FSFfree", "rf_GPLv2compatible", "rf_GPLv3compatible", rf_notes, "rf_Fedora", marydone, rf_active, rf_text_updatable, rf_md5, rf_detector_type, rf_source, rf_risk, rf_spdx_compatible) FROM stdin;
210	AGPL-1.0	AFFERO GENERAL PUBLIC LICENSE\nVersion 1, March 2002\n\nCopyright © 2002 Affero Inc.\n510 Third Street - Suite 225, San Francisco, CA 94107, USA	http://www.affero.org/oagpl.html	\N	\N	\N	Affero General Public License 1.0	\N	\N	\N		\N	f	t	f	f24d9d5f3794d72434867b019a1e524c	1	\N	\N	t
348	LGPL-2.1	GNU LESSER GENERAL PUBLIC LICENSE\n\nVersion 2.1, February 1999	http://www.gnu.org/licenses/old-licenses/lgpl-2.1-standalone.html	\N	\N	\N	GNU Lesser General Public License v2.1 only	\N	\N	\N		\N	f	t	f	3e8a0da7ddcfc529776d35d2110feeac	1	\N	\N	t
509	BSD-style	According to BSD license, add some modifications		\N	\N	\N	BSD-style	\N	\N	\N		\N	f	t	f	10215fc49d301bf3addf289bcad2b238	1	\N	\N	f
\.


--
-- Data for Name: license_ref_bulk; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.license_ref_bulk (lrb_pk, user_fk, group_fk, rf_text, upload_fk, uploadtree_fk) FROM stdin;
\.


--
-- Name: license_ref_bulk_lrb_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.license_ref_bulk_lrb_pk_seq', 1, true);


--
-- Name: license_ref_rf_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.license_ref_rf_pk_seq', 570, true);


--
-- Data for Name: license_set_bulk; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.license_set_bulk (rf_fk, removing, lrb_fk) FROM stdin;
\.


--
-- Data for Name: mimetype; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.mimetype (mimetype_pk, mimetype_name) FROM stdin;
2	application/gzip
3	application/x-gzip
4	application/x-compress
5	application/x-bzip
6	application/x-bzip2
7	application/x-upx
8	application/pdf
9	application/x-pdf
10	application/x-zip
11	application/zip
12	application/x-tar
13	application/x-gtar
14	application/x-cpio
15	application/x-rar
16	application/x-cab
17	application/x-7z-compressed
18	application/x-7z-w-compressed
19	application/x-rpm
20	application/x-archive
21	application/x-debian-package
22	application/x-iso
23	application/x-iso9660-image
24	application/x-fat
25	application/x-ntfs
26	application/x-ext2
27	application/x-ext3
28	application/x-x86_boot
29	application/x-debian-source
30	application/x-xz
31	application/jar
32	application/java-archive
33	application/x-dosexec
34	text/plain
\.


--
-- Data for Name: mimetype_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.mimetype_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
6	7	2	t	\N	2018-04-04 17:21:13.616223+05:30	2018-04-04 17:21:13.71395+05:30
\.


--
-- Name: mimetype_mimetype_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.mimetype_mimetype_pk_seq', 34, true);


--
-- Data for Name: monk_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.monk_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
8	8	2	t	\N	2018-04-04 17:21:13.764897+05:30	2018-04-04 17:21:14.131314+05:30
\.


--
-- Data for Name: nomos_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.nomos_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
5	2	2	t	\N	2018-04-04 17:21:13.603309+05:30	2018-04-04 17:21:14.405825+05:30
\.


--
-- Name: nomos_ars_ars_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.nomos_ars_ars_pk_seq', 8, true);


--
-- Data for Name: obligation_candidate_map; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.obligation_candidate_map (om_pk, ob_fk, rf_fk) FROM stdin;
\.


--
-- Name: obligation_candidate_map_om_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.obligation_candidate_map_om_pk_seq', 1, true);


--
-- Data for Name: obligation_map; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.obligation_map (om_pk, ob_fk, rf_fk) FROM stdin;
\.


--
-- Name: obligation_map_om_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.obligation_map_om_pk_seq', 1, true);


--
-- Data for Name: obligation_ref; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.obligation_ref (ob_pk, ob_type, ob_topic, ob_text, ob_classification, ob_modifications, ob_comment, ob_active, ob_text_updatable, ob_md5) FROM stdin;
\.


--
-- Name: obligation_ref_ob_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.obligation_ref_ob_pk_seq', 1, true);


--
-- Data for Name: package; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.package (package_pk, package_name) FROM stdin;
\.


--
-- Name: package_package_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.package_package_pk_seq', 1, true);


--
-- Data for Name: perm_upload; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.perm_upload (perm_upload_pk, perm, upload_fk, group_fk) FROM stdin;
2	10	2	3
\.


--
-- Name: perm_upload_perm_upload_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.perm_upload_perm_upload_pk_seq', 2, true);


--
-- Data for Name: pfile; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.pfile (pfile_pk, pfile_md5, pfile_sha1, pfile_size, pfile_mimetypefk) FROM stdin;
2	A1582D84C99FDFA8F62207EFC020B289	043F7A2D45C14DA36073D42D738B1EC447A0008B	40960	12
4	1886FF496384AED7E2D8FEE6DC675C66	F8D180B3162D75FCA7540F903D995F02B5687007	15826	34
3	6F4DB2355E914E529CC50C3B8BE71FB6	4C082E3A223662C2E203EDE61CAEBCAA6DBFBCFD	15749	34
5	33ECB722BB791813617971B86565C9E5	3B9759E09D2942D98172E0C23C11A483607AFAE1	1855	34
\.


--
-- Name: pfile_pfile_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.pfile_pfile_pk_seq', 5, true);


--
-- Data for Name: pkg_deb; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.pkg_deb (pkg_name, pkg_arch, version, section, priority, installed_size, maintainer, homepage, source, description, pfile_fk, pkg_pk, summary, format, uploaders, standards_version) FROM stdin;
\.


--
-- Name: pkg_deb_pkg_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.pkg_deb_pkg_pk_seq', 1, true);


--
-- Data for Name: pkg_deb_req; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.pkg_deb_req (req_pk, pkg_fk, req_value) FROM stdin;
\.


--
-- Name: pkg_deb_req_req_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.pkg_deb_req_req_pk_seq', 1, true);


--
-- Data for Name: pkg_rpm; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.pkg_rpm (pkg_name, pkg_alias, pkg_arch, version, rpm_filename, license, pkg_group, packager, release, build_date, vendor, url, source_rpm, summary, description, pfile_fk, pkg_pk) FROM stdin;
\.


--
-- Name: pkg_rpm_pkg_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.pkg_rpm_pkg_pk_seq', 1, true);


--
-- Data for Name: pkg_rpm_req; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.pkg_rpm_req (pkg_fk, req_value, req_pk) FROM stdin;
\.


--
-- Name: pkg_rpm_req_req_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.pkg_rpm_req_req_pk_seq', 1, true);


--
-- Data for Name: pkgagent_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.pkgagent_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
4	14	2	t	\N	2018-04-04 17:21:13.597032+05:30	2018-04-04 17:21:13.631125+05:30
\.


--
-- Data for Name: readmeoss_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.readmeoss_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Data for Name: report_cache; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.report_cache (report_cache_pk, report_cache_tla, report_cache_key, report_cache_value, report_cache_uploadfk) FROM stdin;
\.


--
-- Name: report_cache_report_cache_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.report_cache_report_cache_pk_seq', 1, true);


--
-- Data for Name: report_cache_user; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.report_cache_user (report_cache_user_pk, user_fk, cache_on) FROM stdin;
\.


--
-- Name: report_cache_user_report_cache_user_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.report_cache_user_report_cache_user_pk_seq', 1, true);


--
-- Data for Name: report_info; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.report_info (ri_pk, upload_fk, ri_reviewed, ri_footer, ri_report_rel, ri_community, ri_component, ri_version, ri_release_date, ri_sw360_link, ri_general_assesment, ri_ga_additional, ri_ga_risk, ri_ga_checkbox_selection) FROM stdin;
\.


--
-- Name: report_info_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.report_info_pk_seq', 1, true);


--
-- Data for Name: reportgen; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.reportgen (upload_fk, job_fk, filepath) FROM stdin;
\.


--
-- Data for Name: reportimport_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.reportimport_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Data for Name: reuser_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.reuser_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Data for Name: spdx2_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.spdx2_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Data for Name: spdx2tv_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.spdx2tv_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Data for Name: sysconfig; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.sysconfig (sysconfig_pk, variablename, conf_value, ui_label, vartype, group_name, group_order, description, validation_function) FROM stdin;
19	SupportEmailLabel	Support	Support Email Label	2	Support	1	e.g. "Support"<br>Text that the user clicks on to create a new support email. This new email will be preaddressed to this support email address and subject.  HTML is ok.	
20	SupportEmailAddr	\N	Support Email Address	2	Support	2	e.g. "support@mycompany.com"<br>Individual or group email address to those providing FOSSology support.	check_email_address
21	SupportEmailSubject	FOSSology Support	Support Email Subject line	2	Support	3	e.g. "fossology support"<br>Subject line to use on support email.	
22	BannerMsg	\N	Banner message	3	Banner	1	This is message will be displayed on every page with a banner.  HTML is ok.	
23	LogoImage	\N	Logo Image URL	2	Logo	1	e.g. "http://mycompany.com/images/companylogo.png" or "images/mylogo.png"<br>This image replaces the fossology project logo. Image is constrained to 150px wide.  80-100px high is a good target.  If you change this URL, you MUST also enter a logo URL.	check_logo_image_url
24	LogoLink	\N	Logo URL	2	Logo	2	e.g. "http://mycompany.com/fossology"<br>URL a person goes to when they click on the logo.  If you change the Logo URL, you MUST also enter a Logo Image.	check_logo_url
25	FOSSologyURL	gaurav-VirtualBox/repo/	FOSSology URL	2	URL	1	URL of this FOSSology server, e.g. gaurav-VirtualBox/repo/	check_fossology_url
26	NomostListNum	2200	Maximum licenses to List	2	Number	4	For License List and License List Download, you can set the maximum number of lines to list/download. Default 2200.	\N
27	ShowJobsAutoRefresh	10	ShowJobs Auto Refresh Time	2	Number	\N	No of seconds to refresh ShowJobs	\N
28	ReportHeaderText	FOSSology	Report Header Text	2	ReportText	\N	Report Header Text at right side corner	\N
29	CommonObligation		Common Obligation	3	ReportText	\N	Common Obligation Text, add line break at the end of the line	\N
30	AdditionalObligation		Additional Obligation	3	ReportText	\N	Additional Obligation Text, add line break at the end of the line	\N
31	ObligationAndRisk		Obligation And Risk Assessment	3	ReportText	\N	Obligations and risk assessment, add line break at the end of the line	\N
32	BlockSizeHex	8192	Chars per page in hex view	2	Number	5	Number of characters per page in hex view	\N
33	BlockSizeText	81920	Chars per page in text view	2	Number	5	Number of characters per page in text view	\N
34	UploadFromServerWhitelist	/tmp	Whitelist for serverupload	2	UploadFromServer	5	List of allowed prefixes for upload, separated by ":" (colon)	\N
35	UploadFromServerAllowedHosts	localhost	List of allowed hosts for serverupload	2	UploadFromServer	5	List of allowed hosts for upload, separated by ":" (colon)	\N
\.


--
-- Name: sysconfig_sysconfig_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.sysconfig_sysconfig_pk_seq', 35, true);


--
-- Data for Name: tag; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.tag (tag_pk, tag, tag_desc) FROM stdin;
\.


--
-- Data for Name: tag_file; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.tag_file (tag_file_pk, tag_fk, pfile_fk, tag_file_date, tag_file_text) FROM stdin;
\.


--
-- Data for Name: tag_manage; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.tag_manage (tag_manage_pk, upload_fk, is_disabled) FROM stdin;
\.


--
-- Name: tag_manage_tag_manage_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.tag_manage_tag_manage_pk_seq', 1, true);


--
-- Data for Name: tag_uploadtree; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.tag_uploadtree (tag_uploadtree_pk, tag_fk, uploadtree_fk, tag_uploadtree_date, tag_uploadtree_text) FROM stdin;
\.


--
-- Name: tags_file_tag_file_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.tags_file_tag_file_pk_seq', 1, true);


--
-- Name: tags_tag_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.tags_tag_pk_seq', 1, true);


--
-- Name: tags_uploadtree_tag_uploadtree_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.tags_uploadtree_tag_uploadtree_pk_seq', 1, true);


--
-- Data for Name: unifiedreport_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.unifiedreport_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
\.


--
-- Data for Name: ununpack_ars; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.ununpack_ars (ars_pk, agent_fk, upload_fk, ars_success, ars_status, ars_starttime, ars_endtime) FROM stdin;
2	13	2	t	\N	2018-04-04 17:21:11.427317+05:30	2018-04-04 17:21:11.484719+05:30
\.


--
-- Data for Name: upload; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.upload (upload_pk, upload_desc, upload_filename, user_fk, upload_mode, upload_ts, pfile_fk, upload_origin, uploadtree_tablename, expire_date, expire_action, public_perm) FROM stdin;
2		3files.tar	3	104	2018-04-04 17:21:11.343194+05:30	2	3files.tar	uploadtree_a	\N	\N	0
\.


--
-- Data for Name: upload_clearing; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.upload_clearing (upload_fk, group_fk, assignee, status_fk, status_comment, priority) FROM stdin;
2	3	1	1	\N	2
\.


--
-- Data for Name: upload_clearing_license; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.upload_clearing_license (upload_fk, group_fk, rf_fk) FROM stdin;
\.


--
-- Data for Name: upload_packages; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.upload_packages (package_fk, upload_fk) FROM stdin;
\.


--
-- Data for Name: upload_reuse; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.upload_reuse (upload_fk, reused_upload_fk, group_fk, reused_group_fk, reuse_mode) FROM stdin;
\.


--
-- Name: upload_upload_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.upload_upload_pk_seq', 2, true);


--
-- Data for Name: uploadtree; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.uploadtree (uploadtree_pk, parent, realparent, upload_fk, pfile_fk, ufile_mode, lft, rgt, ufile_name) FROM stdin;
\.


--
-- Data for Name: uploadtree_a; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.uploadtree_a (uploadtree_pk, parent, realparent, upload_fk, pfile_fk, ufile_mode, lft, rgt, ufile_name) FROM stdin;
5	3	2	2	4	33261	3	4	Affero-v1.0
6	3	2	2	5	33261	5	6	BSD_style_a.txt
4	3	2	2	3	33261	7	8	gplv2.1
3	2	2	2	0	805323776	2	9	artifact.dir
2	\N	\N	2	2	536904704	1	10	3files.tar
\.


--
-- Name: uploadtree_uploadtree_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.uploadtree_uploadtree_pk_seq', 6, true);


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: fossy
--

COPY public.users (user_pk, user_name, root_folder_fk, user_desc, user_seed, user_pass, user_perm, user_email, email_notify, user_agent_list, default_bucketpool_fk, ui_preference, new_upload_group_fk, new_upload_perm, group_fk) FROM stdin;
2	Default User	1	Default User when nobody is logged in	Seed	Pass	0	\N	y	\N	\N	simple	\N	\N	2
3	fossy	1	Default Administrator	769151721213861587	1255c0a146b4a39d77f07e7239c502ae424902f3	10	y	y	\N	\N	simple	\N	\N	3
\.


--
-- Name: users_user_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fossy
--

SELECT pg_catalog.setval('public.users_user_pk_seq', 3, true);


--
-- Name: license_file FileLicense_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_file
    ADD CONSTRAINT "FileLicense_pkey" PRIMARY KEY (fl_pk);


--
-- Name: agent agent_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.agent
    ADD CONSTRAINT agent_pkey PRIMARY KEY (agent_pk);


--
-- Name: agent_runstatus agent_runstatus_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.agent_runstatus
    ADD CONSTRAINT agent_runstatus_pkey PRIMARY KEY (ars_pk);


--
-- Name: agent agent_unique_name_rev; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.agent
    ADD CONSTRAINT agent_unique_name_rev UNIQUE (agent_name, agent_rev);


--
-- Name: agent_wc agent_wc_pfile_fk_key; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.agent_wc
    ADD CONSTRAINT agent_wc_pfile_fk_key UNIQUE (pfile_fk);


--
-- Name: agent_runstatus ars_unique; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.agent_runstatus
    ADD CONSTRAINT ars_unique UNIQUE (agent_fk, upload_fk);


--
-- Name: attachments attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.attachments
    ADD CONSTRAINT attachments_pkey PRIMARY KEY (attachment_pk);


--
-- Name: author author_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.author
    ADD CONSTRAINT author_pkey PRIMARY KEY (ct_pk);


--
-- Name: bucket_ars bucket_ars_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucket_ars
    ADD CONSTRAINT bucket_ars_pkey PRIMARY KEY (ars_pk);


--
-- Name: bucket_container bucket_container_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucket_container
    ADD CONSTRAINT bucket_container_pkey PRIMARY KEY (bf_pk);


--
-- Name: bucket_file bucket_file_bucket_fk_key; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucket_file
    ADD CONSTRAINT bucket_file_bucket_fk_key UNIQUE (bucket_fk, pfile_fk, agent_fk, nomosagent_fk);


--
-- Name: bucket_file bucket_file_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucket_file
    ADD CONSTRAINT bucket_file_pkey PRIMARY KEY (bf_pk);


--
-- Name: bucket_def bucket_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucket_def
    ADD CONSTRAINT bucket_pkey PRIMARY KEY (bucket_pk);


--
-- Name: bucketpool bucketpool_bucketpool_name_key1; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucketpool
    ADD CONSTRAINT bucketpool_bucketpool_name_key1 UNIQUE (bucketpool_name, version);


--
-- Name: bucketpool bucketpool_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucketpool
    ADD CONSTRAINT bucketpool_pkey PRIMARY KEY (bucketpool_pk);


--
-- Name: clearing_decision clearing_decision_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.clearing_decision
    ADD CONSTRAINT clearing_decision_pkey PRIMARY KEY (clearing_decision_pk);

ALTER TABLE public.clearing_decision CLUSTER ON clearing_decision_pkey;


--
-- Name: clearing_event clearing_event_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.clearing_event
    ADD CONSTRAINT clearing_event_pkey PRIMARY KEY (clearing_event_pk);


--
-- Name: copyright_decision copyright_decision_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.copyright_decision
    ADD CONSTRAINT copyright_decision_pkey PRIMARY KEY (copyright_decision_pk);


--
-- Name: copyright copyright_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.copyright
    ADD CONSTRAINT copyright_pkey PRIMARY KEY (ct_pk);


--
-- Name: mimetype dirmodemask; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.mimetype
    ADD CONSTRAINT dirmodemask UNIQUE (mimetype_name);


--
-- Name: ecc_decision ecc_decision_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ecc_decision
    ADD CONSTRAINT ecc_decision_pkey PRIMARY KEY (copyright_decision_pk);


--
-- Name: ecc ecc_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ecc
    ADD CONSTRAINT ecc_pkey PRIMARY KEY (ct_pk);


--
-- Name: file_picker file_picker_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.file_picker
    ADD CONSTRAINT file_picker_pkey PRIMARY KEY (file_picker_pk);


--
-- Name: file_picker file_picker_user_fk_key; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.file_picker
    ADD CONSTRAINT file_picker_user_fk_key UNIQUE (user_fk, uploadtree_fk1, uploadtree_fk2);


--
-- Name: folder folder_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.folder
    ADD CONSTRAINT folder_pkey PRIMARY KEY (folder_pk);


--
-- Name: foldercontents foldercontents_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.foldercontents
    ADD CONSTRAINT foldercontents_pkey PRIMARY KEY (foldercontents_pk);


--
-- Name: groups group_group_name_key; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT group_group_name_key UNIQUE (group_name);


--
-- Name: groups group_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT group_pkey PRIMARY KEY (group_pk);


--
-- Name: group_user_member group_user_member_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.group_user_member
    ADD CONSTRAINT group_user_member_pkey PRIMARY KEY (group_user_member_pk);


--
-- Name: job job_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.job
    ADD CONSTRAINT job_pkey PRIMARY KEY (job_pk);


--
-- Name: jobqueue jobqueue_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.jobqueue
    ADD CONSTRAINT jobqueue_pkey PRIMARY KEY (jq_pk);


--
-- Name: license_map license_map_pkpk; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_map
    ADD CONSTRAINT license_map_pkpk PRIMARY KEY (license_map_pk);


--
-- Name: license_ref_bulk license_ref_bulk_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_ref_bulk
    ADD CONSTRAINT license_ref_bulk_pkey PRIMARY KEY (lrb_pk);


--
-- Name: license_ref license_ref_rf_shortname_key; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_ref
    ADD CONSTRAINT license_ref_rf_shortname_key UNIQUE (rf_shortname);


--
-- Name: pfile md5_sha1_size; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pfile
    ADD CONSTRAINT md5_sha1_size UNIQUE (pfile_md5, pfile_sha1, pfile_size);


--
-- Name: mimetype mimetype_pk; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.mimetype
    ADD CONSTRAINT mimetype_pk PRIMARY KEY (mimetype_pk);


--
-- Name: ars_master nomos_ars_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ars_master
    ADD CONSTRAINT nomos_ars_pkey PRIMARY KEY (ars_pk);


--
-- Name: package package_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.package
    ADD CONSTRAINT package_pkey PRIMARY KEY (package_pk);


--
-- Name: perm_upload perm_upload_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.perm_upload
    ADD CONSTRAINT perm_upload_pkey PRIMARY KEY (perm_upload_pk);


--
-- Name: perm_upload perm_upload_upload_fk_group_fk_key; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.perm_upload
    ADD CONSTRAINT perm_upload_upload_fk_group_fk_key UNIQUE (upload_fk, group_fk);


--
-- Name: pfile pfile_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pfile
    ADD CONSTRAINT pfile_pkey PRIMARY KEY (pfile_pk);


--
-- Name: pkg_deb pkg_deb_pfile_fk_key; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkg_deb
    ADD CONSTRAINT pkg_deb_pfile_fk_key UNIQUE (pfile_fk);


--
-- Name: pkg_deb pkg_deb_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkg_deb
    ADD CONSTRAINT pkg_deb_pkey PRIMARY KEY (pkg_pk);


--
-- Name: pkg_deb_req pkg_deb_req_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkg_deb_req
    ADD CONSTRAINT pkg_deb_req_pkey PRIMARY KEY (req_pk);


--
-- Name: pkg_rpm pkg_rpm_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkg_rpm
    ADD CONSTRAINT pkg_rpm_pkey PRIMARY KEY (pkg_pk);


--
-- Name: pkg_rpm_req pkg_rpm_req_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkg_rpm_req
    ADD CONSTRAINT pkg_rpm_req_pkey PRIMARY KEY (req_pk);


--
-- Name: report_cache report_cache_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.report_cache
    ADD CONSTRAINT report_cache_pkey PRIMARY KEY (report_cache_pk);


--
-- Name: report_cache report_cache_report_cache_key_key; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.report_cache
    ADD CONSTRAINT report_cache_report_cache_key_key UNIQUE (report_cache_key);


--
-- Name: report_cache_user report_cache_user_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.report_cache_user
    ADD CONSTRAINT report_cache_user_pkey PRIMARY KEY (report_cache_user_pk);


--
-- Name: license_ref rf_md5unique; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_ref
    ADD CONSTRAINT rf_md5unique UNIQUE (rf_md5);


--
-- Name: license_ref rf_pkpk; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_ref
    ADD CONSTRAINT rf_pkpk PRIMARY KEY (rf_pk);


--
-- Name: sysconfig sysconfig_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.sysconfig
    ADD CONSTRAINT sysconfig_pkey PRIMARY KEY (sysconfig_pk);


--
-- Name: sysconfig sysconfig_variablename_key; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.sysconfig
    ADD CONSTRAINT sysconfig_variablename_key UNIQUE (variablename);


--
-- Name: tag_manage tag_manage_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.tag_manage
    ADD CONSTRAINT tag_manage_pkey PRIMARY KEY (tag_manage_pk);


--
-- Name: tag_file tags_file_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.tag_file
    ADD CONSTRAINT tags_file_pkey PRIMARY KEY (tag_file_pk);


--
-- Name: tag tags_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.tag
    ADD CONSTRAINT tags_pkey PRIMARY KEY (tag_pk);


--
-- Name: tag_uploadtree tags_uploadtree_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.tag_uploadtree
    ADD CONSTRAINT tags_uploadtree_pkey PRIMARY KEY (tag_uploadtree_pk);


--
-- Name: uploadtree ufile_rel_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.uploadtree
    ADD CONSTRAINT ufile_rel_pkey PRIMARY KEY (uploadtree_pk);

ALTER TABLE public.uploadtree CLUSTER ON ufile_rel_pkey;


--
-- Name: upload_clearing upload_clearing_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.upload_clearing
    ADD CONSTRAINT upload_clearing_pkey PRIMARY KEY (upload_fk, group_fk);


--
-- Name: upload upload_pkey_idx; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.upload
    ADD CONSTRAINT upload_pkey_idx PRIMARY KEY (upload_pk);

ALTER TABLE public.upload CLUSTER ON upload_pkey_idx;


--
-- Name: uploadtree_a uploadtree_a_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.uploadtree_a
    ADD CONSTRAINT uploadtree_a_pkey PRIMARY KEY (uploadtree_pk);


--
-- Name: users user_pkey; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT user_pkey PRIMARY KEY (user_pk);


--
-- Name: users user_user_name_key; Type: CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT user_user_name_key UNIQUE (user_name);


--
-- Name: agent_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX agent_fk_idx ON public.copyright USING btree (agent_fk);


--
-- Name: author_agent_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX author_agent_fk_idx ON public.author USING btree (agent_fk);


--
-- Name: author_pfile_fk_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX author_pfile_fk_index ON public.author USING btree (pfile_fk);


--
-- Name: author_pfile_hash_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX author_pfile_hash_idx ON public.author USING btree (hash, pfile_fk);


--
-- Name: bucketcontainer_uploadtree; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX bucketcontainer_uploadtree ON public.bucket_container USING btree (uploadtree_fk);


--
-- Name: clearing_decision_event_clearing_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX clearing_decision_event_clearing_fk_idx ON public.clearing_decision_event USING btree (clearing_decision_fk);


--
-- Name: clearing_decision_group_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX clearing_decision_group_fk_idx ON public.clearing_decision USING btree (group_fk);


--
-- Name: clearing_decision_pfile_fk_scope_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX clearing_decision_pfile_fk_scope_idx ON public.clearing_decision USING btree (pfile_fk, scope);


--
-- Name: clearing_decision_uploadtree_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX clearing_decision_uploadtree_fk_idx ON public.clearing_decision USING btree (uploadtree_fk);


--
-- Name: clearing_decision_uploadtree_group_pfile_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX clearing_decision_uploadtree_group_pfile_idx ON public.clearing_decision USING btree (uploadtree_fk, group_fk, pfile_fk);


--
-- Name: clearing_event_job_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX clearing_event_job_fk_idx ON public.clearing_event USING btree (job_fk);


--
-- Name: clearing_event_uploadtree_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX clearing_event_uploadtree_fk_idx ON public.clearing_event USING btree (uploadtree_fk);


--
-- Name: clearing_event_uploadtree_group_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX clearing_event_uploadtree_group_fk_idx ON public.clearing_event USING btree (uploadtree_fk, group_fk, date_added);


--
-- Name: copyright_decision_clearing_decision_type_fk_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX copyright_decision_clearing_decision_type_fk_index ON public.copyright_decision USING btree (clearing_decision_type_fk);


--
-- Name: copyright_decision_pfile_fk_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX copyright_decision_pfile_fk_index ON public.copyright_decision USING btree (pfile_fk);


--
-- Name: copyright_decision_user_fk_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX copyright_decision_user_fk_index ON public.copyright_decision USING btree (user_fk);


--
-- Name: copyright_pfile_fk_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX copyright_pfile_fk_index ON public.copyright USING btree (pfile_fk);


--
-- Name: copyright_pfile_hash_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX copyright_pfile_hash_idx ON public.copyright USING btree (hash, pfile_fk);


--
-- Name: ecc_agent_fk_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX ecc_agent_fk_index ON public.ecc USING btree (agent_fk);


--
-- Name: ecc_decision_clearing_decision_type_fk_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX ecc_decision_clearing_decision_type_fk_index ON public.ecc_decision USING btree (clearing_decision_type_fk);


--
-- Name: ecc_decision_pfile_fk_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX ecc_decision_pfile_fk_index ON public.ecc_decision USING btree (pfile_fk);


--
-- Name: ecc_decision_user_fk_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX ecc_decision_user_fk_index ON public.ecc_decision USING btree (user_fk);


--
-- Name: ecc_hash_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX ecc_hash_index ON public.ecc USING btree (hash);


--
-- Name: ecc_pfile_fk_index; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX ecc_pfile_fk_index ON public.ecc USING btree (pfile_fk);


--
-- Name: ecc_pfile_hash_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX ecc_pfile_hash_idx ON public.ecc USING btree (hash, pfile_fk);


--
-- Name: fl_rf_fk; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX fl_rf_fk ON public.license_file USING btree (rf_fk);


--
-- Name: group_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX group_fk_idx ON public.perm_upload USING btree (group_fk);


--
-- Name: highlight_bulk_clearing_event_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX highlight_bulk_clearing_event_fk_idx ON public.highlight_bulk USING btree (clearing_event_fk);


--
-- Name: highlight_bulk_lrb_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX highlight_bulk_lrb_fk_idx ON public.highlight_bulk USING btree (lrb_fk);


--
-- Name: highlight_fl_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX highlight_fl_fk_idx ON public.highlight USING btree (fl_fk);


--
-- Name: highlight_keyword_pfile_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX highlight_keyword_pfile_fk_idx ON public.highlight_keyword USING btree (pfile_fk);

ALTER TABLE public.highlight_keyword CLUSTER ON highlight_keyword_pfile_fk_idx;


--
-- Name: lf_agent_fk; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX lf_agent_fk ON public.license_file USING btree (agent_fk);


--
-- Name: lf_pfile_agent_rf_lf_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX lf_pfile_agent_rf_lf_idx ON public.license_file USING btree (pfile_fk, agent_fk, rf_fk, fl_pk);


--
-- Name: lf_pfile_fk; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX lf_pfile_fk ON public.license_file USING btree (pfile_fk);


--
-- Name: lft_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX lft_idx ON public.uploadtree USING btree (lft);


--
-- Name: license_ref_bulk_group_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX license_ref_bulk_group_fk_idx ON public.license_ref_bulk USING btree (group_fk);


--
-- Name: license_ref_bulk_uploadtree_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX license_ref_bulk_uploadtree_fk_idx ON public.license_ref_bulk USING btree (uploadtree_fk);


--
-- Name: license_set_bulk_lrb_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX license_set_bulk_lrb_fk_idx ON public.license_set_bulk USING btree (lrb_fk);


--
-- Name: parent; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX parent ON public.uploadtree USING btree (parent);


--
-- Name: pfile_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX pfile_idx ON public.bucket_file USING btree (pfile_fk);


--
-- Name: pfile_mimetypefk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX pfile_mimetypefk_idx ON public.pfile USING btree (pfile_mimetypefk);


--
-- Name: projtree_projid_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX projtree_projid_idx ON public.uploadtree USING btree (upload_fk);


--
-- Name: report_cache_createdts; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX report_cache_createdts ON public.report_cache USING btree (report_cache_tla);


--
-- Name: rf_shortname_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX rf_shortname_idx ON public.license_ref USING btree (rf_shortname);


--
-- Name: upload_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX upload_fk_idx ON public.perm_upload USING btree (upload_fk);


--
-- Name: uploadtree_a_lft_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX uploadtree_a_lft_idx ON public.uploadtree_a USING btree (lft);


--
-- Name: uploadtree_a_parent_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX uploadtree_a_parent_idx ON public.uploadtree_a USING btree (parent);


--
-- Name: uploadtree_a_pfile_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX uploadtree_a_pfile_fk_idx ON public.uploadtree_a USING btree (pfile_fk);


--
-- Name: uploadtree_a_upload_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX uploadtree_a_upload_fk_idx ON public.uploadtree_a USING btree (upload_fk);


--
-- Name: uploadtree_a_upload_fk_lft_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX uploadtree_a_upload_fk_lft_idx ON public.uploadtree_a USING btree (upload_fk, lft);


--
-- Name: uploadtree_a_upload_lft_ufilemode_pfile_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX uploadtree_a_upload_lft_ufilemode_pfile_idx ON public.uploadtree_a USING btree (upload_fk, lft, ufile_mode, pfile_fk);


--
-- Name: uploadtree_pfile_fk_idx; Type: INDEX; Schema: public; Owner: fossy
--

CREATE INDEX uploadtree_pfile_fk_idx ON public.uploadtree USING btree (pfile_fk);


--
-- Name: bucket_file bf_bucket_agent_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucket_file
    ADD CONSTRAINT bf_bucket_agent_fk FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: bucket_container bucket_container_agent_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucket_container
    ADD CONSTRAINT bucket_container_agent_fk_fkey FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: bucket_container bucket_container_nomosagent_pk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucket_container
    ADD CONSTRAINT bucket_container_nomosagent_pk_fkey FOREIGN KEY (nomosagent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: bucket_def bucketpool_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucket_def
    ADD CONSTRAINT bucketpool_fk FOREIGN KEY (bucketpool_fk) REFERENCES public.bucketpool(bucketpool_pk);


--
-- Name: clearing_decision clearing_decision_pfile_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.clearing_decision
    ADD CONSTRAINT clearing_decision_pfile_fk_fkey FOREIGN KEY (pfile_fk) REFERENCES public.pfile(pfile_pk) ON DELETE CASCADE;


--
-- Name: clearing_decision clearing_decision_user_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.clearing_decision
    ADD CONSTRAINT clearing_decision_user_fk_fkey FOREIGN KEY (user_fk) REFERENCES public.users(user_pk) ON DELETE CASCADE;


--
-- Name: copyright_ars copyright_ars_agent_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.copyright_ars
    ADD CONSTRAINT copyright_ars_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: copyright_ars copyright_ars_upload_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.copyright_ars
    ADD CONSTRAINT copyright_ars_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: copyright copyright_pfile_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.copyright
    ADD CONSTRAINT copyright_pfile_fk_fkey FOREIGN KEY (pfile_fk) REFERENCES public.pfile(pfile_pk) ON DELETE CASCADE;


--
-- Name: dep5_ars dep5_ars_agent_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.dep5_ars
    ADD CONSTRAINT dep5_ars_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: dep5_ars dep5_ars_upload_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.dep5_ars
    ADD CONSTRAINT dep5_ars_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: ecc_ars ecc_ars_agent_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ecc_ars
    ADD CONSTRAINT ecc_ars_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: ecc_ars ecc_ars_upload_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ecc_ars
    ADD CONSTRAINT ecc_ars_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: ecc_decision ecc_decision_user_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ecc_decision
    ADD CONSTRAINT ecc_decision_user_fk FOREIGN KEY (user_fk) REFERENCES public.users(user_pk) ON DELETE CASCADE;


--
-- Name: group_user_member group_user_member_group_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.group_user_member
    ADD CONSTRAINT group_user_member_group_fk_fkey FOREIGN KEY (group_fk) REFERENCES public.groups(group_pk);


--
-- Name: group_user_member group_user_member_user_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.group_user_member
    ADD CONSTRAINT group_user_member_user_fk_fkey FOREIGN KEY (user_fk) REFERENCES public.users(user_pk);


--
-- Name: jobdepends jdep_depends_jq_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.jobdepends
    ADD CONSTRAINT jdep_depends_jq_fk FOREIGN KEY (jdep_jq_depends_fk) REFERENCES public.jobqueue(jq_pk) ON DELETE CASCADE;


--
-- Name: jobdepends jdep_jq_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.jobdepends
    ADD CONSTRAINT jdep_jq_fk FOREIGN KEY (jdep_jq_fk) REFERENCES public.jobqueue(jq_pk) ON DELETE CASCADE;


--
-- Name: job job_job_folder_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.job
    ADD CONSTRAINT job_job_folder_fk_fkey FOREIGN KEY (job_folder_fk) REFERENCES public.folder(folder_pk);


--
-- Name: jobqueue jobqueue_job_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.jobqueue
    ADD CONSTRAINT jobqueue_job_fk FOREIGN KEY (jq_job_fk) REFERENCES public.job(job_pk) ON DELETE CASCADE;


--
-- Name: license_candidate license_candidate_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_candidate
    ADD CONSTRAINT license_candidate_fkey FOREIGN KEY (group_fk) REFERENCES public.groups(group_pk) ON DELETE CASCADE;


--
-- Name: license_map license_map_rf_fkfk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_map
    ADD CONSTRAINT license_map_rf_fkfk FOREIGN KEY (rf_fk) REFERENCES public.license_ref(rf_pk);


--
-- Name: license_set_bulk license_set_bulk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_set_bulk
    ADD CONSTRAINT license_set_bulk_fkey FOREIGN KEY (lrb_fk) REFERENCES public.license_ref_bulk(lrb_pk) ON DELETE CASCADE;


--
-- Name: mimetype_ars mimetype_ars_agent_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.mimetype_ars
    ADD CONSTRAINT mimetype_ars_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: mimetype_ars mimetype_ars_upload_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.mimetype_ars
    ADD CONSTRAINT mimetype_ars_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: pfile mimetype_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pfile
    ADD CONSTRAINT mimetype_fk FOREIGN KEY (pfile_mimetypefk) REFERENCES public.mimetype(mimetype_pk);


--
-- Name: ars_master nomos_ars_agent_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ars_master
    ADD CONSTRAINT nomos_ars_agent_fk_fkey FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: ars_master nomos_ars_upload_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.ars_master
    ADD CONSTRAINT nomos_ars_upload_fk_fkey FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk);


--
-- Name: bucket_file nomosagentpk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.bucket_file
    ADD CONSTRAINT nomosagentpk FOREIGN KEY (nomosagent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: perm_upload perm_upload_fkidx; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.perm_upload
    ADD CONSTRAINT perm_upload_fkidx FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: perm_upload perm_upload_group_fkidx; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.perm_upload
    ADD CONSTRAINT perm_upload_group_fkidx FOREIGN KEY (group_fk) REFERENCES public.groups(group_pk) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: license_file pfile_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.license_file
    ADD CONSTRAINT pfile_fk FOREIGN KEY (pfile_fk) REFERENCES public.pfile(pfile_pk) ON DELETE CASCADE;


--
-- Name: pkg_deb pkg_deb_pfile_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkg_deb
    ADD CONSTRAINT pkg_deb_pfile_fk_fkey FOREIGN KEY (pfile_fk) REFERENCES public.pfile(pfile_pk) ON DELETE CASCADE;


--
-- Name: pkg_rpm pkg_rpm_pfile_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkg_rpm
    ADD CONSTRAINT pkg_rpm_pfile_fk_fkey FOREIGN KEY (pfile_fk) REFERENCES public.pfile(pfile_pk) ON DELETE CASCADE;


--
-- Name: pkgagent_ars pkgagent_ars_agent_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkgagent_ars
    ADD CONSTRAINT pkgagent_ars_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: pkgagent_ars pkgagent_ars_upload_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.pkgagent_ars
    ADD CONSTRAINT pkgagent_ars_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: reportimport_ars reportimport_ars_agent_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.reportimport_ars
    ADD CONSTRAINT reportimport_ars_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: reportimport_ars reportimport_ars_upload_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.reportimport_ars
    ADD CONSTRAINT reportimport_ars_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: spdx2_ars spdx2_ars_agent_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.spdx2_ars
    ADD CONSTRAINT spdx2_ars_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: spdx2_ars spdx2_ars_upload_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.spdx2_ars
    ADD CONSTRAINT spdx2_ars_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: spdx2tv_ars spdx2tv_ars_agent_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.spdx2tv_ars
    ADD CONSTRAINT spdx2tv_ars_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: spdx2tv_ars spdx2tv_ars_upload_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.spdx2tv_ars
    ADD CONSTRAINT spdx2tv_ars_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: tag_manage tag_manage_upload_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.tag_manage
    ADD CONSTRAINT tag_manage_upload_fk_fkey FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: tag_file tags_file_pfile_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.tag_file
    ADD CONSTRAINT tags_file_pfile_fk_fkey FOREIGN KEY (pfile_fk) REFERENCES public.pfile(pfile_pk) ON DELETE CASCADE;


--
-- Name: tag_file tags_file_tag_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.tag_file
    ADD CONSTRAINT tags_file_tag_fk_fkey FOREIGN KEY (tag_fk) REFERENCES public.tag(tag_pk) ON DELETE CASCADE;


--
-- Name: tag_uploadtree tags_uploadtree_tag_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.tag_uploadtree
    ADD CONSTRAINT tags_uploadtree_tag_fk_fkey FOREIGN KEY (tag_fk) REFERENCES public.tag(tag_pk) ON DELETE CASCADE;


--
-- Name: unifiedreport_ars unifiedreport_ars_agent_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.unifiedreport_ars
    ADD CONSTRAINT unifiedreport_ars_agent_fk_fkc FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk);


--
-- Name: unifiedreport_ars unifiedreport_ars_upload_fk_fkc; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.unifiedreport_ars
    ADD CONSTRAINT unifiedreport_ars_upload_fk_fkc FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: upload_packages upload_packages_package_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.upload_packages
    ADD CONSTRAINT upload_packages_package_fk FOREIGN KEY (package_fk) REFERENCES public.package(package_pk) ON DELETE CASCADE;


--
-- Name: upload_packages upload_packages_upload_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.upload_packages
    ADD CONSTRAINT upload_packages_upload_fk FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: upload_reuse upload_reuse_reused_upload_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.upload_reuse
    ADD CONSTRAINT upload_reuse_reused_upload_fk FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: upload_reuse upload_reuse_upload_fk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.upload_reuse
    ADD CONSTRAINT upload_reuse_upload_fk FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: uploadtree_a uploadtree_a_upload_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.uploadtree_a
    ADD CONSTRAINT uploadtree_a_upload_fk_fkey FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: uploadtree uploadtree_uploadfk; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.uploadtree
    ADD CONSTRAINT uploadtree_uploadfk FOREIGN KEY (upload_fk) REFERENCES public.upload(upload_pk) ON DELETE CASCADE;


--
-- Name: users users_default_bucketpool_pk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_default_bucketpool_pk_fkey FOREIGN KEY (default_bucketpool_fk) REFERENCES public.bucketpool(bucketpool_pk);


--
-- Name: users users_group_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_group_id_fkey FOREIGN KEY (group_fk) REFERENCES public.groups(group_pk);


--
-- Name: users users_new_upload_group_fk_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fossy
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_new_upload_group_fk_fkey FOREIGN KEY (new_upload_group_fk) REFERENCES public.groups(group_pk) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

