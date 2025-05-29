/*
 libfossrepo: A set of functions for accessing the file repository.

 SPDX-FileCopyrightText: © 2007-2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: LGPL-2.1-only
*/

/*!
 * \file
 * \brief Repository access functions.  All internal functions are prefixed by '_'.
 */

#include "libfossrepo.h"
#include "libfossscheduler.h"
#include "fossconfig.h"

#include <sys/stat.h>
#include <glib.h>

#ifndef FOSSREPO_CONF
#define FOSSREPO_CONF "/srv/fossology/repository"
#endif
#ifndef FOSSGROUP
#define FOSSGROUP "fossology"
#endif


#ifdef COMMIT_HASH
char LibraryRepoBuildVersion[]="Library libfossrepo Build version: " COMMIT_HASH ".\n";
#endif

#define MAXHOSTNAMELEN        64  ///< Max host name length
#define MAXLINE        1024       ///< Max length of a line
#define REPONAME "REPOSITORY"     ///< Default repo name

#define GROUP 0                   ///< Default group ID

/*** Globals to simplify usage ***/
int RepDepth = 2;
char RepPath[MAXLINE + 1] = "";
#if GROUP
int RepGroup; /** the repository group ID for setgid() */
#endif

#define REPCONFCHECK()        { if (!*RepPath) fo_RepOpen(); }


/*!
 \note This is an internal function.

 \brief Simple check to see if the string S is valid filename.  

 A valid name is composed of only alphanumerics, and `"&%_=+-"`
 \n Used for types, hostnames, and filenames.
 \n (Just like _RepCheckString, except dots are not allowed.)

 \param S string to check
 \return  1=valid, 0=invalid.
 */
int _RepCheckType(const char* S)
{
  int i;
  if (S == NULL) return (0);
  for (i = 0; S[i] != '\0'; i++)
  {
    if (!isalnum(S[i]) && !strchr("@%_=+-", S[i])) return (0);
  }
  return (1);
} /* _RepCheckType() */

/*!
 \note This is an internal function.

 \brief Simple check to see if the string is valid.  

 Valid strings only contain alphanumerics, and `"@%_.=+-"`.
 \n Used for types, hostnames, and filenames.

 \param S string to check
 \return 1=valid, 0=invalid.
 */
int _RepCheckString(char* S)
{
  int i;
  if (S == NULL) return (0);
  if (S[0] == '.') return (0);
  for (i = 0; S[i] != '\0'; i++)
  {
    if (!isalnum(S[i]) && !strchr("@%_.=+-", S[i])) return (0);
  }
  return (1);
} /* _RepCheckString() */

/*!
 \brief Determine the path for the repository's root.

 The RepPath is where all the repository mounts are located.
 The path should NOT end with a "/".
 \return Allocates and returns string with the repo root path, or NULL.
 */
char* fo_RepGetRepPath()
{
  char* MyRepPath = NULL;

  REPCONFCHECK();

  /* allocate the path */
  MyRepPath = (char*) calloc(strlen(RepPath) + 1, 1);
  strcpy(MyRepPath, RepPath);
  return (MyRepPath);
} /* fo_RepGetRepPath() */

/*!
 \brief Determine if a host exists.
 \param Type This is the repo type (files, gold, ununpack, ...)
 \param Host Host to check
 \return 1=exists, 0=not exists, -1 on error.
 */
int fo_RepHostExist(char* Type, char* Host)
{
  char* entry;
  int i, length;
  GError* error;

  REPCONFCHECK();
  if (!_RepCheckType(Type)) return (-1);

  length = fo_config_list_length(sysconfig, "REPOSITORY", Host, &error);
  if (error)
  {
    fprintf(stderr, "ERROR: %s\n", error->message);
    return 0;
  }

  for (i = 0; i < length; i++)
  {
    entry = fo_config_get_list(sysconfig, "REPOSITORY", Host, i, &error);
    if (entry[0] == '*' || strncmp(Type, entry, strlen(Type)) == 0)
      return 1;
  }

  return 0;
} /* fo_RepHostExist() */

/*!
 \brief Determine the host for the tree.

 \note This is an internal only function.

 \param Type Type of data.
 \param Filename Filename to match.
 \param MatchNum Used to identify WHICH match to return.
        (MatchNum permits fallback paths.)

 \return Allocates and returns string with hostname or NULL.
 */
