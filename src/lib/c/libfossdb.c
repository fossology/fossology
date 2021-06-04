/**************************************************************
Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License version 2.1 as published by the Free Software Foundation.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this library; if not, write to the Free Software Foundation, Inc.0
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
**************************************************************/

/*!
 \file
 \brief Common libpq database functions.
 */

#define ERRBUFSIZE 11264

#include "libfossdb.h"

#include <string.h>
#include <json-c/json.h>
#include <curl/curl.h>

struct MemoryStruct
{
  char *memory;
  size_t size;
};

/**
 * @brief Callback function to get chuncks of the response and attachet to a memory location.
 *
 * @param contents  
 * @param size  
 * @param nmemb  
 * @param userp  
 * 
 */

static size_t
WriteMemoryCallback(void *contents, size_t size, size_t nmemb, void *userp)
{
  size_t realsize = size * nmemb;
  struct MemoryStruct *mem = (struct MemoryStruct *)userp;

  char *ptr = realloc(mem->memory, mem->size + realsize + 1);
  if (!ptr)
  {
    /* out of memory! */
    printf("not enough memory (realloc returned NULL)\n");
    return 0;
  }

  mem->memory = ptr;
  memcpy(&(mem->memory[mem->size]), contents, realsize);
  mem->size += realsize;
  mem->memory[mem->size] = 0;

  return realsize;
}

/*!
 \brief Connect to a database. The default is Db.conf.

 \param DBConfFile File path of the Db.conf file to use.  If NULL, use the default Db.conf
 \param ErrorBuf   Address of pointer to error buffer.  fo_dbconnect will allocate this
                   if needed.  The caller should free it.

 \return PGconn*, or NULL on failure to process the config file.  If NULL, ErrorBuff will 
         contain the error message.  If NULL is returned and ErrorBuf is NULL, then 
         there was insufficient memory to allocate ErrorBuf.
****************************************************/
PGconn* fo_dbconnect(char* DBConfFile, char** ErrorBuf)
{
  PGconn* pgConn;
  char CMD[10240];

  CURL *curl_handle;
  CURLcode res;

  struct MemoryStruct chunk;

  chunk.memory = malloc(1); /* will be grown as needed by the realloc above */
  chunk.size = 0;           /* no data at this point */

  curl_global_init(CURL_GLOBAL_ALL);

  /* init the curl session */
  curl_handle = curl_easy_init();

  /* specify URL to get */
  curl_easy_setopt(curl_handle, CURLOPT_URL, "http://192.168.49.2:30079/v2/keys/db");

  /* send all data to this function  */
  curl_easy_setopt(curl_handle, CURLOPT_WRITEFUNCTION, WriteMemoryCallback);

  /* we pass our 'chunk' struct to the callback function */
  curl_easy_setopt(curl_handle, CURLOPT_WRITEDATA, (void *)&chunk);

  /* some servers don't like requests that are made without a user-agent
     field, so we provide one */
  curl_easy_setopt(curl_handle, CURLOPT_USERAGENT, "libcurl-agent/1.0");

  /* get it! */
  res = curl_easy_perform(curl_handle);

  /* check for errors */
  if (res != CURLE_OK)
  {
    fprintf(stderr, "curl_easy_perform() failed: %s\n",
            curl_easy_strerror(res));
  }
  else
  {
    /*
     * Now, our chunk.memory points to a memory block that is chunk.size
     * bytes big and contains the remote file.
     */
    printf("%s \n", chunk.memory);
    struct json_object *parsed_json, *action, *key, *node_db, *db_string;

    parsed_json = json_tokener_parse(chunk.memory);

    json_object_object_get_ex(parsed_json, "action", &action);
    json_object_object_get_ex(parsed_json, "node", &node_db);

    parsed_json = node_db;

    json_object_object_get_ex(parsed_json, "key", &key);
    json_object_object_get_ex(parsed_json, "value", &db_string);

    strcpy(CMD, json_object_get_string(db_string));
    /* Perform the connection */
    pgConn = PQconnectdb(CMD);
    if (PQstatus(pgConn) != CONNECTION_OK)
    {
      *ErrorBuf = malloc(ERRBUFSIZE);
      if (*ErrorBuf)
      {
        int i = 0;
        const char pass[10]= "password=";
        for(i = strstr(CMD,pass) - CMD + strlen(pass); i < strlen(CMD); i++){
          if(CMD[i] == ' '){
            break;
          }
          CMD[i] ='*';
        }
        snprintf(*ErrorBuf, ERRBUFSIZE,
          "ERROR: Unable to connect to the database\n   Connection string: '%s'\n   Connection status: '%d'\n", json_object_get_string(db_string), PQstatus(pgConn));
      }
      return (NULL);
    }
    return (NULL);
  }
} /* fo_dbconnect() */


