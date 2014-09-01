/***************************************************************
 wget_agent: Retrieve a file and put it in the database.

 Copyright (C) 2007-2014 Hewlett-Packard Development Company, L.P.

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

char SQL[MAXCMD];

/* for the DB */
PGconn *pgConn = NULL;
/* input for this system */
long GlobalUploadKey=-1;
char GlobalTempFile[MAXCMD];
char GlobalURL[MAXCMD];
char GlobalType[MAXCMD];
char GlobalParam[MAXCMD];
char *GlobalProxy[6];
char GlobalHttpProxy[MAXCMD];
int GlobalImportGold=1; /* set to 0 to not store file in gold repository */
gid_t ForceGroup=-1;

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
 * \brief Closes the connection to the server, free the database connection, and exit.
 *
 * \param int rc - exit value
 */ 
void  SafeExit(int rc)
{
  if (pgConn) PQfinish(pgConn);
  fo_scheduler_disconnect(rc);
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
 */
void DBLoadGold()
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

  LOG_VERBOSE0("Processing %s",GlobalTempFile);
  Fin = fopen(GlobalTempFile,"rb");
  if (!Fin)
  {
    LOG_FATAL("upload %ld Unable to open temp file %s from %s",
        GlobalUploadKey,GlobalTempFile,GlobalURL);
    SafeExit(1);
  }
  Sum = SumComputeFile(Fin);
  fclose(Fin);
  if (ForceGroup > 0) { chown(GlobalTempFile,-1,ForceGroup); }

  if (!Sum)
  {
    LOG_FATAL("upload %ld Unable to compute checksum for %s from %s",
        GlobalUploadKey,GlobalTempFile,GlobalURL);
    SafeExit(2);
  }
  if (Sum->DataLen <= 0)
  {
    LOG_FATAL("upload %ld No bytes downloaded from %s to %s.",
        GlobalUploadKey,GlobalURL,GlobalTempFile);
    SafeExit(3);
  }
  Unique = SumToString(Sum);
  LOG_VERBOSE0("Unique %s",Unique);

  if (GlobalImportGold)
  {
    LOG_VERBOSE0("Import Gold %s",Unique);
    rc = fo_RepImport(GlobalTempFile,"gold",Unique,1);
    if (rc != 0)
    {
      LOG_FATAL("upload %ld Failed to import %s from %s into repository gold %s",
          GlobalUploadKey,GlobalTempFile,GlobalURL,Unique);
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
  LOG_VERBOSE0("Path is %s",Path);

  if (!Path)
  {
    LOG_FATAL("upload %ld Failed to determine repository location for %s in gold",
        GlobalUploadKey,Unique);
    SafeExit(5);
  }
  LOG_VERBOSE0("Import files %s",Path);
  if (fo_RepImport(Path,"files",Unique,1) != 0)
  {
    LOG_FATAL("upload %ld Failed to import %s from %s into files",
        GlobalUploadKey,Unique,Path);
    SafeExit(6);
  }
  if (ForceGroup >= 0) { chown(Path,-1,ForceGroup); }
  if (Path != GlobalTempFile) 
  {
    if(Path)
    {
      free(Path);
      Path = NULL;
    }
  }

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
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(7);

  /* See if pfile needs to be added */
  if (PQntuples(result) <=0)
  {
    /* Insert it */
    memset(SQL,'\0',MAXCMD);
    snprintf(SQL,MAXCMD-1,"INSERT INTO pfile (pfile_sha1, pfile_md5, pfile_size) VALUES ('%.40s','%.32s',%s)",
        SHA1,MD5,Len);
    PQclear(result);
    result = PQexec(pgConn, SQL);
    if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(8);
    PQclear(result);
    result = PQexec(pgConn, "SELECT currval('pfile_pfile_pk_seq')");
    if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(182);
  }
  PfileKey = atol(PQgetvalue(result,0,0));
  LOG_VERBOSE0("pfile_pk = %ld",PfileKey);

  /* Update the DB so the pfile is linked to the upload record */
  PQclear(result);
  result = PQexec(pgConn, "BEGIN");
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(-1);

  memset(SQL,0,MAXCMD);
  snprintf(SQL,MAXCMD-1,"SELECT * FROM upload WHERE upload_pk=%ld FOR UPDATE;",GlobalUploadKey);
  PQclear(result);
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(-1);

  memset(SQL,0,MAXCMD);
  snprintf(SQL,MAXCMD-1,"UPDATE upload SET pfile_fk=%ld WHERE upload_pk=%ld",
      PfileKey,GlobalUploadKey);
  LOG_VERBOSE0("SQL=%s\n",SQL);
  PQclear(result);
  result = PQexec(pgConn, SQL);
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(9);
  PQclear(result);
  result = PQexec(pgConn, "COMMIT;");
  if (fo_checkPQcommand(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(92);
  PQclear(result);

  /* Clean up */
  if (Sum)
  {
    free(Sum);
    Sum = NULL;
  }
  if (Unique)
  {
    free(Unique);
    Unique = NULL;
  }
} /* DBLoadGold() */


/**
 * \brief Given a URL string, taint-protect it.
 *
 * \param char *Sin - the source URL
 * \param char *Sout - the tainted URL  
 *
 * \return 1=tainted, 0=failed to taint
 */
int TaintURL(char *Sin, char *Sout, int SoutSize)
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
 *                        when running from command, this parameter is null, e.g. //srv/fossology/repository/localhost/wget/wget.32732
 * \param char *URL - the url you want to download
 * \param char *TempFileDir - where you want to store your downloaded file(directory)
 *
 * \return int, 0 on success, non-zero on failure.
 */
int GetURL(char *TempFile, char *URL, char *TempFileDir)
{
  char CMD[MAXCMD];
  char TaintedURL[MAXCMD];
  char TempFileDirectory[MAXCMD];
  char DeleteTempDirCmd[MAXCMD];
  int rc;

  memset(TempFileDirectory,'\0',MAXCMD);
  memset(DeleteTempDirCmd,'\0',MAXCMD);
  
  /** save each upload files in /srv/fossology/repository/localhost/wget/wget.xxx.dir/ */
  sprintf(TempFileDirectory, "%s.dir", TempFile);
  sprintf(DeleteTempDirCmd, "rm -rf %s", TempFileDirectory);
#if 1
  char WgetArgs[]="--no-check-certificate --progress=dot -rc -np -e robots=off -k";
#else
  /* wget < 1.10 does not support "--no-check-certificate" */
  char WgetArgs[]="--progress=dot -rc -np -e robots=off";
#endif

  if (!TaintURL(URL,TaintedURL,MAXCMD))
  {
    LOG_FATAL("Failed to taint the URL '%s'",URL);
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
  char no_proxy[MAXCMD] = {0};
  char proxy[MAXCMD] = {0};
  char proxy_temp[MAXCMD] = {0};

  /* http_proxy is optional so don't error if it doesn't exist */
  /** set proxy */
  if (GlobalProxy[0] && GlobalProxy[0][0])
  {
    snprintf(proxy_temp, MAXCMD-1, "export http_proxy='%s' ;", GlobalProxy[0]);
    strcat(proxy, proxy_temp);
  }
  if (GlobalProxy[1] && GlobalProxy[1][0])
  {
    snprintf(proxy_temp, MAXCMD-1, "export https_proxy='%s' ;", GlobalProxy[1]);
    strcat(proxy, proxy_temp);
  }
  if (GlobalProxy[2] && GlobalProxy[2][0])
  {
    snprintf(proxy_temp, MAXCMD-1, "export ftp_proxy='%s' ;", GlobalProxy[2]);
    strcat(proxy, proxy_temp);
  }
  if (GlobalProxy[3] && GlobalProxy[3][0])
  {
    snprintf(no_proxy, MAXCMD-1, "-e no_proxy=%s", GlobalProxy[3]);
  }

  if (TempFile && TempFile[0])
  {
    /* Delete the temp file if it exists */
    unlink(TempFile);
    snprintf(CMD,MAXCMD-1," %s /usr/bin/wget -q %s -P '%s' '%s' %s %s 2>&1",
        proxy, WgetArgs,TempFileDirectory,TaintedURL,GlobalParam, no_proxy);
  }
  else if(TempFileDir && TempFileDir[0])
  {
    snprintf(CMD,MAXCMD-1," %s /usr/bin/wget -q %s -P '%s' '%s' %s %s 2>&1",
        proxy, WgetArgs, TempFileDir, TaintedURL, GlobalParam, no_proxy);
  }
  else 
  {
    snprintf(CMD,MAXCMD-1," %s /usr/bin/wget -q %s '%s' %s %s 2>&1",
        proxy, WgetArgs,TaintedURL, GlobalParam, no_proxy);
  }
  /* the command is like
  ". /usr/local/etc/fossology/Proxy.conf; 
     /usr/bin/wget -q --no-check-certificate --progress=dot -rc -np -e robots=off -k -P 
     '/srv/fossology/repository/localhost/wget/wget.xxx.dir/'
     'http://a.org/file' -l 1 -R index.html*  2>&1"
   */
  LOG_VERBOSE0("CMD: %s", CMD);
  rc = system(CMD); 

  if (WIFEXITED(rc) && (WEXITSTATUS(rc) != 0))
  {
    LOG_FATAL("upload %ld Download failed; Return code %d from: %s",GlobalUploadKey,WEXITSTATUS(rc),CMD);
    unlink(GlobalTempFile);
    system(DeleteTempDirCmd);
    SafeExit(12);
  }

  /* Run from scheduler! store /srv/fossology/repository/localhost/wget/wget.xxx.dir/<files|directories> to one temp file */
  if (TempFile && TempFile[0])
  {
    char TempFilePath[MAXCMD];
    memset(TempFilePath,'\0',MAXCMD);
    /* for one url http://a.org/test.deb, TempFilePath should be /srv/fossology/repository/localhost/wget/wget.xxx.dir/a.org/test.deb */
    int Position = GetPosition(TaintedURL);
    if (0 == Position)
    {
      LOG_FATAL("path %s is not http://, https://, or ftp://", TaintedURL);
      unlink(GlobalTempFile);
      system(DeleteTempDirCmd);
      SafeExit(26);
    }
    snprintf(TempFilePath, MAXCMD-1, "%s/%s", TempFileDirectory, TaintedURL + Position);

    if (!stat(TempFilePath, &sb))
    {
      memset(CMD,'\0',MAXCMD);
      if (S_ISDIR(sb.st_mode))
      {
        snprintf(CMD,MAXCMD-1, "find '%s' -mindepth 1 -type d -empty -exec rmdir {} \\; > /dev/null 2>&1", TempFilePath);
        system(CMD); // delete all empty directories downloaded
        memset(CMD,'\0',MAXCMD);
        snprintf(CMD,MAXCMD-1, "tar -cvvf  '%s' -C '%s' ./ >/dev/null 2>&1", TempFile, TempFilePath);
      }
      else
      {
        snprintf(CMD,MAXCMD-1, "mv '%s' '%s' 2>&1", TempFilePath, TempFile);
      }
      rc_system = system(CMD);
      if (rc_system != 0)
      {
        unlink(GlobalTempFile);
        system(DeleteTempDirCmd);
        SafeExit(24); // failed to store the temperary directory(one file) as one temperary file
      }
    }
    else
    {
      memset(CMD,'\0',MAXCMD);
      snprintf(CMD,MAXCMD-1, "find '%s' -type f -exec mv {} %s \\; > /dev/null 2>&1", TempFileDirectory, TempFile); 
      rc_system = system(CMD);
      if (rc_system != 0)
      {
        unlink(GlobalTempFile);
        system(DeleteTempDirCmd);
        SafeExit(24); // failed to store the temperary directory(one file) as one temperary file
      }
    }
  } 

  if (TempFile && TempFile[0] && !IsFile(TempFile,1))
  {
    LOG_FATAL("upload %ld File %s not created from URL: %s, CMD: %s",GlobalUploadKey,TempFile,URL, CMD);
    unlink(GlobalTempFile);
    system(DeleteTempDirCmd);
    SafeExit(15);
  }

  /** remove the temp dir /srv/fossology/repository/localhost/wget/wget.xxx.dir/ for this upload */
  system(DeleteTempDirCmd);
  LOG_VERBOSE0("upload %ld Downloaded %s to %s",GlobalUploadKey,URL,TempFile);
  return(0);
} /* GetURL() */

/**
 * \brief get source code from version control system
 * 
 * \return int - 0: successful; others: fail
 */
int GetVersionControl()
{
  char Type[][4] = {"SVN", "Git", "CVS"};
  char command[MAXCMD] = {0};
  char TempFileDirectory[MAXCMD];
  char DeleteTempDirCmd[MAXCMD];
  int rc = 0;
  int flag = 0; // 0: default; 1: home is null before setting, should rollback
  char *homeenv = NULL;
  homeenv = getenv("HOME");
  char *repo = "/srv/fossology";
  if(NULL == strstr(homeenv, repo))
  {
    setenv("HOME", "/srv/fossology", 1);
    flag = 1;
  }

  /** save each upload files in /srv/fossology/repository/localhost/wget/wget.xxx.dir/ */
  sprintf(TempFileDirectory, "%s.dir", GlobalTempFile);
  sprintf(DeleteTempDirCmd, "rm -rf %s", TempFileDirectory);

  if (0 == strcmp(GlobalType, Type[0]))
  {
    if (GlobalProxy[0] && GlobalProxy[0][0])
      sprintf(command, "svn --config-option servers:global:http-proxy-host=%s --config-option servers:global:http-proxy-port=%s export %s %s %s --no-auth-cache >/dev/null 2>&1", GlobalProxy[4], GlobalProxy[5], GlobalURL, GlobalParam, TempFileDirectory);
    else
      sprintf(command, "svn export %s %s %s --no-auth-cache >/dev/null 2>&1", GlobalURL, GlobalParam, TempFileDirectory);
  }
  else if (0 == strcmp(GlobalType, Type[1]))
  {
    replace_url_with_auth();
    if (GlobalProxy[0] && GlobalProxy[0][0])
      sprintf(command, "git config --global http.proxy %s && git clone %s %s %s  && rm -rf %s/.git", GlobalProxy[0], GlobalURL, GlobalParam, TempFileDirectory, TempFileDirectory);
    else
      sprintf(command, "git clone %s %s %s >/dev/null 2>&1 && rm -rf %s/.git", GlobalURL, GlobalParam, TempFileDirectory, TempFileDirectory);
  }

  rc = system(command);
  if (flag) // rollback
  {
    setenv("HOME", homeenv, 1);
  }

  if (rc != 0)
  {
    LOG_FATAL("command is:%s\n", command);
    /** for user fossy */
    /** git: git config --global http.proxy web-proxy.cce.hp.com:8088; git clone http://github.com/schacon/grit.git */
    /** svn: svn checkout --config-option servers:global:http-proxy-host=web-proxy.cce.hp.com --config-option servers:global:http-proxy-port=8088 https://svn.code.sf.net/p/fossology/code/trunk/fossology/utils/ **/
    LOG_FATAL("please make sure the URL of repo is correct, also add correct proxy for your version control system, command is:%s, GlobalTempFile is:%s, rc is:%d. \n", command, GlobalTempFile, rc);
    system(DeleteTempDirCmd); /** remove the temp dir /srv/fossology/repository/localhost/wget/wget.xxx.dir/ for this upload */
    return 1;
  }

  snprintf(command,MAXCMD-1, "tar -cvvf  '%s' -C '%s' ./ >/dev/null 2>&1", GlobalTempFile, TempFileDirectory);
  rc = system(command);
  if (rc != 0)
  {
    LOG_FATAL("command is:%s\n", command);
    system(DeleteTempDirCmd); /** remove the temp dir /srv/fossology/repository/localhost/wget/wget.xxx.dir/ for this upload */
    LOG_FATAL("DeleteTempDirCmd is:%s\n", DeleteTempDirCmd);
    return 1;
  }

  system(DeleteTempDirCmd); /** remove the temp dir /srv/fossology/repository/localhost/wget/wget.xxx.dir/ for this upload */

  return 0; // succeed to retrieve source
}

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
  while((GLen < MAXCMD-4) && S[SLen])
  {
    if ((S[SLen] == '\\') && isprint(S[SLen+1])) // in file path, if include '\ ', that mean this file name include spaces
    {
      LOG_FATAL("S[SLen] is:%c\n", S[SLen]);
      GlobalURL[GLen++] = ' '; 
      SLen += 2;
      continue;
    }
    else if ((S[SLen] != '\\') && isspace(S[SLen])) break;
    else if ((S[SLen] == '\'') || isspace(S[SLen]) || !isprint(S[SLen]))
    {
      sprintf(GlobalURL+GLen,"%%%02x",(unsigned char)(S[SLen]));
      GLen += 3;
    }
    else GlobalURL[GLen++] = S[SLen];
    SLen++;
  }
  S+=SLen;

  while(S[0] && isspace(S[0])) S++; /* skip spaces */

  char Type[][4] = {"SVN", "Git", "CVS"};
  int i = 0; // type index

  memset(GlobalType,'\0',MAXCMD);
  strncpy(GlobalType, S, 3);
  if ((0 == strcmp(GlobalType, Type[i++])) || (0 == strcmp(GlobalType, Type[i++])) || (0 == strcmp(GlobalType, Type[i++])))
  {
    S += 3;
  }
  else
  {
    memset(GlobalType,'\0',MAXCMD);
  }

  strncpy(GlobalParam, S, sizeof(GlobalParam)); // get the parameters, kind of " -A rpm -R fosso -l 1* "
  LOG_VERBOSE0("  upload %ld wget_agent globals loaded:\n  upload_pk = %ld\n  tmpfile=%s  URL=%s  GlobalParam=%s\n",GlobalUploadKey, GlobalUploadKey,GlobalTempFile,GlobalURL,GlobalParam);
} /* SetEnv() */


/**
 * @brief Check if path contains a "%H". If so, substitute the hostname.
 * @parm DirPath Directory path.
 * @returns new directory path
 **/
char *PathCheck(char *DirPath)
{
  char *NewPath;
  char *subs;
  char  TmpPath[2048];
  char  HostName[2048];

  NewPath = strdup(DirPath);

  if ((subs = strstr(NewPath,"%H")) )
  {
    /* hostname substitution */
    gethostname(HostName, sizeof(HostName));

    *subs = 0;
    snprintf(TmpPath, sizeof(TmpPath), "%s%s%s", NewPath, HostName, subs+2);
    free(NewPath);
    NewPath = strdup(TmpPath);
  }

  if ((subs = strstr(NewPath, "%R")) )
  {
    /* repo location substitution */
    *subs = 0;

    snprintf(TmpPath, sizeof(TmpPath), "%s%s%s", NewPath, fo_config_get(sysconfig, "FOSSOLOGY", "path", NULL), subs+2);
    free(NewPath);
    NewPath = strdup(TmpPath);
  }

  return(NewPath);
}

/**
 * \brief if the path(fs) is a directory, create a tar file from files(dir) in a directory 
 * to the temporary directory
 * if the path(fs) is a file, copy the file to the temporary directory
 *
 * \param char *Path - the fs will be handled, directory(file) you want to upload from server 
 * \param char *TempFile - the tar(reguler) file name
 * \param struct stat Status - the status of Path
 *
 * \return 1 on sucess, 0 on failure
 */
int Archivefs(char *Path, char *TempFile, char *TempFileDir, struct stat Status)
{
  char CMD[MAXCMD] = {0};
  int rc_system = 0;

  snprintf(CMD,MAXCMD-1, "mkdir -p '%s' >/dev/null 2>&1", TempFileDir);
  system(CMD);

  if (S_ISDIR(Status.st_mode)) /** directory? */
  {
    memset(CMD, MAXCMD, 0);
    snprintf(CMD,MAXCMD-1, "tar -cvvf  '%s' -C '%s' ./ %s >/dev/null 2>&1", TempFile, Path, GlobalParam);
    rc_system = system(CMD);
    if (rc_system != 0)
    {
      LOG_FATAL("rc_system is:%d, CMD is:%s\n", rc_system, CMD);
      return 0;
    }
  } else if (strstr(Path, "*"))  // wildcards
  {
    memset(CMD, MAXCMD, 0);
    /* for the wildcards upload, keep the path */
    /* copy * files to TempFileDir/temp primarily */
    snprintf(CMD,MAXCMD-1, "mkdir -p %s/temp  > /dev/null 2>&1 && cp %s  %s/temp > /dev/null 2>&1", TempFileDir, Path, TempFileDir);
    rc_system = system(CMD);
    if (rc_system != 0)
    {
      LOG_FATAL("rc_system is:%d, CMD is:%s\n", rc_system, CMD);
      return 0;
    }
    memset(CMD, MAXCMD, 0);
    snprintf(CMD,MAXCMD-1, "tar -cvvf  '%s' -C %s/temp ./  > /dev/null 2>&1 && rm -rf %s/temp  > /dev/null 2>&1", TempFile, TempFileDir, TempFileDir);
    rc_system = system(CMD);
    if (rc_system != 0)
    {
      LOG_FATAL("rc_system is:%d, CMD is:%s\n", rc_system, CMD);
      return 0;
    }
  } else if(S_ISREG(Status.st_mode)) /** regular file? */
  {
    memset(CMD, MAXCMD, 0);
    snprintf(CMD,MAXCMD-1, "cp '%s' '%s' >/dev/null 2>&1", Path, TempFile);
    rc_system = system(CMD);
    if (rc_system != 0)
    {
      LOG_FATAL("rc_system is:%d, CMD is:%s\n", rc_system, CMD);
      return 0;
    }
  }
  else return 0; /** neither a directory nor a regular file */

  return 1;
}

/** 
 * \brief get proxy from fossology.conf
 */
void GetProxy()
{
  int i = 0;
  int count_temp = 0;
  char *http_proxy_host = NULL;
  char *http_proxy_port = NULL;
  char *http_temp = NULL;

  for (i = 0; i < 6; i++)
  {
    GlobalProxy[i++] = NULL;
  }
  GError* error1 = NULL;
  GError* error2 = NULL;
  GError* error3 = NULL;
  GError* error4 = NULL;

  i = 0;
  GlobalProxy[i] = fo_config_get(sysconfig, "FOSSOLOGY", "http_proxy", &error1);
  trim(GlobalProxy[i++]);
  GlobalProxy[i] = fo_config_get(sysconfig, "FOSSOLOGY", "https_proxy", &error2);
  trim(GlobalProxy[i++]);
  GlobalProxy[i] = fo_config_get(sysconfig, "FOSSOLOGY", "ftp_proxy", &error3);
  trim(GlobalProxy[i++]);
  GlobalProxy[i] = fo_config_get(sysconfig, "FOSSOLOGY", "no_proxy", &error4);
  trim(GlobalProxy[i++]);

  
  if (GlobalProxy[0] && GlobalProxy[0][0])
  {
    http_proxy_port = strrchr(GlobalProxy[0], ':');
    strncpy(GlobalHttpProxy, GlobalProxy[0], (http_proxy_port - GlobalProxy[0]));
    http_proxy_port++;

    if (http_proxy_port && http_proxy_port[0])
    {
      /* exclude '/' in http_proxy_port and 'http://' in http_proxy_host */
      http_temp = strchr(http_proxy_port, '/'); 
      if (http_temp && http_temp[0])
      {
        count_temp = http_temp - http_proxy_port;
        http_proxy_port[count_temp] = 0;
      }
      GlobalProxy[4] = GlobalHttpProxy;
      GlobalProxy[5] = http_proxy_port;

      http_proxy_host = strrchr(GlobalHttpProxy, '/');
      if (http_proxy_host && http_proxy_host[0])
      {
        http_proxy_host++;
        GlobalProxy[4] = http_proxy_host;
      }
    }
  }
}

/**
 * \brief Here are some suggested options
 *
 * \param char *Name - the name of the executable, ususlly it is wget_agent
 */
void Usage(char *Name)
{
  printf("Usage: %s [options] [OBJ]\n",Name);
  printf("  -h  :: help (print this message), then exit.\n");
  printf("  -i  :: Initialize the DB connection then exit (nothing downloaded)\n");
  printf("  -g group :: Set the group on processed files (e.g., -g fossy).\n");
  printf("  -G  :: Do NOT copy the file to the gold repository.\n");
  printf("  -d dir :: directory for downloaded file storage\n");
  printf("  -k key :: upload key identifier (number)\n");
  printf("  -A acclist :: Specify comma-separated lists of file name suffixes or patterns to accept.\n");
  printf("  -R rejlist :: Specify comma-separated lists of file name suffixes or patterns to reject.\n");
  printf("  -l depth :: Specify recursion maximum depth level depth.  The default maximum depth is 5.\n");
  printf("  -c configdir :: Specify the directory for the system configuration.\n");
  printf("  -C :: run from command line.\n");
  printf("  -v :: verbose (-vv = more verbose).\n");
  printf("  -V :: print the version info, then exit.\n");
  printf("  OBJ :: if a URL is listed, then it is retrieved.\n");
  printf("         if a file is listed, then it used.\n");
  printf("         if OBJ and Key are provided, then it is inserted into\n");
  printf("         the DB and repository.\n");
  printf("  no file :: process data from the scheduler.\n");
} /* Usage() */

 /**
  * \brief translate authentication of git clone
  * from http://git.code.sf.net/p/fossology/fossology.git --username --password password (input)
  * to http://username:password@git.code.sf.net/p/fossology/fossology.git
  */
void replace_url_with_auth()
{
  const char needle[] = " ";
  const char needle2[] = "//";
  int index = 0;
  char *username = NULL;
  char *password = NULL;
  char http[10] = "";
  char URI[FILEPATH] = "";
  char *token = NULL;
  char *temp = NULL;

  if (strstr(GlobalParam, "password") && strstr(GlobalParam, "username"))
  {
    temp = strstr(GlobalURL, needle2);
    strcpy(URI, temp + 2);
    strncpy(http, GlobalURL, strlen(GlobalURL) - strlen(URI));

    /* get the first token */
    token = strtok(GlobalParam, needle);
    /* walk through other tokens */
    while( token != NULL )
    {
      if (1 == index) username = token;
      if (3 == index) password = token;
      token = strtok(NULL, needle);
      index++;
    }
    snprintf(GlobalURL, FILEPATH, "%s%s:%s@%s", http, username, password, URI);
    memset(GlobalParam,'\0',MAXCMD);
  }
}