char* _RepGetHost(const char* Type, char* Filename, int MatchNum)
{
  char** hosts;
  char* entry;
  char* start;
  char* end;
  char* ret = NULL;
  int Match = 0;
  int i, j, kl, hl;
  GError* error = NULL;

  REPCONFCHECK();
  if (!_RepCheckType(Type) || !_RepCheckString(Filename))
    return (NULL);

  hosts = fo_config_key_set(sysconfig, REPONAME, &kl);
  for (i = 0; i < kl; i++)
  {
    hl = fo_config_list_length(sysconfig, REPONAME, hosts[i], &error);
    for (j = 0; j < hl; j++)
    {
      entry = fo_config_get_list(sysconfig, REPONAME, hosts[i], j, &error);
      char* remainder = NULL;
      strtok_r(entry, " ", &remainder);
      start = strtok_r(NULL, " ", &remainder);
      end = strtok_r(NULL, " ", &remainder);

      if (strcmp(entry, "*") == 0 || strcmp(entry, Type) == 0)
      {
        if ((strncasecmp(start, Filename, strlen(start)) <= 0) &&
          (strncasecmp(end, Filename, strlen(end)) >= 0))
        {
          Match++;
          if (Match == MatchNum)
          {
            ret = (char*) calloc(strlen(hosts[i]) + 1, sizeof(char));
            strcpy(ret, hosts[i]);
            g_free(entry);
            return ret;
          }
        }
      }

      g_free(entry);
    }
  }

  return NULL;
} /* _RepGetHost() */

/*!
 \brief Determine the host for a filename.

 \param Type Type of data.
 \param Filename Filename to match.
 \return Allocates and returns string with hostname or NULL.

 \test
 Test with standalone:
 \code
 ./rephost files 00000cb69c3c9c9fd15cadbf4652bd1552c349de.6caae94bdb579d7c9ada36726cf2e97f.776
 \endcode
 */
char* fo_RepGetHost(char* Type, char* Filename)
{
  return (_RepGetHost(Type, Filename, 1));
} /* fo_RepGetHost() */

/*!
 \brief Given a filename, construct the full path to the file.
 \param Type Type of data.
 \param Filename Filename to construct
 \param Ext An optional extension (for making temporary files).
 \param Which Used to identify WHICH match to return.

 This does NOT make the actual file or modify the file system!
 \note Caller must free the string!

 \test
 Test with standalone:
 \code
 ./reppath files 00000cb69c3c9c9fd15cadbf4652bd1552c349de.6caae94bdb579d7c9ada36726cf2e97f.776
 \endcode

 \return Allocates and returns a string or NULL on error.
 */
char* fo_RepMkPathTmp(const char* Type, char* Filename, char* Ext, int Which)
{
  char* Path;
  char* Host;
  int Len = 0;
  int i;
  int FilenameLen;

  if (!_RepCheckType(Type) || !_RepCheckString(Filename)) return (NULL);

  /* get the hostname */
  Host = _RepGetHost(Type, Filename, Which);
  if (Host)
  {Len += strlen(Host) + 1;}
  if (!Host && (Which > 1))
  {
    free(Host);
    return (NULL);
  }
  /* get the depth */
  if (Type) Len += strlen(Type) + 1;
  /* save the path too */
  Len += strlen(RepPath) + 1;

  /* add in depth */
  Len = Len + 3 * RepDepth;

  /* add in filename size */
  FilenameLen = strlen(Filename);
  Len += FilenameLen;
  if (Ext) Len += 1 + strlen(Ext);

  /* create the name */
  Path = (char*) calloc(Len + 1, 1);
  Len = 0; /* now Len is size of string */
  {
    strcat(Path, RepPath);
    strcat(Path, "/");
    Len += strlen(RepPath) + 1;
  }
  if (Host)
  {
    strcat(Path, Host);
    strcat(Path, "/");
    Len += strlen(Host) + 1;
  }
  if (Type)
  {
    strcat(Path, Type);
    strcat(Path, "/");
    Len += strlen(Type) + 1;
  }

  /* free memory */
  if (Host) free(Host);

  /* check if the filename is too small */
  if (FilenameLen < RepDepth * 2)
  {
    for (i = 0; i < FilenameLen; i++)
    {
      Path[Len++] = tolower(Filename[i]);
      if (i % 2 == 1) Path[Len++] = '/';
    }
    for (; i < RepDepth * 2; i++)
    {
      Path[Len++] = '_';
      if (i % 2 == 1) Path[Len++] = '/';
    }
  }
  else
  {
    /* add the filename */
    for (i = 0; i < RepDepth; i++)
    {
      Path[Len] = tolower(Filename[i * 2]);
      Path[Len + 1] = tolower(Filename[i * 2 + 1]);
      Path[Len + 2] = '/';
      Len += 3;
    }
  }

  for (i = 0; Filename[i] != '\0'; i++)
  {
    Path[Len] = tolower(Filename[i]);
    Len++;
  }

  if (Ext)
  {
    strcat(Path, ".");
    strcat(Path, Ext);
    Len += strlen(Type) + 1;
  }
  return (Path);
} /* fo_RepMkPathTmp() */

