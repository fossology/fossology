/************************************************************
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

 ************************************************************/

#include "checksum.h"
#include "md5.h"
#include "sha1.h"

/**
 * \file  checksum.c
 * \brief Code to compute a three part checksum: SHA1.MD5.Size
 *   - SHA1 = SHA1 of the file.
 *   - MD5 = MD5 value of the file.
 *   - Size = number of bytes in the file.
 * The chances of two files having the same size, same MD5, and
 * same SHA1 is extremely unlikely.
 **/

/**
 * \brief Open and mmap a file.
 * \param Fname File pathname
 * \return CksmFile ptr, or NULL on failure.
 **/
CksumFile *	SumOpenFile	(char *Fname)
{
  CksumFile *CF;
  struct stat Stat;

  CF=(CksumFile *)calloc(1,sizeof(CksumFile));
  if (!CF) return(NULL);

  /* open the file (memory map) */
#ifdef O_LARGEFILE
  CF->FileHandle = open(Fname,O_RDONLY|O_LARGEFILE);
#else
  /** BSD does not need nor use O_LARGEFILE **/
  CF->FileHandle = open(Fname,O_RDONLY);
#endif
  if (CF->FileHandle == -1)
	{
	LOG_ERROR("Unable to open file (%s)\n",Fname);
	free(CF);
	return(NULL);
	}
  if (fstat(CF->FileHandle,&Stat) == -1)
	{
	LOG_ERROR("Unable to stat file (%s)\n",Fname);
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
    CF->Mmap = mmap(0,CF->MmapSize,PROT_READ,MAP_PRIVATE,CF->FileHandle,0);
    if (CF->Mmap == MAP_FAILED)
	{
	LOG_ERROR("Unable to mmap file (%s)\n",Fname);
	close(CF->FileHandle);
	free(CF);
	return(NULL);
	}
    }
  return(CF);
} /* SumOpenFile() */

/**
 * \brief Close a file that was opened with SumOpenFile()
 * \param CF CksumFile ptr
 **/
void	SumCloseFile	(CksumFile *CF)
{
  if ((CF->MmapSize > 0) && CF->Mmap)	munmap(CF->Mmap,CF->MmapSize);
  CF->MmapSize = 0;
  CF->Mmap = NULL;
  close(CF->FileHandle);
  free(CF);
} /* SumCloseFile() */

/**
 * \brief Count how many digits are in a number.
 * \param Num Number
 * \return number of digits
 **/
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

/**
 * \brief Compute the checksum, allocate and
 *        return a string containing the sum value.
 * NOTE: The calling function must free() the string!
 * \param Fin Open file descriptor
 * \return NULL on error.
 **/
Cksum *	SumComputeFile	(FILE *Fin)
{
  int rc;
  SHA1Context sha1;
  MyMD5_CTX md5;
  char Buffer[64];
  Cksum *Sum;
  int ReadLen;
  uint64_t ReadTotalLen=0;

  Sum = (Cksum *)calloc(1,sizeof(Cksum));
  if (!Sum) return(NULL);

  MyMD5_Init(&md5);
  rc = SHA1Reset(&sha1);
  if (rc)
    {
    LOG_ERROR("Unable to initialize sha1\n");
    free(Sum);
    return(NULL);
    }

  while(!feof(Fin))
    {
    ReadLen = fread(Buffer,1,64,Fin);
    if (ReadLen > 0)
	{
	MyMD5_Update(&md5,Buffer,ReadLen);
	if (SHA1Input(&sha1,(uint8_t *)Buffer,ReadLen) != shaSuccess)
	  {
	  LOG_ERROR("Failed to compute sha1 (intermediate compute)\n");
	  free(Sum);
	  return(NULL);
	  }
	ReadTotalLen += ReadLen;
	}
    }

  Sum->DataLen = ReadTotalLen;
  MyMD5_Final(Sum->MD5digest,&md5);
  rc = SHA1Result(&sha1,Sum->SHA1digest);
  if (rc != shaSuccess)
    {
    LOG_ERROR("Failed to compute sha1\n");
    free(Sum);
    return(NULL);
    }
  return(Sum);
} /* SumComputeFile() */

/**
 * \brief Compute the checksum, allocate and
 *        return a Cksum containing the sum value.
 * NOTE: The calling function must free() the returned Cksum!
 * \param CF CksumFile ptr
 * \return Cksum or NULL on error.
 **/
Cksum *	SumComputeBuff	(CksumFile *CF)
{
  int rc;
  SHA1Context sha1;
  MyMD5_CTX md5;
  Cksum *Sum;

  Sum = (Cksum *)calloc(1,sizeof(Cksum));
  if (!Sum) return(NULL);
  Sum->DataLen = CF->MmapSize;

  MyMD5_Init(&md5);
  rc = SHA1Reset(&sha1);
  if (rc)
    {
    LOG_ERROR("Unable to initialize sha1\n");
    free(Sum);
    return(NULL);
    }

  MyMD5_Update(&md5,CF->Mmap,CF->MmapSize);
  MyMD5_Final(Sum->MD5digest,&md5);
  SHA1Input(&sha1,CF->Mmap,CF->MmapSize);
  rc = SHA1Result(&sha1,Sum->SHA1digest);
  if (rc)
    {
    LOG_ERROR("Failed to compute sha1\n");
    free(Sum);
    return(NULL);
    }
  return(Sum);
} /* SumComputeBuff() */


/**
 * \brief Return string representing a Cksum.
 *  NOTE: The calling function must free() the string!
 * \param Sum Cksum ptr
 * \return "sha1.md5.size" string, or NULL on error.
 **/
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

