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

SET check_function_bodies = false;
SET client_min_messages = warning;

CREATE DATABASE fosstest WITH TEMPLATE = template0; -- ENCODING = 'SQL_ASCII';

ALTER DATABASE fosstest OWNER TO fossy;

\connect fosstest

SET check_function_bodies = false;
SET client_min_messages = warning;


SET search_path = public, pg_catalog;
SET default_tablespace = '';
SET default_with_oids = false;

