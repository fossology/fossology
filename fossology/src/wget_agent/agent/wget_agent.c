/***************************************************************
 wget_agent: Retrieve a file and put it in the database.

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

 ***************************************************************/

/**
 * \file wget_agent.c
 */

#include "wget_agent.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

char SQL[MAXCMD];

/* for the DB */
PGconn *pgConn = NULL;
/* input for this system */
long GlobalUploadKey=-1;
char GlobalTempFile[MAXCMD];
char GlobalURL[MAXCMD];
char GlobalParam[MAXCMD];
int GlobalImportGold=1; /* set to 0 to not store file in gold repository */
gid_t ForceGroup=-1;

/* for debugging */
int Debug=0;

/**
 * \brief Given a filename, is it a file?
 *
 * \param int Link - should it follow symbolic links?
 *
 * \return int 1=yes, 0=no.
 */
int IsFile(char *Fname, int Link)
{
  stat_t Stat;
  int rc;
  if (!Fname || (Fname[0]=='\0')) return(0);  /* not a directory */
  if (Link) rc = stat64(Fname,&Stat);
  else rc = lstat64(Fname,&Stat);
  if (rc != 0) return(0); /* bad name */
  return(S_ISREG(Stat.st_mode));
} /* IsFile() */

/**
 * \brief Closes the connection to the server. Also frees memory used by the PGconn object;then exit.
 *
 * \param int rc - exit value
 */ 
void  SafeExit  (int rc)
{
  if (pgConn) PQfinish(pgConn);
  exit(rc);
} /* SafeExit() */

/**
 * \brief Get the position (ending + 1) of http|https|ftp:// of one url
 *
 * \param char *URL - the URL
 *
 * \return the position (ending + 1) of http|https|ftp:// of one url
 *         E.g. http://fossology.org, return 7
 */
int GetPosition(char *URL)
{
  if (NULL != strstr(URL, "http://"))  return 7;
  if (NULL != strstr(URL, "https://"))  return 8;
  if (NULL != strstr(URL, "ftp://"))  return 6;
  return 0;
}

/**
 * \brief Insert a file into the database and repository.
 *        This mimicks the old webgoldimport.
 */
