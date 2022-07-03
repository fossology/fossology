/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#include "run_tests.h"

/**
 * \file
 * \brief unit test case for checksum.c
 */

/**
 * \brief test function CountDigits
 * \test
 * -# Call CountDigits() with a number
 * -# Check if function returned actual count
 */
void testCountDigits()
{
  uint64_t Num = 15;
  int Digits = 0;
  Digits = CountDigits(Num);
  //printf("%d has %d digits\n",Num,Digits);
  FO_ASSERT_EQUAL(Digits, 2);
}

/**
 * \brief test function SumComputeFile
 * \test
 * -# Compute checksum of a known file using SumComputeFile()
 * -# Compare if the function result correct checksum
 */
void testSumComputeFile()
{
  Cksum *SumTest;
  FILE *Fin;
  Filename = "../testdata/test.zip";
  char Fuid[1024];
  int i;

  memset(Fuid,0,sizeof(Fuid));
  Fin = fopen(Filename,"rb");
  if (Fin)
  {
    SumTest = SumComputeFile(Fin);
    if (SumTest)
    {
      for(i=0; i<20; i++) { sprintf(Fuid+0+i*2,"%02X",SumTest->SHA1digest[i]); }
      Fuid[40]='.';
      for(i=0; i<16; i++) { sprintf(Fuid+41+i*2,"%02X",SumTest->MD5digest[i]); }
      Fuid[73]='.';
      snprintf(Fuid+74,sizeof(Fuid)-74,"%Lu",(long long unsigned int)SumTest->DataLen);
      //printf("%s +++++++++\n",Fuid);
      FO_ASSERT_STRING_EQUAL(Fuid, "5CBBD4E0487601E9160A5C887E5C0C1E6541B3DE.5234FC4D5F9786A51B2206B9DEEACA68.825");
      FO_ASSERT_EQUAL((int)SumTest->DataLen, 825);
      free(SumTest);
    }
    fclose(Fin);
  }
}

/**
 * \brief test function SumToString
 * \test
 * -# Get a result from SumComputeFile()
 * -# Call SumToString() on the result
 * -# Check if the function translated the structure to a string
 */
void testSumToString()
{
  Cksum *SumTest;
  FILE *Fin;
  Filename = "../testdata/test.zip";
  char *Fuid = NULL;

  Fin = fopen(Filename,"rb");
  if (Fin)
  {
    SumTest = SumComputeFile(Fin);
    if (SumTest)
    {
      Fuid = SumToString(SumTest);
      FO_ASSERT_STRING_EQUAL(Fuid, "5CBBD4E0487601E9160A5C887E5C0C1E6541B3DE.5234FC4D5F9786A51B2206B9DEEACA68.825");
      free(SumTest);
    }
    fclose(Fin);
  }
}
/* ************************************************************************** */
/* **** cunit test cases **************************************************** */
/* ************************************************************************** */

CU_TestInfo Checksum_testcases[] =
{
  {"Checksum function CountDigits:", testCountDigits},
  {"Checksum function SumComputeFile:", testSumComputeFile},
  {"Checksum function SumToString:", testSumToString},
  CU_TEST_INFO_NULL
};
