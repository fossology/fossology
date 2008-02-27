/***************************************************************
 PkgMetaGetta: Get meta data from a package.

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

 This uses libextractor.
 Currently only RPM and DEB are supported.
 ***************************************************************/
#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <extractor.h>
#include <sys/types.h>
#include <sys/wait.h>

#include "libfossrepo.h"
#include "libfossdb.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

char SQL[256];

struct KeyType
  {
  int DBIndex;  /* the database attribute key_pk */
  int KeyIndex; /* the libextractor code, or -1 for end of list */
  char *Label;
  char *Desc;
  };
typedef struct KeyType KeyType;

/* For the database */
void *DB=NULL;
int Agent_pk=-1;	/* agent identifier */

/* KeywordTypes is similar to extractor.c from libextractor */
KeyType KeywordPkgMeta = { -1,-1,"pkgmeta","Package meta data" };
KeyType KeywordTypes[] = {
  {-1,-2,"Processed","Package meta data processed"},
  {-1,0,"Unknown","Package meta data"},
  {-1,1,"Filename","Package meta data"},
  {-1,2,"Mimetype","Package meta data"},
  {-1,3,"Title","Package meta data"},
  {-1,4,"Author","Package meta data"},
  {-1,5,"Artist","Package meta data"},
  {-1,6,"Description","Package meta data"},
  {-1,7,"Comment","Package meta data"},
  {-1,8,"Date","Package meta data"},
  {-1,9,"Publisher","Package meta data"},
  {-1,10,"Language","Package meta data"},
  {-1,11,"Album","Package meta data"},
  {-1,12,"Genre","Package meta data"},
  {-1,13,"Location","Package meta data"},
  {-1,14,"Version","Package meta data"},
  {-1,15,"Organization","Package meta data"},
  {-1,16,"Copyright","Package meta data"},
  {-1,17,"Subject","Package meta data"},
  {-1,18,"Keywords","Package meta data"},
  {-1,19,"Contributor","Package meta data"},
  {-1,20,"Resource-type","Package meta data"},
  {-1,21,"Format","Package meta data"},
  {-1,22,"Resource-identifier","Package meta data"},
  {-1,23,"Source","Package meta data"},
  {-1,24,"Relation","Package meta data"},
  {-1,25,"Coverage","Package meta data"},
  {-1,26,"Software","Package meta data"},
  {-1,27,"Disclaimer","Package meta data"},
  {-1,28,"Warning","Package meta data"},
  {-1,29,"Translated","Package meta data"},
  {-1,30,"Creation date","Package meta data"},
  {-1,31,"Modification date","Package meta data"},
  {-1,32,"Creator","Package meta data"},
  {-1,33,"Producer","Package meta data"},
  {-1,34,"Page count","Package meta data"},
  {-1,35,"Page orientation","Package meta data"},
  {-1,36,"Paper size","Package meta data"},
  {-1,37,"Used fonts","Package meta data"},
  {-1,38,"Page order","Package meta data"},
  {-1,39,"Created for","Package meta data"},
  {-1,40,"Magnification","Package meta data"},
  {-1,41,"Release","Package meta data"},
  {-1,42,"Group","Package meta data"},
  {-1,43,"Size","Package meta data"},
  {-1,44,"Summary","Package meta data"},
  {-1,45,"Packager","Package meta data"},
  {-1,46,"Vendor","Package meta data"},
  {-1,47,"License","Package meta data"},
  {-1,48,"Distribution","Package meta data"},
  {-1,49,"Build-host","Package meta data"},
  {-1,50,"Operating system","Package meta data"},
  {-1,51,"Dependency","Package meta data"},
  {-1,52,"MD4","Package meta data"},
  {-1,53,"MD5","Package meta data"},
  {-1,54,"SHA0","Package meta data"},
  {-1,55,"SHA1","Package meta data"},
  {-1,56,"RipeMD160","Package meta data"},
  {-1,57,"Resolution","Package meta data"},
  {-1,58,"Category","Package meta data"},
  {-1,59,"Book title","Package meta data"},
  {-1,60,"Priority","Package meta data"},
  {-1,61,"Conflicts","Package meta data"},
  {-1,62,"Replaces","Package meta data"},
  {-1,63,"Provides","Package meta data"},
  {-1,64,"Conductor","Package meta data"},
  {-1,65,"Interpreter","Package meta data"},
  {-1,66,"Owner","Package meta data"},
  {-1,67,"Lyrics","Package meta data"},
  {-1,68,"Media type","Package meta data"},
  {-1,69,"Contact","Package meta data"},
  {-1,70,"Binary thumbnail data","Package meta data"},
  {-1,71,"Publication date","Package meta data"},
  {-1,72,"Camera make","Package meta data"},
  {-1,73,"Camera model","Package meta data"},
  {-1,74,"Exposure","Package meta data"},
  {-1,75,"Aperture","Package meta data"},
  {-1,76,"Exposure bias","Package meta data"},
  {-1,77,"Flash","Package meta data"},
  {-1,78,"Flash bias","Package meta data"},
  {-1,79,"Focal length","Package meta data"},
  {-1,80,"Focal length (35mm equivalent)","Package meta data"},
  {-1,81,"Iso speed","Package meta data"},
  {-1,82,"Exposure mode","Package meta data"},
  {-1,83,"Metering mode","Package meta data"},
  {-1,84,"Macro mode","Package meta data"},
  {-1,85,"Image quality","Package meta data"},
  {-1,86,"White balance","Package meta data"},
  {-1,87,"Orientation","Package meta data"},
  {-1,88,"Template","Package meta data"},
  {-1,89,"Split","Package meta data"},
  {-1,90,"Product version","Package meta data"},
  {-1,91,"Last saved by","Package meta data"},
  {-1,92,"Last printed","Package meta data"},
  {-1,93,"Word count","Package meta data"},
  {-1,94,"Character count","Package meta data"},
  {-1,95,"Total editing time","Package meta data"},
  {-1,96,"Thumbnails","Package meta data"},
  {-1,97,"Security","Package meta data"},
  {-1,98,"Created by software","Package meta data"},
  {-1,99,"Modified by software","Package meta data"},
  {-1,100,"Revision history","Package meta data"},
  {-1,101,"Lower case conversion","Package meta data"},
  {-1,102,"Company","Package meta data"},
  {-1,103,"Generator","Package meta data"},
  {-1,104,"Character set","Package meta data"},
  {-1,105,"Line count","Package meta data"},
  {-1,106,"Paragraph count","Package meta data"},
  {-1,107,"Editing cycles","Package meta data"},
  {-1,108,"Scale","Package meta data"},
  {-1,109,"Manager","Package meta data"},
  {-1,110,"Director","Package meta data"},
  {-1,111,"Duration","Package meta data"},
  {-1,112,"Information","Package meta data"},
  {-1,113,"Full name","Package meta data"},
  {-1,114,"Chapter","Package meta data"},
  {-1,115,"Year","Package meta data"},
  {-1,116,"Link","Package meta data"},
  {-1,117,"Music CD identifier","Package meta data"},
  {-1,118,"Play counter","Package meta data"},
  {-1,119,"Popularity meter","Package meta data"},
  {-1,120,"Content type","Package meta data"},
  {-1,121,"Encoded by","Package meta data"},
  {-1,122,"Time","Package meta data"},
  {-1,123,"Musician credits list","Package meta data"},
  {-1,124,"Mood","Package meta data"},
  {-1,125,"Format version","Package meta data"},
  {-1,126,"Television system","Package meta data"},
  {-1,127,"Song count","Package meta data"},
  {-1,128,"Starting song","Package meta data"},
  {-1,129,"Hardware dependency","Package meta data"},
  {-1,130,"Ripper","Package meta data"},
  {-1,131,"Filesize","Package meta data"},
  {-1,-1,NULL,NULL}
  };

