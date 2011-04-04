/************************************************************
 libfossrepo: A set of functions for accessing the file repository.

 Copyright (C) 2007-2011 Hewlett-Packard Development Company, L.P.
 
 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA

 ************************************************************/
#include "libfossrepo.h"

#include <sys/stat.h>

#ifndef FOSSREPO_CONF
#define FOSSREPO_CONF "/srv/fossology/repository"
#endif
#ifndef FOSSGROUP
#define FOSSGROUP "fossology"
#endif


#ifdef SVN_REV
char LibraryRepoBuildVersion[]="Library libfossrepo Build version: " SVN_REV ".\n";
#endif

#define MAXHOSTNAMELEN	64
#define MAXLINE	1024

#define GROUP 0

/*** Globals to simplify usage ***/
RepMmapStruct *	RepConfig=NULL;
int RepDepth=2;
char RepPath[MAXLINE+1]="";
#if GROUP
  int RepGroup;	/* the repository group ID for setgid() */
#endif

#define REPCONFCHECK()	{ if (RepConfig==NULL) fo_RepOpen(); }


/***********************************************
 @brief _RepCheckType(): Simple check to see if the string
 is valid.  Used for types, hostnames, and filenames.
 (Just like _RepCheckString, except dots are not allowed.)
 This is an internal function.
 @param char *string to check
 @return Returns: 1=valid, 0=invalid.
 ***********************************************/
int	_RepCheckType	(char *S)
{
  int i;
  if (S==NULL) return(0);
  for(i=0; S[i] != '\0'; i++)
    {
    if (!isalnum(S[i]) && !strchr("@%_=+-",S[i])) return(0);
    }
  return(1);
} /* _RepCheckType() */

/***********************************************
 @brief _RepCheckString(): Simple check to see if the string
 is valid.  Used for types, hostnames, and filenames.
 This is an internal function.
 @param char *string to check
 @return Returns: 1=valid, 0=invalid.
 ***********************************************/
int	_RepCheckString	(char *S)
{
  int i;
  if (S==NULL) return(0);
  if (S[0]=='.') return(0);
  for(i=0; S[i] != '\0'; i++)
    {
    if (!isalnum(S[i]) && !strchr("@%_.=+-",S[i])) return(0);
    }
  return(1);
} /* _RepCheckString() */

/***********************************************
 @brief fo_RepGetRepPath(): Determine the path for the repository's root.
 The RepPath is where all the repository mounts are located.
 The path should NOT end with a "/".
 @return Allocates and returns string with path or NULL.
 ***********************************************/
char *	fo_RepGetRepPath	()
{
  char *MyRepPath=NULL;

  REPCONFCHECK();

  /* allocate the path */
  MyRepPath = (char *)calloc(strlen(RepPath)+1,1);
  strcpy(MyRepPath,RepPath);
  return(MyRepPath);
} /* fo_RepGetRepPath() */

/***********************************************
 @brief fo_RepHostExist(): Determine if a host exists.
 @param char *Type This is the repo type (files, gold, ununpack, ...)
 @param char *Host Host to check
 @return Returns 1=exists, 0=not exists, -1 on error.
 ***********************************************/
int	fo_RepHostExist	(char *Type, char *Host)
{
  char LineHost[MAXHOSTNAMELEN];
  char LineType[MAXHOSTNAMELEN];
  char LineStart[MAXHOSTNAMELEN];
  char LineEnd[MAXHOSTNAMELEN];
  int Match=0;
  int i,j;

  REPCONFCHECK();
  if (!RepConfig || !_RepCheckType(Type)) return(-1);

  i=0;
  while(!Match && (i < RepConfig->MmapSize))
    {
    memset(LineHost,0,sizeof(LineHost));
    memset(LineType,0,sizeof(LineType));
    memset(LineStart,0,sizeof(LineStart));
    memset(LineEnd,0,sizeof(LineEnd));
    /* read in 4 space-deliminated strings */
    j=0;
    while((i < RepConfig->MmapSize) && !isspace(RepConfig->Mmap[i]))
      {
      LineHost[j]=RepConfig->Mmap[i];
      j++; i++;
      }
    while(isspace(RepConfig->Mmap[i])) i++;
    j=0;
    while((i < RepConfig->MmapSize) && !isspace(RepConfig->Mmap[i]))
      {
      LineType[j]=RepConfig->Mmap[i];
      j++; i++;
      }
    while(isspace(RepConfig->Mmap[i])) i++;
    j=0;
    while((i < RepConfig->MmapSize) && !isspace(RepConfig->Mmap[i]))
      {
      LineStart[j]=RepConfig->Mmap[i];
      j++; i++;
      }
    while(isspace(RepConfig->Mmap[i])) i++;
    j=0;
    while((i < RepConfig->MmapSize) && !isspace(RepConfig->Mmap[i]))
      {
      LineEnd[j]=RepConfig->Mmap[i];
      j++; i++;
      }
    while(isspace(RepConfig->Mmap[i])) i++;

    /* Check if the host exists with the type */
    if (_RepCheckString(LineHost) &&
        ((!strcmp(LineType,"*") || !strcmp(LineType,Type))) )
	{
	if (!strcmp(LineHost,Host))	return(1);
	}
    } /* while reading data */

  return(0);
} /* fo_RepHostExist() */

