/*******************************************************************
 Copyright (C) 2007-2011 Hewlett-Packard Development Company, L.P.
 
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

#include "ununpack.h"
#include "externs.h"

/**
 * \file ununpack-ar.c
 * \brief The universal unpacker - Code to unpack an AR file.
 **/

/**
 * \brief Given an AR file, extract the contents to the directory.
 *        This uses the command ar
 * \param Source  Pathname of source file
 * \param Destination Unpack destination
 * \return 0 on success, non-zero on failure.
 * NOTE: This spawns multiple processes.
 * NOTE: Things that are known to cause failures:
 *   - Absolute paths in ar files
 *   - Same file name listed twice in the archive
 **/
int	ExtractAR	(char *Source, char *Destination)
{
  char Cmd[FILENAME_MAX*4]; /* command to run */
  char Line[FILENAME_MAX];
  char *s; /* generic string pointer */
  FILE *Fin;
  int rc;
  char TempSource[FILENAME_MAX];
  char CWD[FILENAME_MAX];

  /* judge if the parameters are empty */
  if ((NULL == Source) || (!strcmp(Source, "")) || (NULL == Destination) || (!strcmp(Destination, "")))
    return 1;

  if (getcwd(CWD,sizeof(CWD)) == NULL)
  {
    fprintf(stderr,"ERROR: directory name longer than %d characters\n",(int)sizeof(CWD));
    return(-1);
  }
  if (Verbose > 1) 
  {
    printf("CWD: %s\n",CWD);
    if (!Quiet) fprintf(stderr,"Extracting ar: %s\n",Source);
  }

  if(chdir(Destination) != 0)
  {
    fprintf(stderr, "ERROR %s.%d: Unable to change directory to %s\n",
        __FILE__, __LINE__, Destination);
    fprintf(stderr, "ERROR: errno is: %s\n", strerror(errno));
  }

  if (TaintString(TempSource,FILENAME_MAX,Source,1,NULL))
    return(-1);
  memset(Cmd,'\0',sizeof(Cmd));

  /* get list of directories and make the directories */
  /* Cmd: ar t %s 2>/dev/null | grep '^Directory' */
  if (TempSource[0] != '/')
    snprintf(Cmd,sizeof(Cmd)," (ar t '%s/%s') 2>/dev/null",CWD,TempSource);
  else
    snprintf(Cmd,sizeof(Cmd)," (ar t '%s') 2>/dev/null",TempSource);

  Fin = popen(Cmd,"r");
  if (!Fin)
  {
    fprintf(stderr,"ERROR: ar failed: %s\n",Cmd);
    if(chdir(CWD) != 0)
    {
      fprintf(stderr, "ERROR %s.%d: Unable to change directory to %s\n",
          __FILE__, __LINE__, CWD);
      fprintf(stderr, "ERROR: errno is: %s\n", strerror(errno));
    }
    return(-1);
  }
  while(ReadLine(Fin,Line,sizeof(Line)-1) >= 0)
  {
    /* each line is a file.  Check for directories. */
    if (Line[0]=='/') { pclose(Fin); return(1); } /* NO ABSOLUTE PATHS! */
    s=strrchr(Line,'/'); /* find the last slash */
    if (s == NULL) continue;
    s[0]='\0';
    if (MkDir(Line))
    {
      fprintf(stderr,"ERROR: Unable to mkdir(%s) in ExtractAR\n",Line);
      if (!ForceContinue) exit(-1);
    }
  }
  pclose(Fin);

  /* Now let's extract each file */
  if (TempSource[0] != '/')
    snprintf(Cmd,sizeof(Cmd)," (ar x '%s/%s') 2>/dev/null",CWD,TempSource);
  else
    snprintf(Cmd,sizeof(Cmd)," (ar x '%s') 2>/dev/null",TempSource);
  rc = WEXITSTATUS(system(Cmd));
  if (rc)
  {
    fprintf(stderr,"ERROR: Command failed (rc=%d): %s\n",rc,Cmd);
  }

  /* All done */
  if(chdir(CWD) != 0)
  {
    fprintf(stderr, "ERROR %s.%d: Unable to change directory to %s\n",
        __FILE__, __LINE__, CWD);
    fprintf(stderr, "ERROR: errno is: %s\n", strerror(errno));
  }
  return(rc);
} /* ExtractAR() */
