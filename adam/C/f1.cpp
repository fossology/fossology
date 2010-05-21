/*********************************************************************
Copyright (C) 2009, 2010 Hewlett-Packard Development Company, L.P.

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

/* std library */
#include <stdio.h>
#include <stdlib.h>
#include <limits.h>
#include <math.h>

/* other libraries */
#include <maxent/maxentmodel.hpp>
#include <sparsevect.h>

/* local includes */
#include "tokenizer.h"
#include "re.h"
#include "token.h"
#include "token_feature.h"
#include "maxent_utils.h"
#include "file_utils.h"
#include "sentence.h"
#include "config.h"
#include "hash.h"

/* global defines */
#define NAME_SIZE 256

const double threshold = 0.54;

void load_database(FILE* file, cvector* database_list) {
  /* local variables */
  int i, j, type_size, num_licenses, num_sentences;
  char list_type[NAME_SIZE];
  size_t ret;

  /* start by reading what the file contains */
  memset(list_type, 0 , sizeof(list_type));
  ret = fread(&type_size, sizeof(int), 1, file);
  ret = fread(list_type, sizeof(char), type_size, file);

  /* next read in the file */
  ret = fread(&num_licenses, sizeof(int), 1, file);
  for(i = 0; i < num_licenses; i++) {
    /* create the new cvector that will be loaded */
    cvector next;
    cvector_init(&next, sentence_cvector_registry());

    /* read in the type of the list */
    memset(list_type, 0 , sizeof(list_type));
    ret = fread(&type_size, sizeof(int), 1, file);
    ret = fread(list_type, sizeof(char), type_size, file);

    ret = fread(&num_sentences, sizeof(int), 1, file);
    for(j = 0; j < num_sentences; j++) {
      sentence* next_sentence = (sentence*)calloc(1,sizeof(sentence));
      int len = 0;

      /* read the sentence itself */
      ret = fread(&len, sizeof(int), 1, file);
      next_sentence->string = (char*)calloc(len, sizeof(char));
      ret = fread(next_sentence->string, sizeof(char), len, file);

      /* read the metadata about the sentence */
      ret = fread(&next_sentence->start, sizeof(int), 1, file);
      ret = fread(&next_sentence->end, sizeof(int), 1, file);
      ret = fread(&next_sentence->position, sizeof(int), 1, file);

      /* read the filename that the sentence belongs to */
      ret = fread(&len, sizeof(int), 1, file);
      next_sentence->filename = (char*)calloc(len, sizeof(char));
      ret = fread(next_sentence->filename, sizeof(char), len, file);

      /* read the license name from the file */
      ret = fread(&len, sizeof(int), 1, file);
      next_sentence->licensename = (char*)calloc(len, sizeof(char));
      ret = fread(next_sentence->licensename, sizeof(char), len, file);

      /* read the id and the vector for the sentence */
      ret = fread(&next_sentence->id, sizeof(int), 1, file);
      next_sentence->vector = sv_load(file);

      cvector_push_back(&next, next_sentence);

      sentence_destroy(next_sentence);
    }
    cvector_push_back(database_list, &next);
    cvector_destroy(&next);
  }
}

void print(char* license, int index, int start, int end) {
  int i;

  printf("%s\n", license);
  //  printf("\t%d [%05d, %05d]\n", index, start, end);
}

void classify_file(char *filename, cvector* database_list, MaxentModel m) {
  char *buffer;
  cvector feature_type_list, label_list, file_list, * sentence_list, * compare_list;
  char *t = NULL;
  token_feature *ft = NULL;
  sentence *st = NULL;
  sentence *a = NULL;
  sentence *b = NULL;
  int i, j, k;

  buffer = NULL;
  cvector_init(&feature_type_list, token_feature_cvector_registry());
  cvector_init(&label_list, string_cvector_registry());
  cvector_init(&file_list, sentence_cvector_registry());
  readtomax(filename,&buffer,32768);
  create_features_from_buffer(buffer,&feature_type_list);
  label_sentences(m,&feature_type_list,&label_list,left_window,right_window);

  create_sentences(m, &file_list, buffer, &feature_type_list, &label_list, filename, "", 0);

  free(buffer);
  cvector_destroy(&feature_type_list);
  cvector_destroy(&label_list);

  double best_score = 0.0;
  int best_index = 0;

  for (i = 0; i < database_list->size; i++) {
    /* grab a license to compare to */
    compare_list = (cvector*)cvector_at(database_list, i);

    /* locals to the loop */
    double values[compare_list->size][file_list.size];
    int binary_values[compare_list->size][file_list.size];

    /* zero the matricies */
    memset(values, 0, sizeof(values));
    memset(binary_values, 0 , sizeof(binary_values));

    int best = 0;
    int best_j = 0;
    int best_k = 0;

    /* populate the values and binary matrix with the values */
    for(j = 0; j < compare_list->size; j++) {
      a = (sentence*)cvector_at(compare_list, j);
      for(k = 0; k < file_list.size; k++) {
        b = (sentence *)cvector_at(&file_list, k);
        /* take the dot product of the sentences to find the similarity */
        values[j][k] = sv_inner(a->vector, b->vector);

        /* if the sentence is above the threshold record it */
        if(values[j][k] > threshold) {
          if(j == 0 || k == 0) {
            binary_values[j][k] == 1;
          } else {
            binary_values[j][k] = binary_values[j-1][k-1] + 1;
          }
        }

        if(binary_values[j][k] > best) {
          best = binary_values[j][k];
          best_j = j;
          best_k = k;
        }
      }
    }

    double score = 0.0;
    for(j = 0; j < binary_values[best_j][best_k]; j++) {
      score += values[best_j - j][best_k - j];
    }
    score /= binary_values[best_j][best_k];
    if(score > best_score) {
      best_score = score;
      best_index = i;
    }
  }

  printf("File: \"%s\" -> \"%s\"\n", filename, ((sentence*)cvector_at((cvector*)cvector_at(database_list, best_index), 0))->licensename);
}

int main(int argc, char **argv) {
  FILE *file;
  cvector database_list;
  char **curr;

  MaxentModel m;
  m.load("maxent.dat");

  file = fopen("database.dat", "r");
  if (file==NULL) {
    fputs("File error. Could not read Database.dat\n", stderr);
    exit(1);
  }

  /* create and load the database from a file */
  cvector_init(&database_list, cvector_cvector_registry());
  load_database(file, &database_list);

  for(curr = argv+1; curr-argv < argc; curr++) {
    classify_file(*curr,&database_list,m);
  }

  fclose(file);
  cvector_destroy(&database_list);
  return(0);
}