void	DBLoadGold	()
{
  Cksum *Sum;
  char *Unique=NULL;
  char *SHA1, *MD5, *Len;
  char SQL[MAXCMD];
  long PfileKey;
  char *Path;
  FILE *Fin;
  int rc;
  PGresult *result;

  if (Debug) printf("Processing %s\n",GlobalTempFile);
  Fin = fopen(GlobalTempFile,"rb");
  if (!Fin)
	{
		printf("ERROR upload %ld Unable to open temp file.\n",GlobalUploadKey);
		printf("LOG upload %ld Unable to open temp file %s from %s\n",
		GlobalUploadKey,GlobalTempFile,GlobalURL);
		fflush(stdout);
		SafeExit(1);
	}
  Sum = SumComputeFile(Fin);
  fclose(Fin);
  if (ForceGroup > 0) { chown(GlobalTempFile,-1,ForceGroup); }

  if (!Sum)
	{
		printf("ERROR upload %ld Unable to compute checksum.\n",GlobalUploadKey);
		printf("LOG upload %ld Unable to compute checksum for %s from %s\n",
		GlobalUploadKey,GlobalTempFile,GlobalURL);
		fflush(stdout);
		SafeExit(2);
	}
  if (Sum->DataLen <= 0)
	{
		printf("ERROR upload %ld No bytes downloaded from %s.\n",GlobalUploadKey,GlobalURL);
		printf("LOG upload %ld No bytes downloaded from %s to %s.\n",
		GlobalUploadKey,GlobalURL,GlobalTempFile);
		fflush(stdout);
		SafeExit(3);
	}
  Unique = SumToString(Sum);
  if (Debug) printf("Unique %s\n",Unique);

  if (GlobalImportGold)
  {
    if (Debug) printf("Import Gold %s\n",Unique);
    rc = fo_RepImport(GlobalTempFile,"gold",Unique,1);
    if (rc != 0)
		{
			printf("ERROR upload %ld Failed to import file into the repository (RepImport=%d).\n",GlobalUploadKey,rc);
			printf("LOG upload %ld Failed to import %s from %s into gold %s\n",
			GlobalUploadKey,GlobalTempFile,GlobalURL,Unique);
			fflush(stdout);
			SafeExit(4);
		}	
    /* Put the file in the "files" repository too */
    Path = fo_RepMkPath("gold",Unique);
    if (ForceGroup >= 0) { chown(Path,-1,ForceGroup); }
  } /* if GlobalImportGold */
  else /* if !GlobalImportGold */
  {
    Path = GlobalTempFile;
  } /* else if !GlobalImportGold */
  if (Debug) printf("Path is %s\n",Path);

  if (!Path)
	{
		printf("ERROR upload %ld Failed to determine repository location.\n",GlobalUploadKey);
		printf("LOG upload %ld Failed to determine repository location for %s in gold\n",
		GlobalUploadKey,Unique);
		fflush(stdout);
		SafeExit(5);
	}
  if (Debug) printf("Import files %s\n",Path);
  if (fo_RepImport(Path,"files",Unique,1) != 0)
	{
		printf("ERROR upload %ld Failed to import file into the repository.\n",GlobalUploadKey);
		printf("LOG upload %ld Failed to import %s from %s into files\n",
			GlobalUploadKey,Unique,Path);
		fflush(stdout);
		SafeExit(6);
	}
  if (ForceGroup >= 0) { chown(Path,-1,ForceGroup); }
  if (Path != GlobalTempFile) free(Path);

  /* Now update the DB */
  /** Break out the sha1, md5, len components **/
  SHA1 = Unique;
  MD5 = Unique+41; /* 40 for sha1 + 1 for '.' */
  Len = Unique+41+33; /* 32 for md5 + 1 for '.' */
  /** Set the pfile **/
  memset(SQL,'\0',MAXCMD);
  snprintf(SQL,MAXCMD-1,"SELECT pfile_pk FROM pfile WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = %s;",
	SHA1,MD5,Len);
  result =  PQexec(pgConn, SQL); /* SELECT */
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
		SafeExit(7);
  }

  /* See if pfile needs to be added */
  if (PQntuples(result) <=0)
	{
    /* Insert it */
    memset(SQL,'\0',MAXCMD);
    snprintf(SQL,MAXCMD-1,"INSERT INTO pfile (pfile_sha1, pfile_md5, pfile_size) VALUES ('%.40s','%.32s',%s);",
		SHA1,MD5,Len);
    PQclear(result);
    result = PQexec(pgConn, SQL);
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
		{
			SafeExit(8);
		}
    PQclear(result);
    result = PQexec(pgConn, "SELECT currval('pfile_pfile_pk_seq');");
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
    {
      SafeExit(-1);
    }
  }
  PfileKey = atol(PQgetvalue(result,0,0));
  if (Debug) printf("pfile_pk = %ld\n",PfileKey);

  /* Upload the DB so the pfile is linked to the upload record */
  PQclear(result);
  result = PQexec(pgConn, "BEGIN;");
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    SafeExit(-1);
  }
 
  memset(SQL,0,MAXCMD);
  snprintf(SQL,MAXCMD-1,"SELECT * FROM upload WHERE upload_pk=%ld FOR UPDATE;",GlobalUploadKey);
  PQclear(result);
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    SafeExit(-1);
  }

  memset(SQL,0,MAXCMD);
  snprintf(SQL,MAXCMD-1,"UPDATE upload SET pfile_fk=%ld WHERE upload_pk=%ld;",
	PfileKey,GlobalUploadKey);
  if (Debug) printf("SQL=%s\n",SQL);
  PQclear(result);
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) 
	{
    SafeExit(9);
	}
  PQclear(result);
  result = PQexec(pgConn, "COMMIT;");
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__))
  {
    SafeExit(-1);
  }

  PQclear(result);
  /* Clean up */
  free(Sum);
} /* DBLoadGold() */


