/***************************************************************
 Mimetype: Get the mimetype for a package.

 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
 
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

 Lots of different agents generate mimetype information, but they have
 limitations.  For example:
   - Ununpack: it knows mimetypes!  But only for the files it extracts.
     Unknown files are not assigned mimetypes.
   - Pkgmetagetta: it knows mimetypes!  But only for the files it supports.
     And the mimetypes are not the same as ununpack.  For example,
     Ununpack uses Magic and says "application/x-rpm" while libextractor
     says "application/x-redhat-package-manager".  These are different
     strings.
 This agent is intended as be the official source for mimetypes.
 What it does:
   (1) If ununpack found a mimetype, us it.  This is because ununpack
       actually unpacks the files.  Thus, if the file can ben unpacked
       then this must be the right mimetype.
       Also ununpack uses /etc/UnMagic.mime which identifies more
       special types than regular magic(5).
   (2) If ununpack did not find a mimetype, then use magic(5).
 ***************************************************************/
#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <magic.h>

#include "libfossrepo.h"
#include "libfossdb.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

#define MAXCMD	256
char SQL[MAXCMD];

/* for the DB */
void *DBMime=NULL;	/* contents of mimetype table */
int  MaxDBMime=0;	/* how many rows in DBMime */
void *DB;
int Agent_pk=-1;	/* agent identifier */

/* for /etc/mime.types */
FILE *FMimetype=NULL;

/* for Magic */
magic_t MagicCookie;

/* input for this system */
int Akey = 0;
char A[MAXCMD];

/**************************************************
 ShowHeartbeat(): Given an alarm signal, display a
 heartbeat.
 **************************************************/
void    ShowHeartbeat   (int Sig)
{
  printf("Heartbeat\n");
  fflush(stdout);
  /* re-schedule itself */
  alarm(60);
} /* ShowHeartbeat() */

/**********************************************
 ReadLine(): Read a command from stdin.
 If the line is empty, then try again.
 Returns line length, or -1 of EOF.
 **********************************************/
int     ReadLine (FILE *Fin, char *Line, int MaxLine)
{
  int C;
  int i;

  if (!Fin) return(-1);
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

/*********************************************************
 TaintString(): Create a string with taint quoting.
 Returns static string.
 *********************************************************/
char *	TaintString	(char *S)
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

/*********************************************************
 DBLoadMime(): Populate the DBMime table.
 *********************************************************/
void	DBLoadMime	()
{
  if (DBMime) DBclose(DBMime);
  if (DBaccess(DB,"SELECT mimetype_pk,mimetype_name FROM mimetype ORDER BY mimetype_pk ASC;") < 0)
    {
    printf("ERROR: Unable to access database.\n");
    printf("LOG: Unable to access database: 'SELECT mimetype_pk,mimetype_name FROM mimetype ORDER BY mimetype_pk ASC;'\n");
    fflush(stdout);
    DBclose(DB);
    exit(-1);
    }
  DBMime = DBmove(DB);
  MaxDBMime = DBdatasize(DBMime);
} /* DBLoadMime() */

/*********************************************************
 DBFindMime(): Find a mime type in the DBMime table.
 Returns mimetype ID or -1 if not found.
 *********************************************************/
int	DBFindMime	(char *Mimetype)
{
  int i;

  if (!Mimetype || (Mimetype[0]=='\0')) return(-1);
  if (!DBMime) DBLoadMime();
  for(i=0; i < MaxDBMime; i++)
    {
    if (!strcmp(Mimetype,DBgetvalue(DBMime,i,1)))
      {
      return(atoi(DBgetvalue(DBMime,i,0))); /* return mime type */
      }
    }

  /* If it got here, then the mimetype is unknown.  Add it! */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL)-1,"INSERT INTO mimetype (mimetype_name) VALUES ('%s');",TaintString(Mimetype));
  /* The insert will fail if it already exists.  This is good.  It will
     prevent multiple mimetype agents from inserting the same data at the
     same type. */
  DBaccess(DB,SQL);
  /* Now reload the mimetype table */
  DBLoadMime();
  /* And re-process the request... */
  return(DBFindMime(Mimetype));
} /* DBFindMime() */

/*********************************************************
 CheckMimeTypes(): Given an extension, see if extension exists in
 the /etc/mime.types.  If so, add metatype to DB and return
 DB index.  Otherwise, return -1.
 *********************************************************/
