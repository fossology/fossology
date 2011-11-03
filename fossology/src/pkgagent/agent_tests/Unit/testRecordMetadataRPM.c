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
#include "pkgagent.h"

#include <stdio.h>
#include "CUnit/CUnit.h"

#define MAXSQL  4096
extern char *DBConfFile;
/**
 * \file testRecordMetadataRPM.c
 * \brief unit test for RecordMetadataRPM function
 */

/**
 * \brief Test pkgagent.c function RecordMetadata()
 */
void test_RecordMetadataRPM()
{
  struct rpmpkginfo *pi;
  int data_size, i, j;
  char SQL[MAXSQL];
  PGresult *result;
  char Fuid[1024];
  //char *DBConfFile = NULL;  /* use default Db.conf */
  char *ErrorBuf;

  for(i=0; i<20; i++) { sprintf(Fuid+0+i*2,"%02X",'s'); }
  Fuid[40]='.';
  for(i=0; i<16; i++) { sprintf(Fuid+41+i*2,"%02X",'m'); }
  Fuid[73]='.';
  snprintf(Fuid+74,sizeof(Fuid)-74,"%Lu",(long long unsigned int)100);

  pi = (struct rpmpkginfo *)malloc(sizeof(struct rpmpkginfo));
  int predictValue = 0;

  /* perpare testing data in database */
  db_conn = fo_dbconnect(DBConfFile, &ErrorBuf);
  snprintf(SQL,MAXSQL,"INSERT INTO pfile (pfile_sha1,pfile_md5,pfile_size) VALUES ('%.40s','%.32s','%s');",
          Fuid,Fuid+41,Fuid+74);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Perpare pfile information ERROR!\n");
    exit(-1);
  }
  PQclear(result);
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"SELECT pfile_pk FROM pfile WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = '%s';",
        Fuid,Fuid+41,Fuid+74);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    printf("Get pfile information ERROR!\n");
    exit(-1); 
  }
  pi->pFileFk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);
  strncpy(pi->pkgName, "Test Pkg", sizeof(pi->pkgName));
  strncpy(pi->pkgArch, "Test Arch", sizeof(pi->pkgArch));
  strncpy(pi->version, "Test version", sizeof(pi->version));
  strncpy(pi->license, "Test license", sizeof(pi->license));
  strncpy(pi->packager, "Test packager", sizeof(pi->packager));
  strncpy(pi->release, "Test release", sizeof(pi->release));
  strncpy(pi->buildDate, "Test buildDate", sizeof(pi->buildDate));
  strncpy(pi->vendor, "Test vendor", sizeof(pi->vendor));
  strncpy(pi->pkgAlias, "Test Alias", sizeof(pi->pkgAlias));
  strncpy(pi->rpmFilename, "Test rpmfile", sizeof(pi->rpmFilename));
  strncpy(pi->group, "Test group", sizeof(pi->group));
  strncpy(pi->url, "Test url", sizeof(pi->url));
  strncpy(pi->sourceRPM, "Test sourceRPM", sizeof(pi->sourceRPM));
  strncpy(pi->summary, "Test summary", sizeof(pi->summary));
  strncpy(pi->description, "Test description", sizeof(pi->description));

  data_size = 2;
  pi->requires = calloc(data_size, sizeof(char *));
  for (j=0; j<data_size;j++){
    pi->requires[j] = malloc(MAXCMD);
    strcpy(pi->requires[j],"Test requires");
  }
  pi->req_size = data_size;

  /* Test RecordMetadataRPM function */
  int Result = RecordMetadataRPM(pi);
  printf("RecordMetadataRPM Result is:%d\n", Result);

  /* Check data correction */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"SELECT pkg_pk, pkg_name, pkg_arch, version, license, packager, release, vendor FROM pkg_rpm INNER JOIN pfile ON pfile_fk = '%ld' AND pfile_fk = pfile_pk;", pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    printf("Get pkg information ERROR!\n");
    exit(-1);
  }
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 1), "Test Pkg");
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 2), "Test Arch");
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 3), "Test version");
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 4), "Test license");
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 5), "Test packager");
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 6), "Test release");
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 7), "Test vendor");
  PQclear(result);

  /* Clear testing data in database */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM pkg_rpm_req WHERE pkg_fk IN (SELECT pkg_pk FROM pkg_rpm WHERE pfile_fk = '%ld');", pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Clear pkg_rpm_req test data ERROR!\n");
    exit(-1);
  }
  PQclear(result);
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM pkg_rpm WHERE pfile_fk = '%ld';", pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Clear pkg_rpm test data ERROR!\n");
    exit(-1);
  }
  PQclear(result);
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM pfile WHERE pfile_pk = '%ld'", pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Clear pfile test data ERROR!\n");
    exit(-1);
  }
  PQclear(result);

  PQfinish(db_conn);
  int k;
  for(k=0; k< pi->req_size;k++)
    free(pi->requires[k]);
  free(pi->requires);
  memset(pi, 0, sizeof(struct rpmpkginfo));
  free(pi);
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief testcases for function RecordMetadataRPM
 */
CU_TestInfo testcases_RecordMetadataRPM[] = {
    {"Testing the function RecordMetadataRPM", test_RecordMetadataRPM},
    CU_TEST_INFO_NULL
};

