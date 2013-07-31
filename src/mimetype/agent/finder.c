/***************************************************************
 Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.

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
 ***************************************************************/

/**
 * \file finder.c
 * \brief get mime type for specified package
 */

#include "finder.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

char SQL[MAXCMD];

/** for the DB */
PGresult *DBMime = NULL; /* contents of mimetype */
int  MaxDBMime=0; /* how many rows in DBMime */
PGconn *pgConn;
int Agent_pk=-1; /* agent identifier */

/** for /etc/mime.types */
FILE *FMimetype=NULL;

/** for Magic */
magic_t MagicCookie;

/** input for this system */
int Akey = 0;
char A[MAXCMD];

/**
 * \brief Create a string with taint quoting.
 *
 * \param char *S - the string will be tainted
 *
 * \return char* - static string, tainted string.
 */
char * TaintString(char *S)
{
  static char String[4096];
  int i;

  memset(String,'\0',sizeof(String));
  if (!S) return(String);
  for(i=0; (S[0]!='\0') && (i < sizeof(String)-1); S++)
  {
    if (S[0]=='\n') { String[i++]='\\'; String[i++]='n'; }
    else if (S[0]=='\r') { String[i++]='\\'; String[i++]='r'; }
    else if (S[0]=='\a') { String[i++]='\\'; String[i++]='a'; }
    else if (S[0]=='\'') { String[i++]='\\'; String[i++]='\''; }
    else if (S[0]=='\"') { String[i++]='\\'; String[i++]='"'; }
    else if (S[0]=='\\') { String[i++]='\\'; String[i++]='\\'; }
    else String[i++]=S[0];
  }
  return(String);
} /* TaintString() */

/**
 * \brief populate the DBMime table.
 */
void DBLoadMime()
{
  if (DBMime) PQclear(DBMime);
  memset(SQL, 0, MAXCMD);
  snprintf(SQL, MAXCMD-1, "SELECT mimetype_pk,mimetype_name FROM mimetype ORDER BY mimetype_pk ASC;");
  DBMime =  PQexec(pgConn, SQL); /* SELECT */
  if (fo_checkPQresult(pgConn, DBMime, SQL, __FILE__, __LINE__)) 
  {
    PQfinish(pgConn);
    exit(-1);
  }
  MaxDBMime = PQntuples(DBMime);
} /* DBLoadMime() */

/**
 * \brief find a mime type in the DBMime table.
 * if the Mimetype is alrady in table mimetype, return mimetype_pk,
 * if not, insert it into table mimetype, then return the mimetype_pk
 *
 * \param char *Mimetype - mimetype_name
 * \return int - mimetype ID or -1 if not found.
 */
int DBFindMime(char *Mimetype)
{
  int i;
  PGresult *result;

  if (!Mimetype || (Mimetype[0]=='\0')) return(-1);
  if (!DBMime) DBLoadMime();
  for(i=0; i < MaxDBMime; i++)
  {
    if (!strcmp(Mimetype,PQgetvalue(DBMime,i,1)))
    {
      return(atoi(PQgetvalue(DBMime,i,0))); /* return mime type */
    }
  }

  /* If it got here, then the mimetype is unknown.  Add it! */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL)-1,"INSERT INTO mimetype (mimetype_name) VALUES ('%s');",TaintString(Mimetype));
  /* The insert will fail if it already exists.  This is good.  It will
     prevent multiple mimetype agents from inserting the same data at the
     same type. */
  result = PQexec(pgConn, SQL);
  if ((result==0) || ((PQresultStatus(result) != PGRES_COMMAND_OK) &&
       (strncmp("23505", PQresultErrorField(result, PG_DIAG_SQLSTATE),5))))
  {
    PQfinish(pgConn);
    exit(-1);
  }
  PQclear(result);

  /* Now reload the mimetype table */
  DBLoadMime();
  /* And re-process the request... */
  return(DBFindMime(Mimetype));
} /* DBFindMime() */

