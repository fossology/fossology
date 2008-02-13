/***************************************************************
 specagent: Get meta data from a spec file.

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

 Normally, the pkgmetagetta agent pulls out meta data from an RPM file.
 However, what if the thing being analyzed is a pre-packaged RPM?
 In this case, there is no "RPM" to analyze.  But all of the meta data
 IS available in a file called "*.spec".  (E.g., if the directory is
 "neal" then the file is "neal.spec".)
 This agent identifies spec files and processes the meta data. 
 ***************************************************************/
#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <extractor.h>

#include "libfossrepo.h"
#include "libfossdb.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

char SQL[256];

void *DB=NULL;
int Agent_pk=-1;	/* agent table */

struct KeyType
  {
  int DBIndex;  /* the database attribute key_pk */
  int KeyIndex; /* the libextractor code, or -1 for end of list */
  char *Label;
  char *Desc;
  };
typedef struct KeyType KeyType;

KeyType KeywordMeta = { -1,-1,"specagent","Spec file meta data" };
KeyType KeywordTypes[] = {
  {-1,-2,"Processed","Spec file meta data processed"},
  {-1,0,"Requires","Runtime dependency"}, /* this is the default */
  {-1,1,"Name","Package name"},
  {-1,2,"Epoch","Package epoch"},
  {-1,3,"Version","Package version"},
  {-1,4,"Release","Package release information"},
  {-1,5,"Vendor","Package vendor"},
  {-1,6,"URL","Project home"},
  {-1,7,"Copyright","Overall product copyright"},
  {-1,8,"License","Overall product license"},
  {-1,9,"Distribution","Overall product distribution"},
  {-1,10,"Packager","Individual who created the package"},
  {-1,11,"Group","Product group"},
  {-1,12,"Icon","Product icon"},
  {-1,13,"Summary","Short description"},
  {-1,14,"Obsoletes","Obsoleted by the package"},
  {-1,15,"Provides","Delivered by the package"},
  {-1,-1,NULL,NULL}
  };

#define MAXCMD	2048

int Akey=-1;
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
int	ReadLine	(FILE *Fin, char *Line, int MaxLine)
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
char *	GetFieldValue	(char *Sin, char *Field, int FieldMax,
			 char *Value, int ValueMax, char Separator)
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
  if (Sin[s] != Separator) /* if it is not a field, then just return it. */
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
void	SetEnv	(char *S)
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
    S = GetFieldValue(S,Field,256,Value,1024,'=');
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
 DBGetKey(): Given a field name, return the index in the KeywordType array.
 Check if a keytype is in the DB key table.  Add it if necessary.
 Returns index into KeywordType array.
 *********************************************************/
