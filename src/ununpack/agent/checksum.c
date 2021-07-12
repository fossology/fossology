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
  botan_hash_t mdchecksumhandler;
  botan_hash_t shachecksumhandler;
  int checksumError = 0;
  char Buffer[64];
  Cksum *Sum;
  unsigned char tempBuff[21] = {0};
  int ReadLen;
  uint64_t ReadTotalLen=0;

  Sum = (Cksum *)calloc(1,sizeof(Cksum));
  if (!Sum) return(NULL);

  checksumError = botan_hash_init(&mdchecksumhandler, "MD5", 0);
  checksumError |= botan_hash_init(&shachecksumhandler, "SHA-1", 0);
  if (! mdchecksumhandler || !shachecksumhandler ||
    checksumError != BOTAN_FFI_SUCCESS)
  {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
    LOG_ERROR("Checksum init error: %s\n",
      botan_error_description(checksumError));
#else
    LOG_ERROR("Failed to initialize checksum\n");
#endif
    botan_hash_destroy(mdchecksumhandler);
    botan_hash_destroy(shachecksumhandler);
    free(Sum);
    return(NULL);
  }

  while(!feof(Fin))
  {
    ReadLen = fread(Buffer,1,64,Fin);
    checksumError = 0;
    if (ReadLen > 0)
    {
      checksumError = botan_hash_update(mdchecksumhandler,
        (unsigned char *) Buffer, ReadLen);
      checksumError |= botan_hash_update(shachecksumhandler,
        (unsigned char *) Buffer, ReadLen);
      ReadTotalLen += ReadLen;
    }
    if (checksumError != BOTAN_FFI_SUCCESS)
    {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
      LOG_ERROR("Checksum calc fail: %s\n",
        botan_error_description(checksumError));
#else
      LOG_ERROR("Failed to calculate checksum\n");
#endif
      botan_hash_destroy(mdchecksumhandler);
      botan_hash_destroy(shachecksumhandler);
      free(Sum);
      return(NULL);
    }
  }

  Sum->DataLen = ReadTotalLen;

  checksumError = botan_hash_final(mdchecksumhandler, tempBuff);
  memcpy(Sum->MD5digest, tempBuff, sizeof(Sum->MD5digest));

  checksumError |= botan_hash_final(shachecksumhandler, tempBuff);
  memcpy(Sum->SHA1digest, tempBuff, sizeof(Sum->SHA1digest));

  if (checksumError != BOTAN_FFI_SUCCESS)
  {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
    LOG_ERROR("Checksum calc fail: %s\n",
      botan_error_description(checksumError));
#else
    LOG_ERROR("Failed to calculate checksum\n");
#endif
    botan_hash_destroy(mdchecksumhandler);
    botan_hash_destroy(shachecksumhandler);
    free(Sum);
    return(NULL);
  }

  botan_hash_destroy(mdchecksumhandler);
  botan_hash_destroy(shachecksumhandler);

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
  botan_hash_t mdchecksumhandler;
  botan_hash_t shachecksumhandler;
  int checksumError = 0;
  unsigned char tempBuff[21] = {0};

  checksumError = botan_hash_init(&mdchecksumhandler, "MD5", 0);
  checksumError |= botan_hash_init(&shachecksumhandler, "SHA-1", 0);
  if (! mdchecksumhandler || !shachecksumhandler ||
    checksumError != BOTAN_FFI_SUCCESS)
  {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
    LOG_ERROR("Checksum init error: %s\n",
      botan_error_description(checksumError));
#else
    LOG_ERROR("Failed to initialize checksum\n");
#endif
    botan_hash_destroy(mdchecksumhandler);
    botan_hash_destroy(shachecksumhandler);
    return(NULL);
  }

  Sum = (Cksum *)calloc(1,sizeof(Cksum));
  if (!Sum)
  {
    return(NULL);
  }
  Sum->DataLen = CF->MmapSize;

  checksumError = botan_hash_update(mdchecksumhandler, CF->Mmap, CF->MmapSize);
  checksumError |= botan_hash_update(shachecksumhandler, CF->Mmap, CF->MmapSize);
  if (checksumError != BOTAN_FFI_SUCCESS)
  {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
    LOG_ERROR("Checksum calc fail: %s\n",
      botan_error_description(checksumError));
#else
    LOG_ERROR("Failed to calculate checksum\n");
#endif
    botan_hash_destroy(mdchecksumhandler);
    botan_hash_destroy(shachecksumhandler);
    free(Sum);
    return(NULL);
  }
  checksumError = botan_hash_final(mdchecksumhandler, tempBuff);
  memcpy(Sum->MD5digest, tempBuff, sizeof(Sum->MD5digest));

  checksumError |= botan_hash_final(shachecksumhandler, tempBuff);
  memcpy(Sum->SHA1digest, tempBuff, sizeof(Sum->SHA1digest));

  if (checksumError != BOTAN_FFI_SUCCESS)
  {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
    LOG_ERROR("Checksum calc fail: %s\n",
      botan_error_description(checksumError));
#else
    LOG_ERROR("Failed to calculate checksum\n");
#endif
    botan_hash_destroy(mdchecksumhandler);
    botan_hash_destroy(shachecksumhandler);
    free(Sum);
    return(NULL);
  }

  botan_hash_destroy(mdchecksumhandler);
  botan_hash_destroy(shachecksumhandler);

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
  char sha1sum[41] = {0};
  char md5sum[33] = {0};
  char *Result;
  int checksumError = 0;

  Result = (char *)calloc(1,16*2 +1+ 20*2 +1+ CountDigits(Sum->DataLen) + 1);
  if (!Result) return(NULL);

  checksumError = botan_hex_encode(Sum->SHA1digest, sizeof(Sum->SHA1digest),
    sha1sum, 0);
  if (checksumError != BOTAN_FFI_SUCCESS)
  {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
    LOG_ERROR("Checksum calc fail: %s\n",
      botan_error_description(checksumError));
#else
    LOG_ERROR("Failed to calculate checksum\n");
#endif
    return NULL;
  }
  checksumError = botan_hex_encode(Sum->MD5digest, sizeof(Sum->MD5digest),
    md5sum, 0);
  if (checksumError != BOTAN_FFI_SUCCESS)
  {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
    LOG_ERROR("Checksum calc fail: %s\n",
      botan_error_description(checksumError));
#else
    LOG_ERROR("Failed to calculate checksum\n");
#endif
    return NULL;
  }
  sprintf(Result, "%s.%s.%Lu", sha1sum, md5sum,
    (long long unsigned int)Sum->DataLen);
  return(Result);
} /* SumToString() */

