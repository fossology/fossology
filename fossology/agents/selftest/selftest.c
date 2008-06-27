/********************************************************
 selftest: Check if the agent system is configured properly.

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
 ********************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <ctype.h>
#include <string.h>
#include <time.h>
#include <signal.h>
#include <sys/types.h>
#include <sys/stat.h>

#include <libfossdb.h>
#include <libfossrepo.h>
#include <checksum.h>

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

int Verbose=0;
int Test=0;
#define MAXSQL	1024
#define MAXLINE	1024

/* for DB */
void	*DB=NULL;
char	*Pfile_fk=NULL;
int	Agent_pk=-1;	/* agent ID */
char	SQL[MAXSQL];

/* For heartbeats */
long	ItemsProcessed=0;

/**************************************************
 ShowHeartbeat(): Given an alarm signal, display a
 heartbeat.
 **************************************************/
void    ShowHeartbeat   (int Sig)
{
  if (ItemsProcessed > 0)
    {
    printf("ItemsProcessed %ld\n",ItemsProcessed);
    ItemsProcessed=0;
    }
  else
    {
    printf("Heartbeat\n");
    }
  fflush(stdout);
  /* re-schedule itself */
  alarm(60);
} /* ShowHeartbeat() */

/*********************************************
 MyDBaccess(): DBaccess with debugging.
 *********************************************/
int	MyDBaccess	(void *V, char *S)
{
  int rc;
  if (Verbose > 1) printf("%s\n",S);
  rc = DBaccess(V,S);
  if (rc < 0)
	{
	fprintf(stderr,"FATAL: SQL failed: '%s'.\n",SQL);
	DBclose(DB);
	exit(-1);
	}
  return(rc);
} /* MyDBaccess() */

/*********************************************************
 CheckLicenses(): Verify that every entry in License.bsam
 exists in the DB *and* on the file system.
 List each license file found.
 Returns 1 on success, 0 on failure.
 *********************************************************/
int	CheckLicenses	()
{
  FILE *Fin;
  char Data[1024];
  char SQL[1024];
  int Type,Len,i,c;
  FILE *Ftest;
  struct stat Stat;
  Cksum *Checksum;
  char *String;

  /* License.bsam contains relative filenames.  Go to the relative root. */
  if (chdir(LICDIR))
    {
    printf("FATAL: Unable to access directory '%s'\n",LICDIR);
    return(0);
    }
  if (Verbose) printf("# chdir(%s)\n",LICDIR);

  /* Open the bsam license cache. */
  Fin = fopen(BSAMLIC,"rb");
  if (!Fin)
    {
    printf("FATAL: Unable to access license cache '%s'\n",BSAMLIC);
    return(0);
    }

  Checksum = SumComputeFile(Fin);
  if (!Checksum)
	{
	printf("License.bsam: Not readable.\n");
	return(1);
	}
  String = SumToString(Checksum);
  printf("License.bsam: %s\n",String);
  free(String);
  free(Checksum);
  rewind(Fin);

  /*****
   Things things to check.
     1. Make sure each file exists on the file system.
     2. Make sure each checksum exists in the database.
     3. Is the bsam file properly formatted?
   *****/
  while(!feof(Fin))
    {
    c = fgetc(Fin);
    if (feof(Fin) || (c<0)) continue;
    Type = c*256+fgetc(Fin);
    Len = fgetc(Fin)*256+fgetc(Fin);
    if (feof(Fin))
      {
      printf("License.bsam: Premature end-of-file. Cache is corrupted.\n");
      }
    else
      {
      if (Verbose > 1) printf("# License.bsam: Type=%04x  Len=%d\n",Type,Len);
      switch(Type)
	{
	case 0x0001: /* Filename */
		memset(Data,'\0',sizeof(Data));
		for(i=0; (i<Len) && (i<sizeof(Data)-1); i++)
		  {
		  c = fgetc(Fin);
		  if (c < 0)
		    {
		    printf("License.bsam: Premature end-of-file (reading filename). Cache is corrupted.\n");
		    return(0);
		    }
		  else { Data[i]=c; }
		  }
		if (Verbose) printf("# Filename: %s\n",Data);
		if (stat(Data,&Stat))
		  {
		  printf("License.bsam: License does not exist: '%s'\n",Data);
		  return(1);
		  }
		/* Compute checksum for the file */
		Ftest = fopen(Data,"r");
		if (!Ftest)
		  {
		  printf("License.bsam: License not accessible: '%s'\n",Data);
		  return(1);
		  }
		Checksum = SumComputeFile(Ftest);
		if (!Checksum)
		  {
		  printf("License.bsam: License not readable: '%s'\n",Data);
		  return(1);
		  }
		String = SumToString(Checksum);
		printf("License: '%s' = %s\n",Data,String);
		free(String);
		free(Checksum);
		fclose(Ftest);
		break;
	case 0x0110: /* function unique */
		memset(Data,'\0',sizeof(Data));
		for(i=0; (i<Len) && (i<sizeof(Data)-1); i++)
		  {
		  c = fgetc(Fin);
		  if (c < 0)
		    {
		    printf("License.bsam: Premature end-of-file (reading unique string). Cache is corrupted.\n");
		    return(0);
		    }
		  else { Data[i]=c; }
		  }
		if (Verbose) printf("# Unique: %s\n",Data);
/* TBD: Test against DB */
		break;
	default:
		for(i=0; (i<Len); i++)
		  {
		  c = fgetc(Fin);
		  if (c < 0)
		    {
		    printf("License.bsam: Premature end-of-file. Cache is corrupted.\n");
		    return(0);
		    }
		  }
		break;
	} /* switch */
      /* Align with byte position */
      }
    if ((Len % 2) != 0) fgetc(Fin);
    } /* while !eof */

  /* All done! */
  fclose(Fin);
  return(1);
} /* CheckLicenses() */

