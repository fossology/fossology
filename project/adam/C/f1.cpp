/*********************************************************************
Copyright (C) 2009 Hewlett-Packard Development Company, L.P.

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

void classify_file(char *filename, cvector* database_list, MaxentModel m) {
  char *buffer;
  cvector feature_type_list, label_list, file_list, * sentence_list;
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

  double score      [file_list.size];
  int score_index   [file_list.size];
  int vector              [database_list->size][file_list.size];
  double match_percent    [database_list->size][file_list.size];
  int match_index         [database_list->size][file_list.size];

  for (i = 0; i < database_list->size; i++) {
    /* locals to the loop */
    sentence_list = (cvector*)cvector_at(database_list, i);
    double matrix[file_list.size + 1][sentence_list->size + 1];
    double cosine[file_list.size    ][sentence_list->size    ];
    double m = 0;

    /* zero the matrix and cosine */
    memset(matrix, 0, sizeof(matrix));
    memset(cosine, 0, sizeof(cosine));

    for (j = 0; j < sentence_list->size; j++) {
      a = (sentence *)cvector_at(sentence_list, j);
      /* TODO this threshold stuff needs to be updated */
      double thresh = 1.0 - 2.0 / ((double)sv_nonzeros(a->vector));
      if (thresh < 0.75) {
        thresh = 0.75;
      }
      thresh = 0.5;
      for (k = 0; k < file_list.size; k++) {
        b = (sentence *)cvector_at(&file_list, k);
        cosine[k][j] = sv_inner(a->vector,b->vector);
        if (cosine[k][j] > thresh) {
          matrix[k+1][j+1] = matrix[k][j] + 1;
        } else {
          if (matrix[k][j+1] > matrix[k+1][j]) {
            matrix[k+1][j+1] = matrix[k][j+1];
          } else {
            matrix[k+1][j+1] = matrix[k+1][j];
          }
        }
      }
    }

    for (k = 0; k < file_list.size; k++) {
      vector[i][k] = 0;
      for (j = 0; j < sentence_list->size; j++) {
        if (matrix[k+1][j+1] > vector[i][k]) {
          vector[i][k] = matrix[k+1][j+1];
          match_percent[i][k] = cosine[k][j];
          match_index[i][k] = j;
        }
      }
    }

    for (k = 0; k < file_list.size; k++) {
      if (vector[i][k] == m) {
        vector[i][k] = 0;
        match_percent[i][k] = 0.0;
      } else if (k > 0 && vector[i][k-1] == 0) {
        m = vector[i][k];
        vector[i][k] = 1;
      } else {
        m = vector[i][k];
        if (k > 0) {
          vector[i][k] = vector[i][k-1] + 1;
          match_percent[i][k] += match_percent[i][k-1];
        }
      }
    }

    for (k = file_list.size - 2; k > -1; k--) {
      if (vector[i][k] != 0 && vector[i][k+1] > vector[i][k]) {
        vector[i][k] = vector[i][k+1];
        match_percent[i][k] = match_percent[i][k+1];
      }
    }

    for (k = 0; k < file_list.size; k++) {
      if (vector[i][k] == 1) {
        vector[i][k] = 0;
        match_percent[i][k] = 0.0;
      }
    }
  }

  for (i = 0; i < file_list.size; i++) {
    score[i] = 0;
    for (j = 0; j < database_list->size; j++) {
      if (match_percent[j][i] > score[i]) {
        score[i] = match_percent[j][i];
        score_index[i] = j;
      }
    }
  }

  for (i = 1; i < file_list.size - 1; i++) {
    if (i == 1) {
      if (score[0] < score[1]) {
        score[0] = 0;
      }
    }
    if (i == file_list.size - 2) {
      if (score[i+1] < score[i]) {
        score[i+1] = 0;
      }
    }
    if (score[i-1] == 0 && score[i] < score[i+1]) {
      score[i] = 0;
    } else if (score[i+1] == 0 && score[i] < score[i-1]) {
      score[i] = 0;
    }
  }

  for (i = 0; i < file_list.size; i++) {
    if (score[i] == 0) {
      score_index[i] = 0;
    }
  }

  int prev_index = -1;
  int start_byte = 0;
  int end_byte = 0;
  for (i = 0; i < file_list.size; i++) {
    printf("score_index[i]: %d\n",score_index[i]);
    if (score_index[i] != 0) {
      st = (sentence *)cvector_at(&file_list,i);
      if (prev_index == -1) {
        start_byte = st->start;
        end_byte = st->end;
      } else if (prev_index != score_index[i]) {
        printf("one: ");
        printf("\t%d [%05d, %05d]\n", prev_index, start_byte, end_byte);
        start_byte = st->start;
        end_byte = st->end;
      } else if (prev_index == score_index[i]) {
        end_byte = st->end;
      }
      prev_index = score_index[i];
    } else {
      if (prev_index > -1) {
        printf("two: ");
        printf("\t%d [%05d, %05d]\n", prev_index, start_byte, end_byte);
      }
      prev_index = -1;
      start_byte = 0;
      end_byte = 0;
    }
  }
  if (prev_index != -1) {
    printf("three: ");
    printf("\t%d [%05d, %05d]\n", prev_index, start_byte, end_byte);
  }
}

int main(int argc, char **argv) {
  FILE *file;
  cvector database_list;

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
  classify_file(argv[1],&database_list,m);

  fclose(file);
  cvector_destroy(&database_list);
  return(0);
}
