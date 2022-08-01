/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Unit test cases for Nomos_gap
 */

#include <stdlib.h>
#include <stdio.h>
#include <CUnit/CUnit.h>
#include <stdarg.h>

#include "nomos_gap.h"

/**
 * \brief Test for collapseInvisible()
 * \test
 * -# Create a string with INVISIBLE characters
 * -# Call collapseInvisible()
 * -# Check if the string is collapsed with getPairPosOff()
 */
void test_collapseInvisible()
{
  char* buf, *fer;
  GArray* po;
  buf=g_strdup_printf("\377abc\377\377de\377\377fg\377hi");
  fer=g_strdup(buf);

   po=collapseInvisible(buf,(char)'\377');
  for(int i=0; i<po->len; ++i) {
    pairPosOff* thePoA = getPairPosOff(po, i);
    CU_ASSERT_EQUAL(*(buf+thePoA->pos), *(fer+thePoA->pos+thePoA->off));
  }
  g_array_free(po,TRUE);
  g_free(buf);
  g_free(fer);
}

/**
 * \brief Test for uncollapsePosition() on collapseInvisible()
 * \test
 * -# Create a string with INVISIBLE characters
 * -# Call collapseInvisible()
 * -# Check if the string can be uncollapsed by calling uncollapsePosition()
 */
void test_uncollapseInvisible()
{
  char* buf, *fer;
  GArray* po;
  buf=g_strdup_printf("\377abc\377\377de\377\377fg\377hi");
  fer=g_strdup(buf);
  po=collapseInvisible(buf,(char)'\377'  );

  for(int i=0; i< strlen(buf); i++){
    CU_ASSERT_EQUAL( *(buf+i) , *(fer + uncollapsePosition(i,po)) );
  }
  g_array_free(po,TRUE);
  g_free(buf);
  g_free(fer);
}

/**
 * \brief Test for collapseSpaces()
 * \test
 * -# Create a string with spaces
 * -# Call collapseSpaces()
 * -# Check if the string is collapsed with getPairPosOff()
 */
void test_collapseSpaces()
{
  char* buf, *fer;
  GArray* po;
  buf=g_strdup_printf("  abc  d e      fghi");
  fer=g_strdup(buf);
  po = collapseSpaces(buf);
  for(int i=0; i<po->len; ++i) {
    pairPosOff* thePoA = getPairPosOff(po, i);
    CU_ASSERT_EQUAL(*(buf+thePoA->pos), *(fer+thePoA->pos+thePoA->off));
  }
  g_array_free(po,TRUE);
  g_free(buf);
  g_free(fer);
}

/**
 * \brief Test for uncollapsePosition() on collapseSpaces()
 * \test
 * -# Create a string with spaces
 * -# Call collapseSpaces()
 * -# Check if the string can be uncollapsed by calling uncollapsePosition()
 */
void test_uncollapseSpaces()
{
  char* buf, *fer;
  GArray* po;
  buf=g_strdup_printf("  abc  d e      fghi");
  fer=g_strdup(buf);
  po = collapseSpaces(buf);
  for(int i=0; i< strlen(buf); i++){
    CU_ASSERT_EQUAL( *(buf+i) , *(fer + uncollapsePosition(i,po)) );
  }
  g_array_free(po,TRUE);
  g_free(buf);
  g_free(fer);
}

CU_TestInfo nomos_gap_testcases[] = {
  {"Testing collapse space:", test_collapseSpaces},
  {"Testing uncollapse space:", test_uncollapseSpaces},
  {"Testing collapse:", test_collapseInvisible},
  {"Testing uncollapse:", test_uncollapseInvisible},
  CU_TEST_INFO_NULL
};