/***********************************************
 @brief _RepGetHost(): Determine the host for the tree.
 This is an internal only function.
 @param char *Type is the type of data.
 @param char *Filename is the filename to match.
 @param int MatchNum used to identify WHICH match to return.
 (MatchNum permits fallback paths.)
 @return Allocates and returns string with hostname or NULL.
 ***********************************************/
char *	_RepGetHost	(char *Type, char *Filename, int MatchNum)
{
  char LineHost[MAXHOSTNAMELEN];
  char LineType[MAXHOSTNAMELEN];
  char LineStart[MAXHOSTNAMELEN];
  char LineEnd[MAXHOSTNAMELEN];
  char *NewHost=NULL;
  int Match=0;
  int i,j;

  REPCONFCHECK();
  if (!RepConfig || !_RepCheckType(Type) || !_RepCheckString(Filename))
	return(NULL);

  i=0;
  while((Match != MatchNum) && (i < RepConfig->MmapSize))
    {
    memset(LineHost,0,sizeof(LineHost));
    memset(LineType,0,sizeof(LineType));
    memset(LineStart,0,sizeof(LineStart));
    memset(LineEnd,0,sizeof(LineEnd));
    /* read in 4 space-deliminated strings */
    j=0;
    while((i < RepConfig->MmapSize) && !isspace(RepConfig->Mmap[i]))
      {
      LineHost[j]=RepConfig->Mmap[i];
      j++; i++;
      }
    while(isspace(RepConfig->Mmap[i])) i++;
    j=0;
    while((i < RepConfig->MmapSize) && !isspace(RepConfig->Mmap[i]))
      {
      LineType[j]=RepConfig->Mmap[i];
      j++; i++;
      }
    while(isspace(RepConfig->Mmap[i])) i++;
    j=0;
    while((i < RepConfig->MmapSize) && !isspace(RepConfig->Mmap[i]))
      {
      LineStart[j]=RepConfig->Mmap[i];
      j++; i++;
      }
    while(isspace(RepConfig->Mmap[i])) i++;
    j=0;
    while((i < RepConfig->MmapSize) && !isspace(RepConfig->Mmap[i]))
      {
      LineEnd[j]=RepConfig->Mmap[i];
      j++; i++;
      }
    while(isspace(RepConfig->Mmap[i])) i++;

    if (_RepCheckString(LineHost) &&
        ((!strcmp(LineType,"*") || !strcmp(LineType,Type))) )
	{
	if ((strncasecmp(LineStart,Filename,strlen(LineStart)) <= 0) &&
	    (strncasecmp(LineEnd,Filename,strlen(LineEnd)) >= 0))
		{
		Match++;
		if (Match == MatchNum)
		  {
		  NewHost = (char *)calloc(strlen(LineHost)+1,1);
		  strcpy(NewHost,LineHost);
		  }
		}
	}
    } /* while reading data */

  return(NewHost);
} /* _RepGetHost() */

/***********************************************
 @brief fo_RepGetHost(): Determine the host for the tree.
 @param char *Type is the type of data.
 @param char *Filename is the filename to match.
 @return Allocates and returns string with hostname or NULL.
 Test with standalone:
 ./rephost files 00000cb69c3c9c9fd15cadbf4652bd1552c349de.6caae94bdb579d7c9ada36726cf2e97f.776
 ***********************************************/
