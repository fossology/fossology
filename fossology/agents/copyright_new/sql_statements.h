/* **************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 *
 * TODO determine if this is needed
 */
char* no_copyrights_found = "\
INSERT INTO copyright (agent_fk, pfile_fk, copy_startbyte, copy_endbyte, content, hash, type) \
  VALUES (%d, %d, NULL, NULL, NULL, NULL, 'statement')";

/**
 * This will enter that a copyright has been found for the file that was just
 * analyzed, it enters the start, end, and text of the entry.
 *
 * TODO determine the purpose of the has and type and if I need to keep those.
 */
char* copyrights_found = "\
INSERT INTO copyright (agent_fk, pfile_fk, copy_startbyte, copy_endbyte, content, hash, type) \
  VALUES (%d, %d, %d, %d, E'%s', E'%s', '%s')";


#endif /* SQL_STATEMENTS_H_INCLUDE */
