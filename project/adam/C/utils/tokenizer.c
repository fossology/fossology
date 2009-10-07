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

#include "tokenizer.h"
#include <default_list.h>
#include "re.h"
#include "token.h"
#include "token_feature.h"

char sent_re[] = "<[sS][eE][nN][tT][eE][nN][cC][eE]>(?P<text>.*?)</[sS][eE][nN][tT][eE][nN][cC][eE]>";
char start_nonword_re[] = "^[^A-Za-z0-9]+";
char general_token_re[] = "[A-Za-z0-9]+|[^A-Za-z0-9]+";
char word_token_re[] = "[A-Za-z0-9][A-Za-z0-9]+";

void remove_bad_tokens(feature_type_list) {
#ifdef REMOVE_SPACES
    int i;
    for (i = 0; i < default_list_length(feature_type_list); i++) {
        token_feature *tf = default_list_get(feature_type_list,i);
        if (tf->char_vector[0] == tf->length) {
            default_list_remove(feature_type_list,i);
            i--;
        }
    }
#endif
}

void create_sentence_list(char* buffer, default_list list) {
    int i,j;
    cre *re;

    i = re_compile(sent_re,RE_DOTALL,&re);

    if (i!=0) { re_print_error(i); }

    i = re_find_all(re,buffer,list,&token_create_from_string);

    re_free(re);

    if (i!=0) { re_print_error(i); }

    i = re_compile(start_nonword_re,RE_DOTALL,&re);
    
    if (i!=0) { re_print_error(i); }

    for (i = 1; i < default_list_length(list); i++) {
        token *t = default_list_get(list,i);
        default_list l = default_list_create(default_list_type_token());
        if (t != NULL) {
            j = re_find_all(re,t->string,l,&token_create_from_string);
            if (j!=0) {
                re_print_error(j);
            } else if (default_list_length(l)>0) {
                token *t_1 = default_list_get(list,i-1);
                token *t_2 = default_list_get(l,0);

                // get the previous token and append the begging of this token
                // to the end of it.
                char *new_string = (char*)malloc(sizeof(char)*(strlen(t_1->string)+strlen(t_2->string)+1));
                new_string[0] = '\0';
                strcat(new_string,t_1->string);
                strcat(new_string,t_2->string);
                new_string[strlen(t_1->string)+strlen(t_2->string)] = '\0';
                free(t_1->string);
                t_1->string = new_string;

                new_string = (char*)malloc(sizeof(char)*(strlen(t->string)-strlen(t_2->string)+1));
                strcpy(new_string,t->string+strlen(t_2->string));
                new_string[strlen(t->string)-strlen(t_2->string)] = '\0';
                free(t->string);
                t->string = new_string;

                default_list_destroy(l);
            }
        }
    }
    re_free(re);
}

void create_features_from_sentences(default_list list, default_list feature_type_list,default_list label_list) {
    int i,j;
    cre *re;
    
    char *E = "E";
    char *I = "I";

    i = re_compile(general_token_re,RE_DOTALL,&re);
    if (i!=0) { re_print_error(i); }
    for (i = 0; i < default_list_length(list); i++) {
        token *t = default_list_get(list,i);
        if (t != NULL) {
            j = re_find_all(re,t->string,feature_type_list,&token_feature_create_from_string);
            if (j!=0) { re_print_error(j); break; }
            remove_bad_tokens(feature_type_list);
            while (default_list_length(label_list)<default_list_length(feature_type_list)) {
                if (default_list_length(label_list)+1==default_list_length(feature_type_list)) {
                    default_list_append(label_list,E);
                } else {
                    default_list_append(label_list,I);
                }
            }
        }
    }
    
    re_free(re);
}

void create_features_from_buffer(char *buffer, default_list feature_type_list) {
    int i,j;
    cre *re;

    i = re_compile(general_token_re,RE_DOTALL,&re);
    if (i!=0) { re_print_error(i); }
    i = re_find_all(re,buffer,feature_type_list,&token_feature_create_from_string);
    if (i!=0) { re_print_error(j); }

    remove_bad_tokens(feature_type_list);

    re_free(re);
}