char *	fo_RepGetHost	(char *Type, char *Filename)
{
  return(_RepGetHost(Type,Filename,0));
} /* fo_RepGetHost() */

/***********************************************
 @brief fo_RepMkPathTmp(): Given a filename, construct the full
 path to the file.
 @param char *Type is the type of data.
 @param char *Filename 
 @param char *Ext is an optional extension (for making temporary files).
 @param int Which is used to identify WHICH match to return.

 @return Allocates and returns a string.
 This does NOT make the actual file or modify the file system!
 Caller must free the string!
 Test with standalone:
 ./reppath files 00000cb69c3c9c9fd15cadbf4652bd1552c349de.6caae94bdb579d7c9ada36726cf2e97f.776
 ***********************************************/
char *	fo_RepMkPathTmp	(char *Type, char *Filename, char *Ext, int Which)
{
  char *Path;
  char *Host;
  int Len=0;
  int i;
  int FilenameLen;

  if (!_RepCheckType(Type) || !_RepCheckString(Filename)) return(NULL);

  /* get the hostname */
  Host=_RepGetHost(Type,Filename,Which);
  if (Host) { Len += strlen(Host)+1; }
  if (!Host && (Which > 1)) { free(Host); return(NULL); }
  /* get the depth */
  if (Type) Len += strlen(Type)+1;
  /* save the path too */
  Len += strlen(RepPath)+1;

  /* add in depth */
  Len = Len + 3*RepDepth;

  /* add in filename size */
  FilenameLen = strlen(Filename);
  Len += FilenameLen;
  if (Ext) Len += 1 + strlen(Ext);

  /* create the name */
  Path = (char *)calloc(Len+1,1);
  Len=0; /* now Len is size of string */
  { strcat(Path,RepPath); strcat(Path,"/"); Len += strlen(RepPath)+1; }
  if (Host) { strcat(Path,Host); strcat(Path,"/"); Len += strlen(Host)+1; }
  if (Type) { strcat(Path,Type); strcat(Path,"/"); Len += strlen(Type)+1; }

  /* free memory */
  if (Host) free(Host);

  /* check if the filename is too small */
  if (FilenameLen < RepDepth*2)
    {
    for(i=0; i<FilenameLen; i++)
      {
      Path[Len++] = tolower(Filename[i]);
      if (i%2 == 1) Path[Len++] = '/';
      }
    for( ; i<RepDepth*2; i++)
      {
      Path[Len++] = '_';
      if (i%2 == 1) Path[Len++] = '/';
      }
    }
  else
    {
    /* add the filename */
    for(i=0; i<RepDepth; i++)
      {
      Path[Len] = tolower(Filename[i*2]);
      Path[Len+1] = tolower(Filename[i*2+1]);
      Path[Len+2] = '/';
      Len+=3;
      }
    }

  for(i=0; Filename[i] != '\0'; i++)
    {
    Path[Len] = tolower(Filename[i]);
    Len++;
    }

  if (Ext)
    {
    strcat(Path,".");
    strcat(Path,Ext);
    Len += strlen(Type)+1;
    }
  return(Path);
} /* fo_RepMkPathTmp() */

/***********************************************
 @brief fo_RepMkPath(): Given a filename, construct the full
 path to the file.
 @param char *Type is the type of data.
 @param char *Filename 
 @return Allocates and returns a string.
 This does NOT make the actual file or modify the file system!
 Caller must free the string!
 NOTE: This scans for alternate file locations, in case
 the file exists.
 ***********************************************/
char *	fo_RepMkPath	(char *Type, char *Filename)
{
  char *Path, *AltPath;
  int i;
  struct stat64 Stat;

  Path = fo_RepMkPathTmp(Type,Filename,NULL,1);
  if (!Path) return(NULL);
  /* if something exists, then return it! */
  if (!stat64(Path,&Stat)) { return(Path); }

  /* Check if it exists in an alternate path */
  i=2;
  while(1)
    {
    AltPath = fo_RepMkPathTmp(Type,Filename,NULL,i);
    if (!AltPath) return(Path); /* No alternate */
    /* If there is an alternate, return it. */
    if (!stat64(AltPath,&Stat)) { free(Path); return(AltPath); }
    i++;
    }

  /* should never get here */
  return(Path);
} /* fo_RepMkPath() */

