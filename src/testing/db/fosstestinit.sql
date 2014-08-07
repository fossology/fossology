-- create role fossy with createdb login password 'fossy';

do 
$$
declare num_users integer;
begin
   SELECT count(*) into num_users FROM pg_user WHERE usename = 'fossy';
   IF num_users = 0 THEN
      CREATE ROLE fossy LOGIN PASSWORD 'fossy';
   END IF;
end
$$
;


--
-- version "$Id$"
--
-- PostgreSQL database dump
--

SET client_encoding = 'SQL_ASCII';
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: fosstest; Type: DATABASE; Schema: -; Owner: fossy
--

CREATE DATABASE fosstest WITH TEMPLATE = template0 ENCODING = 'SQL_ASCII';


ALTER DATABASE fosstest OWNER TO fossy;

\connect fosstest
CREATE OR REPLACE LANGUAGE plpgsql;

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

