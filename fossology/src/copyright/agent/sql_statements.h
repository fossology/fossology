/* **************************************************************
Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.

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

 ***************************************************************/

#ifndef SQL_STATEMENTS_H_INCLUDE
#define SQL_STATEMENTS_H_INCLUDE

/**
 * @file sql_statements.h
 * @version v1.3
 *
 * The purpose of this file is to make consolidate the sql statements and make
 * allow them to be readable. This file will allow for the correct indentation
 * in the sql statement since the c indentation is unimportant.
 */

/**
 * Given a pfile number this sql statement will attempt to retrieve a set of
 * filenames from the database for analysis
 */
char* fetch_pfile = "\
SELECT pfile_pk, pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfilename \
  FROM ( \
    SELECT distinct(pfile_fk) AS PF \
      FROM uploadtree \
      WHERE upload_fk = %d and (ufile_mode&x'3C000000'::int)=0 \
  ) AS SS \
    left outer join copyright on (PF = pfile_fk and agent_fk = %d) \
    inner join pfile on (PF = pfile_pk) \
  WHERE ct_pk IS null ";

/**
 * This will enter that no copyright entries were found for the file that was
 * just analyzed
 */
char* insert_no_copyright = "\
INSERT INTO copyright (agent_fk, pfile_fk, copy_startbyte, copy_endbyte, content, hash, type) \
  VALUES (%d, %d, NULL, NULL, NULL, NULL, 'statement')";

/**
 * This will enter that a copyright has been found for the file that was just
 * analyzed, it enters the start, end, and text of the entry.
 */
char* insert_copyright = "\
INSERT INTO copyright (agent_fk, pfile_fk, copy_startbyte, copy_endbyte, content, hash, type) \
  VALUES (%d, %d, %d, %d, E'%s', E'%s', '%s')";

/** This will check to see if the copyright table exists. */
char* check_database_table = "\
SELECT ct_pk \
  FROM copyright \
  LIMIT 1";

/*** Checks if the copyright_ars table exists */
char* check_copyright_ars = " \
SELECT schemaname \
  FROM pg_tables \
    WHERE tablename='copyright_ars';";

/** This will create the copyright sequence in the database */
char* create_database_sequence = "\
CREATE SEQUENCE copyright_ct_pk_seq \
  START WITH 1 \
  INCREMENT BY 1 \
  NO MAXVALUE \
  NO MINVALUE \
  CACHE 1";

/** create the table to the copyright agent */
char* create_database_table = "\
CREATE TABLE copyright (\
  ct_pk bigint            PRIMARY KEY DEFAULT nextval('copyright_ct_pk_seq'::regclass),\
  agent_fk bigint         NOT NULL,\
  pfile_fk bigint         NOT NULL,\
  content text,\
  hash text,\
  type text               CHECK (type in ('statement', 'email', 'url')),\
  copy_startbyte integer,\
  copy_endbyte integer)";

/** create the copyright pfile foreign key index */
char* create_pfile_foreign_index = "\
CREATE INDEX copyright_pfile_fk_index\
  ON copyright\
  USING BTREE (pfile_fk)";

/** create the copyright pfile foreign key index */
char* create_agent_foreign_index = "\
CREATE INDEX copyright_agent_fk_index\
  ON copyright\
  USING BTREE (agent_fk)";

/** TODO ??? */
char* alter_table_pfile = " \
ALTER TABLE ONLY copyright \
  ADD CONSTRAINT pfile_fk \
  FOREIGN KEY (pfile_fk) \
  REFERENCES pfile(pfile_pk) ON DELETE CASCADE";

/** TODO ??? */
char* alter_table_agent = " \
ALTER TABLE ONLY copyright \
  ADD CONSTRAINT agent_fk \
  FOREIGN KEY (agent_fk) \
  REFERENCES agent(agent_pk)";

/** check copyright table foreign key */
char* check_copyright_foreign_key = " \
select tablename from pg_indexes where tablename='copyright' and indexname='copyright_pfile_fk_index'";

/** clean copyright records */
char* cleanup_copyright_records = " \
DELETE FROM copyright WHERE pfile_fk NOT IN (SELECT pfile_pk FROM pfile)";

#endif /* SQL_STATEMENTS_H_INCLUDE */
