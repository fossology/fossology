/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

/*!
 \file
 \brief Common libpq database functions.
 */

#define ERRBUFSIZE 11264

#include "libfossdb.h"

#include <string.h>

/*!
 \brief Connect to a database. The default is Db.conf.

 All key=value; pairs from the Db.conf file are passed to libpq's PQconnectdb().
 This includes standard parameters (dbname, host, user, password) and SSL parameters
 (sslmode, sslcert, sslkey, sslrootcert) for secure connections to databases like
 Azure Database for PostgreSQL.

 \param DBConfFile File path of the Db.conf file to use.  If NULL, use the default Db.conf
 \param ErrorBuf   Address of pointer to error buffer.  fo_dbconnect will allocate this
                   if needed.  The caller should free it.

 \return PGconn*, or NULL on failure to process the config file.  If NULL, ErrorBuff will 
         contain the error message.  If NULL is returned and ErrorBuf is NULL, then 
         there was insufficient memory to allocate ErrorBuf.
****************************************************/
PGconn* fo_dbconnect(char* DBConfFile, char** ErrorBuf)
{
  FILE* Fconf;
  PGconn* pgConn;
  char Line[1024];
  char CMD[10240];
  int i, CMDlen;
  int C;
  int PosEqual; /* index of "=" in Line */
  int PosSemi;  /* index of ";" in Line */
  int BufLen;

  if (DBConfFile)
    Fconf = fopen(DBConfFile, "r");
  else
    Fconf = fopen(FOSSDB_CONF, "r");
  if (!Fconf)
  {
    *ErrorBuf = malloc(ERRBUFSIZE);
    if (*ErrorBuf)
    {
      snprintf(*ErrorBuf, ERRBUFSIZE, "Database conf file: %s, ",
        (DBConfFile ? DBConfFile : "FOSSDB_CONF"));
      BufLen = strlen(*ErrorBuf);
      strerror_r(errno, *ErrorBuf + BufLen, ERRBUFSIZE - BufLen);
    }
    return (NULL);
  }

  /* read the configuration file */
  memset(CMD, '\0', sizeof(CMD));
  CMDlen = 0;
  while (!feof(Fconf))
  {
    C = '@';
    PosEqual = 0;
    PosSemi = 0;
    memset(Line, '\0', sizeof(Line));
    /* read a line of data */
    /* All lines are in the format: "field=value;" */
    /* Lines beginning with "#" are ignored. */
    for (i = 0; (i < sizeof(Line)) && (C != '\n') && (C > 0); i++)
    {
      C = fgetc(Fconf);
      if ((C > 0) && (C != '\n')) Line[i] = C;
      if ((C == '=') && !PosEqual) PosEqual = i;
      else if ((C == ';') && !PosSemi) PosSemi = i;
    }
    /* check for a valid line */
    if (PosSemi < PosEqual) PosEqual = 0;
    if ((Line[0] != '#') && PosEqual && PosSemi)
    {
      /* looks good to me! */
      if (CMD[0] != '\0')
      {
        CMD[CMDlen++] = ' ';
        if (CMDlen >= sizeof(CMD))
        {
          fclose(Fconf);
          goto BadConf;
        }
      }
      Line[PosSemi] = '\0';
      for (i = 0; i < PosEqual; i++)
      {
        if (!isspace(Line[i])) CMD[CMDlen++] = Line[i];
        if (CMDlen >= sizeof(CMD))
        {
          fclose(Fconf);
          goto BadConf;
        }
      }
      CMD[CMDlen++] = '=';
      if (CMDlen >= sizeof(CMD))
      {
        fclose(Fconf);
        goto BadConf;
      }
      for (i = PosEqual + 1; Line[i] != '\0'; i++)
      {
        if (!isspace(Line[i])) CMD[CMDlen++] = Line[i];
        if (CMDlen >= sizeof(CMD))
        {
          fclose(Fconf);
          goto BadConf;
        }
      }
    }
  }

  /* done reading file */
  fclose(Fconf);
  if (CMD[0] == '\0') goto BadConf;

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
        "ERROR: Unable to connect to the database\n   Connection string: '%s'\n   Connection status: '%d'\n  Check: /usr/local/etc/fossology/Db.conf\n", CMD, PQstatus(pgConn));
    }
    return (NULL);
  }

  return (pgConn);

  BadConf:
  *ErrorBuf = malloc(ERRBUFSIZE);
  snprintf(*ErrorBuf, ERRBUFSIZE, "Invalid Database conf file: %s, ",
    (DBConfFile ? DBConfFile : "FOSSDB_CONF"));
  return (NULL);
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