/*!
 \brief Given a filename, construct the full path to the file.
 \param Type Type of data.
 \param Filename  filename

 This does NOT make the actual file or modify the file system!
 \note Caller must free the string!
 \note This scans for alternate file locations, in case the file exists.

 \return Allocates and returns a string.
 */
char* fo_RepMkPath(const char* Type, char* Filename)
{
  char* Path, * AltPath;
  int i;
  struct stat Stat;

  Path = fo_RepMkPathTmp(Type, Filename, NULL, 1);
  if (!Path) return (NULL);
  /* if something exists, then return it! */
  if (!stat(Path, &Stat))
  {return (Path);}

  /* Check if it exists in an alternate path */
  i = 2;
  while (1)
  {
    AltPath = fo_RepMkPathTmp(Type, Filename, NULL, i);
    if (!AltPath) return (Path); /* No alternate */
    /* If there is an alternate, return it. */
    if (!stat(AltPath, &Stat))
    {
      free(Path);
      return (AltPath);
    }
    i++;
  }

  /* should never get here */
  return (Path);
} /* fo_RepMkPath() */

/*!
 \brief Update the last modified time of a file.

 Every file access (read/write) should update the timestamp on the file.
 This allows us to determine when files are stale.

 \note Internal only function.

 \param File file name
 \return none
 */
void _RepUpdateTime(char* File)
{
  struct utimbuf Utime;
  Utime.actime = Utime.modtime = time(NULL);
  utime(File, &Utime);
} /* _RepUpdateTime() */

/*!
 \brief Same as command-line "mkdir -p".

 \note Internal only.
 \param Fname filename
 \return 0 on success, 1 on failure.
 */
int _RepMkDirs(char* Fname)
{
  char Dir[FILENAME_MAX + 1];
  int i;
  int rc = 0;
  mode_t Mask;
#if GROUP
  gid_t Gid;
#endif

  memset(Dir, '\0', sizeof(Dir));
  strcpy(Dir, Fname);
  for (i = 1; Dir[i] != '\0'; i++)
  {
    if (Dir[i] == '/')
    {
      Dir[i] = '\0';
      Mask = umask(0000); /* mode: 0777 */
#if GROUP
      Gid = getegid();
      setegid(RepGroup);
#endif
      rc = mkdir(Dir, 0770); /* create this path segment */
#if GROUP
      setegid(Gid);
#endif
      umask(Mask);
      if (rc && (errno == EEXIST)) rc = 0;
      Dir[i] = '/';
      if (rc)
      {
        fprintf(stderr, "FATAL: 'mkdir %s' failed with rc=%d\n", Dir, rc);
        return (rc);
      }
    }
  }
  return (rc);
} /* _RepMkDirs() */

/*!
 \brief Rename a temp file to a real file.
 \param Type Type of data.
 \param Filename  File to be renamed
 \param Ext An optional extension (for making temporary files).
 \return 0 on succes, !0 on error.
 */
int fo_RepRenameTmp(char* Type, char* Filename, char* Ext)
{
  char* FnameOld, * Fname;
  int rc;

  FnameOld = fo_RepMkPathTmp(Type, Filename, Ext, 1);
  Fname = fo_RepMkPath(Type, Filename);
  if (!FnameOld || !Fname)
  {
    fprintf(stderr, "ERROR: Bad repository name: type='%s' name='%s'\n",
      Type, Filename);
    return (-1);
  }
  rc = rename(FnameOld, Fname);
  free(FnameOld);
  free(Fname);
  return (rc);
} /* fo_RepRenameTmp() */

