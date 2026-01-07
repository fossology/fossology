/*
 wget_agent: Retrieve a file and put it in the database.

 SPDX-FileCopyrightText: © 2007-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file wget_agent.c
 * \brief wget_agent: Retrieve a file and put it in the database.
 */
#define _GNU_SOURCE           // for asprintf

#define ASPRINTF_MEM_ERROR  88
#define ASPRINTF_MEM_ERROR_LOG LOG_FATAL("Not enough memory for asprintf before line %d", __LINE__)

#include "wget_agent.h"
#include <sys/vfs.h>

#ifndef NFS_SUPER_MAGIC
#define NFS_SUPER_MAGIC 0x6969
#endif

char SQL[STRMAX];

PGconn *pgConn = NULL;        ///< For the DB
long GlobalUploadKey=-1;      ///< Input for this system
char GlobalTempFile[STRMAX];  ///< Temp file to be used
char GlobalURL[URLMAX];       ///< URL to download
char GlobalType[STRMAX];      ///< Type of download (FILE/version control)
char GlobalParam[STRMAX];     ///< Additional parameters
char *GlobalProxy[6];         ///< Proxy from fossology.conf
char GlobalHttpProxy[STRMAX]; ///< HTTP proxy command to use
int GlobalImportGold=1;       ///< Set to 0 to not store file in gold repository
gid_t ForceGroup=-1;          ///< Set to group id to be used for download files

/**
 * \brief Given a filename, is it a file?
 * \param Link Should it follow symbolic links?
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
 * \brief Check if a path is on an NFS filesystem
 * \param path Path to check
 * \return int 1=NFS, 0=not NFS or error
 */
int IsNFSMount(const char *path)
{
  struct statfs buf;
  if (!path || path[0] == '\0') return 0;
  
  if (statfs(path, &buf) == 0)
  {
    return (buf.f_type == NFS_SUPER_MAGIC);
  }
  
  /* statfs() failed - log for debugging */
  LOG_WARNING("Failed to check filesystem type for %s: %s. Treating as non-NFS.",
              path, strerror(errno));
  return 0;
} /* IsNFSMount() */

/**
 * \brief Validate file access permissions (read/write)
 * \param path Path to check
 * \return int 0=OK, -1=error
 */
int ValidateFileAccess(const char *path)
{
  if (!path || path[0] == '\0') return -1;
  
  /* Check if file is readable and writable by current user */
  if (access(path, R_OK | W_OK) == 0)
  {
    return 0;
  }
  
  LOG_ERROR("File %s is not readable/writable by current user (uid=%d, gid=%d). "
            "For NFS mounts, ensure: 1) NFS export has correct UID/GID mapping, "
            "2) Mount options include 'rw', 3) File ownership matches container user",
            path, getuid(), getgid());
  return -1;
} /* ValidateFileAccess() */

/**
 * \brief Smart chown: skip on NFS, validate permissions instead
 * \param path Path to chown
 * \param group Group ID
 * \return int 0=success, -1=error
 */
int SmartChown(const char *path, gid_t group)
{
  int rc;
  
  if (!path || path[0] == '\0') return -1;
  
  /* Check if on NFS */
  if (IsNFSMount(path))
  {
    LOG_NOTICE("Skipping chown on NFS mount: %s. Validating permissions instead...", path);
    rc = ValidateFileAccess(path);
    if (rc != 0)
    {
      LOG_ERROR("Permission validation failed for %s on NFS mount", path);
      return -1;
    }
    LOG_VERBOSE0("Permission validation passed for %s", path);
    return 0;
  }
  
  /* Not NFS - do normal chown */
  rc = chown(path, -1, group);
  if (rc != 0)
  {
    LOG_ERROR("chown failed on %s, error: %s", path, strerror(errno));
    return -1;
  }
  
  return 0;
} /* SmartChown() */

/**
 * \brief Closes the connection to the server, free the database connection, and exit.
 * \param rc Exit value
 */
void  SafeExit(int rc)
{
  if (pgConn) PQfinish(pgConn);
  fo_scheduler_disconnect(rc);
  exit(rc);
} /* SafeExit() */