/*********************************************************
 CheckRepo(): Make sure the repo looks correct.
 List each repo line and the self-test contents (if it exists).
 Returns 1 on success, 0 on failure.
 *********************************************************/
int	CheckRepo	()
{
  int i,j;
  char Host[1024];
  char Path[1024];
  extern RepMmapStruct * RepConfig;
  char *RepPath;
  FILE *Fin;

  if (!RepOpen())
    {
    printf("FATAL: Unable to access repository.\n");
    fflush(stdout);
    return(0);
    }
  RepPath = RepGetRepPath();

  i=0;
  while(i < RepConfig->MmapSize)
    {
    while((i < RepConfig->MmapSize) && isspace(RepConfig->Mmap[i])) i++;
    if (i >= RepConfig->MmapSize) return(1);
    fputs("Repository: ",stdout);
    memset(Host,'\0',sizeof(Host));
    j=0;
    while((i < RepConfig->MmapSize) && !isspace(RepConfig->Mmap[i]))
	{
	Host[j]=RepConfig->Mmap[i];
	fputc(RepConfig->Mmap[i],stdout);
	i++; j++;
	}
    /* skip to end of line */
    while((i < RepConfig->MmapSize) && (RepConfig->Mmap[i] != '\n'))
	{
	fputc(RepConfig->Mmap[i],stdout);
	i++;
	}
    i++;

    /* Get the data */
    fputs(" # ",stdout);
    memset(Path,'\0',sizeof(Path));
    snprintf(Path,sizeof(Path),"%s/%s/selftest.txt",RepPath,Host);
    Fin = fopen(Path,"r");
    if (Fin)
      {
      j=1;
      while(!feof(Fin) && (j>0) && (j!='\n'))
        {
	j = fgetc(Fin);
	if ((j > 0) && (j != '\n')) fputc(j,stdout);
	}
      fclose(Fin);
      }
    fputc('\n',stdout);
    }

  free(RepPath);
  RepClose();
  return(1);
} /* CheckRepo() */

/*********************************************************
 GenerateTestData(): Create random test data and place it
 on every host listed in the repo.
 *********************************************************/
void	GenerateTestData	()
{
  time_t Time;
  int i,j;
  char Host[1024];
  char Path[1024];
  extern RepMmapStruct * RepConfig;
  char *RepPath;
  FILE *Fout;

  Time = time(NULL);
  printf("DATA: %s",ctime(&Time));
  if (!RepOpen())
    {
    printf("FATAL: Unable to access repository.\n");
    fflush(stdout);
    exit(-1);
    }
  RepPath = RepGetRepPath();

  i=0;
  while(i < RepConfig->MmapSize)
    {
    while((i < RepConfig->MmapSize) && isspace(RepConfig->Mmap[i])) i++;
    memset(Host,'\0',sizeof(Host));
    j=0;
    while((i < RepConfig->MmapSize) && !isspace(RepConfig->Mmap[i]))
	{
	Host[j]=RepConfig->Mmap[i];
	i++; j++;
	}
    /* skip to end of line */
    while((i < RepConfig->MmapSize) && (RepConfig->Mmap[i] != '\n')) i++;
    i++;

    /* Save the data */
    if (_RepMkDirs(RepPath))
      {
      printf("FATAL: Unable to write to create repository directory: '%s'.\n",RepPath);
      exit(-1);
      }
    memset(Path,'\0',sizeof(Path));
    snprintf(Path,sizeof(Path),"%s/%s/selftest.txt",RepPath,Host);
    Fout = fopen(Path,"w");
    if (!Fout)
      {
      printf("FATAL: Unable to write to repository.\n");
      exit(-1);
      }
    fprintf(Fout,"%s",ctime(&Time));
    fclose(Fout);
    }

  free(RepPath);
  RepClose();
} /* GenerateTestData() */