/*!
 \brief Determine if a file exists.

 \param Type Type of data.
 \param Filename The file in question
 \return 1=exists, 0=not exists, -1 on error.

 \test
 Test with standalone:
 \code
 ./repexist files 00000cb69c3c9c9fd15cadbf4652bd1552c349de.6caae94bdb579d7c9ada36726cf2e97f.776
 \endcode
 */
int fo_RepExist(char* Type, char* Filename)
{
  char* Fname;
  struct stat Stat;
  int rc = 0;

  if (!_RepCheckType(Type))
  {
    fprintf(stderr, "ERROR: Invalid type '%s'\n", Type);
    return (-1);
  }
  if (!_RepCheckString(Filename))
  {
    fprintf(stderr, "ERROR: Invalid filename '%s'\n", Filename);
    return (-1);
  }

  Fname = fo_RepMkPath(Type, Filename);
  if (!Fname)
  {
    fprintf(stderr, "ERROR: Unable to allocate path for '%s/%s'\n", Type, Filename);
    return (-1);
  }
  if (!stat(Fname, &Stat)) rc = 1;
  free(Fname);
  return (rc);
} /* fo_RepExist() */

/*!
 \brief Determine if a file exists.

 If it does not exist, return an error code (errno).
 This is a replacement for fo_RepExist().

 \param Type is the type of data.
 \param Filename 
 \return 0=exists, errno=not exists, -1 = internal errors. 
 A message is also written to stderr for internal errors (bad inputs, etc).

 \test
 Test with standalone:
 \code
 ./repexist files 00000cb69c3c9c9fd15cadbf4652bd1552c349de.6caae94bdb579d7c9ada36726cf2e97f.776
 \endcode
 */
int fo_RepExist2(char* Type, char* Filename)
{
  char* Fname;
  struct stat Stat;
  int rc = 0;

  if (!_RepCheckType(Type))
  {
    fprintf(stderr, "ERROR: Invalid type '%s'\n", Type);
    return (-1);
  }
  if (!_RepCheckString(Filename))
  {
    fprintf(stderr, "ERROR: Invalid filename '%s'\n", Filename);
    return (-1);
  }

  Fname = fo_RepMkPath(Type, Filename);
  if (!Fname)
  {
    fprintf(stderr, "ERROR: Unable to allocate path for '%s/%s'\n", Type, Filename);
    return (-1);
  }
  if (stat(Fname, &Stat)) rc = errno;
  free(Fname);
  return (rc);
} /* fo_RepExist2() */

/*!
 \brief Delete a repository file.

 \param Type Type of data.
 \param Filename File to be deleted.
 \return 0=deleted, !0=error from unlink().

 \note This will LEAVE empty directories!
 */
int fo_RepRemove(char* Type, char* Filename)
{
  char* Fname;
  struct stat Stat;
  int rc = 0;

  if (!_RepCheckType(Type))
  {
    fprintf(stderr, "ERROR: Invalid type '%s'\n", Type);
    return (0);
  }
  if (!_RepCheckString(Filename))
  {
    fprintf(stderr, "ERROR: Invalid filename '%s'\n", Filename);
    return (0);
  }

  Fname = fo_RepMkPath(Type, Filename);
  if (!Fname)
  {
    fprintf(stderr, "ERROR: Unable to allocate path for '%s/%s'\n", Type, Filename);
    return (0);
  }
  if (!stat(Fname, &Stat)) rc = unlink(Fname);
  free(Fname);
  return (rc);
} /* fo_RepRemove() */

/*!
 \brief Perform an fclose.
 \param F File handler
 \return 0 if success.  On error, EOF is returned and global errno is set. 
 */
int fo_RepFclose(FILE* F)
{
  if (!F) return (0);
  return (fclose(F));
} /* fo_RepFclose() */

/*!
 \brief Perform an fopen for reading only.
 \param Type Type of data.
 \param Filename File to open.
 \return FILE pointer, or NULL if file does not exist.
 */