int	CheckMimeTypes	(char *Ext)
{
  char Line[MAXCMD];
  int i;
  int ExtLen;

  if (!FMimetype) return(-1);
  if (!Ext || (Ext[0] == '\0')) return(-1);
  ExtLen = strlen(Ext);
  rewind(FMimetype);

  while(ReadLine(FMimetype,Line,MAXCMD) > 0)
    {
    if (Line[0] == '#') continue;	/* skip comments */
    /* find the extension */
    for(i=0; (Line[i] != '\0') && !isspace(Line[i]); i++)	;
    if (Line[i] == '\0') continue;	/* no file types */
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
		continue;	/* not start of a type */
	/* if the first character does not match is a shortcut.
	   if the string matches AND the next character is a word-space,
	   then match. */
	if ((Line[i] == Ext[0]) && !strncasecmp(Line+i,Ext,ExtLen) &&
	    ( (Line[i+ExtLen] == '\0') || isspace(Line[i+ExtLen]) )
	   )
		{
		/* it matched! */
		return(DBFindMime(Line));	/* return metatype id */
		}
	}
    }

  /* For specagent (used because the DB query 'like %.spec' is slow) */
  if (!strcasecmp(Ext,"spec")) return(DBFindMime("application/x-rpm-spec"));

  return(-1);
} /* CheckMimeTypes() */

/*********************************************************
 DBCheckFileExtention(): given a pfile, identify any ufiles
 and see if any of them have a known extension based on
 /etc/mime.types.
 Returns the mimetype, or -1 if not found.
 *********************************************************/
int	DBCheckFileExtention	()
{
  int u, Maxu;
  char *Ext;
  int rc;

  if (!FMimetype) return(-1);

  if (Akey >= 0)
    {
    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL)-1,"SELECT distinct(ufile_name) FROM ufile WHERE pfile_fk = %d",Akey);
    if (DBaccess(DB,SQL) < 0)
      {
      printf("ERROR: Unable to query the database.\n");
      printf("LOG: Unable to access database: '%s'\n",SQL);
      fflush(stdout);
      DBclose(DB);
      exit(-1);
      }

    Maxu = DBdatasize(DB);
    for(u=0; u<Maxu; u++)
      {
      Ext = strrchr(DBgetvalue(DB,u,0),'.'); /* find the extention */
      if (Ext)
        {
        Ext++; /* move past period */
        rc = CheckMimeTypes(Ext);
        if (rc >= 0) return(rc);
        }
      }
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

/*********************************************************
 GetDefaultMime(): Return the ID for the default mimetype.
 Options are:
   application/x-empty      :: zero-length file
   text/plain               :: 1st 100 characters are printable
   application/octet-stream :: 1st 100 characters contain binary
 Returns -1 on error, or DB index to metatype.
 *********************************************************/
int	GetDefaultMime	(char *MimeType, char *Filename)
{
  int i;
  FILE *Fin;
  int C;

  /* the common case: the default mime type is known already */
  if (MimeType)	return(DBFindMime(MimeType));

  /* unknown mime, so find out what it is... */
  Fin = fopen(Filename,"rb");
  if (!Fin)	return(-1);

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

/*********************************************************
 DBCheckMime(): Given a file, check if it has a mime type
 in the DB.  If it does not, then add it.
 Returns DB entry for the mimetype.
 *********************************************************/
void	DBCheckMime	(char *Filename)
{
  char MimeType[MAXCMD];
  char *MagicType;
  int MimeTypeID;
  int i;

  if (Akey >= 0)
    {
    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL)-1,"SELECT pfile_mimetypefk FROM pfile WHERE pfile_pk = %d AND pfile_mimetypefk is not null;",Akey);
    if (DBaccess(DB,SQL) < 0)
      {
      printf("ERROR: Unable to query the database.\n");
      printf("LOG: Unable to access database: '%s'\n",SQL);
      fflush(stdout);
      DBclose(DB);
      exit(-1);
      }
    if (DBdatasize(DB) > 0)
	{
	return;
	}
    } /* if using DB */

  /* Not in DB, so find out what it is... */
  /* Check using Magic */
  MagicType = (char *)magic_file(MagicCookie,Filename);
  memset(MimeType,'\0',MAXCMD);
  if (MagicType)
      {
      /* Magic contains additional data after a ';' */
      for(i=0;
	  (i<MAXCMD) && (MagicType[i] != '\0') &&
	  !isspace(MagicType[i]) && (MagicType[i] != ';');
	  i++)
	{
	MimeType[i] = MagicType[i];
	}
      }

  /* If there is no mimetype, or there is one but it is a default value,
     then determine based on extension */
  if (!strcmp(MimeType,"text/plain") || !strcmp(MimeType,"application/octet-stream"))
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
	MimeTypeID = DBFindMime("application/octet-string");
	}

  /* Update pfile record */
  if (Akey >= 0)
    {
    memset(SQL,'\0',sizeof(SQL));
    DBaccess(DB,"BEGIN;");
    snprintf(SQL,sizeof(SQL)-1,"SELECT * FROM pfile WHERE pfile_pk = %d FOR UPDATE;",Akey);
    DBaccess(DB,SQL);
    snprintf(SQL,sizeof(SQL)-1,"UPDATE pfile SET pfile_mimetypefk = %d WHERE pfile_pk = %d;",MimeTypeID,Akey);
    if (DBaccess(DB,SQL) < 0)
      {
      printf("ERROR: Unable to update the database.\n");
      printf("LOG: Unable to update database: '%s'\n",SQL);
      fflush(stdout);
      DBclose(DB);
      exit(-1);
      }
    DBaccess(DB,"COMMIT;");
    }
} /* DBCheckMime() */

