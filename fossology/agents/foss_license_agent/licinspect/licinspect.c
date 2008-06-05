/**************************************************************
 licinspect: License term inspection.
 
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
 **************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <errno.h>
#include <sys/mman.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <sys/resource.h>  /* for rlimit */
#include <time.h> /* for time() used when debugging performance */

#include "libfossrepo.h"
#include "libfossdb.h"

#define MAXLINE	2048
#define PATHINC 128	/* how much to increment the Path.PathString */

#ifndef AGENTDATADIR
#define AGENTDATADIR	"/usr/local/share/fossology/agents/licenses"
#endif

/************************************************************
 Globals: used for speed!
 ************************************************************/
/* Repository data */
void	*DB=NULL;	/* database handle */
void	*DBTerms=NULL;	/* database handle */
char	SQL[MAXLINE];	/* current SQL statement */
int	*TermsCounter=NULL; /* how many times has each term been seen? */
int	TermsCounterSize=0; /* how many items allocated to terms counter? */
int	Agent_pk=-1;	/* agent identifier */
long	PfilePk = -1;	/* pfile keys */
char	PfileName[MAXLINE] = "";	/* pfile name */
char	*LicName = NULL;	/* license name */
RepMmapStruct *PfileMmap=NULL;
RepMmapStruct *LicMmap=NULL;

int	Verbose=0;	/* debugging via '-v' */

/* Thresholds for confidence interval */
float	ThresholdSame=98;	/* 98% match == same */
float	ThresholdSimilar=90;	/* 90% match == similar */
float	ThresholdMissing=10;	/* subtract 10% for each missing term */


/**********************************************
 DebugDBaccess(): For debugging.
 **********************************************/
int	DebugDBaccess	(void *a, char *b)
{
  int rc;
  rc = DBaccess(a,b);
  fprintf(stderr,"DEBUG[%d] = %d: '%s'\n",getpid(),rc,b);
  return(rc);
} /* DebugDBaccess() */

long    HeartbeatValue=-1;
long	LastHeartbeatValue=-1;

/**************************************************
 ShowHeartbeat(): Given an alarm signal, display a
 heartbeat.
 **************************************************/
void    ShowHeartbeat   (int Sig)
{

  /* IF we are tracking hearbeat values AND it has not changed,
     THEN don't display a heartbeat message.
     This can happen if I/O is hung, but alarms are still being processed.
   */
  if ((HeartbeatValue == -1) || (HeartbeatValue != LastHeartbeatValue))
    {
    LastHeartbeatValue = HeartbeatValue;
    printf("Heartbeat\n");
    fflush(stdout);
    }

  /* re-schedule itself */
  alarm(60);
} /* ShowHeartbeat() */

/************************************************************/
/************************************************************/
/** Data loading and processing **/
/************************************************************/
/************************************************************/

/**********************************************
 CloseFile(): Close a filename.
 **********************************************/
void	CloseFile	(RepMmapStruct *Rep)
{
  if (Verbose > 1) fprintf(stderr,"Debug: closing\n");
  RepMunmap(Rep);
} /* CloseFile() */

/**********************************************
 OpenFile(): Open and mmap a file.
 Which = load as file 0 or file 1.
 Returns Rep memory structure on success, or NULL on failure.
 **********************************************/
RepMmapStruct *	OpenFile	(char *Filename)
{
  RepMmapStruct *Rep=NULL;
  /* open the file (memory map) */
  if (Verbose > 1) fprintf(stderr,"Debug: opening %s\n",Filename);
  if (PfilePk >= 0)
    {
    /* Check if the file exists before trying to use it. */
    if (!RepExist("files",Filename))
	{
	fprintf(stderr,"WARNING: File not in the repository (%s %s)\n",
		"files",Filename);
	return(NULL);
	}
    Rep = RepMmap("files",Filename);
    if (Rep == NULL)
	{
	/* Not able to open the repository file? */
	/* It is in the repository but cannot be accessed */
	fprintf(stderr,"ERROR: Unable to open repository (%s %s)\n",
		"files",Filename);
	return(NULL);
	}
    } /* if Type is set */
  return(Rep);
} /* OpenFile() */