/**
 * \brief given an extension, see if extension exists in
 *  the /etc/mime.types. 
 *
 * \param har *Ext - the extension
 *
 * \return int -  if the extension exists in
 * the /etc/mime.types, add metatype to DB and return
 * DB index.  Otherwise, return -1.
 */
int CheckMimeTypes(char *Ext)
{
  char Line[MAXCMD];
  int i;
  int ExtLen;

  if (!FMimetype) return(-1);
  if (!Ext || (Ext[0] == '\0')) return(-1);
  ExtLen = strlen(Ext);
  rewind(FMimetype);
  LOG_VERBOSE0("Looking for mimetype based on extension: '%s'",Ext);

  while(ReadLine(FMimetype,Line,MAXCMD) > 0)
  {
    if (Line[0] == '#') continue; /* skip comments */
    /* find the extension */
    for(i=0; (Line[i] != '\0') && !isspace(Line[i]); i++);
    if (Line[i] == '\0') continue; /* no file types */
    Line[i]='\0'; /* terminate the metatype */
    i++;

    /* Now find the extensions and see if any match */
#if 0
    printf("CheckMimeTypes(%s) in '%s' from '%s\n",Ext,Line+i,Line);
#endif
    for( ; Line[i] != '\0'; i++)
    {
      /* Line[i-1] is always valid */
      /* if the previous character is not a word-space, then skip */
      if ((Line[i-1] != '\0') && !isspace(Line[i-1]))
        continue; /* not start of a type */
      /* if the first character does not match is a shortcut.
      if the string matches AND the next character is a word-space,
      then match. */
      if ((Line[i] == Ext[0]) && !strncasecmp(Line+i,Ext,ExtLen) &&
          ( (Line[i+ExtLen] == '\0') || isspace(Line[i+ExtLen]) )
      )
      {
        /* it matched! */
        LOG_VERBOSE0("Found mimetype by extension: '%s' = '%s'",Ext,Line);
        return(DBFindMime(Line)); /* return metatype id */
      }
    }
  }

  /* For specagent (used because the DB query 'like %.spec' is slow) */
  if (!strcasecmp(Ext,"spec")) return(DBFindMime("application/x-rpm-spec"));

  return(-1);
} /* CheckMimeTypes() */

/**
 * \brief given a pfile, identify any filenames
 *  and see if any of them have a known extension based on
 * /etc/mime.types.
 *
 * \return int - return the mimetype id, or -1 if not found.
 */
int DBCheckFileExtention()
{
  int u, Maxu;
  char *Ext;
  int rc;
  PGresult *result;

  if (!FMimetype) return(-1);

  if (Akey >= 0)
  {
    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL)-1,"SELECT distinct(ufile_name) FROM uploadtree WHERE pfile_fk = %d",Akey);
    result = PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
    {
      PQfinish(pgConn);
      exit(-1);
    }

    Maxu = PQntuples(result);
    for(u=0; u<Maxu; u++)
    {
      Ext = strrchr(PQgetvalue(result,u,0),'.'); /* find the extention */
      if (Ext)
      {
        Ext++; /* move past period */
        rc = CheckMimeTypes(Ext);
        if (rc >= 0) return(rc);
      }
    }
    PQclear(result);
  } /* if using DB */
  else
  {
    /* using command-line */
    Ext = strrchr(A,'.'); /* find the extention */
    if (Ext)
    {
      Ext++; /* move past period */
      rc = CheckMimeTypes(Ext);
      if (rc >= 0) return(rc);
    }
  }
  return(-1);
} /* DBCheckFileExtention() */

/**
 * \brief get the ID for the default mimetype.
 *  Options are:
 *  application/x-empty      :: zero-length file
 *  text/plain               :: 1st 100 characters are printable
 *  application/octet-stream :: 1st 100 characters contain binary
 *
 * \param char *MimeType - mimetype name
 * \param char *Filename - file name
 *
 * \return int - return -1 on error, or DB index to metatype.
 */
