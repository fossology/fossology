-- SPDX-FileCopyrightText: © Fossology contributors

-- SPDX-License-Identifier: GPL-2.0-only
--
-- Copy of fossologyinit.sql for gitpod
--

create role gitpod with createdb login password 'gitpod';
--
-- PostgreSQL database dump
--

SET client_encoding = 'UTF8';
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: fossology; Type: DATABASE; Schema: -; Owner: gitpod
--

CREATE DATABASE fossology WITH TEMPLATE = template1 ENCODING = 'UTF8';


ALTER DATABASE fossology OWNER TO gitpod;

\connect fossology
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

