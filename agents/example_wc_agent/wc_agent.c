/*****************************************************************
 wc_agent: the word count agent
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
 
 *************************

 Example wc agent, written in shell script.
 This should be used directly from the scheduler.
 An example scheduler.conf line:
	agent=wc | /usr/local/fossology/agents/wc_agent
 Each line from the scheduler should have two components:
   pfile= and pfile_fk=
 ****************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>

#include "libfossrepo.h"
#include "libfossdb.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

#define MAXCMD  2048

/* for the DB */
void *DB=NULL;
int Agent_pk=-1;        /* agent identifier */

/* input for this system */
long GlobalPfileFk=-1;	/* the pfile_fk to process */
char GlobalPfile[MAXCMD];	/* the pfile (sha1.md5.len) to process */

/* for heartbeat checking */
long    HeartbeatCount=-1;      /* used to flag heartbeats */
long    HeartbeatCounter=-1;    /* used to count heartbeats */

/**************************************************
 ShowHeartbeat(): Given an alarm signal, display a
 heartbeat.  This allows the scheduler to tell when
 the agent is hung.
 **************************************************/
void	ShowHeartbeat	(int Sig)
{
  if ((HeartbeatCount==-1) || (HeartbeatCount != HeartbeatCounter))
    {
    printf("Heartbeat\n");
    fflush(stdout);
    }
  /* re-schedule itself */
  HeartbeatCounter=HeartbeatCount;
  alarm(60);
} /* ShowHeartbeat() */

/**********************************************
 ReadLine(): Read a command from a stream.
 If the line is empty, then try again.
 Returns line length, or -1 of EOF.
 **********************************************/
int	ReadLine	(FILE *Fin, char *Line, int MaxLine)
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

/**********************************************
 GetFieldValue(): Given a string that contains
 field='value' pairs, save the items.
 Returns: pointer to start of next field, or NULL at \0.
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
 SetEnv(): Convert input pairs from the scheduler
 into globals.
 **********************************************/
void	SetEnv	(char *S)
{
  char Field[256];
  char Value[1024];
  int GotOther=0;
  char *OrigS;

  GlobalPfileFk = -1;
  memset(GlobalPfile,'\0',MAXCMD);
  if (!S) return;
  OrigS=S;

  while(S && (S[0] != '\0'))
    {
    S = GetFieldValue(S,Field,256,Value,1024,'=');
    if (Value[0] != '\0')
        {
        if (!strcasecmp(Field,"pfile_fk")) GlobalPfileFk=atol(Value);
        else if (!strcasecmp(Field,"pfile")) strncpy(GlobalPfile,Value,sizeof(GlobalPfile));
        else GotOther=1;
        }
    }

  if (GotOther || (GlobalPfileFk < 0) || (GlobalPfile[0]=='\0'))
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
 Every agent should be defined by a key in the database.
 This gets the key, or sets it if the key is missing.
 -----
 If you use this code for your own agent, be sure to use a
 unique agent name...
 Don't forget to change "wc_agent" to something else.
 *********************************************************/
void	GetAgentKey	()
{
  int rc;

  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='wc_agent' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	printf("ERROR: Unable to access the database\n");
	printf("LOG: Unable to select wc_agent from the database table agent\n");
	DBclose(DB);
	exit(-1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      /**** Give the agent a good description ****/
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('wc_agent','unknown','Computes number of words and lines in a file');");
      if (rc < 0)
	{
	printf("ERROR: Unable to write to the database\n");
	printf("LOG: Unable to write wc_agent to the database table agent\n");
	DBclose(DB);
	exit(-1);
	}
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='wc_agent' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	printf("ERROR: Unable to access the database\n");
	printf("LOG: Unable to select wc_agent from the database table agent\n");
	DBclose(DB);
	exit(-1);
	}
      }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
} /* GetAgentKey() */

/***********************************************
 ProcessData(): This function does the work.
 In this example, we'll just run wc and store the
 results in the database.
 Requires: DB open and ready.
 Returns 0 on success, != 0 on failure.
 ***********************************************/
