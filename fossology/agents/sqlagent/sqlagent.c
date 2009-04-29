/***************************************************************
 sqlagent: Generic agent for processing an SQL query.

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

#include "libfossdb.h"
#include "libfossagent.h"

#define MAXCMD 4096

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

void *DB=NULL;

/**********************************************
 MatchField(): Given a string that contains
 field='value' pairs, check if the field name
 matches.
 Returns: 1 on match, 0 on miss, -1 on no data.
 **********************************************/
int	MatchField	(char *Field, char *S)
{
  int Len;
  if (!S || (S[0]=='\0')) return(-1);
  while(isspace(S[0])) S++;
  Len = strlen(Field);
  if (!strncmp(Field,S,Len))
	{
	/* Matched string, now make sure it is a real match */
	while(isspace(S[Len])) Len++;
	if (S[Len]=='=') return(1);
	}
  return(0);
} /* MatchField() */

/**********************************************
 SkipFieldValue(): Given a string that contains
 field='value' pairs, skip the first pair and
 return the pointer to the next pair (or NULL if
 end of string).
 **********************************************/
char *	SkipFieldValue	(char *S)
{
  char Quote;

  if (!S || (S[0]=='\0')) return(NULL);

  /* Skip the field */
  while((S[0] != '\0') && (S[0]!='=')) S++; /* skip until the '=' is found */
  if (S[0]=='\0') return(NULL);
  S++; /* skip the '=' */
  while(isspace(S[0])) S++; /* Skip any spaces */
  if (S[0]=='\0') return(NULL);

  /* Now for the fun part... Skip the Value.  This may be quoted. */
  switch(S[0])
    {
    case '\"': case '\'':
	Quote=S[0];
	S++;
	break;
    default:
	Quote=' ';
	break;
    }
  while((S[0]!='\0') && (S[0]!=Quote))
	{
	if (S[0]=='\\') { S+=2; }
	else S++;
	}
  if (S[0]==Quote) S++;
  while(isspace(S[0])) S++; /* Skip any spaces */
  return(S);
} /* SkipFieldValue() */

/**********************************************
 UntaintValue(): The scheduler taints field=value
 pairs.  Given a pair, return the untainted value.
 NOTE: In string and out string CAN be the same string!
 NOTE: strlen(Sout) is ALWAYS < strlen(Sin).
 Returns Sout, or NULL if there is an error.
 **********************************************/
char *	UntaintValue	(char *Sin, char *Sout)
{
  char Quote;

  /* Skip the field */
  while((Sin[0] != '\0') && (Sin[0]!='=')) Sin++; /* skip until the '=' is found */
  if (Sin[0]=='\0') return(NULL);
  Sin++; /* skip the '=' */
  while(isspace(Sin[0])) Sin++; /* Skip any spaces */
  if (Sin[0]=='\0') { Sout[0]='\0'; return(NULL); }

  /* The value may be inside quotes */
  switch(Sin[0])
    {
    case '\"': case '\'':
	Quote=Sin[0];
	Sin++;
	break;
    default:
	Quote=' ';
	break;
    }

  /* Now we're ready to untaint the value */
  while((Sin[0]!='\0') && (Sin[0]!=Quote))
	{
	if (Sin[0]=='\\')
	  {
	  Sin++; /* skip quote char */
	  if (Sin[0]=='n') { Sout[0]='\n'; }
	  else if (Sin[0]=='r') { Sout[0]='\r'; }
	  else if (Sin[0]=='a') { Sout[0]='\a'; }
	  else { Sout[0]=Sin[0]; }
	  Sout++;
	  Sin++; /* skip processed char */
	  }
	else
	  {
	  Sout[0] = Sin[0];
	  Sin++;
	  Sout++;
	  };
	}
  Sout[0]='\0'; /* terminate string */
  return(Sout);
} /* UntaintValue() */

/**********************************************
 SetParm(): Convert field=value pairs into parameter.
 This overwrites the parameter string!
 The parameter is untainted from the scheduler.
 Returns 1 if Parm is set, 0 if not.
 **********************************************/
int	SetParm	(char *ParmName, char *Parm)
{
  int rc;
  char *OldParm;
  OldParm=Parm;
  if (!ParmName || (ParmName[0]=='\0')) return(1); /* no change */
  if (!Parm || (Parm[0]=='\0')) return(1); /* no change */

  /* Find the parameter */
  while(!(rc=MatchField(ParmName,Parm)))
    {
    Parm = SkipFieldValue(Parm);
    }
  if (rc != 1) return(0); /* no match */

  /* Found it!  Set the value */
  UntaintValue(Parm,OldParm);
  return(1);
} /* SetParm() */


/*********************************************************
 Usage():
 *********************************************************/
void    Usage   (char *Name)
{
  printf("Usage: %s [options]\n",Name);
  printf("  -i        :: initialize the database, then exit.\n");
  printf("  -a arg    :: Expect SQL in parameter 'arg='.\n");
  printf("  no file   :: process data from the scheduler.\n");
} /* Usage() */

/*********************************************************/
int	main	(int argc, char *argv[])
{
  char Parm[MAXCMD];
  char *ParmName=NULL;
  int c;

  DB = DBopen();
  if (!DB)
	{
	printf("FATAL: Unable to connect to database\n");
	fflush(stdout);
	exit(-1);
	}
  GetAgentKey(DB, 0, SVN_REV);

  /* Process command-line */
  while((c = getopt(argc,argv,"a:i")) != -1)
    {
    switch(c)
	{
	case 'a':
		ParmName = optarg;
		break;
	case 'i':
		/* GetAgentKey() already processed */
		DBclose(DB);
		return(0);
	default:
		Usage(argv[0]);
		fflush(stdout);
		DBclose(DB);
		exit(-1);
	}
    }

#if 0
  /* Process each file */
  for(arg=optind; arg < argc; arg++)
    {
    printf("# Arg: %s\n",argv[arg]);
    }

  /* No args?  Run from schedule! */
  if (argc == 1)
#endif
    {
    signal(SIGALRM,ShowHeartbeat);
    alarm(60);
    printf("OK\n"); /* inform scheduler that we are ready */
    fflush(stdout);
    while(ReadLine(stdin,Parm,MAXCMD) >= 0)
      {
      if (SetParm(ParmName,Parm) && (Parm[0] != '\0'))
	{
	/* Process the repository file */
	switch(DBaccess(DB,Parm))
	  {
	  case -1:	/* duplicate constraint error */
	  	/* ignore it */
		printf("WARNING: SQL had duplicate constraint in sqlagent: '%s'\n",Parm);
		break;
	  case 0:	/* no error */
	  case 1:	/* no error */
		break;
	  default:
		printf("ERROR: SQL failed.\n");
		printf("LOG: SQL failed in sqlagent: '%s'\n",Parm);
		fflush(stdout);
		DBclose(DB);
		exit(-1);
	  }
	} /* if SetParm */
      printf("OK\n"); /* inform scheduler that we are ready */
      fflush(stdout);
      } /* while() */
    }

  DBclose(DB);
  return(0);
} /* main() */