/***********************************************
 @brief _RepUpdateTime(): Every file access (read/write) should update
 the timestamp on the file.  This allows us to determine
 when files are stale.
 Internal only function.
 @param char *File
 @return none
 ***********************************************/
void	_RepUpdateTime	(char *File)
{
  struct utimbuf Utime;
  Utime.actime = Utime.modtime = time(NULL);
  utime(File,&Utime);
} /* _RepUpdateTime() */

/***************************************************
 @brief _RepMkDirs(): Same as command-line "mkdir -p".
 Internal only.
 @param char *filename
 @reutrn Returns 0 on success, 1 on failure.
 ***************************************************/
int	_RepMkDirs	(char *Fname)
{
  char Dir[FILENAME_MAX+1];
  int i;
  int rc=0;
  mode_t Mask;
#if GROUP
  gid_t Gid;
#endif

  memset(Dir,'\0',sizeof(Dir));
  strcpy(Dir,Fname);
  for(i=1; Dir[i] != '\0'; i++)
    {
    if (Dir[i] == '/')
	{
	Dir[i]='\0';
	Mask = umask(0000); /* mode: 0777 */
#if GROUP
	Gid = getegid();
	setegid(RepGroup);
#endif
	rc=mkdir(Dir,0770); /* create this path segment */
#if GROUP
	setegid(Gid);
#endif
	umask(Mask);
	if (rc && (errno == EEXIST)) rc=0;
	Dir[i]='/';
	if (rc)
	  {
	  fprintf(stderr,"FATAL: 'mkdir %s' failed with rc=%d\n",Dir,rc);
	  return(rc);
	  }
	}
    }
  return(rc);
} /* _RepMkDirs() */

/***********************************************
 @brief fo_RepRenameTmp(): Rename a temp file to a real file.
 @param char *Type is the type of data.
 @param char *Filename 
 @param char *Ext is an optional extension (for making temporary files).
 @return Returns 0 on succes, !0 on error.
 ***********************************************/
int	fo_RepRenameTmp	(char *Type, char *Filename, char *Ext)
{
  char *FnameOld, *Fname;
  int rc;

  FnameOld = fo_RepMkPathTmp(Type,Filename,Ext,1);
  Fname = fo_RepMkPath(Type,Filename);
  if (!FnameOld || !Fname)
    {
    fprintf(stderr,"ERROR: Bad repository name: type='%s' name='%s'\n",
	Type,Filename);
    return(-1);
    }
  rc = rename(FnameOld,Fname);
  free(FnameOld);
  free(Fname);
  return(rc);
} /* fo_RepRenameTmp() */

/***********************************************
 @brief fo_RepExist(): Determine if a file exists.
 @param char *Type is the type of data.
 @param char *Filename 
 @return Returns 1=exists, 0=not exists, -1 on error.
 Test with standalone:
 ./repexist files 00000cb69c3c9c9fd15cadbf4652bd1552c349de.6caae94bdb579d7c9ada36726cf2e97f.776
 ***********************************************/
int	fo_RepExist	(char *Type, char *Filename)
{
  char *Fname;
  struct stat64 Stat;
  int rc=0;

  if (!_RepCheckType(Type))
    {
    fprintf(stderr,"ERROR: Invalid type '%s'\n",Type);
    return(-1);
    }
  if (!_RepCheckString(Filename))
    {
    fprintf(stderr,"ERROR: Invalid filename '%s'\n",Filename);
    return(-1);
    }

  Fname = fo_RepMkPath(Type,Filename);
  if (!Fname)
    {
    fprintf(stderr,"ERROR: Unable to allocate path for '%s/%s'\n",Type,Filename);
    return(-1);
    }
  if (!stat64(Fname,&Stat)) rc=1;
  free(Fname);
  return(rc);
} /* fo_RepExist() */

/***********************************************
 @brief fo_RepRemove(): Delete a repository file.
 NOTE: This will LEAVE empty directories!
 @param char *Type is the type of data.
 @param char *Filename 
 @return Returns 0=deleted, !0=error from unlink().
 ***********************************************/