int GetDefaultMime(char *MimeType, char *Filename)
{
  int i;
  FILE *Fin;
  int C;

  /* the common case: the default mime type is known already */
  if (MimeType) return(DBFindMime(MimeType));

  /* unknown mime, so find out what it is... */
  Fin = fopen(Filename,"rb");
  if (!Fin) return(-1);

  i=0; 
  C=fgetc(Fin);
  while(!feof(Fin) && isprint(C) && (i < 100))
  {
    C=fgetc(Fin);
    i++;
  }
  fclose(Fin);

  if (i==0) return(DBFindMime("application/x-empty"));
  if ((C >= 0) && !isprint(C)) return(DBFindMime("application/octet-stream"));
  return(DBFindMime("text/plain"));
} /* GetDefaultMime() */

/**
 * \brief Given a file, check if it has a mime type
 * in the DB.  If it does not, then add it.
 *
 * \param char *Filename - the path of the file
 */
void DBCheckMime(char *Filename)
{
  char MimeType[MAXCMD];
  char *MagicType;
  int MimeTypeID;
  int i;
  PGresult *result;

  if (Akey >= 0)
  {
    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL)-1,"SELECT pfile_mimetypefk FROM pfile WHERE pfile_pk = %d AND pfile_mimetypefk is not null;",Akey);
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
    {
      PQfinish(pgConn);
      exit(-1);
    }

    if (PQntuples(result) > 0)
    {
      PQclear(result);
      return;
    }
    PQclear(result);
  } /* if using DB */

  /* Not in DB, so find out what it is... */
  /* Check using Magic */
  MagicType = (char *)magic_file(MagicCookie,Filename);
  memset(MimeType,'\0',MAXCMD);
  if (MagicType)
  {
    LOG_VERBOSE0("Found mimetype by magic: '%s'",MagicType);
    /* Magic contains additional data after a ';' */
    for(i=0;
        (i<MAXCMD) && (MagicType[i] != '\0') &&
            !isspace(MagicType[i]) && !strchr(",;",MagicType[i]);
        i++)
    {
      MimeType[i] = MagicType[i];
    }
    if (!strchr(MimeType,'/')) { memset(MimeType,'\0',MAXCMD); }
  }

  /* If there is no mimetype, or there is one but it is a default value,
     then determine based on extension */
  if (!strcmp(MimeType,"text/plain") || !strcmp(MimeType,"application/octet-stream") || (MimeType[0]=='\0'))
  {
    /* unknown type... Guess based on file extention */
    MimeTypeID = DBCheckFileExtention();
    /* not known?  */
    if (MimeTypeID < 0) MimeTypeID = GetDefaultMime(MimeType,Filename);
  }
  else
  {
    /* We have a mime-type! Update the database */
    MimeTypeID = DBFindMime(MimeType);
  }

  /* Make sure there is a mime-type */
  if (MimeTypeID < 0)
  {
    /* This should never happen; give it a default. */
    MimeTypeID = DBFindMime("application/octet-stream");
  }

  /* Update pfile record */
  if (Akey >= 0)
  {
    result =  PQexec(pgConn, "BEGIN;");
    if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
    {
      PQfinish(pgConn);
      exit(-1);
    }

    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL)-1,"SELECT * FROM pfile WHERE pfile_pk = %d FOR UPDATE;",Akey);
    PQclear(result);
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
    {
      PQfinish(pgConn);
      exit(-1);
    }

    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL)-1,"UPDATE pfile SET pfile_mimetypefk = %d WHERE pfile_pk = %d;",MimeTypeID,Akey);
    PQclear(result);
    result =  PQexec(pgConn, SQL);
    if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
    {
      PQfinish(pgConn);
      exit(-1);
    }

    PQclear(result);
    result =  PQexec(pgConn, "COMMIT;");
    if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__))
    {
      PQfinish(pgConn);
      exit(-1);
    }
    PQclear(result);
  }
  else
  {
    /* IF no Akey, then display to stdout */
    int i;
    for(i=0; i < MaxDBMime; i++)
    {
      if (MimeTypeID == atoi(PQgetvalue(DBMime,i,0)))
      {
        printf("%s : mimetype_pk=%d : ",PQgetvalue(DBMime,i,1),MimeTypeID);
      }
    }
    printf("%s\n",Filename);
  }
} /* DBCheckMime() */

