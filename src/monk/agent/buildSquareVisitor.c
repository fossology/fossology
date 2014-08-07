/*
Author: Daniele Fognini, Andreas Wuerl
Copyright (C) 2013-2014, Siemens AG

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
#include <math.h>
#include <stdio.h>
#include <glib.h>

#include "monk.h"

#define SIZE MAX_ALLOWED_DIFF_LENGTH

#ifndef M_PI_4
#define M_PI_2 1.57079632679489661923 /* pi/2 */
#define M_PI_4 0.78539816339744830962 /* pi/4 */
#endif

typedef struct {
  unsigned int x;
  unsigned int y;
  unsigned int time;
} point;

int pointSorter(const gconstpointer a, gconstpointer b) {
  unsigned int aTime = ((const point *) a)->time;
  unsigned int bTime = ((const point *) b)->time;
  if (aTime > bTime)
    return 1;
  if (aTime < bTime)
    return -1;
  return 0;
}

void circleVisit(unsigned int timeOfVisit[SIZE][SIZE]) {
  unsigned int time = 0;

  for (double r = 0; r < SIZE; r += 0.5)
    for (double theta = 0; theta < M_PI_2; theta += M_PI_4 / (SIZE)) {
      time += 1;
      int x = floor(r * sin(theta));
      int y = floor(r * cos(theta));
      if ((x < SIZE) && (x >= 0) && (y < SIZE) && (y >= 0))
        timeOfVisit[x][y] = time;
    }
}

GArray * generateTimeOrderedVisitor(unsigned int timeOfVisit[SIZE][SIZE]) {
  GArray * visitor = g_array_new(TRUE, FALSE, sizeof (point));
  for (int i = 0; i < SIZE; i++)
    for (int j = 0; j < SIZE; j++) {
      point p;
      p.x = i;
      p.y = j;
      p.time = timeOfVisit[i][j];
      if ((p.time > 0) && (i + j > 0))
        g_array_append_val(visitor, p);
    }

  g_array_sort(visitor, pointSorter);
  return visitor;
}

int writeVisitorToSourceFiles(GArray * visitor) {
  FILE * fc = fopen("_squareVisitor.c", "w");

  if (!fc) {
    return 2;
  }

  fprintf(fc, "unsigned int squareVisitorX[] = ");
  point p0 = g_array_index(visitor, point, 0);
  fprintf(fc, "{%u", p0.x);
  for (size_t i = 1; i < visitor->len; i++) {
    point p = g_array_index(visitor, point, i);
    fprintf(fc, ",\n\t%u", p.x);
  }
  fprintf(fc, "};\n");

  fprintf(fc, "unsigned int squareVisitorY[] = ");
  fprintf(fc, "{%u", p0.y);
  for (size_t i = 1; i < visitor->len; i++) {
    point p = g_array_index(visitor, point, i);
    fprintf(fc, ",\n\t%u", p.y);
  }
  fprintf(fc, "};\n");

  fclose(fc);

  FILE * fh = fopen("_squareVisitor.h.gen", "w");

  if (!fh) {
    return 2;
  }

  fprintf(fh, "#define SQUARE_VISITOR_LENGTH %u\n", visitor->len);

  fclose(fh);
  return 0;
}

int main() {
  unsigned int timeOfVisit[SIZE][SIZE];

  for (int i = 0; i < SIZE; i++)
    for (int j = 0; j < SIZE; j++)
      timeOfVisit[i][j] = 0;

  circleVisit(timeOfVisit);

#ifdef SQUARE_BUILDER_DEBUG
  printf("time of visit:\n");
  for (int i = 0; i < SIZE; i++) {
    for (int j = 0; j < SIZE; j++)
      printf("%u ", timeOfVisit[i][j]);
    printf("\n");
  }

  printf("visited:\n");
  for (int i = 0; i < SIZE; i++) {
    for (int j = 0; j < SIZE; j++)
      if (timeOfVisit[i][j] > 0)
        printf("+");
      else
        printf("-");
    printf("\n");
  }
#endif //SQUARE_BUILDER_DEBUG

  GArray * visitor = generateTimeOrderedVisitor (timeOfVisit);

  if (visitor->len == 0)
    return 1;

#ifdef SQUARE_BUILDER_DEBUG
  printf("sorted visitor is:\n");
  for (size_t i = 0; i < visitor->len; i++) {
    point p = g_array_index(visitor, point, i);
    printf("[%u]:{%u,%u}, ", p.time, p.x, p.y);
  }
  printf("\n");
#endif //SQUARE_BUILDER_DEBUG

  return writeVisitorToSourceFiles(visitor);
}
