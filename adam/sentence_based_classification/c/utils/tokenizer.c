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
#include "list.h"
#include "re.h"
#include "stem.h"
#include "token.h"
#include "feature_type.h"

unsigned char sent_re[] = "<SENTENCE>(?P<text>.*?)</SENTENCE>";
unsigned char start_nonword_re[] = "^[^A-Za-z0-9]+";
unsigned char general_token_re[] = "[A-Za-z0-9]+|[^A-Za-z0-9 ]+";
unsigned char word_token_re[] = "[A-Za-z0-9][A-Za-z0-9]+";

void create_sentence_list(unsigned char* buffer, default_list **list) {
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
        token *t = NULL;
        default_list *l = NULL;
        if (default_list_get(list,i,(void**)&t)==0) {
            j = re_find_all(re,t->string,&l,&token_create_from_string);
            if (j!=0) {
                re_print_error(j);
            } else if (default_list_length(&l)>0) {
                token *t_1 = NULL;
                token *t_2 = NULL;

                default_list_get(list,i-1,(void**)&t_1);
                default_list_get(&l,0,(void**)&t_2);

                // get the previous token and append the begging of this token
                // to the end of it.
                unsigned char *new_string = (unsigned char*)malloc(sizeof(unsigned char)*(strlen(t_1->string)+strlen(t_2->string)+1));
                new_string[0] = '\0';
                strcat(new_string,t_1->string);
                strcat(new_string,t_2->string);
                new_string[strlen(t_1->string)+strlen(t_2->string)] = '\0';
                free(t_1->string);
                t_1->string = new_string;

                new_string = (unsigned char*)malloc(sizeof(unsigned char)*(strlen(t->string)-strlen(t_2->string)+1));
                strcpy(new_string,t->string+strlen(t_2->string));
                new_string[strlen(t->string)-strlen(t_2->string)] = '\0';
                free(t->string);
                t->string = new_string;

                default_list_free(&l,&token_free);
            }
        }
    }
    re_free(re);
}

void create_features_from_sentences(default_list **list, default_list **feature_type_list,default_list **label_list) {
    int i,j;
    cre *re;

    i = re_compile(general_token_re,RE_DOTALL,&re);
    if (i!=0) { re_print_error(i); }
    for (i = 0; i < default_list_length(list); i++) {
        token *t = NULL;
        if (default_list_get(list,i,(void**)&t)==0) {
            token *t2 = NULL;
            j = re_find_all(re,t->string,feature_type_list,&feature_type_create_from_string);
            if (j!=0) { re_print_error(j); break; }
            while (default_list_length(label_list)<default_list_length(feature_type_list)) {
                t2 = (token*)malloc(sizeof(token));
                t2->string = (unsigned char*)malloc(sizeof(unsigned char)*2);
                strcpy(t2->string,"I");
                t2->string[1] = '\0';
                if (default_list_length(label_list)+1==default_list_length(feature_type_list)) {
                    t2->string[0] = 'E';

                }
                default_list_append(label_list,(void**)&t2);
            }
        }
    }
    re_free(re);
}

void create_features_from_buffer(unsigned char *buffer, default_list **feature_type_list) {
    int i,j;
    cre *re;

    i = re_compile(general_token_re,RE_DOTALL,&re);
    if (i!=0) { re_print_error(i); }
    i = re_find_all(re,buffer,feature_type_list,&feature_type_create_from_string);
    if (i!=0) { re_print_error(j); }
    re_free(re);
}