int	fo_RepRemove	(char *Type, char *Filename)
{
  char *Fname;
  struct stat64 Stat;
  int rc=0;

  if (!_RepCheckType(Type))
    {
    fprintf(stderr,"ERROR: Invalid type '%s'\n",Type);
    return(0);
    }
  if (!_RepCheckString(Filename))
    {
    fprintf(stderr,"ERROR: Invalid filename '%s'\n",Filename);
    return(0);
    }

  Fname = fo_RepMkPath(Type,Filename);
  if (!Fname)
    {
    fprintf(stderr,"ERROR: Unable to allocate path for '%s/%s'\n",Type,Filename);
    return(0);
    }
  if (!stat64(Fname,&Stat)) rc=unlink(Fname);
  free(Fname);
  return(rc);
} /* fo_RepRemove() */

/***********************************************
 @brief fo_RepFclose(): Perform a fclose.
 @param FILE *Filehandle
 @return 0 if success or null file pointer else EOF
 ***********************************************/
int	fo_RepFclose	(FILE *F)
{
  if (!F) return(0);
  return(fclose(F));
} /* fo_RepFclose() */

/***********************************************
 @brief fo_RepFread(): Perform an fopen for reading only.
 @param char *Type is the type of data.
 @param char *Filename 
 @return Returns FILE pointer, or NULL if file does not exist.
 ***********************************************/
FILE *	fo_RepFread	(char *Type, char *Filename)
{
  FILE *F=NULL;
  char *Fname;

  if (!_RepCheckType(Type))
    {
    fprintf(stderr,"ERROR: Invalid type '%s'\n",Type);
    return(NULL);
    }
  if (!_RepCheckString(Filename))
    {
    fprintf(stderr,"ERROR: Invalid filename '%s'\n",Filename);
    return(NULL);
    }

  Fname = fo_RepMkPath(Type,Filename);
  if (!Fname)
    {
    fprintf(stderr,"ERROR: Unable to allocate path for '%s/%s'\n",Type,Filename);
    return(NULL);
    }
  _RepUpdateTime(Fname);
  F = fopen(Fname,"rb");
  free(Fname);
  return(F);
} /* fo_RepFread() */

/***********************************************
 @brief fo_RepFwriteTmp(): Perform an fwrite.  Also creates directories.
 @param char *Type is the type of data.
 @param char *Filename 
 @param char *Ext is an optional extension (for making temporary files).
 @return Returns FILE pointer, or NULL if it fails.
 ***********************************************/
FILE *	fo_RepFwriteTmp	(char *Type, char *Filename, char *Ext)
{
  FILE *F=NULL;
  char *Fname;
  mode_t Mask;
#if GROUP
  gid_t Gid;
#endif

  if (!_RepCheckType(Type))
    {
    fprintf(stderr,"ERROR: Invalid type '%s'\n",Type);
    return(NULL);
    }
  if (!_RepCheckString(Filename))
    {
    fprintf(stderr,"ERROR: Invalid filename '%s'\n",Filename);
    return(NULL);
    }

  Fname = fo_RepMkPathTmp(Type,Filename,Ext,1);
  if (!Fname)
    {
    fprintf(stderr,"ERROR: Unable to allocate path for '%s/%s'\n",Type,Filename);
    return(NULL);
    }
  if (_RepMkDirs(Fname))
    {
    free(Fname);
    return(NULL);
    }
  _RepUpdateTime(Fname);
  Mask = umask(0117); /* mode: 0660 */
#if GROUP
  Gid = getegid();
  setegid(RepGroup);
#endif
  F = fopen(Fname,"wb");
  if (!F)
  {
    fprintf(stderr, "ERROR: %s, in %s:%d, failed to open [%s]\n",
            strerror(errno), __FILE__,__LINE__, Fname);
    free(Fname);
    return(NULL);
  }
  chmod(Fname,S_ISGID|S_IRUSR|S_IWUSR|S_IRGRP|S_IWGRP); /* when umask fails */
#if GROUP
  setegid(Gid);
#endif
  umask(Mask);
  free(Fname);
  return(F);
} /* fo_RepFwriteTmp() */

/***********************************************
 @brief fo_RepFwrite(): Perform an fwrite.  Also creates directories.
 Same as fo_RepFwriteTmp but without ext.
 @param char *Type is the type of data.
 @param char *Filename 
 @return Returns FILE pointer, or NULL if it fails.
 ***********************************************/
