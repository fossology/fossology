/*******************************************************************
 mkschedconf: Create a scheduler configuration file.

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
 *******************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <libfossrepo.h>

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

/*********************************************************
 PrintConfig(): Create all of the config records.
 This is called one time per host.
 Basic limits:
   - Some jobs are one-per-host: wget, unpack, filter_clean
   - Others are one-per-cpu: filter_license
   - License agent is a huge pig: one less than number of CPUs
 Returns: 0 if OK, 1 if any host/type was invalid.
 *********************************************************/
int	PrintConfig	(FILE *Fout, int NumCPU, char *UseHost, char *RemoteCmd)
{
  char *Host = "localhost";
  char *Rcmd = "%s";
  int i,NumCPU1;
  char Cmd[1024];
  char CmdHost[256];
  int rc;
  char *RepPath;

  /* Init values */
  rc=0;
  if (UseHost && UseHost[0]) Host = UseHost;
  if (RemoteCmd && RemoteCmd[0]) Rcmd = RemoteCmd;

  /* Number of CPUs "minus one" */
  NumCPU1 = NumCPU-1;
  if (NumCPU1 <= 0) NumCPU1 = 1;

  memset(CmdHost,'\0',sizeof(CmdHost));
  if (UseHost) snprintf(CmdHost,sizeof(CmdHost)-1,"host=%s ",Host);

  /* Start printing! */
  fprintf(Fout,"%%Host %s %d 1\n",Host,NumCPU);

  /* Check if host is known for the repository */
  if (UseHost)
    {
    if (!RepHostExist("gold",Host)) { fprintf(stderr,"WARNING: Host label '%s' not valid for repository 'gold'\n",Host); rc=1; }
    if (!RepHostExist("files",Host)) { fprintf(stderr,"WARNING: Host label '%s' not valid for repository 'files'\n",Host); rc=1; }
    if (!RepHostExist("license",Host)) { fprintf(stderr,"WARNING: Host label '%s' not valid for repository 'license'\n",Host); rc=1; }
    }

  /** wget **/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/wget_agent -d %s",AGENTDIR,PROJECTSTATEDIR);
  fprintf(Fout,"agent=wget %s| ",CmdHost);
  fprintf(Fout,Rcmd,Cmd);
  fprintf(Fout,"\n");

  /** unpack **/
  fprintf(Fout,"agent=unpack %s| ",CmdHost);
  memset(Cmd,'\0',sizeof(Cmd));
  RepPath = RepGetRepPath();
  snprintf(Cmd,sizeof(Cmd)-1,Rcmd,"%s/engine-shell unpack '%s/ununpack -d %s/ununpack/%s -qRCQx'");
  fprintf(Fout,Cmd,AGENTDIR,AGENTDIR,RepPath,"%{U}");
  fprintf(Fout,"\n");
  free(RepPath);

  /** adj2nest **/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/adj2nest",AGENTDIR);
  fprintf(Fout,"agent=adj2nest %s| ",CmdHost);
  fprintf(Fout,Rcmd,Cmd);
  fprintf(Fout,"\n");

  /** filter license **/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/Filter_License",AGENTDIR);
  for(i=0; i<NumCPU; i++)
    {
    fprintf(Fout,"agent=filter_license %s| ",CmdHost);
    fprintf(Fout,Rcmd,Cmd);
    fprintf(Fout,"\n");
    }

  /** license analysis (uses bsam) ***/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/bsam-engine -L 20 -A 0 -B 60 -G 15 -M 10 -E -T license -O n -- - %s/agents/License.bsam",AGENTDIR,PROJECTSTATEDIR);
  for(i=0; i<NumCPU1; i++)
    {
    fprintf(Fout,"agent=license %s| ",CmdHost);
    fprintf(Fout,Rcmd,Cmd);
    fprintf(Fout,"\n");
    }

  /** license inspector (uses licinspect) ***/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/licinspect",AGENTDIR);
  for(i=0; i<NumCPU1; i++)
    {
    fprintf(Fout,"agent=licinspect %s| ",CmdHost);
    fprintf(Fout,Rcmd,Cmd);
    fprintf(Fout,"\n");
    }

  /** mimetype ***/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/mimetype",AGENTDIR);
  for(i=0; i<NumCPU; i++)
    {
    fprintf(Fout,"agent=mimetype %s| ",CmdHost);
    fprintf(Fout,Rcmd,Cmd);
    fprintf(Fout,"\n");
    }

  /** specagent (it's fast, so only allocate one) ***/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/specagent",AGENTDIR);
  fprintf(Fout,"agent=specagent %s| ",CmdHost);
  fprintf(Fout,Rcmd,Cmd);
  fprintf(Fout,"\n");

  /** filter clean ***/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/filter_clean -s",AGENTDIR);
  fprintf(Fout,"agent=filter_clean %s| ",CmdHost);
  fprintf(Fout,Rcmd,Cmd);
  fprintf(Fout,"\n");

  /** delagent -- host-less **/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/delagent -s",AGENTDIR);
  fprintf(Fout,"agent=delagent %s| ",CmdHost);
  fprintf(Fout,Rcmd,Cmd);
  fprintf(Fout,"\n");

  /** sqlagent -- host-less **/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/sqlagent",AGENTDIR);
  fprintf(Fout,"agent=sqlagent %s| ",CmdHost);
  fprintf(Fout,Rcmd,Cmd);
  fprintf(Fout,"\n");

  /** sqlagent -- host-specific **/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/sqlagent -a sql",AGENTDIR);
  fprintf(Fout,"agent=sqlagenthost %s| ",CmdHost);
  fprintf(Fout,Rcmd,Cmd);
  fprintf(Fout,"\n");

  /** pkgmetagetta **/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/pkgmetagetta",AGENTDIR);
  for(i=0; i<NumCPU; i++)
    {
    fprintf(Fout,"agent=pkgmetagetta %s| ",CmdHost);
    fprintf(Fout,Rcmd,Cmd);
    fprintf(Fout,"\n");
    }

  /** fosscp **/
  fprintf(Fout,"agent=fosscp_agent %s| ",CmdHost);
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,Rcmd,"%s/engine-shell fosscp_agent '%s/cp2foss %{*}'");
  fprintf(Fout,Cmd,AGENTDIR,BINDIR);
  fprintf(Fout,"\n");

  /** selftest -- host-specific **/
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)-1,"%s/selftest -s",AGENTDIR);
  fprintf(Fout,"agent=selftest %s| ",CmdHost);
  fprintf(Fout,Rcmd,Cmd);
  fprintf(Fout,"\n");

  /* all done */
  fprintf(Fout,"\n");
  return(rc);
} /* PrintConfig() */

