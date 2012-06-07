/*********************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
*********************************************************************/

/* cunit includes */
#include <CUnit/CUnit.h>
#include "finder.h"
#include <string.h>

/**
 * \file testDBCheckMime.c
 * \brief testing for the function DBCheckMime
 */

static PGresult *result = NULL;
static long upload_pk = -1;
static long pfile_pk = -1;
extern char *DBConfFile;

/**
 * \brief initialize
 */
int  DBCheckMimeInit()
{
  char *ErrorBuf;

  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);

  if (!pgConn)
  {
    LOG_FATAL("Unable to connect to database");
    exit(-1);
  }
  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"BEGIN;");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);
  /* insert pfile */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"INSERT INTO pfile (pfile_sha1,pfile_md5,pfile_size) VALUES ('%.40s','%.32s','%s');",
          "F1D2319DF20ABC4CEB02CA5A3C2021BD87B26810","87972FC55E2CDD2609ED85051BE50BAF","722");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /* select pfile_pk */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"SELECT pfile_pk FROM pfile WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = '%s';",
        "F1D2319DF20ABC4CEB02CA5A3C2021BD87B26810","87972FC55E2CDD2609ED85051BE50BAF","722");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    printf("Get pfile information ERROR!\n");
    return (-1);
  }
  pfile_pk  = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);

  /** insert upload a executable file */
   memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"INSERT INTO upload (upload_filename,upload_mode,upload_ts,pfile_fk) VALUES ('mimetype',40,now(),'%ld');", pfile_pk);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    exit(-1);
  }
  PQclear(result);
 
  /* select upload_pk */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"SELECT upload_pk FROM upload WHERE pfile_fk = '%ld';",
        pfile_pk);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    printf("Get pfile information ERROR!\n");
    return (-1);
  }
  upload_pk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);

  /** insert uploadtree */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL,MAXCMD,"INSERT INTO uploadtree (upload_fk,pfile_fk,lft,rgt,ufile_name) VALUES (%ld,%ld,1,48,'mimetype');", upload_pk, pfile_pk);
  result = PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);

  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"COMMIT;");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);
  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  /** clear all data in mimetype */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype;");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);

  Akey = pfile_pk;
  FMimetype = fopen("/etc/mime.types","rb");
  if (!FMimetype)
  {
    LOG_WARNING("Unable to open /etc/mime.types\n");
  }

  return 0;
}
/**
 * \brief clean the env
 */
int DBCheckMimeClean()
{
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"BEGIN;");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /** delete uploadtree info */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"DELETE FROM uploadtree WHERE upload_fk = %ld;", upload_pk);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /** delete upload info */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"DELETE FROM upload WHERE upload_pk = %ld;", upload_pk);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /** delete pfile info */
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"DELETE FROM pfile WHERE pfile_pk = %ld;", pfile_pk);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);


  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"COMMIT;");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /** delete the record the mimetype_name is application/octet-stream in mimetype, after testing */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype where mimetype_name = 'application/octet-stream';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);
  if (pgConn) PQfinish(pgConn);
  if (FMimetype) fclose(FMimetype);
  Akey = 0;
  return 0;
}

/* test functions */

/**
 * \brief for function DBCheckMime 
 * the file is a c source file  
 */
void testDBCheckMime()
{
  char SQL[MAXCMD] = {0};
  PGresult *result = NULL;
  char file_path[MAXCMD] = "../../agent/mimetype";
  char mimetype_name[] = "application/octet-stream";
  int pfile_mimetypefk = 0;
  int mimetype_id = -1;
  
  DBCheckMime(file_path);
  /** get　mimetype_pk from table mimetype */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL)-1,"SELECT mimetype_pk FROM mimetype WHERE mimetype_name= '%s';", mimetype_name);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  mimetype_id = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  
  /** get mimetype id from pfile */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL)-1,"SELECT pfile_mimetypefk FROM pfile WHERE pfile_pk= %ld;", pfile_pk);
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  pfile_mimetypefk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);

  CU_ASSERT_EQUAL(pfile_mimetypefk, mimetype_id);
  /** delete the record the mimetype_name is application/octet-stream in mimetype, after testing */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype where mimetype_name = 'text/x-csrc';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);
}

/**
 * \brief testcases for function DBCheckMime 
 */
CU_TestInfo testcases_DBCheckMime[] =
{
#if 0
#endif
{"DBCheckMime:C", testDBCheckMime},
  CU_TEST_INFO_NULL
};