FILE *	fo_RepFwrite	(char *Type, char *Filename)
{
  return(fo_RepFwriteTmp(Type,Filename,NULL));
} /* fo_RepFwrite() */

/***********************************************
 @brief fo_RepMunmap(): Perform a munmap.
 This frees the struct RepMmap.
 @param RepMmapStruct *M
 ***********************************************/
void	fo_RepMunmap	(RepMmapStruct *M)
{
  if (!M) return;
  if (M->_MmapSize > 0) munmap(M->Mmap,M->_MmapSize);
  close(M->FileHandle);
  free(M);
} /* fo_RepMunmap() */

/***********************************************
 @brief fo_RepMmapFile(): Perform a mmap on a regular file name.
 @param char *Filename
 @return Returns filled RepMmapStruc, or NULL on error.
 ***********************************************/
RepMmapStruct *	fo_RepMmapFile	(char *Fname)
{
  RepMmapStruct *M;
  struct stat64 Stat;
  int PageSize;

  M = (RepMmapStruct *)calloc(1,sizeof(RepMmapStruct));
  if (!M) { return(NULL); }

  /* open the file (memory map) */
  M->FileHandle = open(Fname,O_RDONLY);
  if (M->FileHandle == -1)
    {
    fprintf(stderr,"ERROR: Unable to open file for mmap (%s)\n",Fname);
    free(M);
    return(NULL);
    }

  /* find how big the file is (to allocate it) */
  if (fstat64(M->FileHandle,&Stat) == -1)
    {
    fprintf(stderr,"ERROR: Unable to stat file (%s)\n",Fname);
    close(M->FileHandle);
    free(M);
    return(NULL);
    }
  PageSize = getpagesize();

  /* only mmap the first 1G */
  if (Stat.st_size > 0x7fffffff) Stat.st_size=0x80000000;

  M->MmapSize = Stat.st_size;
  M->_MmapSize = M->MmapSize + PageSize - (M->MmapSize % PageSize);
  M->Mmap = mmap(0,M->_MmapSize,PROT_READ,MAP_PRIVATE,M->FileHandle,0);
  if (M->Mmap == MAP_FAILED)
    {
    fprintf(stderr,"ERROR: Unable to mmap file (%s)\n",Fname);
    close(M->FileHandle); 
    free(M);
    return(NULL);
    }
  return(M);
} /* fo_RepMmapFile() */

/***********************************************
 @brief fo_RepMmap(): Perform a mmap.
 NOTE: This only works for READ-ONLY files!
 @param char *Type is the type of data.
 @param char *Filename is the filename to match.
 @return Returns an allocated struct RepMmap.
 ***********************************************/
RepMmapStruct *	fo_RepMmap	(char *Type, char *Filename)
{
  RepMmapStruct *M;
  char *Fname;

  if (!_RepCheckType(Type) || !_RepCheckString(Filename)) return(NULL);

  Fname = fo_RepMkPath(Type,Filename);
  if (!Fname) return(NULL);
  _RepUpdateTime(Fname);

  M = fo_RepMmapFile(Fname);
  free(Fname);
  return(M);
} /* fo_RepMmap() */

/***********************************************
 @brief fo_RepImport(): Import a file into the repository.
 This is a REALLY FAST copy.
 @param char *Source source filename
 @param char *Type is the type of data.
 @param char *Filename is the destination filename
 @param int  Link, true if this should be a hardlink instead of a copy
 @return Returns: 0=success, !0 for error.
 ***********************************************/
