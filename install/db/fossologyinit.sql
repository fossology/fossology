-- SPDX-FileCopyrightText: © Fossology contributors

-- SPDX-License-Identifier: GPL-2.0-only
create role fossy with createdb login password 'fossy';
--
-- PostgreSQL database dump
--
-- The database name comes from the psql variable "dbname"; it defaults to
-- fossology when the script is run without -v dbname=<name>.
\if :{?dbname}
\else
\set dbname fossology
\endif

SET client_encoding = 'UTF8';
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: fossology; Type: DATABASE; Schema: -; Owner: fossy
--

CREATE DATABASE :"dbname" WITH TEMPLATE = template1 ENCODING = 'UTF8';


ALTER DATABASE :"dbname" OWNER TO fossy;

\connect :"dbname"
CREATE LANGUAGE plpgsql;

SET client_encoding = 'UTF8';
SET check_function_bodies = false;
SET client_min_messages = warning;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: postgres
--

COMMENT ON SCHEMA public IS 'Standard public schema';

SET search_path = public, pg_catalog;
SET default_tablespace = '';
SET default_with_oids = false;

