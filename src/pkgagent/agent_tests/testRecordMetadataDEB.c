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

/* Test pkgagent.c RecordMetadataDEB function */
extern int RecordMetadataDEB(struct debpkginfo *pi);

/**
 * \brief test_RecordMetadataDEB(struct debpkginfo *pi)
 *
 * Test pkgagent.c function RecordMetadataDEB()
 *
 */
void test_RecordMetadataDEB()
{
  struct debpkginfo *pi;
  int data_size, i, j;
  char SQL[MAXSQL];
  PGresult *result;
  char Fuid[1024];

  for(i=0; i<20; i++) { sprintf(Fuid+0+i*2,"%02X",'s'); }
  Fuid[40]='.';
  for(i=0; i<16; i++) { sprintf(Fuid+41+i*2,"%02X",'m'); }
  Fuid[73]='.';
  snprintf(Fuid+74,sizeof(Fuid)-74,"%Lu",(long long unsigned int)100);

  pi = (struct debpkginfo *)malloc(sizeof(struct debpkginfo));
  int predictValue = 0;

  /* perpare testing data in database */
  db_conn = fo_dbconnect();
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
  CU_ASSERT_EQUAL(Result, predictValue);
}

CU_TestInfo testcases_RecordMetadataDEB[] = {
    {"Testing the function RecordMetadataDEB", test_RecordMetadataDEB},
    CU_TEST_INFO_NULL
};