/**
 * \brief Given a URL string, taint-protect it.
 *
 * \param char *Sin - the source URL
 * \param char *Sout - the tainted URL  
 *
 * \return 1=tainted, 0=failed to taint
 */
int	TaintURL	(char *Sin, char *Sout, int SoutSize)
{
  int i;
  int si;
  memset(Sout,'\0',SoutSize);
  SoutSize--; /* always keep the EOL */
  for(i=0,si=0; (si<SoutSize) && (Sin[i] != '\0'); i++)
	{
    if (Sin[i] == '#') return(0);  /* end at the start of comment */
    if (!strchr("'`",Sin[i]) && !isspace(Sin[i])) Sout[si++] = Sin[i];
		else
		{
			if (si+3 >= SoutSize) return(0); /* no room */
			snprintf(Sout+si,4,"%%%02X",Sin[i]);
			si+=3;
		}
	}
  return(Sin[i]=='\0');
} /* TaintURL() */

/**
 * \brief Do the wget.
 *
 * \param char *TempFile - used when upload from URL by the scheduler, the downloaded file(directory) will be archived as this file
 *                        when running from command, this parameter is null, e.g. /var/local/lib/fossology/agents/wget.32732
 * \param char *URL - the url you want to download
 * \param char *TempFileDir - where you want to store your downloaded file(directory)
 *
 * \return int, 0 on success, non-zero on failure.
 */