/*********************************************************
 Usage(): Print usage.
 *********************************************************/
void	Usage	(char *ProgName)
{
  char *Name;
  Name = strrchr(ProgName,'/');
  if (!Name) Name = ProgName;
  else Name++;

  printf("Usage: %s [options]\n",Name);
  printf("    -h :: this help menu.\n");
  printf("    -? :: this help menu.\n");
  printf("  Configuration options:\n");
  printf("    -C num :: specify the number of CPUs to subsequent -L or -H options\n");
  printf("              Default is number of CPUs - 1.\n");
  printf("    -R cmd :: Command for remote access.\n");
  printf("              This must include one '%%s' for the command to run.\n");
  printf("              Do not use single-quotes around the '%%s'!\n");
  printf("  Output options:\n");
  printf("    -o file :: write output to file.  Use '-' for stdout.\n");
  printf("    -a file :: append output to file.  Use '-' for stdout.\n");
  printf("    -L      :: create output for running on localhost.\n");
  printf("    -H host :: create output for running on host.\n");
  printf("    -B      :: create output for running on no specified host (-B for blank).\n");
  printf("    NOTE: -H and -R are independent but may need to be set alike.\n");
  printf("\n");
  printf("  All options may be specified multiple times.  For example:\n");
  printf("  %s -C 4 -R 'ssh alpha \"%%s\"' -H alpha -C 2 -R 'ssh beta \"%%s\"' -H beta\n",Name);
} /* Usage() */

/******************************************************************/
int	main	(int argc, char *argv[])
{
  int CPUcount; /* Number of CPUs to use */
  int c;
  FILE *Fout;
  char *RemoteCmd=NULL;
  int rc=0;

  /* Set defaults */
  Fout = stdout;
  CPUcount = sysconf(_SC_NPROCESSORS_ONLN) - 1;
  if (CPUcount <= 0) CPUcount = 1; /* minimum of 1 */

  if (argc < 2)
	{
	Usage(argv[0]);
	exit(-1);
	}

  if (!RepOpen()) /* Repository needed for Repository's Path */
    {
    fprintf(stderr,"ERROR: Opening repository.\n");
    exit(-1);
    }

  while((c = getopt(argc,argv,"a:BC:H:hLo:R:?")) != -1)
    {
    switch(c)
      {
      case 'a':
        if (!strcmp(optarg,"-"))
	  {
	  Fout = stdout;
	  }
	else
	  {
	  Fout = fopen(optarg,"ab");
	  if (!Fout)
	    {
	    fprintf(stderr,"ERROR: Unable to append to file '%s'\n",optarg);
	    exit(-1);
	    }
	  }
	break;

      case 'C': /* set number of cpus */
    	CPUcount = atoi(optarg);
	break;

      case 'H': /* create a host record */
	rc |= PrintConfig(Fout,CPUcount,optarg,RemoteCmd);
	break;

      case 'h': case '?':
	Usage(argv[0]);
	exit(0);

      case 'L': /* create a localhost record */
	rc |= PrintConfig(Fout,CPUcount,"","");
	break;

      case 'B': /* create a blank host record */
	rc |= PrintConfig(Fout,CPUcount,NULL,RemoteCmd);
	break;

      case 'o':
        if (!strcmp(optarg,"-"))
	  {
	  Fout = stdout;
	  }
	else
	  {
	  Fout = fopen(optarg,"wb");
	  if (!Fout)
	    {
	    fprintf(stderr,"ERROR: Unable to write to file '%s'\n",optarg);
	    exit(-1);
	    }
	  }
	break;

      case 'R': /* specify a remote command */
	RemoteCmd = optarg;
	break;

      default:
	Usage(argv[0]);
	exit(-1);
      } /* switch() */
    }

  RepClose();
  fclose(Fout);
  if (rc)
    {
    fprintf(stderr,"WARNING: Be sure the host label is listed in the repository configuration.\n");
    }
  return(rc);
} /* main() */