FILE* fo_RepFread(char* Type, char* Filename)
{
  FILE* F = NULL;
  char* Fname;

  if (!_RepCheckType(Type))
  {
    fprintf(stderr, "ERROR: Invalid type '%s'\n", Type);
    return (NULL);
  }
  if (!_RepCheckString(Filename))
  {
    fprintf(stderr, "ERROR: Invalid filename '%s'\n", Filename);
    return (NULL);
  }

  Fname = fo_RepMkPath(Type, Filename);
  if (!Fname)
  {
    fprintf(stderr, "ERROR: Unable to allocate path for '%s/%s'\n", Type, Filename);
    return (NULL);
  }
  _RepUpdateTime(Fname);
  F = fopen(Fname, "rb");
  free(Fname);
  return (F);
} /* fo_RepFread() */

/*!
 \brief Perform an fwrite.  Also creates directories.
 \param Type Type of data.
 \param Filename File to write to
 \param Ext An optional extension (for making temporary files).
 \return FILE pointer, or NULL if it fails.
 */
FILE* fo_RepFwriteTmp(char* Type, char* Filename, char* Ext)
{
  FILE* F = NULL;
  char* Fname;
  mode_t Mask;
#if GROUP
  gid_t Gid;
#endif

  if (!_RepCheckType(Type))
  {
    fprintf(stderr, "ERROR: Invalid type '%s'\n", Type);
    return (NULL);
  }
  if (!_RepCheckString(Filename))
  {
    fprintf(stderr, "ERROR: Invalid filename '%s'\n", Filename);
    return (NULL);
  }

  Fname = fo_RepMkPathTmp(Type, Filename, Ext, 1);
  if (!Fname)
  {
    fprintf(stderr, "ERROR: Unable to allocate path for '%s/%s'\n", Type, Filename);
    return (NULL);
  }
  if (_RepMkDirs(Fname))
  {
    free(Fname);
    return (NULL);
  }
  _RepUpdateTime(Fname);
  Mask = umask(0117); /* mode: 0660 */
#if GROUP
  Gid = getegid();
  setegid(RepGroup);
#endif
  F = fopen(Fname, "wb");
  if (!F)
  {
    fprintf(stderr, "ERROR: %s, in %s:%d, failed to open [%s]\n",
      strerror(errno), __FILE__, __LINE__, Fname);
    free(Fname);
    return (NULL);
  }
  chmod(Fname, S_ISGID | S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP); /* when umask fails */
#if GROUP
  setegid(Gid);
#endif
  umask(Mask);
  free(Fname);
  return (F);
} /* fo_RepFwriteTmp() */

/*!
 \brief Perform an fwrite.  Also creates directories.

 Same as fo_RepFwriteTmp() but without ext.
 \param Type Type of data.
 \param Filename File to write
 \return FILE pointer, or NULL if it fails.
 */
FILE* fo_RepFwrite(char* Type, char* Filename)
{
  return (fo_RepFwriteTmp(Type, Filename, NULL));
} /* fo_RepFwrite() */

/*!
 \brief Perform a munmap.

 This frees the struct RepMmap.
 \param M RepMmapStruct pointer
 */
void fo_RepMunmap(RepMmapStruct* M)
{
  if (!M) return;
  if (M->_MmapSize > 0) munmap(M->Mmap, M->_MmapSize);
  close(M->FileHandle);
  free(M);
} /* fo_RepMunmap() */

/*!
 \brief Perform a mmap on a regular file name.
 \param Filename
 \return filled RepMmapStruc, or NULL on error.
 */
RepMmapStruct* fo_RepMmapFile(char* Fname)
{
  RepMmapStruct* M;
  struct stat Stat;
  int PageSize;

  M = (RepMmapStruct*) calloc(1, sizeof(RepMmapStruct));
  if (!M)
  {return (NULL);}

  /* open the file (memory map) */
  M->FileHandle = open(Fname, O_RDONLY);
  if (M->FileHandle == -1)
  {
    fprintf(stderr, "ERROR: Unable to open file for mmap (%s)\n", Fname);
    free(M);
    return (NULL);
  }

  /* find how big the file is (to allocate it) */
  if (fstat(M->FileHandle, &Stat) == -1)
  {
    fprintf(stderr, "ERROR: Unable to stat file (%s)\n", Fname);
    close(M->FileHandle);
    free(M);
    return (NULL);
  }
  PageSize = getpagesize();

  /* only mmap the first 1G */
  if (Stat.st_size > 0x7fffffff) Stat.st_size = 0x80000000;

  M->MmapSize = Stat.st_size;
  M->_MmapSize = M->MmapSize + PageSize - (M->MmapSize % PageSize);
  M->Mmap = mmap(0, M->_MmapSize, PROT_READ, MAP_PRIVATE, M->FileHandle, 0);
  if (M->Mmap == MAP_FAILED)
  {
    fprintf(stderr, "ERROR: Unable to mmap file (%s)\n", Fname);
    close(M->FileHandle);
    free(M);
    return (NULL);
  }
  return (M);
} /* fo_RepMmapFile() */

