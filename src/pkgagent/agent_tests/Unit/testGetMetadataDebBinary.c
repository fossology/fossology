/*********************************************************************
Copyright (C) 2011-2014 Hewlett-Packard Development Company, L.P.

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
#include "pkgagent.h"

#include <stdio.h>
#include "CUnit/CUnit.h"

#define MAXSQL  4096
extern char *DBConfFile;
/**
 * \file testGetMetadataDebBinary.c
 * \brief unit test for GetMetadataDebBinary function
 */

/**
 * \brief Prepare database
 *
 * \param db_conn the database connection
 * \param pi the pointer of debpkginfo
 *
 * \return upload_pk on OK, -1 on failure
 */
long prepare_Database(PGconn *db_conn, struct debpkginfo *pi)
{
  long upload_pk;
  char SQL[MAXSQL];
  PGresult *result;
  long control_pfilepk;

  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"BEGIN;");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /* insert mimetype */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"INSERT INTO mimetype (mimetype_name) VALUES ('application/x-rpm');");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare mimetype information ERROR!\n");
    return (-1);
  }
  PQclear(result);
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"INSERT INTO mimetype (mimetype_name) VALUES ('application/x-debian-package');");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare mimetype information ERROR!\n");
    return (-1);
  }
  PQclear(result);
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"INSERT INTO mimetype (mimetype_name) VALUES ('application/x-debian-source');");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare mimetype information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /* insert pfile: fossology-web_1.4.1_all.deb */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"INSERT INTO pfile (pfile_sha1,pfile_md5,pfile_size) VALUES ('%.40s','%.32s','%s');",
          "AF1DF2C4B32E4115DB5F272D9EFD0E674CF2A0BC","2239AA7DAC291B6F8D0A56396B1B8530","4560");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);
  /* insert pfile: control */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"INSERT INTO pfile (pfile_sha1,pfile_md5,pfile_size) VALUES ('%.40s','%.32s','%s');",
          "F1D2319DF20ABC4CEB02CA5A3C2021BD87B26810","87972FC55E2CDD2609ED85051BE50BAF","722");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  /* select pfile_pk: fossology-web_1.4.1_all.deb */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"SELECT pfile_pk FROM pfile WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = '%s';",
        "AF1DF2C4B32E4115DB5F272D9EFD0E674CF2A0BC","2239AA7DAC291B6F8D0A56396B1B8530","4560");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    printf("Get pfile information ERROR!\n");
    return (-1);
  }
  pi->pFileFk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);

  /* select pfile_pk: control */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"SELECT pfile_pk FROM pfile WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = '%s';",
        "F1D2319DF20ABC4CEB02CA5A3C2021BD87B26810","87972FC55E2CDD2609ED85051BE50BAF","722");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    printf("Get pfile information ERROR!\n");
    return (-1);
  }
  control_pfilepk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);

  /* insert upload: fossology-web_1.4.1_all.deb */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"INSERT INTO upload (upload_filename,upload_mode,upload_ts,pfile_fk) VALUES ('%s',40,now(),%ld);",
          "fossology-web_1.4.1_all.deb", pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    exit(-1);
  }
  PQclear(result);
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"SELECT upload_pk FROM upload WHERE pfile_fk = '%ld';",
        pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    printf("Get pfile information ERROR!\n");
    return (-1);
  }
  upload_pk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);

  /* insert uploadtree_a: fossology-web_1.4.1_all.deb */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"INSERT INTO uploadtree_a (upload_fk,pfile_fk,lft,rgt,ufile_name) VALUES (%ld,%ld,1,48,'fossology-web_1.4.1_all.deb');",
          upload_pk, pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);
  /* insert uploadtree_a: control */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"INSERT INTO uploadtree_a (upload_fk,pfile_fk,lft,rgt,ufile_name) VALUES (%ld,%ld,9,10,'control');",
          upload_pk, control_pfilepk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"COMMIT;");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  return upload_pk;
}

/**
 * \brief Prepare repository
 *
 * \return 0 on OK, -1 on failure
 */
int prepare_Repository()
{
  char *Source = "../testdata/control";
  char *Pfile = "F1D2319DF20ABC4CEB02CA5A3C2021BD87B26810.87972FC55E2CDD2609ED85051BE50BAF.722";
  if (!fo_RepExist("files",Pfile))
  {
    if (fo_RepImport(Source, "files", Pfile, 1) != 0)
    {
      printf("Failed to import %s\n", Source);
      return (-1);
    }  
  }
  return (0);
}

/**
 * \brief remove database
 *
 * \param db_conn the database connection
 * \param pi the pointer of debpkginfo
 * \param upload_pk
 * 
 * \return 0 on OK, -1 on failure
 */
int remove_Database(PGconn *db_conn, struct debpkginfo *pi, long upload_pk)
{
  char SQL[MAXSQL];
  PGresult *result;

  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"BEGIN;");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM mimetype;");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove mimetype information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM uploadtree_a WHERE upload_fk = %ld;", upload_pk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM upload WHERE upload_pk = %ld;", upload_pk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM pfile WHERE pfile_pk = %ld;", pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM pfile WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = '%s';", "F1D2319DF20ABC4CEB02CA5A3C2021BD87B26810","87972FC55E2CDD2609ED85051BE50BAF","722");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Remove pfile database 'control' ERROR!\n");
    return (-1);
  }
  PQclear(result);

  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"COMMIT;");
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    return (-1);
  }
  PQclear(result);

  return (0);  
}