/**
 * \brief given a string that contains
 *  field='value' pairs, save the items.
 *
 * \return char * - return pointer to start of next field, or
 *  NULL at \0.
 */
char *  GetFieldValue   (char *Sin, char *Field, int FieldMax,
    char *Value, int ValueMax)
{
  int s,f,v;
  int GotQuote;

  memset(Field,0,FieldMax);
  memset(Value,0,ValueMax);

  while(isspace(Sin[0])) Sin++; /* skip initial spaces */
  if (Sin[0]=='\0') return(NULL);
  f=0; v=0;

  for(s=0; (Sin[s] != '\0') && !isspace(Sin[s]) && (Sin[s] != '='); s++)
  {
    Field[f++] = Sin[s];
  }
  while(isspace(Sin[s])) s++; /* skip spaces after field name */
  if (Sin[s] != '=') /* if it is not a field, then just return it. */
  {
    return(Sin+s);
  }
  if (Sin[s]=='\0') return(NULL);
  s++; /* skip '=' */
  while(isspace(Sin[s])) s++; /* skip spaces after '=' */
  if (Sin[s]=='\0') return(NULL);

  GotQuote='\0';
  if ((Sin[s]=='\'') || (Sin[s]=='"'))
  {
    GotQuote = Sin[s];
    s++; /* skip quote */
    if (Sin[s]=='\0') return(NULL);
  }
  if (GotQuote)
  {
    for( ; (Sin[s] != '\0') && (Sin[s] != GotQuote); s++)
    {
      if (Sin[s]=='\\') Value[v++]=Sin[++s];
      else Value[v++]=Sin[s];
    }
  }
  else
  {
    /* if it gets here, then there is no quote */
    for( ; (Sin[s] != '\0') && !isspace(Sin[s]); s++)
    {
      if (Sin[s]=='\\') Value[v++]=Sin[++s];
      else Value[v++]=Sin[s];
    }
  }
  while(isspace(Sin[s])) s++; /* skip spaces */
  return(Sin+s);
} /* GetFieldValue() */

/**
 * \brief read a line each time from one file
 *
 * \param FILE *Fin - the file stream
 * \param char *Line - save a line of content
 * \param int MaxLine - max character count one time to read a line
 * 
 * \return int - the character count of the line
 *
 */
int ReadLine(FILE *Fin, char *Line, int MaxLine)
{
  int C;
  int i;

  memset(Line,'\0',MaxLine);
  if (feof(Fin)) return(-1);
  i=0;
  C=fgetc(Fin);
  if (C<0) return(-1);
  while(!feof(Fin) && (C>=0) && (i<MaxLine))
  {
    if (C=='\n')
    {
      if (i > 0) return(i);
      /* if it is a blank line, then ignore it. */
    }
    else
    {
      Line[i]=C;
      i++;
    }
    C=fgetc(Fin);
  }
  return(i);
} /* ReadLine() */

/**
 * \brief Here are some suggested options
 *
 * \param char *Name - the name of the executable, ususlly it is mimetype
 */
void Usage(char *Name)
{
  printf("Usage: %s [options] [file [file [...]]\n",Name);
  printf("  -h   :: help (print this message), then exit.\n");
  printf("  -i   :: initialize the database, then exit.\n");
  //printf("  -v   :: verbose (-vv = more verbose)\n");
  printf("  -c   :: Specify the directory for the system configuration.\n");
  printf("  -C   :: run from command line.\n");
  printf("  file :: if files are listed, display their mimetype.\n");
  printf("  no file :: process data from the scheduler.\n");
} /* Usage() */