/**
 * \brief Get the position (ending + 1) of http|https|ftp:// of one url
 * \param URL The URL
 * \return the position (ending + 1) of http|https|ftp:// of one url
 *         E.g. http://fossology.org return 7
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
 *
 * Copy the downloaded file to gold repository according to
 * the checksum and change the owner if ForceGroup is set.
 * Then insert in upload and pfile tables.
 */
void DBLoadGold()
{
  Cksum *Sum;
  char *Unique=NULL;
  char *SHA1, *MD5, *Len;
  char SQL[STRMAX];
  long PfileKey;
  char *Path;
  char SHA256[65];
  FILE *Fin;
  int rc = -1;
  PGresult *result;
  memset(SHA256, '\0', sizeof(SHA256));

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

  // Calculate sha256 value
  rc = calc_sha256sum(GlobalTempFile, SHA256);
  if (rc != 0)
  {
    LOG_FATAL("Unable to calculate SHA256 of %s\n", GlobalTempFile);
    SafeExit(56);
  }

  if ((int)ForceGroup > 0)
  {
    if (SmartChown(GlobalTempFile, ForceGroup) != 0)
    {
      LOG_FATAL("Failed to set permissions on %s", GlobalTempFile);
      SafeExit(10);
    }
  }

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
    if ((int)ForceGroup >= 0)
    {
      if (SmartChown(Path, ForceGroup) != 0)
      {
        LOG_FATAL("Failed to set permissions on %s", Path);
        SafeExit(11);
      }
    }
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

  /* Set permissions on the files repository copy (not the gold repo) */
  if ((int)ForceGroup >= 0)
  {
    char *filesPath = fo_RepMkPath("files", Unique);
    if (filesPath == NULL)
    {
      LOG_FATAL("Failed to determine repository location for %s in files", Unique);
      SafeExit(12);
    }
    if (SmartChown(filesPath, ForceGroup) != 0)
    {
      LOG_FATAL("Failed to set permissions on %s", filesPath);
      free(filesPath);
      SafeExit(12);
    }
    free(filesPath);
  }

  if (Path != GlobalTempFile)
  {
    if(Path)
    {
      free(Path);
      Path = NULL;
    }
  }

  /* Now update the DB */
  /* Break out the sha1, md5, len components **/
  SHA1 = Unique;
  MD5 = Unique+41; /* 40 for sha1 + 1 for '.' */
  Len = Unique+41+33; /* 32 for md5 + 1 for '.' */
  /* Set the pfile */
  memset(SQL,'\0',STRMAX);
  snprintf(SQL,STRMAX-1,"SELECT pfile_pk FROM pfile WHERE pfile_sha1 = '%.40s' AND pfile_md5 = '%.32s' AND pfile_size = %s;",
      SHA1,MD5,Len);
  result =  PQexec(pgConn, SQL); /* SELECT */
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(7);

  /* See if pfile needs to be added */
  if (PQntuples(result) <=0)
  {
    /* Insert it */
    memset(SQL,'\0',STRMAX);
    snprintf(SQL,STRMAX-1,"INSERT INTO pfile (pfile_sha1, pfile_md5, pfile_sha256, pfile_size) VALUES ('%.40s','%.32s','%.64s',%s)",
        SHA1,MD5,SHA256,Len);
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

  memset(SQL,0,STRMAX);
  snprintf(SQL,STRMAX-1,"SELECT * FROM upload WHERE upload_pk=%ld FOR UPDATE;",GlobalUploadKey);
  PQclear(result);
  result = PQexec(pgConn, SQL);
  if (fo_checkPQresult(pgConn, result, SQL, __FILE__, __LINE__)) SafeExit(-1);

  memset(SQL,0,STRMAX);
  snprintf(SQL,STRMAX-1,"UPDATE upload SET pfile_fk=%ld WHERE upload_pk=%ld",
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
 * \param[in]  Sin The source URL
 * \param[out] Sout The tainted URL
 * \param[in]  SoutSize The capacity of Sout
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
 * \brief Prepare directory for wget.
 * \param TempFile
 * \param TempFileDir
 * \param TempFileDirectory
 * \parblock
 * Internal helper function for function GetURL
 * \endparblock
 * \return destination for wget download or NULL
 */
char *PrepareWgetDest(char *TempFile, char *TempFileDir, char *TempFileDirectory)
{
  if (TempFile && TempFile[0])
  {
    /* Delete the temp file if it exists */
    unlink(TempFile);
    return TempFileDirectory;
  }
  else if(TempFileDir && TempFileDir[0])
  {
    return TempFileDir;
  }

  return NULL;
}


/**
 * \brief Do the wget.
 * \param TempFile
 * \parblock
 * Used when upload from URL by the scheduler, the downloaded file(directory) will be archived as this file.
 * When running from command, this parameter is null, e.g. /srv/fossology/repository/localhost/wget/wget.32732
 * \endparblock
 * \param URL The url you want to download
 * \param TempFileDir Where you want to store your downloaded file(directory)
 * \return int, 0 on success, non-zero on failure.
 */
int GetURL(char *TempFile, char *URL, char *TempFileDir)
{
  char *cmd;
  char TaintedURL[STRMAX];
  char TempFileDirectory[STRMAX+128];
  char *delete_tmpdir_cmd;
  int rc;
  int res;

  memset(TempFileDirectory,'\0',STRMAX+128);

  /* save each upload files in /srv/fossology/repository/localhost/wget/wget.xxx.dir/ */
  sprintf(TempFileDirectory, "%s.dir", TempFile);
  res = asprintf(&delete_tmpdir_cmd, "rm -rf %s", TempFileDirectory);
  if (res == -1)
  {
    ASPRINTF_MEM_ERROR_LOG;
    SafeExit(ASPRINTF_MEM_ERROR);
  }
#if 1
  char WgetArgs[]="--no-check-certificate --progress=dot -rc -np -e robots=off";
#else
  /* wget < 1.10 does not support "--no-check-certificate" */
  char WgetArgs[]="--progress=dot -rc -np -e robots=off";
#endif

  if (!TaintURL(URL,TaintedURL,STRMAX))
  {
    LOG_FATAL("Failed to taint the URL '%s'",URL);
    SafeExit(10);
  }

  /*
   Wget options:
   --progress=dot :: display a new line as it progresses.
   --no-check-certificate :: download HTTPS files even if the cert cannot
     be validated.  (Neal has many issues with SSL and does not view it
     as very secure.)  Without this, some caching proxies and web sites
     with old certs won't download.  Granted, in theory a bad cert should
     prevent downloads.  In reality, 99.9% of bad certs are because the
     admin did not notice that they expired and not because of a hijacking
     attempt.
   */

  struct stat sb;
  int rc_system =0;
  char no_proxy[STRMAX] = {0};
  char proxy[STRMAX] = {0};
  char proxy_temp[STRMAX] = {0};

  /* http_proxy is optional so don't error if it doesn't exist */
  /** set proxy */
  if (GlobalProxy[0] && GlobalProxy[0][0])
  {
    snprintf(proxy_temp, STRMAX-1, "export http_proxy='%s' ;", GlobalProxy[0]);
    strcat(proxy, proxy_temp);
  }
  if (GlobalProxy[1] && GlobalProxy[1][0])
  {
    snprintf(proxy_temp, STRMAX-1, "export https_proxy='%s' ;", GlobalProxy[1]);
    strcat(proxy, proxy_temp);
  }
  if (GlobalProxy[2] && GlobalProxy[2][0])
  {
    snprintf(proxy_temp, STRMAX-1, "export ftp_proxy='%s' ;", GlobalProxy[2]);
    strcat(proxy, proxy_temp);
  }
  if (GlobalProxy[3] && GlobalProxy[3][0])
  {
    snprintf(no_proxy, STRMAX-1, "-e no_proxy='%s'", GlobalProxy[3]);
  }

  char *dest;

  dest = PrepareWgetDest(TempFile, TempFileDir, TempFileDirectory);

  if (dest) {
    res = asprintf(&cmd," %s /usr/bin/wget -q %s -P '%s' '%s' %s %s 2>&1",
        proxy, WgetArgs, dest, TaintedURL, GlobalParam, no_proxy);
  }
  else
  {
    res = asprintf(&cmd," %s /usr/bin/wget -q %s '%s' %s %s 2>&1",
        proxy, WgetArgs, TaintedURL, GlobalParam, no_proxy);
  }

  if (res == -1)
  {
    ASPRINTF_MEM_ERROR_LOG;
    free(delete_tmpdir_cmd);
    SafeExit(ASPRINTF_MEM_ERROR);
  }

  /* the command is like
  ". /usr/local/etc/fossology/Proxy.conf;
     /usr/bin/wget -q --no-check-certificate --progress=dot -rc -np -e robots=off -P
     '/srv/fossology/repository/localhost/wget/wget.xxx.dir/'
     'http://a.org/file' -l 1 -R index.html*  2>&1"
   */
  LOG_VERBOSE0("CMD: %s", cmd);
  rc = system(cmd);

  if (WIFEXITED(rc) && (WEXITSTATUS(rc) != 0))
  {
    LOG_FATAL("upload %ld Download failed; Return code %d from: %s",GlobalUploadKey,WEXITSTATUS(rc),cmd);
    unlink(GlobalTempFile);
    rc_system = system(delete_tmpdir_cmd);
    if (!WIFEXITED(rc_system)) systemError(__LINE__, rc_system, delete_tmpdir_cmd)
    free(delete_tmpdir_cmd);
    SafeExit(12);
  }

  /* Run from scheduler! store /srv/fossology/repository/localhost/wget/wget.xxx.dir/<files|directories> to one temp file */
  if (TempFile && TempFile[0])
  {
    char* tmpfile_path;
    /* for one url http://a.org/test.deb, TempFilePath should be /srv/fossology/repository/localhost/wget/wget.xxx.dir/a.org/test.deb */
    int Position = GetPosition(TaintedURL);
    if (0 == Position)
    {
      LOG_FATAL("path %s is not http://, https://, or ftp://", TaintedURL);
      unlink(GlobalTempFile);
      rc_system = system(delete_tmpdir_cmd);
      if (!WIFEXITED(rc_system)) systemError(__LINE__, rc_system, delete_tmpdir_cmd)
      free(delete_tmpdir_cmd);
      SafeExit(26);
    }
    res = asprintf(&tmpfile_path, "%s/%s", TempFileDirectory, TaintedURL + Position);
    if (res == -1)
    {
      ASPRINTF_MEM_ERROR_LOG;
      free(delete_tmpdir_cmd);
      SafeExit(ASPRINTF_MEM_ERROR);
    }

    if (!stat(tmpfile_path, &sb))
    {
      if (S_ISDIR(sb.st_mode))
      {
        res = asprintf(&cmd, "find '%s' -mindepth 1 -type d -empty -exec rmdir {} \\; > /dev/null 2>&1", tmpfile_path);
        if (res == -1)
        {
          ASPRINTF_MEM_ERROR_LOG;
          free(tmpfile_path);
          free(delete_tmpdir_cmd);
          SafeExit(ASPRINTF_MEM_ERROR);
        }
        rc_system = system(cmd); // delete all empty directories downloaded
        if (!WIFEXITED(rc_system)) systemError(__LINE__, rc_system, cmd)
        free(cmd);

        res = asprintf(&cmd, "tar -cf  '%s' -C '%s' ./ 1>/dev/null", TempFile, tmpfile_path);
        if (res == -1)
        {
          ASPRINTF_MEM_ERROR_LOG;
          free(tmpfile_path);
          free(delete_tmpdir_cmd);
          SafeExit(ASPRINTF_MEM_ERROR);
        }
      }
      else
      {
        res = asprintf(&cmd, "mv '%s' '%s' 2>&1", tmpfile_path, TempFile);
        if (res == -1)
        {
          ASPRINTF_MEM_ERROR_LOG;
          free(tmpfile_path);
          free(delete_tmpdir_cmd);
          SafeExit(ASPRINTF_MEM_ERROR);
        }
      }

      free(tmpfile_path);

      rc_system = system(cmd);
      if (rc_system != 0)
      {
        systemError(__LINE__, rc_system, cmd)
        free(cmd);
        unlink(GlobalTempFile);
        rc_system = system(delete_tmpdir_cmd);
        if (!WIFEXITED(rc_system)) systemError(__LINE__, rc_system, delete_tmpdir_cmd)
        free(delete_tmpdir_cmd);
        SafeExit(24); // failed to store the temperary directory(one file) as one temperary file
      }

    }
    else
    {
      res = asprintf(&cmd, "find '%s' -type f -exec mv {} %s \\; > /dev/null 2>&1", TempFileDirectory, TempFile);
      if (res == -1)
      {
        ASPRINTF_MEM_ERROR_LOG;
        free(delete_tmpdir_cmd);
        SafeExit(ASPRINTF_MEM_ERROR);
      }
      rc_system = system(cmd);
      if (rc_system != 0)
      {
        systemError(__LINE__, rc_system, cmd)
        free(cmd);
        unlink(GlobalTempFile);
        rc_system = system(delete_tmpdir_cmd);
        if (!WIFEXITED(rc_system)) systemError(__LINE__, rc_system, delete_tmpdir_cmd)
        free(delete_tmpdir_cmd);
        SafeExit(24); // failed to store the temperary directory(one file) as one temperary file
      }

    }
  }

  if (TempFile && TempFile[0] && !IsFile(TempFile,1))
  {
    LOG_FATAL("upload %ld File %s not created from URL: %s, CMD: %s",GlobalUploadKey,TempFile,URL, cmd);
    free(cmd);
    unlink(GlobalTempFile);
    rc_system = system(delete_tmpdir_cmd);
    if (!WIFEXITED(rc_system)) systemError(__LINE__, rc_system, delete_tmpdir_cmd)
    free(delete_tmpdir_cmd);
    SafeExit(15);
  }

  free(cmd);

  /* remove the temp dir /srv/fossology/repository/localhost/wget/wget.xxx.dir/ for this upload */
  rc_system = system(delete_tmpdir_cmd);
  if (!WIFEXITED(rc_system)) systemError(__LINE__, rc_system, delete_tmpdir_cmd)
  LOG_VERBOSE0("upload %ld Downloaded %s to %s",GlobalUploadKey,URL,TempFile);

  free(delete_tmpdir_cmd);

  return(0);
} /* GetURL() */

/**
 * \brief Get source code from version control system
 * \return int - 0: successful; others: fail
 */
int GetVersionControl()
{
  char *command = NULL;
  char *tmp_file_directory;
  char *delete_tmpdir_cmd;
  char *tmp_home;

  int rc = 0;
  int resethome = 0; // 0: default; 1: home is null before setting, should rollback
  char *homeenv = NULL;
  int res;

  homeenv = getenv("HOME");
  if(NULL == homeenv) resethome = 1;

  /* We need HOME to point to where .gitconfig is installed
   * path is the repository path and .gitconfig is installed in its parent directory
   */
  res = asprintf(&tmp_home, "%s/..", fo_config_get(sysconfig, "FOSSOLOGY", "path", NULL));
  if (res == -1)
  {
    return ASPRINTF_MEM_ERROR;
  }

  setenv("HOME", tmp_home, 1);
  free(tmp_home);

  /* save each upload files in /srv/fossology/repository/localhost/wget/wget.xxx.dir/ */
  res = asprintf(&tmp_file_directory, "%s.dir", GlobalTempFile);
  if (res == -1)
  {
    ASPRINTF_MEM_ERROR_LOG;
    return ASPRINTF_MEM_ERROR;
  }

  res = asprintf(&delete_tmpdir_cmd, "rm -rf %s", tmp_file_directory);
  if (res == -1)
  {
    ASPRINTF_MEM_ERROR_LOG;
    free(tmp_file_directory);
    return ASPRINTF_MEM_ERROR;
  }

  command = GetVersionControlCommand(1);
  if (!command)
  {
    free(tmp_file_directory);
    return ASPRINTF_MEM_ERROR;
  }
  rc = system(command);
  free(command);

  if (resethome) // rollback
    unsetenv("HOME");
  else
    setenv("HOME", homeenv, 1);

  if (rc != 0)
  {
    command = GetVersionControlCommand(-1);
    if (!command)
    {
      ASPRINTF_MEM_ERROR_LOG;
      free(tmp_file_directory);
      return ASPRINTF_MEM_ERROR;
    }
    systemError(__LINE__, rc, command)
    /** for user fossy
    \code git: git config --global http.proxy web-proxy.cce.hp.com:8088; git clone http://github.com/schacon/grit.git
    svn: svn checkout --config-option servers:global:http-proxy-host=web-proxy.cce.hp.com --config-option servers:global:http-proxy-port=8088 https://svn.code.sf.net/p/fossology/code/trunk/fossology/utils/ \endcode
    */
    LOG_FATAL("please make sure the URL of repo is correct, also add correct proxy for your version control system, command is:%s, GlobalTempFile is:%s, rc is:%d. \n", command, GlobalTempFile, rc);
    /* remove the temp dir /srv/fossology/repository/localhost/wget/wget.xxx.dir/ for this upload */
    rc = system(delete_tmpdir_cmd);
    if (!WIFEXITED(rc)) systemError(__LINE__, rc, delete_tmpdir_cmd)
    free(command);
    free(tmp_file_directory);
    free(delete_tmpdir_cmd);
    return 1;
  }

  res = asprintf(&command, "tar -cf  '%s' -C '%s' ./ 1>/dev/null", GlobalTempFile, tmp_file_directory);
  if (res == -1)
  {
    ASPRINTF_MEM_ERROR_LOG;
    free(tmp_file_directory);
    free(delete_tmpdir_cmd);
    return ASPRINTF_MEM_ERROR;
  }
  free(tmp_file_directory);
  rc = system(command);
  if (rc != 0)
  {
    systemError(__LINE__, rc, command)
    /* remove the temp dir /srv/fossology/repository/localhost/wget/wget.xxx.dir/ for this upload */
    rc = system(delete_tmpdir_cmd);
    if (!WIFEXITED(rc)) systemError(__LINE__, rc, delete_tmpdir_cmd)
    LOG_FATAL("DeleteTempDirCmd is:%s\n", delete_tmpdir_cmd);
    free(delete_tmpdir_cmd);
    return 1;
  }

  /* remove the temp dir /srv/fossology/repository/localhost/wget/wget.xxx.dir/ for this upload */
  rc = system(delete_tmpdir_cmd);
  if (!WIFEXITED(rc)) systemError(__LINE__, rc, delete_tmpdir_cmd)
  free(delete_tmpdir_cmd);

  return 0; // succeed to retrieve source
}

/**
 * \brief Convert input pairs into globals.
 *
 * This functions taints the parameters as needed.
 * \param S The parameters for wget_aget have 2 parts, one is from scheduler, that is S
 * \param TempFileDir The parameters for wget_aget have 2 parts, one is from wget_agent.conf, that is TempFileDir
 */
void    SetEnv  (char *S, char *TempFileDir)
{
  int SLen,GLen; /* lengths for S and global string */

  GlobalUploadKey = -1;
  memset(GlobalTempFile,'\0',STRMAX);
  memset(GlobalURL,'\0',URLMAX);
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
  while((GLen < STRMAX-4) && S[SLen] && !isspace(S[SLen]))
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
    memset(GlobalTempFile,'\0',STRMAX);
    snprintf(GlobalTempFile,STRMAX-1,"%s/wget.%d",TempFileDir,getpid());
  }

  /* third value is the URL location -- taint any single-quotes */
  SLen=0;
  GLen=0;
  while((GLen < STRMAX-4) && S[SLen])
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

  memset(GlobalType,'\0',STRMAX);
  strncpy(GlobalType, S, 3);
  if ((0 == strcmp(GlobalType, Type[i++])) || (0 == strcmp(GlobalType, Type[i++])) || (0 == strcmp(GlobalType, Type[i++])))
  {
    S += 3;
  }
  else
  {
    memset(GlobalType,'\0',STRMAX);
  }

  strncpy(GlobalParam, S, sizeof(GlobalParam) - 1); // get the parameters, kind of " -A rpm -R fosso -l 1* "
  LOG_VERBOSE0("  upload %ld wget_agent globals loaded:\n  upload_pk = %ld\n  tmpfile=%s  URL=%s  GlobalParam=%s\n",GlobalUploadKey, GlobalUploadKey,GlobalTempFile,GlobalURL,GlobalParam);
} /* SetEnv() */


/**
 * \brief Check if path contains a "%H", "%R".
 *
 * Substitute the "%H" with hostname and "%R" with repo location.
 * \parm DirPath Directory path.
 * \returns new directory path
 */
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
 * \brief Copy downloaded files to temporary directory
 *
 * If the path(fs) is a directory, create a tar file from files(dir) in a directory
 * to the temporary directory.
 *
 * If the path(fs) is a file, copy the file to the temporary directory
 * \param Path The fs will be handled, directory(file) you want to upload from server
 * \param TempFile The tar(regular) file name
 * \param TempFileDir Temporary directory path
 * \param Status The status of Path
 *
 * \return 1 on sucess, 0 on failure
 */
int Archivefs(char *Path, char *TempFile, char *TempFileDir, struct stat Status)
{
  char *cmd;
  int rc_system = 0;
  int res;

  res = asprintf(&cmd , "mkdir -p '%s' >/dev/null 2>&1", TempFileDir);
  if (res == -1)
  {
    ASPRINTF_MEM_ERROR_LOG;
    return 0;
  }

  rc_system = system(cmd);
  if (!WIFEXITED(rc_system))
  {
    LOG_FATAL("[%s:%d] Could not create temporary directory", __FILE__, __LINE__);
    systemError(__LINE__, rc_system, cmd)
    free(cmd);
    return 0;
  }
  free(cmd);

  if (S_ISDIR(Status.st_mode)) /* directory? */
  {
    res = asprintf(&cmd, "tar %s -cf  '%s' -C '%s' ./ 1>/dev/null", GlobalParam, TempFile, Path);
    if (res == -1)
    {
      ASPRINTF_MEM_ERROR_LOG;
      return 0;
    }
    rc_system = system(cmd);
    if (!WIFEXITED(rc_system))
    {
      systemError(__LINE__, rc_system, cmd)
      free(cmd);
      return 0;
    }
    free(cmd);
  } else if (strstr(Path, "*"))  // wildcards
  {
    /* for the wildcards upload, keep the path */
    /* copy * files to TempFileDir/temp primarily */
    res = asprintf(&cmd, "mkdir -p '%s/temp'  > /dev/null 2>&1 && cp -r %s '%s/temp' > /dev/null 2>&1", TempFileDir, Path, TempFileDir);
    if (res == -1)
    {
      ASPRINTF_MEM_ERROR_LOG;
      return 0;
    }
    rc_system = system(cmd);
    if (rc_system != 0)
    {
      systemError(__LINE__, rc_system, cmd)
      free(cmd);
      return 0;
    }
    free(cmd);
    res = asprintf(&cmd, "tar -cf  '%s' -C %s/temp ./  1> /dev/null && rm -rf %s/temp  > /dev/null 2>&1", TempFile, TempFileDir, TempFileDir);
    if (res == -1)
    {
      ASPRINTF_MEM_ERROR_LOG;
      return 0;
    }
    rc_system = system(cmd);
    if (rc_system != 0)
    {
      systemError(__LINE__, rc_system, cmd)
      free(cmd);
      return 0;
    }
    free(cmd);
  } else if(S_ISREG(Status.st_mode)) /* regular file? */
  {
    res = asprintf(&cmd, "cp '%s' '%s' >/dev/null 2>&1", Path, TempFile);
    if (res == -1)
    {
      ASPRINTF_MEM_ERROR_LOG;
      return 0;
    }
    rc_system = system(cmd);
    if (rc_system != 0)
    {
      systemError(__LINE__, rc_system, cmd)
      free(cmd);
      return 0;
    }
    free(cmd);
  } else return 0; /* neither a directory nor a regular file */

  return 1;
}

/**
 * \brief Get proxy from fossology.conf
 *
 * Get proxy from fossology.conf and copy in GlobalProxy array
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
 * \param Name The name of the executable, usually it is wget_agent
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
  * \brief Translate authentication of git clone
  *
  * Translate authentication of git clone
  * from http://git.code.sf.net/p/fossology/fossology.git --username --password password (input)
  * to http://username:password@git.code.sf.net/p/fossology/fossology.git
  */
void replace_url_with_auth()
{
#define PREFIXMAX 10

  const char needle[] = " ";
  const char needle2[] = "//";
  int index = 0;
  char *username = NULL;
  char *password = NULL;
  char http[PREFIXMAX] = "";
  char URI[FILEPATH] = "";
  char *token = NULL;
  char *temp = NULL;
  char *additionalParams = NULL;

  if (strstr(GlobalParam, "password") && strstr(GlobalParam, "username"))
  {
    temp = strstr(GlobalURL, needle2);
    if (!temp || (temp - GlobalURL) < 3)
    {
      return;
    }
    strcpy(URI, temp + 2);
    if (strlen(GlobalURL) - strlen(URI) > PREFIXMAX - 1)
    {
      return;
    }

    strncpy(http, GlobalURL, strlen(GlobalURL) - strlen(URI));
    /* get the first token */
    token = strtok(GlobalParam, needle);
    /* walk through other tokens */
    while( token != NULL )
    {
      if (1 == index) username = token;
      if (3 == index) {
        password = token;
        additionalParams = token + strlen(token) + 1;
        break;
      }
      token = strtok(NULL, needle);
      index++;
    }
    snprintf(GlobalURL, URLMAX-1, "%s%s:%s@%s", http, username, password, URI);

    if (strlen(additionalParams) > 0) {
      memmove(GlobalParam, additionalParams, strlen(additionalParams) +1);
    }
    else {
      memset(GlobalParam,'\0',STRMAX);
    }
  }
}

/**
 * \brief Get the username from GlobalParam and create new parameters without password
 */
void MaskPassword()
{
  const char needle[] = " ";
  int index = 0;
  int secondIndex = 0;
  char *username = NULL;
  char *token = NULL;
  char newParam[STRMAX];
  char *beg = NULL;
  char *end = NULL;

  memset(newParam, '\0', STRMAX);
  // SVN if parameters exists
  if (strstr(GlobalParam, "password") && strstr(GlobalParam, "username")) {
    /* get the first token */
    token = strtok(GlobalParam, needle);
    /* walk through other tokens */
    while( token != NULL )
    {
      if (1 == index) {  //username is the first parameter
        username = token;
        break;
      }
      token = strtok(NULL, needle);
      index++;
    }
    // Create new parameters with masked password
    sprintf(newParam, " --username %s --password ****", username);
    memset(GlobalParam, '\0', STRMAX);
    strcpy(GlobalParam, newParam);
  }
  // GIT
  else {
    // First : from http://
    index = strcspn(GlobalURL, ":");
    // Second after username
    secondIndex = strcspn(GlobalURL + index + 1, ":");
    index = index + secondIndex + 1;
    if(index < strlen(GlobalURL)) {  // Contains second :
      beg = (char *)malloc(index + 2);
      memset(beg, '\0', index + 2);
      strncpy(beg, GlobalURL, index + 1);
      // Place where password ends
      end = strchr(GlobalURL, '@');
      sprintf(newParam, "%s****%s", beg, end);
      strcpy(GlobalURL, newParam);
    }
  }
}

/**
 * \brief get the command to run to get files from version control system
 * \param int withPassword true to make command with actual or false to mask password
 * \return char* null terminated string
 */
char* GetVersionControlCommand(int withPassword)
{
  char Type[][4] = {"SVN", "Git", "CVS"};
  char *command;
  char *tmpfile_dir;
  int res;

  /** save each upload files in /srv/fossology/repository/localhost/wget/wget.xxx.dir/ */
  res = asprintf(&tmpfile_dir, "%s.dir", GlobalTempFile);
  if (res == -1)
  {
    return NULL;
  }

  if(withPassword < 0) MaskPassword();
  if (0 == strcmp(GlobalType, Type[0]))
  {
    if (GlobalProxy[0] && GlobalProxy[0][0])
    {
      res = asprintf(&command, "svn --config-option servers:global:http-proxy-host=%s --config-option servers:global:http-proxy-port=%s export %s %s %s --no-auth-cache", GlobalProxy[4], GlobalProxy[5], GlobalURL, GlobalParam, tmpfile_dir);
    }
    else
    {
      res = asprintf(&command, "svn export %s %s %s --no-auth-cache", GlobalURL, GlobalParam, tmpfile_dir);
    }
  }
  else if (0 == strcmp(GlobalType, Type[1]))
  {
    replace_url_with_auth();
    if (GlobalProxy[0] && GlobalProxy[0][0])
    {
      res = asprintf(&command, "git config --global http.proxy %s && git clone %s %s %s  && rm -rf %s/.git", GlobalProxy[0], GlobalURL, GlobalParam, tmpfile_dir, tmpfile_dir);
    }
    else
    {
      res = asprintf(&command, "git clone %s %s %s && rm -rf %s/.git", GlobalURL, GlobalParam, tmpfile_dir, tmpfile_dir);
    }
  }
  if (res == -1)
  {
    free(tmpfile_dir);
    return NULL;
  }

  return command;
}
