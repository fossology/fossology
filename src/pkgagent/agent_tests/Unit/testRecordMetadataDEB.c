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
 * \file testRecordMetadataDEB.c
 * \brief unit test for RecordMetadataDEB function
 */

/**
 * \brief Test pkgagent.c function RecordMetadataDEB()
 */
void test_RecordMetadataDEB()
{
  struct debpkginfo *pi;
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

  pi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));
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
  strncpy(pi->maintainer, "Test maintainer", sizeof(pi->maintainer));
  strncpy(pi->description, "Test description", sizeof(pi->description));
  strncpy(pi->section, "Test section", sizeof(pi->section));
  strncpy(pi->priority, "Test priority", sizeof(pi->priority));
  strncpy(pi->homepage, "Test homepage", sizeof(pi->homepage));
  strncpy(pi->source, "Test source", sizeof(pi->source));
  strncpy(pi->summary, "Test summary", sizeof(pi->summary));
  strncpy(pi->format, "Test format", sizeof(pi->format));
  strncpy(pi->uploaders, "Test uploaders", sizeof(pi->uploaders));
  strncpy(pi->standardsVersion, "Test standard", sizeof(pi->standardsVersion));
  pi->installedSize = 0;

  data_size = 2;
  pi->depends = calloc(data_size, sizeof(char *));
  for (j=0; j<data_size;j++){
    pi->depends[j] = malloc(MAXCMD);
    strcpy(pi->depends[j],"Test depends");
  }
  pi->dep_size = data_size;

  /* Test RecordMetadataRPM function */
  int Result = RecordMetadataDEB(pi);
  printf("RecordMetadataDEB Result is:%d\n", Result);

  /* Check data correction */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"SELECT pkg_pk, pkg_name, pkg_arch, version, maintainer, description FROM pkg_deb INNER JOIN pfile ON pfile_fk = '%ld' AND pfile_fk = pfile_pk;", pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
  {
    printf("Get pkg information ERROR!\n");
    exit(-1);
  }
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 1), "Test Pkg");
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 2), "Test Arch");
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 3), "Test version");
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 4), "Test maintainer");
  CU_ASSERT_STRING_EQUAL(PQgetvalue(result, 0, 5), "Test description");
  PQclear(result);


  /* Clear testing data in database */
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM pkg_deb_req WHERE pkg_fk IN (SELECT pkg_pk FROM pkg_deb WHERE pfile_fk = '%ld');", pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Clear pkg_deb_req test data ERROR!\n");
    exit(-1);
  }
  PQclear(result);
  memset(SQL,'\0',MAXSQL);
  snprintf(SQL,MAXSQL,"DELETE FROM pkg_deb WHERE pfile_fk = '%ld';", pi->pFileFk);
  result =  PQexec(db_conn, SQL);
  if (fo_checkPQcommand(db_conn, result, SQL, __FILE__ ,__LINE__))
  {
    printf("Clear pkg_deb test data ERROR!\n");
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
  for(k=0; k< pi->dep_size;k++)
  free(pi->depends[k]);
  free(pi->depends);
  memset(pi,0,sizeof(struct debpkginfo));
  free(pi);
  CU_ASSERT_EQUAL(Result, predictValue);
}

/**
 * \brief testcases for function RecordMetadataDEB
 */
CU_TestInfo testcases_RecordMetadataDEB[] = {
    {"Testing the function RecordMetadataDEB", test_RecordMetadataDEB},
    CU_TEST_INFO_NULL
};