/**********************************************
 ReadLine(): Read a single line from a file.
 Used to read from stdin.
 Process line elements.
 Returns: 1 of read data, 0=no data, -1=EOF.
 **********************************************/
int     ReadLine        (FILE *Fin)
{
  int C='@';
  int i=0;      /* index */
  char FullLine[MAXLINE];
  char *L;
  int rc=0;     /* assume no data */

  memset(FullLine,0,MAXLINE);
  /* inform scheduler that we're ready for data */
  printf("OK\n");
  alarm(60);
  fflush(stdout);

  if (feof(Fin))
    {
    return(-1);
    }

  /* read a line */
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
  if (Verbose > 1) fprintf(stderr,"DEBUG: Line='%s'\n",FullLine);

  /* process the line. */
  L = FullLine;
  while(isspace(L[0])) L++;
  rc = L[0];

  CheckRepo() && CheckLicenses();
  return(rc);
} /* ReadLine() */

/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 *********************************************************/
void    GetAgentKey     ()
{
  int rc;

  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='selftest' ORDER BY agent_id DESC;");
  if (rc < 0)
        {
        printf("ERROR: unable to access the database\n");
        printf("LOG: unable to select 'selftest' from the database table 'agent'\n");
        fflush(stdout);
        DBclose(DB);
        exit(-1);
        }
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('selftest','unknown','Validate agent configuration');");
      if (rc < 0)
        {
        printf("ERROR: unable to write to the database\n");
        printf("LOG: unable to write 'selftest' to the database table 'agent'\n");
        fflush(stdout);
        DBclose(DB);
        exit(-1);
        }
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='selftest' ORDER BY agent_id DESC;");
      if (rc < 0)
        {
        printf("ERROR: unable to access the database\n");
        printf("LOG: unable to select 'selftest' from the database table 'agent'\n");
        fflush(stdout);
        DBclose(DB);
        exit(-1);
        }
      }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
} /* GetAgentKey() */

/*********************************************
 Usage():
 *********************************************/
void	Usage	(char *Name)
{
  fprintf(stderr,"Usage: %s [options]\n",Name);
  fprintf(stderr,"  List or delete uploads.\n");
  fprintf(stderr,"  Options\n");
  fprintf(stderr,"  -i   :: Initialize the DB, then exit.\n");
  fprintf(stderr,"  -g   :: Generate data for self-test.\n");
  fprintf(stderr,"  -s   :: Run from the scheduler.\n");
  fprintf(stderr,"  -v   :: Verbose (-vv for more verbose)\n");
  fprintf(stderr,"  Returns 0 on success, non-zero on failure.\n");
} /* Usage() */

/**********************************************************************/
int	main	(int argc, char *argv[])
{
  int c;
  int Scheduler=0; /* should it run from the scheduler? */
  int GotArg=0;

  while((c = getopt(argc,argv,"gisv")) != -1)
    {
    switch(c)
      {
      case 'g':
	GenerateTestData();
	break;
      case 'i':
	DB = DBopen();
	if (!DB)
	  {
	  fprintf(stderr,"ERROR: Unable to open DB\n");
	  exit(-1);
	  }
	GetAgentKey();
	DBclose(DB);
	return(0);
      case 's': Scheduler=1; GotArg=1; break;
      case 'v': Verbose++; break;
      default:	Usage(argv[0]); exit(-1);
      }
    }

  DB=DBopen();
  if (!DB)
    {
    printf("FATAL: Unable to access database.\n");
    return(1);
    }
  GetAgentKey();
  signal(SIGALRM,ShowHeartbeat);

  alarm(60);  /* from this point on, handle the alarm */

  /* process from the scheduler */
  if (Scheduler)
    {
    while(ReadLine(stdin) >= 0) ;
    }

  DBclose(DB);
  return(0);
} /* main() */

