// SPDX-License-Identifier: GPL-2.0-only
/*!
 * main.c
 *
 * The SCANOSS Agent for Fossology tool
 *
 * Copyright (C) 2018-2022 SCANOSS.COM
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
#include "snippet_scan.h"
#include "string.h"

#include <stdio.h>
#include <sys/stat.h>
#include <unistd.h>
#include <json-c/json.h>

#ifdef COMMIT_HASH_S
char BuildVersion[] = "scanoss build version: " VERSION_S " r(" COMMIT_HASH_S ").\n";
#else
char BuildVersion[] = "scanoss build version: NULL.\n";
#endif
char *baseTMP = "/tmp/scanoss";
int Agent_pk;
char ApiUrl[400];
char accToken[100];
extern PGconn *db_conn;
void *pgConn = NULL;
//#define _SCANOSS_LOGME
extern void ParseResults(char *folder);
extern int ScanFolder(char *folder);
extern int RebuildUpload(long upload_pk,char *tempFolder);
void logme(char *msg)
{
  #ifdef _SCANOSS_LOGME
  FILE *fptr;

  // use appropriate location if you are using MacOS or Linux
  fptr = fopen("/home/fossy/snippet_scan.txt", "a");

  if (fptr == NULL)
  {
    printf("Error!");
    exit(1);
  }
  fprintf(fptr, "->%s", msg);
  fclose(fptr);
  #endif
}
void loadAgentConfiguration(PGconn *pg_conn)
{
  PGresult *result;
  char sqlA[] = "select conf_value from sysconfig where variablename='ScAPIURL';";

  result = PQexec(pg_conn, sqlA);
  // check if ApiUrl exists
  if (fo_checkPQresult(pg_conn, result, sqlA, __FILE__, __LINE__)) {
   sprintf(ApiUrl, "%s", "");
  } else {
    if(PQgetisnull(result,0,0)){
      char sqlHost[]="INSERT INTO sysconfig (variablename, conf_value, ui_label, vartype, group_name, group_order, description, validation_function, option_value) \
      VALUES('ScAPIURL', '', 'SCANOSS API URL', 2, 'SCANOSS', 1, '(leave blank for default https://osskb.org/api/scan/direct))', NULL, NULL);";
      result = PQexec(pg_conn, sqlHost);
      if (fo_checkPQcommand(pg_conn, result, sqlHost, __FILE__, __LINE__)) {
        LOG_ERROR("Can't default ScAPIURL") ;
      }
      sprintf(ApiUrl, "%s", "");
    }  else {
      sprintf(ApiUrl, "%s", PQgetvalue(result, 0, 0));
    }
  }


  char sqlB[] = "select conf_value from sysconfig where variablename='ScToken';";

  result = PQexec(pg_conn, sqlB);
  if (fo_checkPQresult(pg_conn, result, sqlB, __FILE__, __LINE__))
  {
    memset(accToken,'\0',100);

  } else {
    if(PQgetisnull(result,0,0)){
      char sqlToken[]="INSERT INTO sysconfig ( variablename, conf_value, ui_label, vartype, group_name, group_order, description, validation_function, option_value) \
        VALUES( 'ScToken', '', 'SCANOSS access token', 2, 'SCANOSS', 2, 'Set token to access full scanning service.', NULL, NULL);";
      result = PQexec(pg_conn, sqlToken);
      if (fo_checkPQcommand(pg_conn, result, sqlToken, __FILE__, __LINE__)) {
         LOG_ERROR("Can't store default ScToken") ;
      }
      printf(accToken, "%s", "");
    }  else {
    sprintf(accToken, "%s", PQgetvalue(result, 0, 0));
   }
  }

}



int createTables(PGconn* pgConn)
{
  char sql[8192];
  PGresult* result;

  if (!fo_tableExists(pgConn, "scanoss_fileinfo")) {

    snprintf(sql, sizeof(sql), "\
          CREATE TABLE scanoss_fileinfo (\
	          pfile_fk int4 NOT NULL,\
            matchtype text NULL,\
            lineranges text NULL,\
            purl varchar NULL,\
            url varchar NULL,\
            filepath varchar NULL,\
            fileinfo_pk serial4 NOT NULL\
          );");

    result = PQexec(pgConn, sql);
    if (fo_checkPQcommand(pgConn, result, sql, __FILE__, __LINE__)) {

    // Can 't create table scanoss_fileinfo
  }
  }
return 0;

}



/*!
 * \brief main function for the scanoss
 * \param argc the number of command line arguments
 * \param argv the command line arguments
 * \return 0 on a successful program execution
 */
