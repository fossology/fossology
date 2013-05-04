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

/**
 * \file ununpack-iso.c
 * \brief The universal unpacker code to unpack an ISO file system.
 **/

#include "ununpack.h"
#include "externs.h"

/**
 * \brief Given a line in drwxrwxrwx format,
 *        convert it to a numeric mode.
 * NOTE: ISO are always read-only!
 * (They may be saved with write bits, but they can never
 * be written to!)
 * \param Line
 * \return UGO mode bits.
 **/
mode_t	GetISOMode	(char *Line)
{
  mode_t Mode=0;
  if (Line[1]=='r') Mode |= S_IRUSR;
#if 0
  if (Line[2]=='w') Mode |= S_IWUSR;
#endif
  if (Line[3]=='x') Mode |= S_IXUSR;
  if (Line[3]=='s') Mode |= S_IXUSR | S_ISUID;
  if (Line[3]=='S') Mode |= S_ISUID;

  if (Line[4]=='r') Mode |= S_IRGRP;
#if 0
  if (Line[5]=='w') Mode |= S_IWGRP;
#endif
  if (Line[6]=='x') Mode |= S_IXGRP;
  if (Line[6]=='s') Mode |= S_IXGRP | S_ISGID;
  if (Line[6]=='S') Mode |= S_ISGID;

  if (Line[7]=='r') Mode |= S_IROTH;
#if 0
  if (Line[8]=='w') Mode |= S_IWOTH;
#endif
  if (Line[9]=='x') Mode |= S_IXOTH;
  if (Line[9]=='t') Mode |= S_IXOTH | S_ISVTX;
  if (Line[9]=='T') Mode |= S_ISVTX;

  return(Mode);
} /* GetISOMode() */

/**
 * \brief Given an ISO image and a directory,
 *        extract the image to the directory.
 *        ISO images have magic type "application/x-iso".
 *        This can unpack any known ISO9660 format including:
 *        ISO9660, Rock Ridge, Joliet, and El Torrito.
 * NOTE: This spawns multiple processes.
 * Uses the following external commands: isoinfo grep
 * \param Source
 * \param Destination
 * \return 0 on success, non-zero on failure.
 **/