int	DBGetKey	(int Index)
{
  int rc;

  if (!DB) return(Index);

  /* Check for pfile first */
  if (KeywordMeta.DBIndex < 0)
    {
    memset(SQL,0,sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"SELECT key_pk FROM key WHERE key_name = '%s' AND key_parent_fk = 0;",
	KeywordMeta.Label);
    rc = DBaccess(DB,SQL);
    if (rc < 0)
	{
	printf("ERROR pfile %d Unable to access database.\n",Akey);
	printf("LOG pfile %d ERROR: %s\n",Akey,SQL);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
    if (DBdatasize(DB) <= 0)
	{
	memset(SQL,0,sizeof(SQL));
	snprintf(SQL,sizeof(SQL),"INSERT INTO key (key_name,key_desc,key_parent_fk,key_agent_fk) values ('%s','%s',0,%d);",
		KeywordMeta.Label,KeywordMeta.Desc,Agent_pk);
	DBaccess(DB,SQL);
	DBaccess(DB,"SELECT currval('key_key_pk_seq'::regclass);");
	}
    KeywordMeta.DBIndex = atoi(DBgetvalue(DB,0,0));
    }

  /* Now get the actual entry */
  if (KeywordTypes[Index].DBIndex < 0)
    {
    memset(SQL,0,sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"SELECT key_pk FROM key WHERE key_name = '%s' AND key_parent_fk = %d;",
	KeywordTypes[Index].Label,KeywordMeta.DBIndex);
    rc = DBaccess(DB,SQL);
    if (rc < 0)
	{
	printf("ERROR pfile %d Unable to access database.\n",Akey);
	printf("LOG pfile %d ERROR: %s\n",Akey,SQL);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
    if (DBdatasize(DB) <= 0)
	{
	memset(SQL,0,sizeof(SQL));
	snprintf(SQL,sizeof(SQL),"INSERT INTO key (key_name,key_desc,key_parent_fk,key_agent_fk) values ('%s','%s',%d,%d);",
		KeywordTypes[Index].Label,KeywordTypes[Index].Desc,
		KeywordMeta.DBIndex,Agent_pk);
	DBaccess(DB,SQL);
	DBaccess(DB,"SELECT currval('key_key_pk_seq'::regclass);");
	}
    KeywordTypes[Index].DBIndex = atoi(DBgetvalue(DB,0,0));
    }
  return(KeywordTypes[Index].DBIndex);
} /* DBGetKey() */

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
 *********************************************************/
void	PrintKeys	(char *Filename, int SaveToDB)
{
  FILE *Fin;
  char Line[MAXCMD];
  int Len;
  int Key;
  int i;

  memset(Line,'\0',MAXCMD);
  snprintf(Line,MAXCMD-1,"/usr/bin/rpm -q --queryformat 'Name: %%{Name}\\nEpoch: %%{Epoch}\\nVersion: %%{Version}\\nRelease: %%{Release}\\nVendor: %%{Vendor}\\nURL: %%{URL}\\nCopyright: %%{Copyright}\\nLicense: %%{License}\\nDistribution: %%{Distribution}\\nPackager: %%{Packager}\\nGroup: %%{Group}\\nIcon: %%{Icon}\\nSummary: %%{Summary}\\nObsoletes: %%{Obsoletes}\\nProvides: %%{Provides}\\nSource: %%{Source}\\nPatch: %%{Patch}\\n' -R --specfile '%s' 2>/dev/null | /bin/grep -v '(none)'",
    Filename);

  Fin = popen(Line,"r");
  if (!Fin)
    {
    perror("ERROR");
    printf("ERROR pfile %d Unable to process file.\n",Akey);
    printf("LOG pfile %d Unable to process command: %s\n",Akey,Line);
    fflush(stdout);
    DBclose(DB);
    exit(-1);
    }

  while(ReadLine(Fin,Line,MAXCMD) > 0)
    {
    /* Remove extra spaces */
    Len = strlen(Line);
    while((Len > 0) && isspace(Line[Len-1])) { Line[Len-1]='\0'; Len--; }

    /* Find which type it matched */
    Key=-1;
    for(i=0; (Key == -1) && KeywordTypes[i].Label; i++)
      {
      Len = strlen(KeywordTypes[i].Label);
      if ((Line[Len] == ':') && (!strncmp(Line,KeywordTypes[i].Label,Len)))
	{
	Key = i;
	}
      }
    if (Key == -1) { Key=1; Len = 0; } /* default to "requires" */
    else
	{
	Len++; /* skip the ':' */
	Len++; /* skip the ' ' */
	}
    /* Convert Key to Database ID */
    DBGetKey(Key);

    if (SaveToDB)
      {
      /* Save the attribute to the database */
      memset(SQL,'\0',sizeof(SQL));
      snprintf(SQL,sizeof(SQL),"INSERT INTO attrib (attrib_key_fk,attrib_value,pfile_fk) VALUES ('%d','%s','%d');",
	KeywordTypes[Key].DBIndex,
	TaintString(Line+Len),
	Akey);
      DBaccess(DB,SQL);
      }
    else
	{
	/* Show results to the screen */
	printf("%s: '%s' at DBindex %d\n",
	  KeywordTypes[Key].Label,Line+Len,KeywordTypes[Key].DBIndex);
	}
    }
  pclose(Fin);
} /* PrintKeys() */

/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 *********************************************************/
void	GetAgentKey	()
{
  int rc;

  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='specagent' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'specagent' from the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('specagent','unknown','Analyze source rpm .spec files');");
      if (rc < 0)
	{
	printf("ERROR: unable to write to the database\n");
	printf("LOG: unable to write 'specagent' to the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='specagent' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'specagent' from the database table 'agent'\n");
	fflush(stdout);
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
  char Parm[MAXCMD];
  char *Path;
  int c;

  DB = DBopen();
  if (!DB)
	{
	printf("FATAL: Unable to connect to database\n");
	fflush(stdout);
	exit(-1);
	}
  GetAgentKey();

  /* Process command-line */
  while((c = getopt(argc,argv,"i")) != -1)
    {
    switch(c)
	{
	case 'i':
		/* insert EVERY meta type */
		for(c=0; KeywordTypes[c].Label; c++) DBGetKey(c);
		DBclose(DB);
		return(0);
	default:
		Usage(argv[0]);
		fflush(stdout);
		DBclose(DB);
		exit(-1);
	}
    }

  /* Process each file */
  for(arg=optind; arg < argc; arg++)
    {
    printf("# File: %s\n",argv[arg]);
    PrintKeys(argv[arg],0);
    }

  /* No args?  Run from schedule! */
  if (argc == 1)
    {
    signal(SIGALRM,ShowHeartbeat);
    printf("OK\n"); /* inform scheduler that we are ready */
    fflush(stdout);
    while(ReadLine(stdin,Parm,MAXCMD) >= 0)
      {
      SetEnv(Parm); /* set environment (as appropriate) */
      if (Parm[0] != '\0')
	{
	/* Process the repository file */
	alarm(0);	/* allow scheduler to tell if this hangs */
	/** Find the path **/
	Path = RepMkPath("files",A);
	if (Path && RepExist("files",A))
	  {
	  /* Save results to the DB */
	  PrintKeys(Path,1);
	  /* Mark it as processed */
	  memset(SQL,0,MAXCMD);
	  snprintf(SQL,MAXCMD-1,"INSERT INTO attrib (attrib_key_fk,attrib_value,pfile_fk) VALUES ('%d','true','%d');",DBGetKey(0),Akey);
	  DBaccess(DB,SQL);
	  /* Done */
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
    }

  DBclose(DB);
  return(0);
} /* main() */