int calc_sha256sum(char*filename, char* dst)
{
  botan_hash_t checksumhandler;
  int checksumError = 0;
  unsigned char buf[32];
  unsigned char *tempBuff;
  size_t buffsize = 0;
  memset(buf, '\0', sizeof(buf));
  FILE *f;
  if(!(f=fopen(filename, "rb")))
  {
    LOG_FATAL("Failed to open file '%s'\n", filename);
    return(1);
  }
  checksumError = botan_hash_init(&checksumhandler, "SHA-256", 0);
  if (! checksumhandler || checksumError != BOTAN_FFI_SUCCESS)
  {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
    LOG_ERROR("Checksum init error: %s\n",
      botan_error_description(checksumError));
#else
    LOG_ERROR("Failed to initialize checksum\n");
#endif
    return(2);
  }

  int i=0;
  while((i=fread(buf, 1, sizeof(buf), f)) > 0)
  {
    checksumError = botan_hash_update(checksumhandler, buf, i);
    if (checksumError != BOTAN_FFI_SUCCESS)
    {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
      LOG_ERROR("Checksum calc fail: %s\n",
        botan_error_description(checksumError));
#else
      LOG_ERROR("Failed to calculate checksum\n");
#endif
      botan_hash_destroy(checksumhandler);
      return(3);
    }
  }
  fclose(f);

  botan_hash_output_length(checksumhandler, &buffsize);
  tempBuff = (unsigned char *) calloc(buffsize + 1, sizeof(unsigned char *));
  checksumError = botan_hash_final(checksumhandler, tempBuff);
  if (checksumError != BOTAN_FFI_SUCCESS)
  {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
    LOG_ERROR("Checksum calc fail: %s\n",
      botan_error_description(checksumError));
#else
    LOG_ERROR("Failed to calculate checksum\n");
#endif
    botan_hash_destroy(checksumhandler);
    return(3);
  }
  botan_hash_destroy(checksumhandler);

  checksumError = botan_hex_encode(tempBuff, buffsize, dst, 0);
  if (checksumError != BOTAN_FFI_SUCCESS)
  {
#if BOTAN_VERSION_MAJOR > 2 && BOTAN_VERSION_MINOR > 4
    LOG_ERROR("Checksum calc fail: %s\n",
      botan_error_description(checksumError));
#else
    LOG_ERROR("Failed to calculate checksum\n");
#endif
    return -1;
  }
  free(tempBuff);

  return 0;
}