int main(int argc, char *argv[])
{
  int c;
  char *agent_desc = "scanoss";

  int ars_pk = 0;

  int upload_pk = 0; /* the upload primary key */
  int user_pk = 0;   // the user  primary key
  char *AgentARSName = "scanoss_ars";
  int rv;
  char sqlbuf[1024];
  char *COMMIT_HASH;
  char *VERSION;
  char agent_rev[MAXCMD];
  int CmdlineFlag = 0; /* run from command line flag, 1 yes, 0 not */

  fo_scheduler_connect(&argc, argv, &db_conn);

  COMMIT_HASH = fo_sysconfig("scanoss", "COMMIT_HASH");
  VERSION = fo_sysconfig("scanoss", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, COMMIT_HASH);

  Agent_pk = fo_GetAgentKey(db_conn, basename(argv[0]), 0, agent_rev, agent_desc);
  createTables(db_conn);
  loadAgentConfiguration(db_conn);
  mkdir(baseTMP, 0700);

  /* Process command-line */
  char filename[200];

  while ((c = getopt(argc, argv, "ic:CvVh")) != -1)
  {
    switch (c)
    {
    case 'i':
      PQfinish(db_conn); /* DB was opened above, now close it and exit */
      exit(0);
    case 'v':
      break;
    case 'c':
      break; /* handled by fo_scheduler_connect() */
    case 'C':
      CmdlineFlag = 1;
      strcpy(filename, argv[2]);
      break;
    case 'V':
      printf("%s", BuildVersion);
      PQfinish(db_conn);
      return (0);
    default:
      Usage(argv[0]);
      PQfinish(db_conn);
      exit(-1);
    }
  }

  if (CmdlineFlag == 0)  /* If no args, run from scheduler! */
  {
    user_pk = fo_scheduler_userID(); /* get user_pk for user who queued the agent */
    while (fo_scheduler_next())
    {
      upload_pk = atoi(fo_scheduler_current());
      if (GetUploadPerm(db_conn, upload_pk, user_pk) < PERM_WRITE)  /* Check Permissions */
      {
        LOG_ERROR("You have no update permissions on upload %d", upload_pk);
        continue;
      }
      rv = fo_tableExists(db_conn, AgentARSName);
      if (!rv)
      {
        rv = fo_CreateARSTable(db_conn, AgentARSName);
        if (!rv)
          return (0);
      }
      memset(sqlbuf, 0, sizeof(sqlbuf));
      snprintf(sqlbuf, sizeof(sqlbuf),
               "select ars_pk from scanoss_ars,agent \
               where agent_pk=agent_fk and ars_success=true \
               and upload_fk='%d' and agent_fk='%d'",
               upload_pk, Agent_pk);

      ars_pk = fo_WriteARS(db_conn, ars_pk, upload_pk, Agent_pk, AgentARSName, 0, 0);
      if (upload_pk == 0)
        continue;
      char  tempFolder[512];
      sprintf(tempFolder,"%s/%d",baseTMP,upload_pk);
      mkdir(tempFolder, 0700);
      if (RebuildUpload(upload_pk,tempFolder) != 0) /* process the upload_pk code */{
          LOG_ERROR("Error processing upload\n");
      } else {
        char *proxy_url;
        proxy_url=NULL;
        proxy_url= fo_config_get(sysconfig, "FOSSOLOGY", "https_proxy", NULL); 
        if (proxy_url==NULL) {
            proxy_url= fo_config_get(sysconfig, "FOSSOLOGY", "http_proxy", NULL); 
        }
        
        if (proxy_url!=NULL) { 
          if (setenv("https_proxy", proxy_url, 1) != 0) {
            perror("Error setting https_proxy env-var");
            return 1;
          }
          LOG_NOTICE("Using proxy configuration:%s\n", proxy_url)
        }
        ScanFolder(tempFolder);
        ParseResults(tempFolder);
        char cmdRemove[600];
        memset(cmdRemove,0,600);
        sprintf(cmdRemove,"rm  -r %s",tempFolder);
        FILE *removes = popen(cmdRemove, "r");  /* Run the command */
        pclose(removes);
      }
      ars_pk = fo_WriteARS(db_conn, ars_pk, upload_pk, Agent_pk, AgentARSName, NULL, 1);
    }
  }
  else
  {
    /* Run the scanner from command line */
    char Cmd[MAXCMD];
    char outputFile[MAXCMD];
    unsigned char apiurl[410];
    unsigned char key[110];

    if(ApiUrl[0] != '\0') {
      sprintf((char *) apiurl,"--apiurl %s", ApiUrl);
    }
    else {
      memset(apiurl, 0, sizeof(apiurl));
    }

    if(accToken[0]!='\0' && accToken[0]!=' ')  {
      sprintf((char *)key,"--key %s", accToken);
    }
    else {
      memset(key, 0, sizeof(key));
    }

    char tempFolder[512];
    sprintf(tempFolder, "%s/%ld", baseTMP, time(NULL));
    mkdir(tempFolder, 0700);
    sprintf(outputFile, "%s/result.json", tempFolder);

    sprintf(Cmd, "PYTHONPATH='/home/%s/pythondeps/' /home/%s/pythondeps/bin/scanoss-py "
                 "scan %s %s -o %s %s", FO_USER_S, FO_USER_S, apiurl, key,
        outputFile, filename); /* Create the command to run */
    FILE *Fin = popen(Cmd, "r");  /* Run the command */
    if (!Fin) {
      LOG_ERROR("Snippet scan: failed to start scan %s", strerror(errno));
      pclose(Fin);
      return -1;
    }
    pclose(Fin);

    struct json_object *result_json = json_object_from_file(outputFile);
    if (result_json == NULL) {
#if JSON_C_MINOR_VERSION > 12
      LOG_ERROR("Unable to parse json output: %s", json_util_get_last_err());
#else
      LOG_ERROR("Unable to parse json output.");
#endif
      return -1;
    }
    sprintf(Cmd, "rm -rf %s", tempFolder);
    system(Cmd);
    json_object_object_foreach(result_json, obj_filename, obj_val)
    {
      for (int i = 0; i < json_object_array_length(obj_val); ++i) {
        struct json_object *inner_obj = json_object_array_get_idx(obj_val, i);
        struct json_object *licenses_array = json_object_object_get(inner_obj, "licenses");
        if (licenses_array == NULL) {
          continue;
        }
        for (int j = 0; j < json_object_array_length(licenses_array); ++j) {
          struct json_object *license_obj = json_object_array_get_idx(licenses_array, j);
          printf("%s -> %s\n", obj_filename,
              json_object_get_string(json_object_object_get(license_obj, "name")));
        }
        const char *matched = json_object_get_string(
            json_object_object_get(inner_obj, "matched"));
        printf("%s matched with purls: ", matched);
        struct json_object *purl_array = json_object_object_get(inner_obj, "purl");
        for (int j = 0; j < json_object_array_length(purl_array); ++j) {
          printf("%s,", json_object_get_string(
              json_object_array_get_idx(purl_array, j)));
        }
        printf("\n");
      }
    }
  }
  PQfinish(db_conn);
  fo_scheduler_disconnect(0);
  return (0);
} /* main() */
