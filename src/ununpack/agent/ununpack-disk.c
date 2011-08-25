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
 * \file ununpack-disk.c
 * \brief The universal unpacker code to unpack a disk file system.
 **/

#include "ununpack.h"
#include "externs.h"

#include <utime.h>

struct permlist
{
    char *inode;
    struct utimbuf Times;
    int perm; /* inode permissions */
    struct permlist *Next;
};
typedef struct permlist permlist;


/**
 * \brief Special handling for FAT names.
 *  -  Convert to lowercase.
 *  -  remove any short name (name in parenthesis).
 * \param Name Filename
 **/
void	FatDiskName	(char *Name)
{
  int i;

  for(i=0; Name[i] != '\0'; i++)
  {
    if (isupper(Name[i])) Name[i]=tolower(Name[i]);
  }
  /* i == strlen(Name) */
  if (i <= 0) return;
  i--;
  if (Name[i] != ')')	return; /* no paren name! */
  /* remove the parenthasis name */
  while((i>1) && (Name[i] != '(')) i--;
  if (Name[i]=='(')
  {
    i--;
    if (Name[i]==' ') Name[i]='\0';
  }
} /* FatDiskName() */

/**
 * \brief deallocate perms
 * \param List permlist
 **/
void	FreeDiskPerms	(permlist *List)
{
  permlist *Next;
  while(List)
  {
    Next=List->Next;
    if (List->inode) free(List->inode);
    free(List);
    List=Next;
  }
} /* FreeDiskPerms() */

/**
 * \brief Given a disk, load in all of the file permissions.
 *        Assumes Source is already quote-tainted!
 * \param FStype Filesystem type
 * \param Source Source filepath
 * \return permlist
 **/
permlist *	ExtractDiskPerms	(char *FStype, char *Source)
{
  permlist *List=NULL, *NewList;
  FILE *Fin;
  char Cmd[FILENAME_MAX*2]; /* command to run */
  char Line[FILENAME_MAX*2];
  char *L; /* pointer into Line */
  char *inode;

  /* Format of "fls -m /" (as determined from the fls source code fs_dent.c):
     0|/etc/terminfo/b/bterm|0|95|33188|-/-rw-r--r--|1|0|0|0|1204|1128185330|1128185330|1128185330|1024|0
     0 = always zero (may become md5 some day, but fls does not do it yet)
     /etc/terminfo/b/bterm = filename relative to "-m /"
       -- filename may contain ":stream" for NTFS streams
       -- filename may contain "-> filename" for symbolic links
       -- filename may contain "(deleted...)" (e.g., deleted-realloc)
     0 = always zero (may change in the future
     95 = inode (treat as string)
     33188 = numeric (decimal) of permissions
     -/-rw-r--r-- = text of permissions
     1 = number of hard links
     0 = uid
     0 = gid
     0 = always zero
     1204 = file size (bytes)
     1128185330 = atime
     1128185330 = mtime
     1128185330 = ctime
     1024 = block size
     0 = always zero
   */

  snprintf(Cmd,sizeof(Cmd),"fls -m / -f '%s' -lpr '%s' 2>/dev/null",
      FStype,Source);
  Fin = popen(Cmd,"r");
  if (!Fin)
  {
    fprintf(stderr,"ERROR: Disk failed: %s\n",Cmd);
    return(NULL);
  }

  while(ReadLine(Fin,Line,sizeof(Line)-1) >= 0)
  {
    NewList = (permlist *)malloc(sizeof(permlist));
    if (!NewList)
    {
      printf("FATAL: Unable to allocated %d bytes of memory\n",(int)sizeof(permlist));
      SafeExit(-1);
    }
    NewList->inode = NULL;
    NewList->Next = NULL;
    L=Line;
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* name */
    /* skip any realloc'ed names (deleted is fine...) */
    if (strstr(L,"realloc)|")) {FreeDiskPerms(NewList); continue;}
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* zero */
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* inode */
    inode = L; /* start of inode, but not length */
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* perm */
    NewList->perm = atoi(L);
    /* now save inode string info */
    if (L <= inode) {FreeDiskPerms(NewList); continue;}
    NewList->inode = (char *)calloc(L-inode,1);
    if (!NewList->inode)
    {
      printf("FATAL: Unable to allocate %d bytes.\n",(int)(L-inode));
      SafeExit(-1);
    }
    memcpy(NewList->inode,inode,L-inode-1);

    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* perm text */
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* hard links */
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* uid */
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* gid */
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* zero */
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* file size */
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* atime */
    NewList->Times.actime = atoi(L);
    L=strchr(L,'|'); if (!L) {FreeDiskPerms(NewList); continue;} L++; /* mtime */
    NewList->Times.modtime = atoi(L);
    /* NOTE: No way to set ctime! */
    /* save item */
    NewList->Next = List;
    List = NewList;
  } /* while read line */
  pclose(Fin);
  return(List);
} /* ExtractDiskPerms() */