int	ProcessData	(long PfileFk, char *Pfile)
{
  char *RepFile;
  char Cmd[MAXCMD];
  char SQL[MAXCMD];
  long Bytes,Words,Lines;
  FILE *Fin;

  /* Get the path to the actual file */
  RepFile = RepMkPath("files",Pfile);
  if (!RepFile)
	{
	printf("FATAL pfile %ld Word count unable to open file.\n",GlobalPfileFk);
	printf("LOG pfile %ld Word count unable to open file: pfile_fk=%ld pfile=%s\n",GlobalPfileFk,GlobalPfileFk,GlobalPfile);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}

  /* Create the command to run */
  memset(Cmd,'\0',MAXCMD);
  snprintf(Cmd,MAXCMD,"/usr/bin/wc '%s' 2>/dev/null",RepFile);

  /* Run the command and read the results */
  Fin = popen(Cmd,"r");
  if (!Fin)
	{
	printf("FATAL pfile %ld Word count unable to count words.\n",GlobalPfileFk);
	printf("LOG pfile %ld Word count unable to run command: %s\n",GlobalPfileFk,Cmd);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}

  /* Read the results */
  fscanf(Fin,"%ld %ld %ld",&Lines,&Words,&Bytes);

  /* Store the results */
  if (!DB)
    {
    printf("%s:  Bytes=%ld  Words=%ld  Lines=%ld\n",Pfile,Bytes,Words,Lines);
    }
  else
    {
    /* Store results in the database */
    memset(Cmd,'\0',MAXCMD);
    snprintf(Cmd,MAXCMD,"INSERT INTO agent_wc (pfile_fk,wc_words,wc_lines) VALUES (%ld,%ld,%ld);",
	PfileFk,Words,Lines);
    switch(DBaccess(DB,SQL))
	{
	case 1: /* Select worked! */
	case 0: /* Insert worked! */
	case -1: /* Constraint error */
		/* Do nothing */
		break;
	case -2: /* Other error */
	case -3: /* Timeout */
	default: /* any other error */
		printf("FATAL pfile %ld Database insert failed.\n",GlobalPfileFk);
		printf("LOG pfile %ld Database insert failed: %s\n",GlobalPfileFk,SQL);
		fflush(stdout);
		DBclose(DB);
		exit(-1);
	}
    }

  /* Clean up */
  pclose(Fin);
  free(RepFile);
  return(0);
} /* ProcessData() */

/***********************************************
 Usage(): Say how to run this program.
 Many agents permit running from the command-line
 for testing.
 At minimum, you need "-i" to initialize the DB and exit.
 ***********************************************/
void	Usage	(char *Name)
{
  printf("Usage: %s [options]\n",Name);
  printf("  -i  :: Initialize the DB connection then exit (nothing downloaded)\n");
} /* Usage() */

/*********************************************************/
/*********************************************************/
/*********************************************************/
int	main	(int argc, char *argv[])
{
  int c;
  int InitFlag=0; /* is the system just going to initialize? */
  char Parm[MAXCMD];

  /* Process command-line */
  while((c = getopt(argc,argv,"i")) != -1)
    {
    switch(c)
	{
	case 'i':
		InitFlag=1;
		break;
	default:
		Usage(argv[0]);
		exit(-1);
	}
    }

  /* Init */
  DB = DBopen();
  if (!DB)
	{
	printf("FATAL: Unable to connect to database\n");
	fflush(stdout);
	exit(-1);
	}
  GetAgentKey();

  /* When initializing the DB, don't do anything else */
  if (InitFlag)
	{
	DBclose(DB);
	return(0);
	}

  /* Run from scheduler! */
  if (optind == argc)
    {
    signal(SIGALRM,ShowHeartbeat);

    printf("OK\n"); /* inform scheduler that we are ready */
    fflush(stdout);
    HeartbeatCount=-1;
    alarm(60);
    while(ReadLine(stdin,Parm,MAXCMD) >= 0)
	{
	if (Parm[0] != '\0')
	  {
	  alarm(0);       /* allow scheduler to know if this hangs */
	  HeartbeatCount=0;
	  HeartbeatCounter=-1;
	  /* 2 parameters: pfile_fk and pfile */
	  SetEnv(Parm); /* set globals */
	  if (ProcessData(GlobalPfileFk,GlobalPfile) != 0)
	    {
	    printf("FATAL pfile %ld Word count failed.\n",GlobalPfileFk);
	    printf("LOG pfile %ld Word count failed: pfile_fk=%ld pfile=%s\n",GlobalPfileFk,GlobalPfileFk,GlobalPfile);
	    fflush(stdout);
	    DBclose(DB);
	    exit(-1);
	    }
	  printf("OK\n"); /* inform scheduler that we are ready */
	  fflush(stdout);
	  alarm(60);
	  }
	}
    } /* if run from scheduler */

  /* Clean up */
  DBclose(DB);
  return(0);
} /* main() */

