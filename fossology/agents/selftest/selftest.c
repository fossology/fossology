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
#include <grp.h>
#include <libgen.h>

#include <libfossdb.h>
#include <libfossrepo.h>
#include <libfossagent.h>
#include <checksum.h>

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

int Verbose=0;
int Test=0;
#define MAXSQL	1024
#define MAXLINE	1024
gid_t	Group=0;

/* for DB */
void	*DB=NULL;
char	SQL[MAXSQL];


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
 CheckAgents(): Verify each agent installed on the system.
 Returns 1 on success, 0 on failure.
 *********************************************************/
int	CheckAgents	()
{
  if (chdir(AGENTDIR) != 0)
    {
    printf("ERROR: Unable to access %s\n",AGENTDIR);
    return(0);
    }
  system("ls -1 | sort -f | while read i ; do echo -n \"Agent $i: \" ; strings $i | grep \"^Build version:\" | while read A; do echo -n $A ; done; echo \"\"; done");
  fflush(stdout);
  return(1);
} /* CheckAgents() */

/*********************************************************
 CheckPerm(): Make sure a specific repo path look correct.
 Checks group owner, setgid, and access (rwx).
 Parameters:
   - Gid: Each path must have this group id.
   - RepPath: Path to the repository.
   - Host: Host in the repository.
   - Repo: Directory on the host (gold, files, etc.)
   - LogFlag: Some directories should be checked but not printed.
     Items printed are compared against all agents.
     Items not printed are only checked locally.
     For example, RepPath/ununpack is host-specific.  Different agents
     may not have this path.  (ununpack is not supposed to be mounted.)
     Even though they can be different, if it exists then it should
     be checked.
 If Repo is null, then only RepPath/Host is checked.
 If Host is null, then only RepPath is checked.
 Returns 1 on success, 0 on failure.
 *********************************************************/
int	CheckPerm	(gid_t Gid, char *RepPath, char *Host, char *Repo,
			 int LogFlag)
{
  char Path[1024];
  struct stat Stat;
  memset(Path,'\0',sizeof(Path));
  if (Repo) snprintf(Path,sizeof(Path),"%s/%s/%s",RepPath,Host,Repo);
  else if (Host) snprintf(Path,sizeof(Path),"%s/%s",RepPath,Host);
  else snprintf(Path,sizeof(Path),"%s",RepPath);
  if (!stat(Path,&Stat))
    {
    if (Stat.st_gid != Gid)
        {
	printf("FATAL: %s not in correct group. Expected %d, found %d\n",Path, (int)Gid, (int)Stat.st_gid);
	fflush(stdout);
	return(0);
	}
    if ((Stat.st_mode & 00070) != 00070)
        {
	printf("FATAL: Wrong group permissions for %s.\n   Expected Group rwx, found %o.\n",Path, Stat.st_mode );
	fflush(stdout);
	return(0);
	}
    if (!(Stat.st_mode & S_ISGID))
        {
	printf("FATAL: Setgid for group missing on %s\n",Path);
	fflush(stdout);
	return(0);
	}
    if (LogFlag)
      {
      printf("Permissions: OK RepPath.conf");
      if (Host) printf(" %s",Host);
      if (Repo) printf(" %s",Repo);
      printf("\n");
      }
    }
  return(1);
} /* CheckPerm() */

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
  extern int RepDepth;
  char *RepPath;
  FILE *Fin;
  struct group *Group;

  /* Check group */
  Group = getgrnam(PROJECTGROUP);
  if (!Group)
    {
    printf("FATAL: Group '%s' does not exist.\n",PROJECTGROUP);
    fflush(stdout);
    return(0);
    }
  printf("Group: '%s' is %d\n",PROJECTGROUP,(int)Group->gr_gid);

  if (!RepOpen())
    {
    printf("FATAL: Unable to access repository.\n");
    fflush(stdout);
    return(0);
    }

  printf("Repository: Depth.conf is %d\n",RepDepth);

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

    /* Check permissions on common repo directories */
    if (!CheckPerm(Group->gr_gid,RepPath,NULL,NULL,1)) return(0);
    if (!CheckPerm(Group->gr_gid,RepPath,Host,NULL,1)) return(0);
    if (!CheckPerm(Group->gr_gid,RepPath,Host,"gold",1)) return(0);
    if (!CheckPerm(Group->gr_gid,RepPath,Host,"files",1)) return(0);
    if (!CheckPerm(Group->gr_gid,RepPath,Host,"license",1)) return(0);
    if (!CheckPerm(Group->gr_gid,RepPath,"ununpack",NULL,0)) return(0);
    }

  free(RepPath);
  RepClose();
  fflush(stdout);
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
  if (Verbose) printf("DATA: %s",ctime(&Time));
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
    unlink(Path);
    Fout = fopen(Path,"w");
    if (!Fout)
      {
      printf("FATAL: Unable to write to repository.\n");
      exit(-1);
      }
    //fprintf(Fout,"%s",ctime(&Time));
    fprintf(Fout,"\n");
    fclose(Fout);
    }

  free(RepPath);
  RepClose();
} /* GenerateTestData() */

/**********************************************
 ReadFileLine(): Read a single line from a file.
 Used to read from stdin.
 Process line elements.
 Returns: 1 of read data, 0=no data, -1=EOF.
 **********************************************/
int     ReadFileLine        (FILE *Fin)
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

  if (CheckRepo()) CheckAgents();
  return(rc);
} /* ReadFileLine() */


/*********************************************
 Usage():
 *********************************************/
void	Usage	(char *Name)
{
  fprintf(stderr,"Usage: %s [options]\n",Name);
  fprintf(stderr,"  Validate repo, bsam licenses, and agents.\n");
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
  char *agent_desc = "Validate repository and agents";

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
	GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agent_desc);
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
  GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agent_desc);
  signal(SIGALRM,ShowHeartbeat);

  alarm(60);  /* from this point on, handle the alarm */

  /* process from the scheduler */
  if (Scheduler)
    {
    while(ReadFileLine(stdin) >= 0) ;
    }
  else /* !Scheduler */
    {
    if (CheckRepo()) CheckAgents();
    }

  DBclose(DB);
  return(0);
} /* main() */

