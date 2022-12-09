/*
 SPDX-FileCopyrightText: © 2007-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <gcrypt.h>

#include "checksum.h"

/**
 * \file
 * \brief Code to compute a three part checksum: SHA1.MD5.Size
 *   - SHA1 = SHA1 of the file.
 *   - MD5 = MD5 value of the file.
 *   - Size = number of bytes in the file.
 * The chances of two files having the same size, same MD5, and
 * same SHA1 is extremely unlikely.
 */

/**
 * \brief Open and mmap a file.
 * \param Fname File pathname
 * \return CksmFile ptr, or NULL on failure.
 */
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
  /* BSD does not need nor use O_LARGEFILE */
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
 */
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
 */
int	CountDigits	(uint64_t Num)
{
  uint64_t Val=10;
  int Digits=1;
  for(Val=10; (Val > 0) && (Val < Num); Val = Val * 10)
  {
    Digits++;
	}
  return(Digits);
} /* CountDigits() */

/**
 * \brief Compute the checksum, allocate and
 *        return a string containing the sum value.
 * \note The calling function must free() the string!
 * \param Fin Open file descriptor
 * \return NULL on error.
 */
Cksum *	SumComputeFile	(FILE *Fin)
{
  gcry_md_hd_t checksumhandler;
  gcry_error_t checksumError = 0;
  char Buffer[64];
  Cksum *Sum;
  unsigned char *tempBuff;
  int ReadLen;
  uint64_t ReadTotalLen=0;

  Sum = (Cksum *)calloc(1,sizeof(Cksum));
  if (!Sum) return(NULL);

  checksumError = gcry_md_open(&checksumhandler, GCRY_MD_NONE, 0);
  if (! checksumhandler)
  {
    LOG_ERROR("Unable to initialize checksum\n");
    free(Sum);
    return(NULL);
  }
  checksumError = gcry_md_enable(checksumhandler, GCRY_MD_MD5);
  if (gcry_err_code(checksumError) != GPG_ERR_NO_ERROR)
  {
    LOG_ERROR("GCRY Error: %s/%s\n", gcry_strsource(checksumError),
        gcry_strerror(checksumError));
    free(Sum);
    return(NULL);
  }

  checksumError = gcry_md_enable(checksumhandler, GCRY_MD_SHA1);
  if (gcry_err_code(checksumError) != GPG_ERR_NO_ERROR)
  {
    LOG_ERROR("GCRY Error: %s/%s\n", gcry_strsource(checksumError),
        gcry_strerror(checksumError));
    free(Sum);
    return(NULL);
  }

  while(!feof(Fin))
  {
    ReadLen = fread(Buffer,1,64,Fin);
    if (ReadLen > 0)
    {
      gcry_md_write(checksumhandler, Buffer, ReadLen);
      ReadTotalLen += ReadLen;
    }
  }

  Sum->DataLen = ReadTotalLen;

  tempBuff = gcry_md_read(checksumhandler, GCRY_MD_MD5);
  memcpy(Sum->MD5digest, tempBuff, sizeof(Sum->MD5digest));

  tempBuff = gcry_md_read(checksumhandler, GCRY_MD_SHA1);
  memcpy(Sum->SHA1digest, tempBuff, sizeof(Sum->SHA1digest));
  gcry_md_close(checksumhandler);

  return(Sum);
} /* SumComputeFile() */

/**
 * \brief Compute the checksum, allocate and
 *        return a Cksum containing the sum value.
 * \note The calling function must free() the returned Cksum!
 * \param CF CksumFile ptr
 * \return Cksum or NULL on error.
 */
Cksum *	SumComputeBuff	(CksumFile *CF)
{
  Cksum *Sum;
  gcry_md_hd_t checksumhandler;
  gcry_error_t checksumError;
  unsigned char *tempBuff;

  checksumError = gcry_md_open(&checksumhandler, GCRY_MD_NONE, 0);
  if (! checksumhandler)
  {
    LOG_ERROR("Unable to initialize checksum\n");
    return(NULL);
  }
  checksumError = gcry_md_enable(checksumhandler, GCRY_MD_MD5);
  if (gcry_err_code(checksumError) != GPG_ERR_NO_ERROR)
  {
    LOG_ERROR("GCRY Error: %s/%s\n", gcry_strsource(checksumError),
        gcry_strerror(checksumError));
    return(NULL);
  }

  checksumError = gcry_md_enable(checksumhandler, GCRY_MD_SHA1);
  if (gcry_err_code(checksumError) != GPG_ERR_NO_ERROR)
  {
    LOG_ERROR("GCRY Error: %s/%s\n", gcry_strsource(checksumError),
        gcry_strerror(checksumError));
    return(NULL);
  }

  Sum = (Cksum *)calloc(1,sizeof(Cksum));
  if (!Sum)
  {
    return(NULL);
  }
  Sum->DataLen = CF->MmapSize;

  gcry_md_write(checksumhandler, CF->Mmap, CF->MmapSize);

  tempBuff = gcry_md_read(checksumhandler, GCRY_MD_MD5);
  memcpy(Sum->MD5digest, tempBuff, sizeof(Sum->MD5digest));

  tempBuff = gcry_md_read(checksumhandler, GCRY_MD_SHA1);
  memcpy(Sum->SHA1digest, tempBuff, sizeof(Sum->SHA1digest));

  gcry_md_close(checksumhandler);
  return(Sum);
} /* SumComputeBuff() */


/**
 * \brief Return string representing a Cksum.
 *  NOTE: The calling function must free() the string!
 * \param Sum Cksum ptr
 * \return "sha1.md5.size" string, or NULL on error.
 */
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

int calc_sha256sum(char*filename, char* dst) {
  gcry_md_hd_t checksumhandler;
  unsigned char buf[32];
  unsigned char *tempBuff;
  memset(buf, '\0', sizeof(buf));
  FILE *f;
  if(!(f=fopen(filename, "rb")))
  {
    LOG_FATAL("Failed to open file '%s'\n", filename);
    return(1);
  }
  gcry_md_open(&checksumhandler, GCRY_MD_SHA256, 0);
  if (! checksumhandler ||
    (! gcry_md_is_enabled(checksumhandler, GCRY_MD_SHA256)))
  {
    LOG_ERROR("Unable to initialize checksum\n");
    return(2);
  }

  int i=0;
  while((i=fread(buf, 1, sizeof(buf), f)) > 0) {
    gcry_md_write(checksumhandler, buf, i);
  }
  fclose(f);
  memset(buf, '\0', sizeof(buf));
  tempBuff = gcry_md_read(checksumhandler, GCRY_MD_SHA256);
  memcpy(buf, tempBuff, sizeof(buf));

  gcry_md_close(checksumhandler);

  for (i=0; i<32; i++)
  {
    snprintf(dst+i*2, 3, "%02X", buf[i]);
  }

  return 0;
}