/**
 * \brief remove repository
 *
 * \return 0 on OK, -1 on failure
 */
int remove_Repository()
{
  char *Pfile = "F1D2319DF20ABC4CEB02CA5A3C2021BD87B26810.87972FC55E2CDD2609ED85051BE50BAF.722";
  if (fo_RepExist("files",Pfile))
  {
    if (fo_RepRemove("files", Pfile) != 0)
    {
      printf("Failed to remove %s\n", Pfile);
      return (-1);
    }
  }
  return (0);
}

/**
 * \brief Test pkgagent.c GetMetadataDebBinary function 
 * get debian binary package info
 */
void test_GetMetadataDebBinary()
{
  struct debpkginfo *pi;
  long upload_pk;
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  pi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));
  int predictValue = 0;

  /* perpare testing data in database */
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  
  upload_pk = prepare_Database(db_conn, pi);
  if (upload_pk == -1)
    CU_FAIL_FATAL("Prepare database data ERROR!");
    
  if (prepare_Repository() == -1)
  {
    remove_Database(db_conn, pi, upload_pk);
    CU_FAIL_FATAL("Prepare repository data ERROR!");
  }
     
  /* Test GetMetadataDebBinary function */
  int Result = GetMetadataDebBinary(upload_pk, pi);
  //printf("GetMetadataDebBinary Result is:%d\n", Result);

  /* Check data correction */
  CU_ASSERT_STRING_EQUAL(pi->pkgName, "fossology-web");
  CU_ASSERT_STRING_EQUAL(pi->pkgArch, "all");
  CU_ASSERT_STRING_EQUAL(pi->version, "1.4.1");
  CU_ASSERT_STRING_EQUAL(pi->section, "utils");
  CU_ASSERT_STRING_EQUAL(pi->priority, "extra");
  CU_ASSERT_STRING_EQUAL(pi->maintainer, "Matt Taggart <taggart@debian.org>");
  CU_ASSERT_STRING_EQUAL(pi->homepage, "http://fossology.org");
  CU_ASSERT_EQUAL(pi->dep_size, 3);

  CU_ASSERT_EQUAL(Result, predictValue);

  /* Clear testing data in database */
  if (remove_Database(db_conn, pi, upload_pk) == -1)
    CU_FAIL_FATAL("Remove database data ERROR!");
  if (remove_Repository() == -1)
    CU_FAIL_FATAL("Remove repository data ERROR!");

  PQfinish(db_conn);
  int i;
  for(i=0; i< pi->dep_size;i++)
    free(pi->depends[i]);
  free(pi->depends);
  memset(pi, 0, sizeof(struct debpkginfo));
  free(pi);
}

/**
 * \brief Test pkgagent.c GetMetadataDebBinary function 
 * with no upload_pk in database
 */
void test_GetMetadataDebBinary_no_uploadpk()
{
  struct debpkginfo *pi;
  long upload_pk = 0;
  char *ErrorBuf;  
  
  pi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));
  int predictValue = -1;
  
  /* perpare testing data in database */
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf); 
 
  /* Test GetMetadataDebBinary function */
  int Result = GetMetadataDebBinary(upload_pk, pi);
  CU_ASSERT_EQUAL(Result, predictValue);

  PQfinish(db_conn);
  int i;
  for(i=0; i< pi->dep_size;i++)
    free(pi->depends[i]);
  free(pi->depends);
  memset(pi, 0, sizeof(struct debpkginfo));
  free(pi);
}

/**
 * \brief Test pkgagent.c ProcessUpload function
 * give the upload_pk of debian binary package, 
 * get the package information about this upload id
 */
void test_ProcessUpload()
{
  struct debpkginfo *pi;
  long upload_pk;
  char *ErrorBuf;

  int predictValue = 0;
  pi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));
 
  /* perpare testing data in database */
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);

  upload_pk = prepare_Database(db_conn, pi);
  if (upload_pk == -1)
    CU_FAIL_FATAL("Prepare database data ERROR!");

  if (prepare_Repository() == -1)
  {
    remove_Database(db_conn, pi, upload_pk);
    CU_FAIL_FATAL("Prepare repository data ERROR!");
  }
  
  /* Test ProcessUpload function */
  int Result = ProcessUpload(upload_pk);
  printf("ProcessUpload Result is:%d\n", Result);
 
  CU_ASSERT_EQUAL(Result, predictValue);

  /* Clear testing data in database */
  if (remove_Database(db_conn, pi, upload_pk) == -1)
    CU_FAIL_FATAL("Remove database data ERROR!");
  if (remove_Repository() == -1)
    CU_FAIL_FATAL("Remove repository data ERROR!");

  PQfinish(db_conn); 
  memset(pi, 0, sizeof(struct debpkginfo));
  free(pi);
}
/**
 * \brief testcases for function GetMetadataDebBinary and ProcessUpload
 */
CU_TestInfo testcases_GetMetadataDebBinary[] = {
    {"Testing the function GetMetadataDebBinary", test_GetMetadataDebBinary},
    {"Testing the function GetMetadataDebBinary with no uploadpk", test_GetMetadataDebBinary_no_uploadpk},
    {"Testing the function ProcessUpload for debian binary package", test_ProcessUpload},
    CU_TEST_INFO_NULL
};

