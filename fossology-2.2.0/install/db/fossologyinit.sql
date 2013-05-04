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

