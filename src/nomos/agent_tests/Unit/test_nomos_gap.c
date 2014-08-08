/*
Copyright (C) 2014, Siemens AG

Author:  Steffen Weber
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
*/

#include <stdlib.h>
#include <stdio.h>
#include <CUnit/CUnit.h>
#include <stdarg.h>

#include "nomos_gap.h"

void test_collapseInvisible() {
  char* buf, *fer;
  GArray* po;//= g_array_new(FALSE, FALSE, sizeof(pairPosOff));
  buf=(char*) malloc(20);
  fer=(char*) malloc(20);
  sprintf(fer, "\377abc\377\377de\377\377fg\377hi");
  strcpy(buf,fer);

   po=collapseInvisible(buf,(char)'\377');
  for(int i=0; i<po->len; ++i) {
    pairPosOff* thePoA = getPairPosOff(po, i);
    CU_ASSERT_EQUAL(*(buf+thePoA->pos), *(fer+thePoA->pos+thePoA->off));
  }
  free(buf);
  free(fer);
}

void test_uncollapseInvisible() {
  char* buf, *fer;
  GArray* po; //= g_array_new(FALSE, FALSE, sizeof(pairPosOff));;
  fer=(char*) malloc(20);
  buf=(char*) malloc(20);
  sprintf(buf, "\377abc\377\377de\377\377fg\377hi");
  strcpy(fer,buf);
  po=collapseInvisible(buf,(char)'\377'  );

  for(int i=0; i< strlen(buf); i++){
    CU_ASSERT_EQUAL( *(buf+i) , *(fer + uncollapsePosition(i,po)) );
  }
  free(buf);
  free(fer);
}

void test_collapseSpaces() {
  char* buf, *fer;
  GArray* po;
  buf=(char*) malloc(30);
  fer=(char*) malloc(30);
  sprintf(fer, "  abc  d e      fghi");
  strcpy(buf,fer);
  po = collapseSpaces(buf);
  for(int i=0; i<po->len; ++i) {
    pairPosOff* thePoA = getPairPosOff(po, i);
    CU_ASSERT_EQUAL(*(buf+thePoA->pos), *(fer+thePoA->pos+thePoA->off));
  }
  free(buf);
  free(fer);
}

void test_uncollapseSpaces() {
  char* buf, *fer;
  GArray* po;
  fer=(char*) malloc(30);
  buf=(char*) malloc(30);
  sprintf(buf, "  abc  d e      fghi");
  strcpy(fer,buf);
  po = collapseSpaces(buf);
  for(int i=0; i< strlen(buf); i++){
    CU_ASSERT_EQUAL( *(buf+i) , *(fer + uncollapsePosition(i,po)) );
  }
  free(buf);
  free(fer);
}


CU_TestInfo nomos_gap_testcases[] = {
  {"Testing collapse space:", test_collapseSpaces},
  {"Testing uncollapse space:", test_uncollapseSpaces},
  {"Testing collapse:", test_collapseInvisible},
  {"Testing uncollapse:", test_uncollapseInvisible},
  CU_TEST_INFO_NULL
};
