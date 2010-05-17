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

/*#define REMOVE_SPACES*/

/* other libraries */
#include <cvector.h>

/* local includes */
#include "tokenizer.h"
#include "re.h"
#include "token.h"
#include "token_feature.h"

char sent_re[] = "<[sS][eE][nN][tT][eE][nN][cC][eE]>(?P<text>.*?)</[sS][eE][nN][tT][eE][nN][cC][eE]>";
char start_nonword_re[] = "^[^A-Za-z0-9]+";
char general_token_re[] = "[A-Za-z0-9]+|[^A-Za-z0-9]+";
char word_token_re[] = "[A-Za-z0-9][A-Za-z0-9]+";

void remove_bad_tokens(cvector* feature_type_list) {
#ifdef REMOVE_SPACES
  cvector_iterator iter;
  for(iter = cvector_begin(feature_type_list); iter != cvector_end(feature_type_list); iter++) {
    token_feature *tf = (token_feature*)*iter;
    if(tf->char_vector[0] == tf->length) {
      iter = cvector_remove(feature_type_list, iter) - 1;
    }
  }
#endif
}

void create_sentence_list(char* buffer, cvector* list) {
  int i,j;
  cre *re;

  /* strip out the sentence tags   */
  /* i.e. <sentence>***</sentence> */
  /* place these sentence in list  */
  i = re_compile(sent_re, RE_DOTALL, &re);
  if (i != 0) { re_print_error(i); }
  i = re_find_all(re, buffer, list, &token_create_from_string);
  if (i != 0) { re_print_error(i); }
  re_free(re);

  /* that which will loop over the sentences */
  /* this is used to get rid of non words    */
  i = re_compile(start_nonword_re, RE_DOTALL, &re);
  if ( i!=0 ) { re_print_error(i); }

  /* remove the non words from the beginning of each sentence */
  for (i = 1; i < list->size; i++) {
    /* grab a token and create a new token list */
    token* t = cvector_at(list, i);
    cvector l;
    cvector_init(&l, token_cvector_registry());

    /* find all instances of the regular expression and store them in l */
    j = re_find_all(re, t->string, &l, &token_create_from_string);

    /* check the error code to be sure that somthing valid was returned */
    if (j != 0) {
      re_print_error(j);
    } else if (l.size > 0) {
      /* grab the next sentence */
      token *t_1 = cvector_at(list, i-1);
      /* grab the first thing that matched in the current sentence */
      token *t_2 = cvector_at(&l, 0);

      /* concatenate both strings onto each other */
      /* TODO I don't like this, lets see if I can get rid of this later */
      char *new_string = (char*)calloc((strlen(t_1->string)+strlen(t_2->string)+1), sizeof(char));
      strcat(new_string,t_1->string);
      strcat(new_string,t_2->string);
      new_string[strlen(t_1->string)+strlen(t_2->string)] = '\0';
      free(t_1->string);
      t_1->string = new_string;

      /* remove what wasn't a word from the beginning of the string */
      new_string = (char*)calloc(strlen(t->string)-strlen(t_2->string)+1, sizeof(char));
      strcpy(new_string,t->string+strlen(t_2->string));
      new_string[strlen(t->string)-strlen(t_2->string)] = '\0';
      free(t->string);
      t->string = new_string;
    }

    /* clean up the list that was used */
    cvector_destroy(&l);
  }

  /* clean up the regular expresion */
  re_free(re);
}

void create_features_from_sentences(cvector* list, cvector* feature_type_list, cvector* label_list) {
  /* locals */
  cvector_iterator iter;
  int j;
  cre *re;

  char *E = "E";
  char *I = "I";

  /* spilts a sentence into individual words */
  j = re_compile(general_token_re,RE_DOTALL,&re);
  if (j!=0) { re_print_error(j); }

  /* loop over everyone in the list passed in */
  for (iter = cvector_begin(list); iter != cvector_end(list); iter++) {
    /* grab the current token */
    token *t = (token*)(*iter);

    /* split the sentence into individual words */
    j = re_find_all(re, t->string, feature_type_list, &token_feature_create_from_string);
    if (j!=0) { re_print_error(j); break; }

    /* removes any unnecessary tokens from a list of features */
    remove_bad_tokens(feature_type_list);

    /* this is used to tell if the current feature is thought */
    /* to be the end of a sentence or internal to a sentence  */
    while (label_list->size < feature_type_list->size) {
      if (label_list->size + 1 == feature_type_list->size) {
        cvector_push_back(label_list, E);
      } else {
        cvector_push_back(label_list, I);
      }
    }
  }

  re_free(re);
}


void create_features_from_buffer(char *buffer, cvector* feature_type_list) {
  int i,j;
  cre *re;

  /* compile regex and use to create token appending this to the list */
  i = re_compile(general_token_re,RE_DOTALL,&re);
  if (i!=0) { re_print_error(i); }
  i = re_find_all(re, buffer, feature_type_list, &token_feature_create_from_string);
  if (i!=0) { re_print_error(j); }

  /* removes any unnecessary tokens from a list of features */
  remove_bad_tokens(feature_type_list);

  re_free(re);
}