int	GetURL	(char *TempFile, char *URL, char *TempFileDir)
{
  char CMD[MAXCMD];
  char TaintedURL[MAXCMD];
  int rc;
#if 1
  char WgetArgs[]="--no-check-certificate --progress=dot -rc -np -e robots=off -k";
#else
  /* wget < 1.10 does not support "--no-check-certificate" */
  char WgetArgs[]="--progress=dot -rc -np -e robots=off";
#endif

  if (!TaintURL(URL,TaintedURL,MAXCMD))
	{
    FATAL("Failed to parse the URL\n");
    printf("LOG: Failed to taint the URL '%s'\n",URL);
    fflush(stdout);
		SafeExit(10);
	}

  memset(CMD,'\0',MAXCMD);
  /***
   Wget options:
   --progress=dot :: display a new line as it progresses.
   --no-check-certificate :: download HTTPS files even if the cert cannot
     be validated.  (Neal has many issues with SSL and does not view it
     as very secure.)  Without this, some caching proxies and web sites
     with old certs won't download.  Granted, in theory a bad cert should
     prevent downloads.  In reality, 99.9% of bad certs are because the
     admin did not notice that they expired and not because of a hijacking
     attempt.
   ***/
 
  struct stat sb;
  int rc_system =0;

  /* Run from scheduler! delete the temp directory, /var/local/lib/fossology/agents/wget */
  if (!stat(TempFileDir, &sb) && TempFile && TempFile[0])
  {
    memset(CMD,'\0',MAXCMD);
    snprintf(CMD,MAXCMD-1, "rm -rf '%s' 2>&1", TempFileDir);
    rc_system = system(CMD);
    if (rc_system != 0) exit(23); // failed to delete the temperary directory
     
  }

  if (TempFile && TempFile[0])
  {
    /* Delete the temp file if it exists */
    unlink(TempFile);
    snprintf(CMD,MAXCMD-1,". %s ; /usr/bin/wget %s -P '%s' '%s' %s 2>&1",
        PROXYFILE,WgetArgs,TempFileDir,TaintedURL,GlobalParam);
  }
  else if(TempFileDir && TempFileDir[0])
  {
    snprintf(CMD,MAXCMD-1,". %s ; /usr/bin/wget %s -P '%s' '%s' %s 2>&1",
      PROXYFILE,WgetArgs, TempFileDir, TaintedURL, GlobalParam);
  }
  else 
  {
    snprintf(CMD,MAXCMD-1,". %s ; /usr/bin/wget %s '%s' %s 2>&1",
      PROXYFILE,WgetArgs,TaintedURL, GlobalParam);
  }
  /* the command is like
	   ". /usr/local/etc/fossology/Proxy.conf; 
	   /usr/bin/wget --no-check-certificate --progress=dot -rc -np -e robots=off -k -P '/var/local/lib/fossology/agents/wget'
		 'http://a.org/file' -l 1 -R index.html*  2>&1"
	*/
  rc = system(CMD); 
 
  if (WIFEXITED(rc) && (WEXITSTATUS(rc) != 0))
	{
		printf("ERROR upload %ld Download failed\n",GlobalUploadKey);
		printf("LOG upload %ld Download failed; Return code %d from: %s\n",GlobalUploadKey,WEXITSTATUS(rc),CMD);
		fflush(stdout);
		unlink(GlobalTempFile);
		SafeExit(12);
	}

  if (WIFEXITED(rc) && WIFSIGNALED(rc))
	{
		printf("ERROR upload %ld Download killed by a signal\n",GlobalUploadKey);
		printf("LOG upload %ld Download killed by signal %d\n",GlobalUploadKey,WTERMSIG(rc));
		fflush(stdout);
		unlink(GlobalTempFile);
		SafeExit(13);
	}

  if (WIFEXITED(rc) && WIFSIGNALED(rc))
	{
		printf("ERROR upload %ld Download killed by a signal\n",GlobalUploadKey);
		printf("LOG upload %ld Download killed by signal %d\n",GlobalUploadKey,WTERMSIG(rc));
		fflush(stdout);
		unlink(GlobalTempFile);
		SafeExit(14);
	}

  /* Run from scheduler! store /var/local/lib/fossology/agents/wget/../<files|directories> to one temp file */
  if (TempFile && TempFile[0])
  {
    char TempFilePath[MAXCMD];
    memset(TempFilePath,'\0',MAXCMD);
    /* for one url http://a.org/test.deb, TempFilePath should be /var/local/lib/fossology/agents/wget/a.org/test.deb */
    int Position = GetPosition(TaintedURL);
    if (0 == Position) SafeExit(26);
    snprintf(TempFilePath, MAXCMD-1, "%s/%s", TempFileDir, TaintedURL + Position);
    if (!stat(TempFilePath, &sb))
    {
      memset(CMD,'\0',MAXCMD);
      if (S_ISDIR(sb.st_mode))
      {
        snprintf(CMD,MAXCMD-1, "find '%s' -mindepth 1 -type d -empty -exec rmdir {} \\; > /dev/null 2>&1", TempFilePath);
        system(CMD); // delete all empty directories downloaded
        memset(CMD,'\0',MAXCMD);
        snprintf(CMD,MAXCMD-1, "tar -cvvf '%s' -C '%s' ./ 2>&1", TempFile, TempFilePath);
      }
      else
      {
        snprintf(CMD,MAXCMD-1, "mv '%s' '%s' 2>&1", TempFilePath, TempFile);
      }
      rc_system = system(CMD);
      if (rc_system != 0) SafeExit(24); // failed to store the temperary directory(one file) as one temperary file
    }
  } 

  if (TempFile && TempFile[0] && !IsFile(TempFile,1))
	{
		printf("ERROR upload %ld File %s not created from %s\n",GlobalUploadKey,TempFile,URL);
		printf("LOG upload %ld File not created from command: %s\n",GlobalUploadKey,CMD);
		fflush(stdout);
		SafeExit(15);
	}

  printf("LOG upload %ld Downloaded %s to %s\n",GlobalUploadKey,URL,TempFile);
  return(0);
} /* GetURL() */

