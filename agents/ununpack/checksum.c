/************************************************************
 Code to compute a "less likely for collision" checksum.

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

 *********************
 Use -DMAIN to make it a command-line.
 The checksum value contains 3 parts:
   SHA1.MD5.Size
 SHA1 = SHA1 of the file.
 MD5 = MD5 value of the file.
 Size = number of bytes in the file.
 The chances of two files having the same size, same MD5, and
 same SHA1 is extremely unlikely.  (But it might happen!)
 ************************************************************/

#include <stdlib.h>

/* specify support for files > 2G */
#define __USE_LARGEFILE64
#define __USE_FILE_OFFSET64

#include <stdio.h>
#include <unistd.h>

#include <stdint.h>
#include <string.h>
#include <errno.h>
#include <sys/mman.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <dirent.h>

#include "checksum.h"
#include "md5.h"
#include "sha1.h"

/**********************************************
 SumOpenFile(): Open and mmap a file.
 Returns structure, or NULL on failure.
 **********************************************/
CksumFile *	SumOpenFile	(char *Fname)
{
  CksumFile *CF;
  struct stat64 Stat;

  CF=(CksumFile *)calloc(1,sizeof(CksumFile));
  if (!CF) return(NULL);

  /* open the file (memory map) */
  CF->FileHandle = open(Fname,O_RDONLY|O_LARGEFILE);
  if (CF->FileHandle == -1)
	{
	fprintf(stderr,"ERROR: Unable to open file (%s)\n",Fname);
	free(CF);
	return(NULL);
	}
  if (fstat64(CF->FileHandle,&Stat) == -1)
	{
	fprintf(stderr,"ERROR: Unable to stat file (%s)\n",Fname);
	close(CF->FileHandle);
	free(CF);
	return(NULL);
	}
  CF->MmapSize = Stat.st_size;
  CF->MmapOffset = 0;

  /* reject files that are too long */
  if (CF->MmapSize >= (uint32_t)(-1))
	{
	close(CF->FileHandle);
	free(CF);
	return(NULL);
	}

  if (CF->MmapSize > 0)
    {
    CF->Mmap = mmap64(0,CF->MmapSize,PROT_READ,MAP_PRIVATE,CF->FileHandle,0);
    if (CF->Mmap == MAP_FAILED)
	{
	fprintf(stderr,"ERROR: Unable to mmap file (%s)\n",Fname);
	close(CF->FileHandle);
	free(CF);
	return(NULL);
	}
    }
  return(CF);
} /* SumOpenFile() */

/**********************************************
 SumCloseFile(): Close a filename.
 **********************************************/
void	SumCloseFile	(CksumFile *CF)
{
  if ((CF->MmapSize > 0) && CF->Mmap)	munmap(CF->Mmap,CF->MmapSize);
  CF->MmapSize = 0;
  CF->Mmap = NULL;
  close(CF->FileHandle);
  free(CF);
} /* SumCloseFile() */

/**********************************************
 CountDigits(): How many digits are in a number?
 **********************************************/
int	CountDigits	(uint64_t Num)
{
  uint64_t Val=10;
  int Digits=1;
  for(Val=10; (Val > 0) && (Val < Num); Val = Val * 10)
  	{
	Digits++;
	}
  /* printf("%d has %d digits\n",Num,Digits); */
  return(Digits);
} /* CountDigits() */

/**********************************************
 SumComputeFile(): Compute the checksum, allocate and
 return a string containing the sum value.
 NOTE: The calling function must free() the string!
 Returns NULL on error.
 **********************************************/
Cksum *	SumComputeFile	(FILE *Fin)
{
  int rc;
  SHA1Context sha1;
  MD5_CTX md5;
  char Buffer[64];
  Cksum *Sum;
  int ReadLen;
  uint64_t ReadTotalLen=0;

  Sum = (Cksum *)calloc(1,sizeof(Cksum));
  if (!Sum) return(NULL);

  MD5_Init(&md5);
  rc = SHA1Reset(&sha1);
  if (rc)
    {
    fprintf(stderr,"ERROR: Unable to initialize sha1\n");
    free(Sum);
    return(NULL);
    }

  while(!feof(Fin))
    {
    ReadLen = fread(Buffer,1,64,Fin);
    if (ReadLen > 0)
	{
	MD5_Update(&md5,Buffer,ReadLen);
	if (SHA1Input(&sha1,(uint8_t *)Buffer,ReadLen) != shaSuccess)
	  {
	  fprintf(stderr,"ERROR: Failed to compute sha1 (intermediate compute)\n");
	  free(Sum);
	  return(NULL);
	  }
	ReadTotalLen += ReadLen;
	}
    }

  Sum->DataLen = ReadTotalLen;
  MD5_Final(Sum->MD5digest,&md5);
  rc = SHA1Result(&sha1,Sum->SHA1digest);
  if (rc != shaSuccess)
    {
    fprintf(stderr,"ERROR: Failed to compute sha1\n");
    free(Sum);
    return(NULL);
    }
  return(Sum);
} /* SumComputeFile() */

/**********************************************
 SumComputeBuff(): Compute the checksum, allocate and
 return a string containing the sum value.
 NOTE: The calling function must free() the string!
 Returns NULL on error.
 **********************************************/
