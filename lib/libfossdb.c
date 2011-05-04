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

/**************************************************************
 * Common database functions 
 * \file libfossdb.c
 * \brief common libpq database functions
 **************************************************************/

#include "libfossdb.h"

#ifndef FOSSDB_CONF
#define FOSSDB_CONF "/etc/fossology/Db.conf"
#endif


/*****************************************************
 fo_dbconnect(): Open the DB.
 Returns PGconn*, or NULL on failure.
 *****************************************************/
PGconn *fo_dbconnect()
{
  FILE *Fconf;
  PGconn *pgConn;
  char *Env;
  char Line[1024];
  char CMD[10240];
  int i,CMDlen;
  int C;
  int PosEqual; /* index of "=" in Line */
  int PosSemi;  /* index of ";" in Line */

  /* Normally, this tries to open the file FOSSDB_CONF.
     This is a compile-time string.
     However, for debugging you can override the config file
     with the environment variable "FOSSDBCONF".
   */

  /* Env FOSSDBCONF = debugging override for the config file */
  Env = getenv("FOSSDBCONF");

  if (Env)
    Fconf = fopen(Env,"r");
  else 
    Fconf = fopen(FOSSDB_CONF,"r");
  if (!Fconf) return(NULL);

  /* read the configuration file */
  memset(CMD,'\0',sizeof(CMD));
  CMDlen = 0;
  while(!feof(Fconf))
  {
    C='@';
    PosEqual=0;
    PosSemi=0;
    memset(Line,'\0',sizeof(Line));
    /* read a line of data */
    /* All lines are in the format: "field=value;" */
    /* Lines beginning with "#" are ignored. */
    for(i=0; (i<sizeof(Line)) && (C != '\n') && (C > 0); i++)
    {
      C = fgetc(Fconf);
      if ((C > 0) && (C != '\n')) Line[i]=C;
      if ((C=='=') && !PosEqual) PosEqual=i;
      else if ((C==';') && !PosSemi) PosSemi=i;
    }
    /* check for a valid line */
    if (PosSemi < PosEqual) PosEqual=0;
    if ((Line[0] != '#') && PosEqual && PosSemi)
    {
      /* looks good to me! */
      if (CMD[0] != '\0')
      {
        CMD[CMDlen++] = ' ';
        if (CMDlen >= sizeof(CMD)) { fclose(Fconf); return(NULL); }
	    }
      Line[PosSemi] = '\0';
      for(i=0; i < PosEqual; i++)
      {
        if (!isspace(Line[i])) CMD[CMDlen++] = Line[i];
        if (CMDlen >= sizeof(CMD)) { fclose(Fconf); return(NULL); }
	    }
      CMD[CMDlen++] = '=';
      if (CMDlen >= sizeof(CMD)) { fclose(Fconf); return(NULL); }
      for(i=PosEqual+1; Line[i] != '\0'; i++)
      {
        if (!isspace(Line[i])) CMD[CMDlen++] = Line[i];
        if (CMDlen >= sizeof(CMD)) { fclose(Fconf); return(NULL); }
      }
    }
  }

  /* done reading file */
  fclose(Fconf);
  if (CMD[0] == '\0') return(NULL);

  /* Perform the connection */
  pgConn = PQconnectdb(CMD);
  if (PQstatus(pgConn) != CONNECTION_OK)
  {
    printf("ERROR: Unable to connect to the database\n");
    printf("  Connection string: '%s'\n",CMD);
    printf("  Connection status: '%d'\n",PQstatus(pgConn));
    return(NULL);
  }

  return(pgConn);
} /* fo_dbconnect() */
 

/****************************************************
 fo_checkPQresult

 check the result status of a postgres SELECT
 If an error occured, write the error to stdout

 @param PGconn *pgConn
 @param PGresult *result
 @param char *sql     the sql query
 @param char * FileID is a file identifier string to write into 
                      the error message.  Typically the caller
                      will use __FILE__, but any identifier string
                      is ok.
 @param int LineNumb  the line number of the caller (__LINE__)

 @return 0 on OK, -1 on failure.
 On failure, result will be freed.
****************************************************/
int fo_checkPQresult(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb)
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


/****************************************************
 fo_checkPQcommand

 check the result status of a postgres commands (not select)
 If an error occured, write the error to stdout

 @param PGconn *pgConn
 @param PGresult *result
 @param char *sql the sql query
 @param char * FileID is a file identifier string to write into 
                      the error message.  Typically the caller
                      will use __FILE__, but any identifier string
                      is ok.
 @param int LineNumb  the line number of the caller (__LINE__)

 @return 0 on OK, -1 on failure.
 On failure, result will be freed.
****************************************************/
int fo_checkPQcommand(PGconn *pgConn, PGresult *result, char *sql, char *FileID, int LineNumb)
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