/**
 * \brief Determine if two inodes are the same.
 *        Strings MUST be null terminated!
 *        Valid characters: 0-9 and hyphen.
 * \param Inode1
 * \param Inode2
 * \return 1 on match, 0 on miss.
 **/
int	SameInode	(char *Inode1, char *Inode2)
{
  int i;
  int v1,v2;
  for(i=0; Inode1[i] && Inode2[i]; i++)
  {
    if (isdigit(Inode1[i]) || (Inode1[i]=='-'))
    {
      if (Inode1[i] != Inode2[i]) return(0);
    }
    else break; /* out of the loop */
  }
  /* ok, they differ... */
  v1 = (isdigit(Inode1[i]) || (Inode1[i]=='-'));
  v2 = (isdigit(Inode2[i]) || (Inode2[i]=='-'));
  return(v1==v2); /* if they are both end-of-inode, then ok! */
} /* SameInode() */

/**
 * \brief Find a disk permission by inode, set the
 *        permissions on the file, and free the memory.
 * \param inode
 * \param List permlist
 * \param Destination = target directory containing file
 * \param Target = filename (may also include path components)
 * \return new permlist or NULL on error
 **/
permlist *	SetDiskPerm	(char *inode, permlist *List,
    char *Destination, char *Target)
{
  permlist *NewList, *Parent;
  char *Cwd;

  /* base case */
  if (!List) return(NULL);

  /* inodes could start with a non-digit */
  while((inode[0] != '\0') && !isdigit(inode[0])) inode++;

  /* base case */
  if (SameInode(List->inode,inode)) goto FoundPerm;

  /* else, find the list */
  Parent = List;
  while(Parent->Next)
  {
    if (SameInode(Parent->Next->inode,inode))
    {
      /* re-order so desired element is head of list */
      NewList = Parent->Next; /* hold matching element */
      Parent->Next = NewList->Next; /* bypass matching element */
      NewList->Next = List; /* move element to start of list */
      List = NewList; /* reset start of list */
      goto FoundPerm;
    }
    Parent = Parent->Next;
  }
  if (Verbose) fprintf(stderr,"LOG pfile %s WARNING Could not find inode: %s\n",Pfile,inode);
  return(List);	/* don't change list */

  FoundPerm:
  Cwd = getcwd(NULL,0);
  if (!Cwd)
  {
    printf("ERROR: Current directory no longer exists! Aborting!\n");
    SafeExit(-1); /* this never returns */
  }

  if(chdir(Destination) != 0)
  {
    fprintf(stderr, "ERROR %s.%d: Unable to change directory to %s\n",
        __FILE__, __LINE__, Destination);
    fprintf(stderr, "ERROR: errno is: %s\n", strerror(errno));
  }

  if (Verbose > 1) fprintf(stderr,"DEBUG: setting inode %s, name %s to %07o\n",List->inode,Target,List->perm);
  chmod(Target,List->perm); /* allow suid */
  utime(Target,&(List->Times));

  if(chdir(Cwd) != 0)
  {
    fprintf(stderr, "ERROR %s.%d: Unable to change directory to %s\n",
        __FILE__, __LINE__, Cwd);
    fprintf(stderr, "ERROR: errno is: %s\n", strerror(errno));
  }

  free(Cwd);
  Parent = List->Next;
  List->Next=NULL;
  FreeDiskPerms(List);
  return(Parent);
} /* SetDiskPerm() */

/**
 * \brief Given a disk image, type of system, and
 *        a directory, extract all files!
 *        This can handle any filesystem supported by fls/icat.
 *        Special: FAT is case-insensitive, so everything is converted to lowercase.
 *        NOTE: This spawns multiple processes.
 *        Uses the following external commands: fls icat
 *        icat and fls are from the package "sleuthkit".
 * \param Source
 * \param FStype  filesystem type
 * \param Destination
 * \return 0 on success, non-zero on failure. 
 **/