/*!
 \brief Perform a mmap.
 \param Type Type of data.
 \param Filename The filename to match.
 \return An allocated struct RepMmap.
 \note This only works for READ-ONLY files!
 */
RepMmapStruct* fo_RepMmap(char* Type, char* Filename)
{
  RepMmapStruct* M;
  char* Fname;

  if (!_RepCheckType(Type) || !_RepCheckString(Filename)) return (NULL);

  Fname = fo_RepMkPath(Type, Filename);
  if (!Fname) return (NULL);
  _RepUpdateTime(Fname);

  M = fo_RepMmapFile(Fname);
  free(Fname);
  return (M);
} /* fo_RepMmap() */

/*!
 \brief Import a file into the repository.

 This is a REALLY FAST copy.
 \param Source Source filename
 \param Type Type of data.
 \param Filename The destination filename
 \param Link true if this should be a hardlink instead of a copy
 \return 0=success, !0 for error.
 */
int fo_RepImport(char* Source, char* Type, char* Filename, int Link)
{
  if (0 == strcmp(Type, "files"))
  {
    chmod(Source, S_ISGID | S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP | S_IROTH); /* change mode */
  }
  /*** code uses read/write ***/
  /*** Could use mmap, but it isn't noticably faster and could have
  problems with multi-gig files ***/
  int LenIn, LenOut;
  int i;
  char Buf[0x80000]; /* 80K blocks */
  char vBuf[0x80000]; /* 80K blocks */
  FILE* Fin;
  FILE* Fout;
  char* FoutPath;

  /* easy route: make a hard link */
  if (Link)
  {
    FoutPath = fo_RepMkPath(Type, Filename);
    if (!FoutPath) return (0);
    if (_RepMkDirs(FoutPath)) /* make the directory */
    {
      free(FoutPath);
      return (1);
    }
    if (link(Source, FoutPath) == 0)
    {
      free(FoutPath);
      return (0);
    }
    free(FoutPath);
  } /* try a hard link */

  /* hard route: actually copy the file */
  Fin = fopen(Source, "rb");
  if (!Fin)
  {
    fprintf(stderr, "ERROR: Unable to open source file '%s'\n", Source);
    return (1);
  }
  setvbuf(Fin, vBuf, _IOFBF, sizeof(vBuf));

  Fout = fo_RepFwriteTmp(Type, Filename, "I"); /* tmp = ".I" for importing... */
  if (!Fout)
  {
    fprintf(stderr, "ERROR: Invalid -- type='%s' filename='%s'\n", Type, Filename);
    fclose(Fin);
    return (2);
  }

  LenIn = fread(Buf,1,sizeof(Buf),Fin);
  while(LenIn > 0)
  {
    LenOut=0;
    while(LenOut < LenIn)
    {
      i = fwrite(Buf+LenOut,1,LenIn - LenOut,Fout);
      LenOut += i;
      if (i == 0)
      {
        /*** Oh no!  Write failed! ***/
        fclose(Fout);
#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wuse-after-free"
        // Used to close the pointer :-)
        fo_RepFclose(Fout);
#pragma GCC diagnostic pop
        fo_RepRemove(Type,Filename);
        fprintf(stderr,"ERROR: Write failed -- type='%s' filename='%s'\n",Type,Filename);
        fclose(Fin);
        return(3);
      }
    }
    LenIn = fread(Buf,1,sizeof(Buf),Fin);
  }
  fo_RepFclose(Fout);
  fclose(Fin);
  fo_RepRenameTmp(Type, Filename, "I"); /* mv .I to real name */
  return (0);
} /* fo_RepImport() */