int	ExtractISO	(char *Source, char *Destination)
{
  char Cmd[FILENAME_MAX*4]; /* command to run */
  char Line[FILENAME_MAX];
  int Len;
  char *s; /* generic string pointer */
  FILE *Fin;
  int rc;
  char TempSource[FILENAME_MAX], TempDestination[FILENAME_MAX];

  /* judge if the parameters are empty */
  if ((NULL == Source) || (!strcmp(Source, "")) || (NULL == Destination) || (!strcmp(Destination, "")))
    return 1;

  if (!Quiet && Verbose) fprintf(stderr,"Extracting ISO: %s\n",Source);

  /* get list of directories in the ISO and make the directories */
  if (TaintString(TempSource,FILENAME_MAX,Source,1,NULL) ||
      TaintString(TempDestination,FILENAME_MAX,Destination,1,NULL))
	return(-1);
  memset(Cmd,'\0',sizeof(Cmd));
  snprintf(Cmd,sizeof(Cmd)," (isoinfo -l -R -J -i '%s' || isoinfo -l -R -i '%s' || isoinfo -l -i '%s') 2>/dev/null | grep '^Directory'",
      TempSource,TempSource,TempSource);


  Fin = popen(Cmd,"r");
  if (!Fin)
    {
    fprintf(stderr,"ERROR: ISO failed: %s\n",Cmd);
    return(-1);
    }
  while(ReadLine(Fin,Line,sizeof(Line)-1) >= 0)
    {
    s=strchr(Line,'/');	/* find first slash */
    if (s==NULL) continue;
    snprintf(Cmd,sizeof(Cmd),"%s%s",Destination,s);
    if (Verbose > 1) printf("ISO directory: %s\n",Cmd);
    if (MkDir(Cmd))
	{
	fprintf(stderr,"ERROR: Unable to mkdir(%s) in ExtractISO\n",Cmd);
        if (!ForceContinue) SafeExit(40);
	}
    }
  pclose(Fin);

  /* Now let's extract each file */
  snprintf(Cmd,sizeof(Cmd),"(isoinfo -f -R -J -i '%s' || isoinfo -f -R -i '%s' || isoinfo -f -i '%s') 2>/dev/null",TempSource,TempSource,TempSource);

  Fin = popen(Cmd,"r");
  if (!Fin)
    {
    fprintf(stderr,"ERROR: ISO failed: %s\n",Cmd);
    return(-1);
    }

  memset(Line,'\0',sizeof(Line));
  strcpy(Line,Destination);
  Len=strlen(Destination);
  while(ReadLine(Fin,Line+Len,sizeof(Line)-1-Len) >= 0)
    {
    if (Line[Len] != '/') continue; /* should not happen, but... */
    if (IsDir(Line))	continue;	/* don't do directories */
    if (Verbose > 1) printf("ISO file: %s\n",Line);
    /* create extraction command */
    snprintf(Cmd,sizeof(Cmd),"(isoinfo -R -J -i '%s' -x '%s' || isoinfo -R -i '%s' -x '%s' || isoinfo -i '%s' -x '%s') > '%s' 2>/dev/null",TempSource,Line+Len,TempSource,Line+Len,TempSource, Line+Len,Line);
    rc = system(Cmd);
    if (WIFSIGNALED(rc))
        {
        printf("ERROR: Process killed by signal (%d): %s\n",WTERMSIG(rc),Cmd);
	SafeExit(-1);
        }
    rc = WEXITSTATUS(rc);
    if (rc)
      {
      fprintf(stderr,"ERROR: Command failed (rc=%d): %s\n",rc,Cmd);
      pclose(Fin);
      return(rc);
      }
    }
  pclose(Fin);


#if 0
  /* save the ISO information */
  snprintf(Cmd,sizeof(Cmd),"(isoinfo -d -R -i '%s' || isoinfo -d -R -J -i '%s' || isoinfo -d -i '%s') > '%s/ISO_INFO' 2>/dev/null",TempSource,TempSource,TempSource,TempDestination);
  rc = system(Cmd);
  if (WIFSIGNALED(rc))
        {
        printf("ERROR: Process killed by signal (%d): %s\n",WTERMSIG(rc),Cmd);
        SafeExit(-1);
        }
  rc = WEXITSTATUS(rc);
  if (rc)
      {
      fprintf(stderr,"ERROR: Command failed (rc=%d): %s\n",rc,Cmd);
      return(rc);
      }
#endif


  /* Set the permissions on every file and directory */
  /** Only RockRidge saves permission information! **/
    snprintf(Cmd,sizeof(Cmd),"(isoinfo -l -R -J -i '%s' || isoinfo -l -R -i '%s' || isoinfo -l -i '%s') 2>/dev/null",TempSource, TempSource, TempSource);
  Fin = popen(Cmd,"r");
  if (Fin)
    {
    mode_t Mode;
    char Dir[FILENAME_MAX];
    memset(Dir,'\0',sizeof(Dir));
    while((Len = ReadLine(Fin,Line,sizeof(Line)-1)) >= 0)
      {
      if (Len == 0) continue;
      if (!strchr("Dd-",Line[0])) continue;
      /* Every line is either a "Directory" or desirable chmod */
      if (!strncmp(Line,"Directory listing of ",21))
	{
	strcpy(Dir,Line+22);
	continue;
	}
      snprintf(Cmd,sizeof(Cmd)-1,"%s/%s%s",Destination,Dir,Line+67);
      Mode = GetISOMode(Line);
      chmod(Cmd,Mode);
      }
    pclose(Fin);
    }


  /* All done */
  return(0);
} /* ExtractISO() */