/*!
 \brief Check the result status of a postgres SELECT.

 If an error occured, write the error to stdout

 \param pgConn  Database connection object
 \param result  Postgres result object
 \param sql     the sql query
 \param FileID is a file identifier string to write into
                      the error message.  Typically the caller
                      will use __FILE__, but any identifier string
                      is ok.
 \param LineNumb  the line number of the caller (__LINE__)

 \return 0 on OK, -1 on failure.  On failure, result will be freed.
****************************************************/
int fo_checkPQresult(PGconn* pgConn, PGresult* result, char* sql, char* FileID, int LineNumb)
{
  if (!result)
  {
    printf("FATAL: %s:%d, %s\nOn: %s\n",
      FileID, LineNumb, PQerrorMessage(pgConn), sql);
    return -1;
  }

  /* If no error, return */
  if (PQresultStatus(result) == PGRES_TUPLES_OK) return 0;

  printf("ERROR: %s:%d, %s\nOn: %s\n",
    FileID, LineNumb, PQresultErrorMessage(result), sql);
  PQclear(result);
  return (-1);
} /* fo_checkPQresult */


/*!
 @brief Check the result status of a postgres commands (not select)
        If an error occured, write the error to stdout

 @param pgConn  Database connection object
 @param result  Postgres result object
 @param sql the sql query
 @param FileID is a file identifier string to write into
                      the error message.  Typically the caller
                      will use __FILE__, but any identifier string
                      is ok.
 @param LineNumb  the line number of the caller (__LINE__)

 @return 0 on OK, -1 on failure.  On failure, result will be freed.
****************************************************/
int fo_checkPQcommand(PGconn* pgConn, PGresult* result, char* sql, char* FileID, int LineNumb)
{
  if (!result)
  {
    printf("FATAL: %s:%d, %sOn: %s\n",
      FileID, LineNumb, PQerrorMessage(pgConn), sql);
    return -1;
  }

  /* If no error, return */
  if (PQresultStatus(result) == PGRES_COMMAND_OK) return 0;

  printf("ERROR: %s:%d, %sOn: %s\n",
    FileID, LineNumb, PQresultErrorMessage(result), sql);
  PQclear(result);
  return (-1);
} /* fo_checkPQcommand */


/**
@brief Check if table exists.
Note, this assumes the database name is 'fossology'.

@param pgConn database connection
@param tableName  The table in question

@return 1 if table exists, 0 on error (which is logged) or if table does not exist.
****************************************************/
int fo_tableExists(PGconn* pgConn, const char* tableName)
{
  char sql[256];
  PGresult* result;
  int TabCount;

  snprintf(sql, sizeof(sql),
    "select count(*) from information_schema.tables where table_catalog='%s' and table_name='%s'",
    PQdb(pgConn), tableName);
  result = PQexec(pgConn, sql);
  if (fo_checkPQresult(pgConn, result, sql, __FILE__, __LINE__)) return 0;

  TabCount = atol(PQgetvalue(result, 0, 0));

  PQclear(result);
  return (TabCount);
} /* fo_tableExists()  */