#define MAXCMD	65536

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

/*********************************************************
 GetKey(): Given a libextractor index, return the index
 in the KeywordType array.
 Check if a keytype is in the DB key table.  Add it if necessary.
 Returns index into KeywordType array.
 *********************************************************/
int	GetKey	(int LEIndex)
{
  int rc;
  int K;	/* key index */

  /* Find the entry in the keyword table */
  for(K=0; KeywordTypes[K].Label && (KeywordTypes[K].KeyIndex != LEIndex); K++)
	;
  if (KeywordTypes[K].KeyIndex < 0) K=0;

  if (!DB) return(K);

  /* Check for pfile first */
  if (KeywordPkgMeta.DBIndex < 0)
    {
    memset(SQL,0,sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"SELECT key_pk FROM key WHERE key_name = '%s' AND key_parent_fk = 0;",
	KeywordPkgMeta.Label);
    rc = DBaccess(DB,SQL);
    if (rc < 0)
	{
	printf("ERROR pfile %s Unable to access database.\n",getenv("ARG_akey"));
	printf("LOG pfile %s ERROR: %s\n",getenv("ARG_akey"),SQL);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
    if (DBdatasize(DB) <= 0)
	{
	memset(SQL,0,sizeof(SQL));
	snprintf(SQL,sizeof(SQL),"INSERT INTO key (key_name,key_desc,key_parent_fk,key_agent_fk) values ('%s','%s',0,%d);",
		KeywordPkgMeta.Label,KeywordPkgMeta.Desc,Agent_pk);
	rc = DBaccess(DB,SQL);
        if (rc < 0)
		{
		printf("ERROR pfile %s Unable to access database.\n",getenv("ARG_akey"));
		printf("LOG pfile %s ERROR: %s\n",getenv("ARG_akey"),SQL);
		fflush(stdout);
		DBclose(DB);
		exit(-1);
		}
	DBaccess(DB,"SELECT currval('key_key_pk_seq'::regclass);");
	}
    KeywordPkgMeta.DBIndex = atoi(DBgetvalue(DB,0,0));
    }

  /* Now get the actual entry */
  if (KeywordTypes[K].DBIndex < 0)
    {
    memset(SQL,0,sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"SELECT key_pk FROM key WHERE key_name = '%s' AND key_parent_fk = %d;",
	KeywordTypes[K].Label,KeywordPkgMeta.DBIndex);
    rc = DBaccess(DB,SQL);
    if (rc < 0)
	{
	printf("ERROR pfile %s Unable to access database.\n",getenv("ARG_akey"));
	printf("LOG pfile %s ERROR: %s\n",getenv("ARG_akey"),SQL);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
    if (DBdatasize(DB) <= 0)
	{
	memset(SQL,0,sizeof(SQL));
	snprintf(SQL,sizeof(SQL),"INSERT INTO key (key_name,key_desc,key_parent_fk,key_agent_fk) values ('%s','%s',%d,%d);",
		KeywordTypes[K].Label,KeywordTypes[K].Desc,
		KeywordPkgMeta.DBIndex,Agent_pk);
	DBaccess(DB,SQL);
	DBaccess(DB,"SELECT currval('key_key_pk_seq'::regclass);");
	}
    KeywordTypes[K].DBIndex = atoi(DBgetvalue(DB,0,0));
    }
  return(K);
} /* GetKey() */

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
 PrintKeys(): Display the keywords from a file.
 (My replacement for EXTRACTOR_printKeywords.)
 *********************************************************/
void	PrintKeys	(EXTRACTOR_KeywordList *keywords)
{
  int K;
  char SQL[8192];

  for( ; keywords; keywords=keywords->next)
    {
    K = GetKey(keywords->keywordType);

    if (!DB)
      {
      printf("%d: %s = ",KeywordTypes[K].KeyIndex,TaintString(KeywordTypes[K].Label));
      printf("'%s'\n",TaintString(keywords->keyword));
      }
    else
      {
      /* Insert record into database.  Ignore insert errors. */
      memset(SQL,'\0',sizeof(SQL));
      snprintf(SQL,sizeof(SQL),"INSERT INTO attrib (attrib_key_fk,attrib_value,pfile_fk) VALUES ('%d','%s','%s');",
	KeywordTypes[K].DBIndex,
	TaintString(keywords->keyword),
	getenv("ARG_akey"));
      DBaccess(DB,SQL);
      }
    } /* for() */
} /* PrintKeys() */

/**********************************************
 ReadLine(): Read a command from stdin.
 If the line is empty, then try again.
 Returns line length, or -1 of EOF.
 **********************************************/
int     ReadLine (FILE *Fin, char *Line, int MaxLine)
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
  strcpy(Field,"ARG_");
  f=4; v=0;

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
 environment variables.
 Env = what to do: 0=unsetenv(Field), 1=setenv(Field=Value), -1=nothing
 **********************************************/
void    SetEnv  (char *S, int Env)
{
  char Field[256];
  char Value[1024];
  int GotA=0;
  int GotAkey=0;
  int GotOther=0;
  char *OrigS;

  OrigS=S;

  while(S && (S[0] != '\0'))
    {
    S = GetFieldValue(S,Field,256,Value,1024);
    if (Value[0] != '\0')
      {
      switch(Env)
        {
        case 0: unsetenv(Field);        break;
        case 1:
		setenv(Field,Value,1);
		if (!strcmp(Field,"ARG_a")) GotA=1;
		else if (!strcmp(Field,"ARG_akey")) GotAkey=1;
		else GotOther=1;
		break;
        default:        break;
        }
      }
    }

  if (Env && (!GotA || !GotAkey || GotOther))
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

  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='pkgmetagetta' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'pkgmetagetta' from the database table 'agent'\n");
	DBclose(DB);
	exit(-1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('pkgmetagetta','unknown','Load attributes with meta data from pfile');");
      if (rc < 0)
	{
	printf("ERROR: unable to write to the database\n");
	printf("LOG: unable to write 'pkgmetagetta' to the database table 'agent'\n");
	DBclose(DB);
	exit(-1);
	}
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='pkgmetagetta' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'pkgmetagetta' from the database table 'agent'\n");
	DBclose(DB);
	exit(-1);
	}
      }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
} /* GetAgentKey() */

/*********************************************************
 Usage():
 *********************************************************/
void    Usage   (char *Name)
{
  printf("Usage: %s [-i] [file.spec [file.spec [...]]\n",Name);
  printf("  -i        :: initialize the database, then exit.\n");
  printf("  file.spec :: if files are listed, display their meta data.\n");
  printf("  no file   :: process data from the scheduler.\n");
} /* Usage() */


/*********************************************************/
int	main	(int argc, char *argv[])
{
  int arg;
  int c;
  EXTRACTOR_ExtractorList *extractors;
  EXTRACTOR_KeywordList *keywords;
  char Parm[MAXCMD];
  char *Path;
  char *Env;
  int rc;

  /* Init extractor */
  extractors = EXTRACTOR_loadDefaultLibraries();
  if (!extractors)
    {
    fprintf(stderr,"FATAL: Failed to load default extractor libraries.\n");
    exit(-1);
    }

  /* Process command-line */
  while((c = getopt(argc,argv,"i")) != -1)
    {
    switch(c)
	{
	case 'i':
		DB = DBopen();
		if (!DB)
			{
			printf("FATAL: Unable to connect to database\n");
			exit(-1);
			}
		GetAgentKey();
		/* insert EVERY meta type */
		for(c=0; KeywordTypes[c].Label; c++)
			GetKey(KeywordTypes[c].KeyIndex);
		DBclose(DB);
		return(0);
	default:
		Usage(argv[0]);
		exit(-1);
	}
    }

  /* Process each file */
  for(arg=optind; arg < argc; arg++)
    {
    printf("# File: %s\n",argv[arg]);
    /***
     Here's the problem: EXTRACTOR_getKeywords can crash on some files.
     Bugs have been submitted, but until a fix comes along, we need to
     work around the crash.
     The workaround: fork() the analysis.  If the child crashes, then it
     won't hurt the parent.
     ***/
    rc = fork();
    if (rc == 0)
	{
	/* Child does the work */
	keywords = EXTRACTOR_getKeywords(extractors,argv[arg]);
	/* Use my own print since I don't like EXTRACTOR_printKeywords */
	PrintKeys(keywords);
	EXTRACTOR_freeKeywords(keywords);
	exit(-1);
	}
    else
	{
	/* Wait for the child to finish */
	int Status;
	waitpid(rc,&Status,0);
	}
    /* Clean up */
    }

  /* No args?  Run from schedule! */
  if (optind == argc)
    {
    DB = DBopen();
    if (!DB)
	{
	printf("FATAL: Unable to connect to database\n");
	exit(-1);
	}
    GetAgentKey();

    signal(SIGALRM,ShowHeartbeat);
    alarm(60);

    printf("OK\n"); /* inform scheduler that we are ready */
    fflush(stdout);
    while(ReadLine(stdin,Parm,MAXCMD) >= 0)
      {
      SetEnv(Parm,1); /* set environment (as appropriate) */
      if (Parm[0] != '\0')
	{
	/* Process the repository file */
        alarm(0);  /* allow the scheduler to tell when this hangs */
	/** Find the path **/
	Env = getenv("ARG_a");
	if (!Env)
	  {
	  printf("ERROR pfile %s Unable to process.\n",Env);
	  printf("LOG pfile %s ",getenv("ARG_akey"));
	  printf("File '%s' not found.\n",getenv("ARG_a"));
	  fflush(stdout);
	  DBclose(DB);
	  exit(-1);
	  }
	Path = RepMkPath("files",Env);
	if (Path && RepExist("files",Env))
	  {
	  rc = fork();
	  if (rc == 0)
	    {
	    keywords = EXTRACTOR_getKeywords(extractors,Path);
	    /* Save results to the DB */
	    PrintKeys(keywords);
	    /* Mark it as processed */
	    memset(SQL,0,sizeof(SQL));
	    snprintf(SQL,sizeof(SQL),"INSERT INTO attrib (attrib_key_fk,attrib_value,pfile_fk) VALUES ('%d','true','%s');",KeywordTypes[GetKey(-2)].DBIndex,getenv("ARG_akey"));
	    DBaccess(DB,SQL);
	    /* Done */
	    EXTRACTOR_freeKeywords(keywords);
	    exit(-1);
	    }
	  else
	    {
	    int Status;
	    waitpid(rc,&Status,0);
	    }
	  }
	else
	  {
	  printf("ERROR pfile %s Unable to process.\n",getenv("ARG_akey"));
	  printf("LOG pfile %s ",getenv("ARG_akey"));
	  printf("File '%s' not found.\n",getenv("ARG_a"));
	  fflush(stdout);
	  DBclose(DB);
	  exit(-1);
	  }
	printf("OK\n"); /* inform scheduler that we are ready */
	alarm(60);
	fflush(stdout);
	}
      SetEnv(Parm,0); /* clear environment (as appropriate) */
      }
    DBclose(DB);
    }

  /* Clean up */
  EXTRACTOR_removeAll(extractors);
  return(0);
} /* main() */

