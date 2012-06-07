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
 * \file testOtherFunctions.c
 * \brief testing for the function CheckMimeTypes, GetDefaultMime, GetFieldValue 
 */

extern int CheckMimeTypes  (char *Ext);
extern int DBCheckFileExtention();
extern char * GetFieldValue (char *Sin, char *Field, int FieldMax,
      char *Value, int ValueMax);
extern char *DBConfFile;

static PGresult *result = NULL;
static long upload_pk = -1;
static long pfile_pk = -1;

/**
 * \brief initialize
 */
int  DBInit()
{
  char *ErrorBuf;

  pgConn = fo_dbconnect(DBConfFile, &ErrorBuf);
  if (!pgConn)
  {
    LOG_FATAL("Unable to connect to database");
    exit(-1);
  }
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

  /** insert upload ununpack.c */
   memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD,"INSERT INTO upload (upload_filename,upload_mode,upload_ts,pfile_fk) VALUES ('ununpack.c',40,now(),'%ld');", pfile_pk);
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
  snprintf(SQL,MAXCMD,"INSERT INTO uploadtree (upload_fk,pfile_fk,lft,rgt,ufile_name) VALUES (%ld,%ld,1,48,'ununpack.c');", upload_pk, pfile_pk);
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
int DBClean()
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
  if (pgConn) PQfinish(pgConn);
  if (FMimetype) fclose(FMimetype);
  Akey = 0;
  return 0;
}


/* test functions */

/**
 * \brief for function CheckMimeTypes
 */
void testCheckMimeTypes()
{
  /** for the file, if the extension is bin, the mime type is application/octet-stream */
  char Ext[] = "bin";
  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  /** extension is bin */
  /** delete the record the mimetype_name is application/octet-stream in mimetype, before testing */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype where mimetype_name = 'application/octet-stream';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);
  /** testing the function CheckMimeTypes */
  int ret = CheckMimeTypes(Ext);
  /** justify if the record  mimetype_name is application/octet-stream is in mimetype */ 
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "SELECT mimetype_pk from mimetype where mimetype_name = 'application/octet-stream';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  int mimetype_pk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  CU_ASSERT_EQUAL(ret, mimetype_pk);
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
#if 0
#endif
  /** mimetype_name is spec */
  /** for the file, if the extension is spec, the mime type is application/x-rpm-spec */
  char Ext2[] = "spec";
  /** delete the record the mimetype_name is application/x-rpm-spec in mimetype, before testing */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype where mimetype_name = 'application/x-rpm-spec';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);
  /** testing the function CheckMimeTypes */
  ret = CheckMimeTypes(Ext2);
  /** justify if the record  mimetype_name is application/x-rpm-spec is in mimetype */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "SELECT mimetype_pk from mimetype where mimetype_name = 'application/x-rpm-spec';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  mimetype_pk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  CU_ASSERT_EQUAL(ret, mimetype_pk);
  /** delete the record the mimetype_name is application/x-rpm-spec in mimetype, after testing */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype where mimetype_name = 'application/x-rpm-spec';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);
}

/**
 * \brief for function DBCheckFileExtention 
 */
void testDBCheckFileExtention()
{
  /** delete the record the mimetype_name is text/x-csrc in mimetype, before testing */
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL, MAXCMD, "DELETE FROM mimetype where mimetype_name = 'text/x-csrc';");
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);

  int mimetype_id =  DBCheckFileExtention();
  memset(SQL, '\0', MAXCMD);
  snprintf(SQL,sizeof(SQL)-1,"SELECT mimetype_pk FROM mimetype where mimetype_name = 'text/x-csrc';" );
  result =  PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  int mimetype_pk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  CU_ASSERT_EQUAL(mimetype_id, mimetype_pk);
  /** delete the record the mimetype_name is text/x-csrc in mimetype, after testing */
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
 * \brief for function GetFieldValue
 */
void testGetFieldValue()
{
  char Sin[] = "akey='20' pfile_fk='37331'" ;
  char Field[256] = {0};
  char Value[1024] = {0};
  GetFieldValue(Sin, Field, 256, Value, 1024);
  CU_ASSERT_STRING_EQUAL(Field, "akey");
  CU_ASSERT_STRING_EQUAL(Value, "20");
}


/**
 * \brief testcases for function CheckMimeTypes
 */
CU_TestInfo testcases_CheckMimeTypes[] =
{
#if 0
#endif
{"CheckMimeTypes:C", testCheckMimeTypes},
  CU_TEST_INFO_NULL
};

/**
 * \brief testcases for function DBCheckFileExtention 
 */
CU_TestInfo testcases_DBCheckFileExtention[] =
{
#if 0
#endif
{"DBCheckFileExtention:C", testDBCheckFileExtention},
  CU_TEST_INFO_NULL
};

/**
 * \brief testcases for function GetFieldValue
 */
CU_TestInfo testcases_Utilities[] =
{
#if 0
#endif
{"GetFieldValue:Exist", testGetFieldValue},
  CU_TEST_INFO_NULL
};