int	ExtractDisk	(char *Source, char *FStype, char *Destination)
{
  int rc;
  char Cmd[FILENAME_MAX*4]; /* command to run */
  char Line[FILENAME_MAX*2];
  char *s;
  FILE *Fin;
  int FatFlag=0;
  char *Inode,I;
  int InodeLen;
  /* for tainting strings in commands */
  char TempSource[FILENAME_MAX];
  char TempInode[FILENAME_MAX], TempDest[FILENAME_MAX], TempS[FILENAME_MAX];
  permlist *Perms;

  /* judge if the parameters are empty */ 
  if ((NULL == FStype) || (!strcmp(FStype, "")) || (NULL == Source) || (!strcmp(Source, "")) || (NULL == Destination) || (!strcmp(Destination, "")))
    return 1;

  if (!Quiet && Verbose) fprintf(stderr,"Extracting %s: %s\n",FStype,Source);

  if (!strcmp(FStype,"fat"))	FatFlag=1;

  /* get list of directories to extract to */
  /* NOTE: There is no distinction between real and deleted directories */
  /* CMD: fls -f 'FStype' -Dpr 'Source' */
  if (TaintString(TempSource,FILENAME_MAX,Source,1,NULL))
    return(-1);
  snprintf(Cmd,sizeof(Cmd),"fls -f '%s' -Dpr '%s' 2>&1",FStype,TempSource);
  Fin = popen(Cmd,"r");
  if (!Fin)
  {
    fprintf(stderr,"ERROR: Disk failed: %s\n",Cmd);
    return(-1);
  }
  while(ReadLine(Fin,Line,sizeof(Line)-1) >= 0)
  {
    /* check for errors */
    if (!memcmp(Line,"fls: ",5))
    {
      fprintf(stderr,"WARNING pfile %s Unable to extract\n",Pfile);
      fprintf(stderr,"LOG pfile %s WARNING: fls extraction issue on '%s'. %s\n",
          Pfile,TempSource,Line);
    }
    /* line should start "d/d" */
    /* other line types: "l/d" */
    if (memcmp(Line,"d/d",3) != 0) continue;	/* line should start "d/d" */
    if (strstr(Line," (deleted-realloc)") != NULL) continue; /* skip reallocs */
    if (FatFlag) FatDiskName(Line);
    s=strchr(Line,'\t'); /* filename starts at tab */
    if (s==NULL) continue;	/* there can be blank lines */
    s++;
    snprintf(Cmd,sizeof(Cmd),"%s/%s",Destination,s);
    if (MkDir(Cmd))
    {
      printf("ERROR: Unable to mkdir(%s) in ExtractDisk\n",Cmd);
      if (!ForceContinue) SafeExit(-1);
    }
  }
  pclose(Fin);

  /* Get disk permissions */
  /** NOTE: Do this AFTER making directories because:
      (1) We know extraction will work.
      (2) If we chmod before extraction then directory may not allow writing
      NOTE: Permissions on NTFS file systems looks broken in fls!
   **/
  {
    Perms = ExtractDiskPerms(FStype,TempSource);
    if (!Perms)
    {
      fprintf(stderr,"WARNING pfile %s Unable to extract permission\n",Pfile);
      fprintf(stderr,"LOG pfile %s WARNING: Unable to extract permission from %s\n",Pfile,Source);
    }
  }

  /* get list of regular (not deleted) files to extract */
  /* CMD: fls -f 'FStype' -Fupr 'Source' */
  snprintf(Cmd,sizeof(Cmd),"fls -f '%s' -Fupr '%s' 2>/dev/null",FStype,TempSource);
  Fin = popen(Cmd,"r");
  if (!Fin)
  {
    fprintf(stderr,"ERROR: Disk failed: %s\n",Cmd);
    FreeDiskPerms(Perms);
    return(-1);
  }
  while(ReadLine(Fin,Line,sizeof(Line)-1) >= 0)
  {
    if (FatFlag) FatDiskName(Line);
    /* Sample line: "r/r 95: etc/terminfo/b/bterm" */
    /* only handle regular files */
    if (memcmp(Line,"r/r",3) != 0) continue;	/* line should start "r/r" */
    s=strchr(Line,'\t'); /* filename starts after tab */
    if (s==NULL) continue;	/* there could be blank lines */
    s++;
    /* Under unix, inodes are numbers.  Under ntfs, it can be a string */
    Inode = Line+4; /* should be a number ended with a colon */
    InodeLen=0;
    while(Inode[InodeLen] && (Inode[InodeLen] != ':'))
    {
      InodeLen++;
    }

    /* CMD: icat -f 'FStype' 'Source' 'Inode' > 'Destination/s' */
    I=Inode[InodeLen];
    Inode[InodeLen]='\0';
    if (TaintString(TempInode,FILENAME_MAX,Inode,1,NULL) ||
        TaintString(TempDest,FILENAME_MAX,Destination,1,NULL) ||
        TaintString(TempS,FILENAME_MAX,s,1,NULL))
    {
      Inode[InodeLen]=I;
      FreeDiskPerms(Perms);
      return(-1);
    }
    Inode[InodeLen]=I;
    if (Verbose) printf("Extracting: icat '%s/%s'\n",TempDest,TempS);
    snprintf(Cmd,sizeof(Cmd),"icat -f '%s' '%s' '%s' > '%s/%s' 2>/dev/null",
        FStype,TempSource,TempInode,TempDest,TempS);

    rc = system(Cmd);
    if (WIFSIGNALED(rc))
    {
      printf("ERROR: Process killed by signal (%d): %s\n",WTERMSIG(rc),Cmd);
      SafeExit(-1);
    }
    rc = WEXITSTATUS(rc);
    if (rc)
    {
      fprintf(stderr,"WARNING pfile %s File extraction failed\n",Pfile);
      fprintf(stderr,"LOG pfile %s WARNING: Extraction failed (rc=%d): %s\n",Pfile,rc,Cmd);
    }

    /* set file permissions */
    Perms = SetDiskPerm(Inode,Perms,Destination,s);
  } /* while read Line */
  pclose(Fin);

  /* get list of DELETED files to extract (fls -d means deleted) */
  /* CMD: fls -f 'FStype' -Fdpr 'Source' */
  snprintf(Cmd,sizeof(Cmd),"fls -f '%s' -Fdpr '%s' 2>/dev/null",FStype,TempSource);
  Fin = popen(Cmd,"r");
  if (!Fin)
  {
    fprintf(stderr,"ERROR: Disk failed: %s\n",Cmd);
    FreeDiskPerms(Perms);
    return(-1);
  }
  while(ReadLine(Fin,Line,sizeof(Line)-1) >= 0)
  {
    if (FatFlag) FatDiskName(Line);
    /* Sample line: "r/r 95: etc/terminfo/b/bterm" */
    /* only handle regular files */
    if (memcmp(Line,"r/r",3) != 0) continue;	/* line should start "r/r" */
    s=strchr(Line,'\t'); /* filename starts after tab */
    if (s==NULL) continue;	/* there could be blank lines */
    s++;
    Inode = Line+6; /* should be "* number:" or "* number(realloc)" */
    InodeLen=0;
    while(Inode[InodeLen] && !strchr(":(",Inode[InodeLen]))
    {
      InodeLen++;
    }
    if (Inode[InodeLen] =='(') continue; /* skip reallocs */
    /* The same file may exist multiple times (lots of deletes).
       For uniqueness, the inode number is included.
       NOTE: "realloc" means the inode was reallocated! */
    /* CMD: icat -f 'FStype' 'Source' 'Inode' > 'Destination/s.deleted.Inode' */
    I=Inode[InodeLen];
    Inode[InodeLen]='\0';
    if (TaintString(TempInode,FILENAME_MAX,Inode,1,NULL) ||
        TaintString(TempDest,FILENAME_MAX,Destination,1,NULL) ||
        TaintString(TempS,FILENAME_MAX,s,1,NULL))
    {
      Inode[InodeLen]=I;
      FreeDiskPerms(Perms);
      return(-1);
    }
    Inode[InodeLen]=I;
    snprintf(Cmd,sizeof(Cmd),"icat -f '%s' '%s' '%s' > '%s/%s.deleted.%s' 2>/dev/null",
        FStype,TempSource,TempInode,TempDest,TempS,TempInode);

    if (Verbose) printf("Extracting: icat '%s/%s'\n",TempDest,TempS);
    rc = system(Cmd);
    if (WIFSIGNALED(rc))
    {
      printf("ERROR: Process killed by signal (%d): %s\n",WTERMSIG(rc),Cmd);
      SafeExit(-1);
    }
    rc = WEXITSTATUS(rc);
    if (rc)
    {
      fprintf(stderr,"WARNING pfile %s File extraction failed\n",Pfile);
      fprintf(stderr,"LOG pfile %s WARNING: Extraction failed (rc=%d): %s\n",Pfile,rc,Cmd);
    }

    /* set file permissions */
    Perms = SetDiskPerm(Inode,Perms,Destination,s);
  } /* while read line */
  pclose(Fin);

  /* for completeness, put back directory permissions */
  snprintf(Cmd,sizeof(Cmd),"fls -f '%s' -Dpr '%s' 2>/dev/null",FStype,TempSource);
  Fin = popen(Cmd,"r");
  if (!Fin)
  {
    fprintf(stderr,"ERROR: Disk failed: %s\n",Cmd);
    return(-1);
  }
  while(ReadLine(Fin,Line,sizeof(Line)-1) >= 0)
  {
    if (memcmp(Line,"d/d",3) != 0) continue;	/* line should start "d/d" */
    if (FatFlag) FatDiskName(Line);
    Inode = Line+4;
    s=strchr(Line,'\t'); /* filename starts at tab */
    if (s==NULL) continue;	/* there can be blank lines */
    s++;
    Perms = SetDiskPerm(Inode,Perms,Destination,s);
  }
  pclose(Fin);

  /* all done! */
  /** if done right, Perms should be null.  But just in case... **/
  FreeDiskPerms(Perms);
  return(0);
} /* ExtractDisk() */
