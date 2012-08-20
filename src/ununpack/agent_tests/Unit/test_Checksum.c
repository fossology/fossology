/*********************************************************************
Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
*********************************************************************/
#include "run_tests.h"

/**
 * \file test_Checksum.c
 * \brief unit test case for checksum.c
 *
 */

/**
 * \brief test function CountDigits
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
 */
void testSumComputeFile()
{
  Cksum *SumTest;
  FILE *Fin;
  Filename = "../test-data/testdata4unpack/threezip.zip";
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
      FO_ASSERT_STRING_EQUAL(Fuid, "E1ABCC8332A1A477D06EF9BA46A62ECCED2C357E.7613AE78F7E175DCB132472F20028F50.557949");
      FO_ASSERT_EQUAL((int)SumTest->DataLen, 557949);
      free(SumTest);
    }
    fclose(Fin);
  } 
}

/**
 * \brief test function SumToString
 */
void testSumToString()
{
  Cksum *SumTest;
  FILE *Fin;
  Filename = "../test-data/testdata4unpack/threezip.zip";
  char *Fuid = NULL;

  Fin = fopen(Filename,"rb");
  if (Fin)
  {
    SumTest = SumComputeFile(Fin);
    if (SumTest)
    {
      Fuid = SumToString(SumTest);
      FO_ASSERT_STRING_EQUAL(Fuid, "E1ABCC8332A1A477D06EF9BA46A62ECCED2C357E.7613AE78F7E175DCB132472F20028F50.557949");
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