/**
 * \brief Convert input pairs into globals.
 *        This functions taints the parameters as needed.
 *
 * \param char *S - the parameters for wget_aget have 2 parts, one is from scheduler, that is S
 * \param char *TempFileDir - the parameters for wget_aget have 2 parts, one is from wget_agent.conf, that is TempFileDir
 */
void    SetEnv  (char *S, char *TempFileDir)
{
  int SLen,GLen; /* lengths for S and global string */

  GlobalUploadKey = -1;
  memset(GlobalTempFile,'\0',MAXCMD);
  memset(GlobalURL,'\0',MAXCMD);
  if (!S) return;

  /* first value is the upload_pk */
  GlobalUploadKey = atol(S);
  while(S[0] && isdigit(S[0])) S++;
  while(S[0] && isspace(S[0])) S++; /* skip spaces */

#if 1
  /* second value is the temp file location */
  /** This will be removed when the jobqueue is changed. **/
  SLen=0;
  GLen=0;
  while((GLen < MAXCMD-4) && S[SLen] && !isspace(S[SLen]))
  {
    if ((S[SLen] == '\'') || isspace(S[SLen]) || !isprint(S[SLen]))
		{
			sprintf(GlobalTempFile+GLen,"%%%02x",(unsigned char)(S[SLen]));
			GLen += 3;
		}
		else GlobalTempFile[GLen++] = S[SLen];
    SLen++;
	}
  S+=SLen;
  while(S[0] && isspace(S[0])) S++; /* skip spaces */
#endif
  if (TempFileDir)
	{
		memset(GlobalTempFile,'\0',MAXCMD);
		snprintf(GlobalTempFile,MAXCMD-1,"%s/wget.%d",TempFileDir,getpid());
	}

  /* third value is the URL location -- taint any single-quotes */
  SLen=0;
  GLen=0;
  while((GLen < MAXCMD-4) && S[SLen] && !isspace(S[SLen]))
  {
    if ((S[SLen] == '\'') || isspace(S[SLen]) || !isprint(S[SLen]))
    {
      sprintf(GlobalURL+GLen,"%%%02x",(unsigned char)(S[SLen]));
      GLen += 3;
    }
    else GlobalURL[GLen++] = S[SLen];
    SLen++;
  }
  S+=SLen;

  while(S[0] && isspace(S[0])) S++; /* skip spaces */
  strncpy(GlobalParam, S, sizeof(GlobalParam)); // get the parameters, kind of " -A rpm -R fosso -l 1* "
#if 1
  printf("  LOG upload %ld wget_agent globals loaded:\n  upload_pk = %ld\n  tmpfile=%s\n  URL=%s  GlobalParam=%s\n",GlobalUploadKey,
  	GlobalUploadKey,GlobalTempFile,GlobalURL,GlobalParam);
#endif
} /* SetEnv() */

/**
 * \brief Here are some suggested options
 *
 * \param char *Name - the name of the executable, ususlly it is wget_agent
 */
void	Usage	(char *Name)
{
  printf("Usage: %s [options] [OBJ]\n",Name);
  printf("  -i  :: Initialize the DB connection then exit (nothing downloaded)\n");
  printf("  -g group :: Set the group on processed files (e.g., -g fossy).\n");
  printf("  -G  :: Do NOT copy the file to the gold repository.\n");
  printf("  -d dir :: directory for downloaded file storage\n");
  printf("  -k key :: upload key identifier (number)\n");
  printf("  -A acclist :: Specify comma-separated lists of file name suffixes or patterns to accept.\n");
  printf("  -R rejlist :: Specify comma-separated lists of file name suffixes or patterns to reject.\n");
  printf("  -l depth :: Specify recursion maximum depth level depth.  The default maximum depth is 5.\n");
  printf("  OBJ :: if a URL is listed, then it is retrieved.\n");
  printf("         if a file is listed, then it used.\n");
  printf("         if OBJ and Key are provided, then it is inserted into\n");
  printf("         the DB and repository.\n");
  printf("  no file :: process data from the scheduler.\n");
} /* Usage() */