/*!
 \brief Close and unmap the repository configuration file.
 */
void fo_RepClose()
{
  RepDepth = 2; /* default depth */
  memset(RepPath, '\0', sizeof(RepPath));
  RepPath[0] = '.'; /* default to local directory */
} /* fo_RepClose() */

/*!
 * \brief wrapper function for agents. Simply call
 *        fo_RepOpenFull() passing in the default system
 *        configuration
 *
 * @return 1 on opened, 0 on failed.
 */
int fo_RepOpen()
{
  return fo_RepOpenFull(sysconfig);
}

/*!
 * \brief Loads common information from configuration
 *        files into ram.
 *
 * \param config  The configuration to use
 * \return 1 on opened, 0 on failed.
 */
int fo_RepOpenFull(fo_conf* config)
{
  GError* error = NULL;
  char* path;

#if GROUP
  struct group *Group;
  gid_t Gid;
#endif

  fo_RepClose(); /* reset everything */

#if GROUP
  /* Make sure we can use group */
  Group = getgrnam(FOSSGROUP);
  if (!Group) return(0);	/* no such group */
  RepGroup = Group->gr_gid;
  Gid = getegid();
  if ((Gid != RepGroup) && setegid(RepGroup))
  {
    perror("Huh?");
    return(0);
  }
  setegid(Gid);
#endif

  /* Load the depth configuration */
  char* repDepthStr = fo_config_get(config, "FOSSOLOGY", "depth", &error);
  if (error)
  {
    fprintf(stderr, "ERROR %s.%d: %s\n", __FILE__, __LINE__, error->message);
    return 0;
  }
  RepDepth = atoi(repDepthStr);

  /* Load the path configuration */
  path = fo_config_get(config, "FOSSOLOGY", "path", &error);
  if (error)
  {
    fprintf(stderr, "ERROR %s.%d: %s\n", __FILE__, __LINE__, error->message);
    return 0;
  }
  strncpy(RepPath, path, sizeof(RepPath)-1);
  RepPath[sizeof(RepPath)-1] = 0;

  return 1;
} /* fo_RepOpen() */

/**
* @brief validates the repository configuration information.
*
* Checks that the repository entries in fossology.conf are correct. If this
* function does not return NULL, then the caller owns the return value.
*
* @param config  the configuration information
* @return        nothing if correct, the offending line if there was an error
*/
char* fo_RepValidate(fo_conf* config)
{
  char* retval = NULL;
  int32_t nhosts, nlist, i, j;
  char* gname = "REPOSITORY";
  char** hosts;
  char* curr;
  GRegex* regex = NULL;
  GMatchInfo* match = NULL;
  uint32_t begin, end;
  gchar* begin_str;
  gchar* end_str;


  if ((hosts = fo_config_key_set(config, gname, &nhosts)) == NULL)
    return g_strdup("The fossology.conf file does not contain a \"REPOSITORY\" group.");

  /* Regex to match repository lines in the configuration file.
   *
   * This will match a file type followed by two hexidecimal numbers. Possible
   * file types are gold, files, logs, license, test, and all type (denoted by
   * a *).
   *
   * example match:
   *   * 00 ff
   */
  regex = g_regex_new(
    "(\\*|gold|files|logs|license|test)\\s+([[:xdigit:]]+)\\s+([[:xdigit:]]+)$",
    0, 0, NULL);

  for (i = 0; i < nhosts; i++)
  {
    nlist = fo_config_list_length(config, gname, hosts[i], NULL);

    for (j = 0; j < nlist; j++)
    {
      curr = fo_config_get_list(config, gname, hosts[i], j, NULL);

      if (!g_regex_match(regex, curr, 0, &match))
      {
        retval = g_strdup_printf("%s[] = %s", hosts[i], curr);
        break;
      }

      begin_str = g_match_info_fetch(match, 2);
      end_str = g_match_info_fetch(match, 3);

      begin = strtoul(begin_str, NULL, 16);
      end = strtoul(end_str, NULL, 16);

      if (begin >= end)
      {
        retval = g_strdup_printf("%s[] = %s", hosts[i], curr);
        break;
      }

      g_free(begin_str);
      g_free(end_str);
      g_match_info_free(match);
    }
  }

  g_regex_unref(regex);
  return retval;
} /* fo_RepValidate() */