/**********************************************
 GetFieldValue(): Given a string that contains
 field='value' pairs, save the items.
 Returns: pointer to start of next field, or
 NULL at \0.
 **********************************************/
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

/**********************************************
 SetEnv(): Convert field=value pairs into
 variables: A and Akey.
 **********************************************/
void    SetEnv  (char *S)
{
  char Field[256];
  char Value[1024];
  int GotOther=0;
  char *OrigS;

  OrigS=S;
  Akey=-1;
  memset(A,'\0',sizeof(A));

  while(S && (S[0] != '\0'))
    {
    S = GetFieldValue(S,Field,256,Value,1024);
    if (Value[0] != '\0')
	{
	if (!strcasecmp(Field,"akey")) Akey=atoi(Value);
	else if (!strcasecmp(Field,"a")) strncpy(A,Value,sizeof(A));
	else GotOther=1;
	}
    }

  if (GotOther || (Akey < 0) || (A[0]=='\0'))
    {
    printf("ERROR: Data is in an unknown format.\n");
    printf("LOG: Unknown data: '%s'\n",OrigS);
    fflush(stdout);
    DBclose(DB);
    exit(-1);
    }
} /* SetEnv() */

/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 *********************************************************/
void	GetAgentKey	()
{
  int rc;

  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='mimetype' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'mimetype' from the database table 'agent'\n");
	DBclose(DB);
	exit(-1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('mimetype','unknown','Sets pfile mimetype from magic or ufile extension');");
      if (rc < 0)
	{
	printf("ERROR: unable to write to the database\n");
	printf("LOG: unable to write 'mimetype' to the database table 'agent'\n");
	DBclose(DB);
	exit(-1);
	}
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='mimetype' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'mimetype' from the database table 'agent'\n");
	DBclose(DB);
	exit(-1);
	}
      }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
} /* GetAgentKey() */

/***********************************************
 Usage():
 ***********************************************/
void	Usage	(char *Name)
{
  printf("Usage: %s [-i] [file [file [...]]\n",Name);
  printf("  -i   :: initialize the database, then exit.\n");
  printf("  file :: if files are listed, display their mimetype.\n");
  printf("  no file :: process data from the scheduler.\n");
} /* Usage() */

/*********************************************************/
int	main	(int argc, char *argv[])
{
  int arg;
  char Parm[MAXCMD];
  char *Path;
  int c;

  /* Init */
  DB = DBopen();
  if (!DB)
	{
	printf("FATAL: Unable to connect to database\n");
	fflush(stdout);
	exit(-1);
	}
  GetAgentKey();

  FMimetype = fopen("/etc/mime.types","rb");
  if (!FMimetype)
	{
	printf("WARNING: Unable to open /etc/mime.types\n");
	}

  MagicCookie = magic_open(MAGIC_PRESERVE_ATIME|MAGIC_MIME);
  if (MagicCookie == NULL)
	{
	printf("FATAL: Failed to initialize magic cookie\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
  if (magic_load(MagicCookie,NULL) != 0)
	{
	printf("FATAL: Failed to load magic file: UnMagic\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}

  /* Process command-line */
  while((c = getopt(argc,argv,"i")) != -1)
    {
    switch(c)
	{
	case 'i':
		DBclose(DB);
		return(0);
	default:
		Usage(argv[0]);
		DBclose(DB);
		exit(-1);
	}
    }

  /* Run from the command-line (for testing) */
  for(arg=optind; arg < argc; arg++)
    {
    Akey = -1;
    memset(A,'\0',sizeof(A));
    strncpy(A,argv[arg],sizeof(A));
    DBCheckMime(A);
    }

  /* Run from scheduler! */
  if (argc == 1)
    {
    signal(SIGALRM,ShowHeartbeat);
    alarm(60);

    printf("OK\n"); /* inform scheduler that we are ready */
    fflush(stdout);
    while(ReadLine(stdin,Parm,MAXCMD) >= 0)
      {
      if (Parm[0] != '\0')
	{
	alarm(0);	/* allow scheduler to know if this hangs */
	SetEnv(Parm); /* set environment (A and Akey globals) */
	/* Process the repository file */
	/** Find the path **/
	Path = RepMkPath("files",A);
	if (Path && RepExist("files",A))
	  {
	  /* Get the mimetype! */
	  DBCheckMime(Path);
	  }
	else
	  {
	  printf("ERROR pfile %d Unable to process.\n",Akey);
	  printf("LOG pfile %d File '%s' not found.\n",Akey,A);
	  fflush(stdout);
	  DBclose(DB);
	  exit(-1);
	  }
	printf("OK\n"); /* inform scheduler that we are ready */
	alarm(60);
	fflush(stdout);
	}
      }
    } /* if run from scheduler */

  /* Clean up */
  if (FMimetype) fclose(FMimetype);
  magic_close(MagicCookie);
  if (DBMime) DBclose(DBMime);
  if (DB) DBclose(DB);
  return(0);
} /* main() */