Cksum *	SumComputeBuff	(CksumFile *CF)
{
  int rc;
  SHA1Context sha1;
  MD5_CTX md5;
  Cksum *Sum;

  Sum = (Cksum *)calloc(1,sizeof(Cksum));
  if (!Sum) return(NULL);
  Sum->DataLen = CF->MmapSize;

  MD5_Init(&md5);
  rc = SHA1Reset(&sha1);
  if (rc)
    {
    fprintf(stderr,"ERROR: Unable to initialize sha1\n");
    free(Sum);
    return(NULL);
    }

  MD5_Update(&md5,CF->Mmap,CF->MmapSize);
  MD5_Final(Sum->MD5digest,&md5);
  SHA1Input(&sha1,CF->Mmap,CF->MmapSize);
  rc = SHA1Result(&sha1,Sum->SHA1digest);
  if (rc)
    {
    fprintf(stderr,"ERROR: Failed to compute sha1\n");
    free(Sum);
    return(NULL);
    }
  return(Sum);
} /* SumComputeBuff() */


/**********************************************
 SumToString(): Compute the checksum, allocate and
 return a string containing the sum value.
 NOTE: The calling function must free() the string!
 Returns NULL on error.
 **********************************************/
char *	SumToString	(Cksum *Sum)
{
  int i;
  char *Result;

  Result = (char *)calloc(1,16*2 +1+ 20*2 +1+ CountDigits(Sum->DataLen) + 1);
  if (!Result) return(NULL);

  for(i=0; i<20; i++)
    {
    sprintf(Result + (i*2),"%02X",Sum->SHA1digest[i]);
    }
  Result[40]='.';
  for(i=0; i<16; i++)
    {
    sprintf(Result + 41 + (i*2),"%02X",Sum->MD5digest[i]);
    }
  Result[41+32]='.';
  sprintf(Result + 33 + 41,"%Lu",(long long unsigned int)Sum->DataLen);
  return(Result);
} /* SumToString() */

/**********************************************
 RecurseFiles(): Process all files in all directories.
 **********************************************/
void	RecurseFiles	(char *S)
{
  char NewS[FILENAME_MAX+1];
  DIR *Dir;
  struct dirent *Entry;
  struct stat64 Stat;
  CksumFile *CF;
  char *Result=NULL;
  Cksum *Sum;

  Dir = opendir(S);
  if (Dir == NULL)
	{
	Result=NULL;
	/* it's a single file -- compute checksum */
	CF = SumOpenFile(S);
	if (CF == NULL)
	  {
	  FILE *Fin;
	  Fin = fopen64(S,"rb");
	  if (!Fin)
	    {
	    perror("Huh?");
	    fprintf(stderr,"ERROR: cannot open file \"%s\".\n",S);
	    }
	  else
	    {
	    Sum = SumComputeFile(Fin);
	    if (Sum) { Result=SumToString(Sum); free(Sum); }
	    fclose(Fin);
	    }
	  }
	else
	  {
	  Sum = SumComputeBuff(CF);
	  if (Sum) { Result=SumToString(Sum); free(Sum); }
	  SumCloseFile(CF);
	  }
	if (Result != NULL)
		{
		printf("%s %s\n",Result,S);
		free(Result);
		Result=NULL;
		}
	return;
	}
  Entry = readdir(Dir);
  while(Entry != NULL)
	{
	if (!strcmp(Entry->d_name,".")) goto skip;
	if (!strcmp(Entry->d_name,"..")) goto skip;
	memset(NewS,'\0',sizeof(NewS));
	strcpy(NewS,S);
	strcat(NewS,"/");
	strcat(NewS,Entry->d_name);
	lstat64(NewS,&Stat);
	Result=NULL;
	if (S_ISDIR(Stat.st_mode)) RecurseFiles(NewS);
	else
	  {
	  /* compute checksum */
	  CF = SumOpenFile(NewS);
	  if (CF == NULL)
	    {
	    FILE *Fin;
	    Fin = fopen64(NewS,"rb");
	    if (!Fin)
	      fprintf(stderr,"ERROR: Cannot open file \"%s\".\n",NewS);
	    else
	      {
	      Sum = SumComputeFile(Fin);
	      if (Sum) { Result=SumToString(Sum); free(Sum); }
	      fclose(Fin);
	      }
	    }
	  else
	    {
	    Sum = SumComputeBuff(CF);
	    if (Sum) { Result=SumToString(Sum); free(Sum); }
	    SumCloseFile(CF);
	    }
	  if (Result != NULL)
		{
		printf("%s %s\n",Result,NewS);
		free(Result);
		Result=NULL;
		}
	  }
skip:
	Entry = readdir(Dir);
	}
  closedir(Dir);
} /* RecurseFiles() */


/**************************************************************************/
#ifdef MAIN
int	main	(int argc, char *argv[])
{
  int i;
  char *Result=NULL;
  Cksum *Sum;

  if (argc==1)
	{
	/* no args? read from stdin */
	Sum = SumComputeFile(stdin);
	if (Sum) { Result=SumToString(Sum); free(Sum); }
	if (Result) { printf("%s\n",Result); free(Result); }
	}

  for(i=1; i<argc; i++)
    {
    if (!strcmp(argv[i],"-"))
      {
      /* read from stdin */
      Sum = SumComputeFile(stdin);
      if (Sum) { Result=SumToString(Sum); free(Sum); }
      if (Result != NULL)
	{
	printf("%s %s\n",Result,argv[i]);
	free(Result);
	Result=NULL;
	}
      }
    else
      {
      /* read from a file */
      RecurseFiles(argv[i]);
      }
    }
  return(0);
} /* main() */
#endif