int	fo_RepImport	(char *Source, char *Type, char *Filename, int Link)
{
  /*** code uses read/write ***/
  /*** Could use mmap, but it isn't noticably faster and could have
       problems with multi-gig files ***/
  int LenIn,LenOut;
  int i;
  char Buf[0x80000]; /* 80K blocks */
  char vBuf[0x80000]; /* 80K blocks */
  FILE *Fin;
  FILE *Fout;
  char *FoutPath;

  /* easy route: make a hard link */
  if (Link)
    {
    FoutPath = fo_RepMkPath(Type,Filename);
    if (!FoutPath) return(0);
    if (_RepMkDirs(FoutPath)) /* make the directory */
      {
      free(FoutPath);
      return(1);
      }
    if (link(Source,FoutPath) == 0)
      {
      free(FoutPath);
      return(0);
      }
    free(FoutPath);
    } /* try a hard link */

  /* hard route: actually copy the file */
  Fin = fopen(Source,"rb");
  if (!Fin)
    {
    fprintf(stderr,"ERROR: Unable to open source file '%s'\n",Source);
    return(1);
    }
  setvbuf(Fin,vBuf,_IOFBF,sizeof(vBuf));

  Fout = fo_RepFwriteTmp(Type,Filename,"I"); /* tmp = ".I" for importing... */
  if (!Fout)
    {
    fprintf(stderr,"ERROR: Invalid -- type='%s' filename='%s'\n",Type,Filename);
    fclose(Fin);
    return(2);
    }

  LenIn=1;
  while(LenIn > 0)
    {
    LenIn=fread(Buf,1,sizeof(Buf),Fin);
    if (LenIn > 0)
      {
      LenOut=0;
      while(LenOut < LenIn)
	{
	i = fwrite(Buf+LenOut,1,LenIn - LenOut,Fout);
	LenOut += i;
	if (i == 0)
	  {
	  /** Oh no!  Write failed! **/
	  fclose(Fout);
	  fo_RepFclose(Fout);
	  fo_RepRemove(Type,Filename);
	  fprintf(stderr,"ERROR: Write failed -- type='%s' filename='%s'\n",
	 	Type,Filename);
	  return(3);
	  }
	}
      }
    }
  fo_RepFclose(Fout);
  fclose(Fin);
  fo_RepRenameTmp(Type,Filename,"I"); /* mv .I to real name */
  return(0);
} /* fo_RepImport() */

/***********************************************
 @brief fo_RepClose(): Every other function uses the repository
 configuration files.  Why open them 100,000 times when
 it can be opened once and stored in RAM?
 This unsets structures.
 ***********************************************/
void	fo_RepClose	()
{
  RepDepth = 2; /* default depth */
  memset(RepPath,'\0',sizeof(RepPath));
  RepPath[0]='.'; /* default to local directory */
  if (RepConfig != NULL)
    {
    fo_RepMunmap(RepConfig);
    RepConfig = NULL;
    }
} /* fo_RepClose() */

/***********************************************
 @brief fo_RepOpen(): Every other function uses the repository
 configuration files.  Why open them 100,000 times when
 it can be opened once and stored in RAM?
 This sets global structures.
 @return Returns: 1 on opened, 0 on failed.
 ***********************************************/
int	fo_RepOpen	()
{
  char CWD[PATH_MAX+1];
  char *Env;
  RepMmapStruct *Config;
  int i;
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

  if (getcwd(CWD,sizeof(CWD)) == NULL) return(0); /* no directory */

  /* By default, the configuration directory is FOSSREPO_CONF.
     This variable is set during the compile-time.
     For debugging, you can override the string with an environment
     variable: FOSSREPCONF.
   */
  Env = getenv("FOSSREPCONF"); /* could be NULL */
  if (Env && (Env[0] != '\0'))
    {
    if (chdir(Env))      return(0); /* no directory */
    }
  else
    {
    if (chdir(FOSSREPO_CONF)) return(0); /* no directory */
    }

  /** I'm in the config directory. **/
  /* Map the host file to a global. */
  RepConfig = fo_RepMmapFile("Hosts.conf");

  /* Load the depth file. */
  Config = fo_RepMmapFile("Depth.conf");
  if (Config)
    {
    if ((Config->MmapSize > 1) && (Config->Mmap[Config->MmapSize-1] == '\n'))
	{
	RepDepth = atoi((char *)(Config->Mmap));
	}
    fo_RepMunmap(Config);
    }

  /* Load the path file. */
  Config = fo_RepMmapFile("RepPath.conf");
  if (Config)
    {
    for(i=0; (i<Config->MmapSize) && (Config->Mmap[i] != '\n'); i++)
	;
    if ((i > 0) && (Config->Mmap[i] == '\n')) strncpy(RepPath,(char *)(Config->Mmap),i);
    while(RepPath[0] && (RepPath[strlen(RepPath)-1] == '/'))
	{
	/* RepPath should not end with a "/" */
	RepPath[strlen(RepPath)-1] = '\0';
	}
    fo_RepMunmap(Config);
    }

  if(chdir(CWD)) return 0;
  return(RepConfig != NULL);
} /* fo_RepOpen() */