/**********************************************
 GetFieldValue(): Given a string that contains
 field='value' pairs, save the items.
 Returns: pointer to start of next field, or
 NULL at \0.
 **********************************************/
char *	GetFieldValue	(char *Sin, char *Field, int FieldMax,
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
    s++; /* move past the quote */
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
 ReadLine(): Read a single line from a file.
 Used to read from stdin.
 Process line elements.
 Returns: 1 of read data, 0=no data, -1=EOF.
 **********************************************/
int	ReadLine	(FILE *Fin)
{
  int C='@';
  int i=0;	/* index */
  char FullLine[MAXLINE];
  char Field[MAXLINE];
  char Value[MAXLINE];
  char *FieldInset;
  int rc=0;	/* assume no data */

  memset(FullLine,0,MAXLINE);
  /* inform scheduler that we're ready for data */
  printf("OK\n");
  alarm(60);
  HeartbeatValue = -1;
  fflush(stdout);

  if (feof(Fin))
    {
    return(-1);
    }
  while(!feof(Fin) && (i < MAXLINE-1) && (C != '\n') && (C>0))
    {
    C=fgetc(Fin);
    if ((C>0) && (C!='\n'))
      {
      FullLine[i]=C;
      i++;
      }
    else if ((C=='\n') && (i==0))
      {
      C='@';  /* ignore blank lines */
      }
    }
  if ((i==0) && feof(Fin)) return(-1);

  /* process the line. */
  /** line format: field='value' **/
  /** Known fields:
      A='Afilename in repository'
      Akey='pfile key for A'
   **/
  FieldInset = FullLine;
  rc=0; /* assume no data */
  PfilePk = -1;
  memset(PfileName,'\0',sizeof(PfileName));
  while((FieldInset = GetFieldValue(FieldInset,Field,MAXLINE,Value,MAXLINE)) != NULL)
    {
    /* process field/value */
    if (!strcasecmp(Field,"Akey")) { PfilePk = atol(Value); rc |= 1; }
    if (!strcasecmp(Field,"A")) { strncpy(PfileName,Value,sizeof(PfileName)); rc |= 2; }
    }
  return(rc==3);
} /* ReadLine() */

/************************************************************/
/************************************************************/
/** Load DB info **/
/************************************************************/
/************************************************************/

/*********************************************************
 GetTerms(): Load the list of DB terms from the DB.
 *********************************************************/
void	GetTerms	()
{
  int rc;
  if (DBTerms) DBclose(DBTerms);
  if (TermsCounter) free(TermsCounter);
  DBTerms = NULL;
  TermsCounter = NULL;
  TermsCounterSize = 0;
  rc = DBaccess(DB,"SELECT licterm_words_pk,licterm_words_text FROM licterm_words ORDER BY licterm_words_text DESC;");
  if (rc < 0) return;
  if (DBdatasize(DB) <= 0)
    {
    fprintf(stderr,"ERROR: No terms in the database.\n");
    DBclose(DB);
    exit(-1);
    }
  if (Verbose)
    {
    printf("# Terms loaded: %d\n",DBdatasize(DB));
    }
  TermsCounterSize = DBdatasize(DB);
  TermsCounter = (int *)malloc(TermsCounterSize*sizeof(int));
  DBTerms = DBmove(DB);
} /* GetTerms() */

/*********************************************************
 MatchTerm(): See if a term matches a string.
 - Str must point to the start of the match for Term.
 Returns length of match, or 0 on miss.
 - All comparisons are lowercase.
 - Any space in the term string is treated as one or more "not alnum".
 *********************************************************/
int	MatchTerm	(char *Term, char *Str, long StrLen)
{
  long t,j=0;
  for(t=0; Term[t]; t++)
    {
    if (j >= StrLen) return(0); /* miss: too short */
    if (isspace(Term[t]))
      {
      if (isalnum(Str[j])) return(0); /* miss */
      while((j<StrLen) && !isalnum(Str[j])) j++;
      }
    else
      {
      if (tolower(Term[t]) != tolower(Str[j])) return(0); /* miss */
      j++;
      }
    }
  return(j);
} /* MatchTerm() */

/*********************************************************
 DiscoverTerms(): Given a range, identify all of the matched terms.
 NOTE: This populates TermsCounter with the number of times
 the term is seen.  The value of TermsCounter come from Mask:
   0x00 = term not seen.
   0x01 = term seen in unknown file.
   0x02 = term seen in known (license) file.
 *********************************************************/
void	DiscoverTerms	(long Start, long End, RepMmapStruct *Mmap, int Mask)
{
  int t; /* which term to look for */
  long i; /* which byte to look at */
  int rc;
  i=Start;
  while(i<=End)
    {
    /* validate scan range (should already be valid) */
    if (i < 0) continue;
    if (i >= Mmap->MmapSize) continue;

    /* Check each term for a match */
    rc=0;
    for(t=0; !rc && (t<TermsCounterSize); t++)
      {
      rc = MatchTerm(DBgetvalue(DBTerms,t,1),(char *)(Mmap->Mmap+i),End-i);
      if (rc > 0)
	{
	if (Verbose > 1) printf("Matched: Term='%s' rc=%d\n",DBgetvalue(DBTerms,t,1),rc);
	i+=rc;
	TermsCounter[t] |= Mask;
	}
      }

    /* Increment to next word */
    while((i < Mmap->MmapSize) && isalnum(Mmap->Mmap[i])) i++; /* skip word */
    while((i < Mmap->MmapSize) && !isalnum(Mmap->Mmap[i])) i++; /* skip non-word */
    }
} /* DiscoverTerms() */

/*********************************************************
 ComputeConfidence(): TermsCounter is populated.  Let's
 see what we found.
 The value of TermsCounter come from Mask:
   0x00 = term not seen.
   0x01 = term seen in unknown file.
   0x02 = term seen in known (license) file.
 *********************************************************/
void	ComputeConfidence	(int IsPhrase, float LicPercent, char *LicName)
{
  float ConfidenceValue = 0;
  int t;
  int TermAdded=0;
  int TermRemoved=0;
  int TermSame=0;
  int HasOutput=0;
  char *LicenseName;
  /*
  Here's how the Confidence Value works:
     - Start with the percent match of the license.
     - For each term removed (TermsCounter = 0x02), remove the
       ThresholdMissing percent.
       Thus: if the initial percent was 98% and ThresholdMissing is 10%
       with 2 terms missing, then the value decreases to 78%.
     - If the value >= ThresholdSame, then call it by the license name.
     - If the value >= ThresholdSimilar, then say it is similar to the
       license name.
     - If the value < ThresholdSimilar, but no new license terms added
       (no 0x01 in TermsCounter), then say "partial".
     - If any new terms added (0x01 in TermsCounter), then list those too.

   Phrases are always a 100% match, but terms can override the output.
   */
  ConfidenceValue = LicPercent;
  for(t=0; t<TermsCounterSize; t++)
    {
    switch(TermsCounter[t]) /* two bits of info */
      {
      case 0x00:	/* no terms */
	break;
      case 0x03:	/* no term changes */
	TermSame++;
	break;
      case 0x01:	/* new term */
	TermAdded++;
	if (Verbose) printf("Term added: %s\n",DBgetvalue(DBTerms,t,1));
	break;
      case 0x02:	/* term removed */
	TermRemoved++;
	if (Verbose) printf("Term removed: %s\n",DBgetvalue(DBTerms,t,1));
	ConfidenceValue -= ThresholdMissing;
	break;
      }
    }

  /* See what we got */
  LicenseName = strrchr(LicName,'/');
  if (LicenseName) LicName = LicenseName+1;
  if (!TermRemoved && !IsPhrase)
    {
    HasOutput=1;
    if (ConfidenceValue >= ThresholdSame)
	{
	printf("  %s\n",LicName);
	}
    else if (ConfidenceValue >= ThresholdSimilar)
	{
	printf("  '%s'-style\n",LicName);
	}
    }
  if (TermAdded)
    {
    for(t=0; t<TermsCounterSize; t++)
      {
      if (TermsCounter[t] & 0x01)
	{
	HasOutput=1;
	printf("  %s\n",DBgetvalue(DBTerms,t,1));
	}
      }
    }
  if (!HasOutput)
	{
	if (IsPhrase) printf("  %s\n",LicName);
	else printf("  '%s'-partial\n",LicName);
	}
} /* ComputeConfidence() */

/*********************************************************
 ProcessTerms(): Given a Pfile, identify all of the matched
 ranges (found in the agent_lic_meta table).  Then find all
 of the terms found in the pfile.
 *********************************************************/
void	ProcessTerms	()
{
  int i; /* which byte to look at */
  void *DBRanges;
  char *Range;
  long MaxRanges,Start,End;
  char LicName[MAXLINE];
  float LicPercent;
  int IsPhrase;

  /* Get the list of license segments */
  snprintf(SQL,MAXLINE,"SELECT pfile_path,license_path,lic_name,tok_match,tok_license,lic_unique,tok_pfile_start FROM agent_lic_meta INNER JOIN agent_lic_raw ON lic_pk = lic_fk WHERE pfile_fk = '%ld' ORDER BY tok_pfile_start;",PfilePk);
  DBaccess(DB,SQL);
  DBRanges = DBmove(DB);
  for(MaxRanges=0; MaxRanges < DBdatasize(DBRanges); MaxRanges++)
    {
    /* Determine the license match */
    LicPercent = 100.0 * atof(DBgetvalue(DBRanges,MaxRanges,3)) / atof(DBgetvalue(DBRanges,MaxRanges,4));

    /* Pfile: Load the start and end */
    Range = DBgetvalue(DBRanges,MaxRanges,0);
    Start = atol(Range);
    for(i=strlen(Range); (i>0) && isdigit(Range[i-1]); i--)	;
    End = atoi(Range+i);
    printf("# Section %ld - %ld:\n",Start,End);
    if (Verbose > 1)
	{
	printf("============================================\n");
	printf("%.*s\n",(int)(End-Start),PfileMmap->Mmap + Start);
	printf("============================================\n");
	}

    /* Set counters */
    memset(TermsCounter,0,TermsCounterSize*sizeof(int));
    DiscoverTerms(Start,End,PfileMmap,0x01);

    /* License: Load the start and end */
    /** Phrases do not have a sha1.md5.len unique value **/
    if (strlen(DBgetvalue(DBRanges,MaxRanges,5)) > 72)
      {
      /* not a phrase */
      IsPhrase=0;
      Range = DBgetvalue(DBRanges,MaxRanges,1);
      Start = atol(Range);
      for(i=strlen(Range); (i>0) && isdigit(Range[i-1]); i--)	;
      End = atoi(Range+i);
      snprintf(LicName,sizeof(LicName),"%s/%s",AGENTDATADIR,DBgetvalue(DBRanges,MaxRanges,2));
      LicMmap = RepMmapFile(LicName);
      if (LicMmap)
	{
	DiscoverTerms(Start,End,LicMmap,0x02);
	RepMunmap(LicMmap);
	}
      }
    else
      {
      /* if Phrase */
      IsPhrase=1;
      snprintf(LicName,sizeof(LicName),"%s",DBgetvalue(DBRanges,MaxRanges,2));
      }
    ComputeConfidence(IsPhrase,LicPercent,LicName);
    }

  DBclose(DBRanges);
} /* ProcessTerms() */

/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 TBD: When this engine is used for other things, we will need
 a switch statement for the different types of agents.
 *********************************************************/
void	GetAgentKey	()
{
  int rc;

  /* Order in descenting order, so longest strings come first. */
  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='licinspect' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'licinspect' from the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('licinspect','unknown','Analyze files for licenses');");
      if (rc < 0)
	{
	printf("ERROR: unable to write to the database\n");
	printf("LOG: unable to write 'licinspect' to the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      DBaccess(DB,"ANALYZE agent;");
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='licinspect' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'licinspect' from the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
} /* GetAgentKey() */

/**********************************************
 Usage(): Display program usage.
 **********************************************/
void	Usage	(char *Name)
{
  printf("Usage: %s [options] [pfile_pk [pfile_pk ...]]\n",Name);
  printf("  For debugging, a list of pfile_pk values may be listed on the command-line.\n");
  printf("  Otherwise, stdin is used for communicating with the scheduler.\n");
  printf("  Stdin format: field=value pairs, separated by spaces.\n");
  printf("    A=file        :: set fileA to be a pfile ID or regular file.\n");
  printf("    Akey=file_key :: set fileA pfile ID and this is the pfile_pk.\n");
  printf("  Threshold options:\n");
  printf("    -S ##         :: 'same' threshold %% for license (default: %.0f)\n",ThresholdSame);
  printf("    -s ##         :: 'similar' threshold %% for license (default: %.0f)\n",ThresholdSimilar);
  printf("    -M ##         :: penalty %% for missing terms in license text (default: %.0f)\n",ThresholdMissing);
  printf("  Debugging options:\n");
  printf("    -i = Initialize the database, then exit.\n");
  printf("    -v = Verbose (-vv = more verbose, etc.)\n");
} /* Usage() */

/************************************************************/
/************************************************************/
/** Main **/
/************************************************************/
/************************************************************/

/**********************************************
 main():
 **********************************************/
int	main	(int argc, char *argv[])
{
  int c;
  int rc;

  while((c = getopt(argc,argv,"S:s:M:iv")) != -1)
    {
    switch(c)
      {
      case 'i':
	DB = DBopen();
	if (!DB)
	  {
	  fprintf(stderr,"FATAL: Unable to open DB\n");
	  exit(-1);
	  }
	GetAgentKey();
	DBclose(DB);
	return(0);
      case 'S': ThresholdSame = atof(optarg); break; /* same */
      case 's': ThresholdSimilar = atof(optarg); break; /* similar */
      case 'M': ThresholdMissing = atof(optarg); break; /* missing penalty */
      case 'v':	Verbose++;	break;
      default:
	Usage(argv[0]);
	DBclose(DB);
	exit(-1);
      } /* switch */
    } /* while(getopt) */

  signal(SIGALRM,ShowHeartbeat);
  DB = DBopen();
  if (!DB)
	{
	fprintf(stderr,"FATAL: Unable to open DB\n");
	exit(-1);
	}
  GetTerms();

  if (optind < argc)
    {
    /* command-line contains a list of pfile_pk values */
    for( ; optind < argc; optind++)
      {
      PfilePk = atol(argv[optind]);
      memset(SQL,'\0',MAXLINE);
      snprintf(SQL,MAXLINE,"SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size AS pfile FROM pfile WHERE pfile_pk = '%ld';",PfilePk);
      DBaccess(DB,SQL);
      memset(PfileName,'\0',MAXLINE);
      strncpy(PfileName,DBgetvalue(DB,0,0),MAXLINE-1);
      printf("### Processing: pfile_pk=%ld '%s'\n",PfilePk,PfileName);
      PfileMmap = OpenFile(PfileName);
      if (PfileMmap)
	{
	ProcessTerms();
	CloseFile(PfileMmap);
	}
      }
    } /* if reading from the command-line */
  else
    {
    rc = ReadLine(stdin);
    do
      {
      if (rc > 0)
	{
	if (Verbose) fprintf(stderr,"Processing: pfile_pk=%ld '%s'\n",PfilePk,PfileName);
	PfileMmap = OpenFile(PfileName);
	if (PfileMmap)
	  {
	  ProcessTerms();
	  CloseFile(PfileMmap);
	  }
	}
      rc = ReadLine(stdin);
      } while(rc >= 0);
    } /* if reading from the scheduler */

  if (DB) DBclose(DB);
  if (DBTerms) DBclose(DBTerms);
  if (TermsCounter) free(TermsCounter);
  return(0);
} /* main() */

