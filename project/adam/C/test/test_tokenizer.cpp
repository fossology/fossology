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

#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include "tokenizer.h"
#include "default_list.h"
#include "re.h"
#include "token.h"
#include "file_utils.h"
#include "config.h"
#include "repr.h"

int main(int argc, char **argv) {
    int i,j;

    
    
    FILE *pFile;
    pFile = fopen(argv[1], "rb");
    if (pFile==NULL) {
        fprintf(stderr, "File error opening %s.\n", argv[1]);
        exit(1);
    }
    char *filename = NULL;
    while (readline(pFile,&filename)!=EOF) {
        char *buffer;
        printf("%s.\n", filename);
        openfile(filename,&buffer);
        default_list sentence_list = default_list_create(default_list_type_token());
        default_list feature_type_list = default_list_create(default_list_type_token_feature());
        default_list label_list = default_list_create(default_list_type_string());

        create_sentence_list(buffer,sentence_list);
        create_features_from_sentences(sentence_list,feature_type_list, label_list);
        for (i = 0; i < default_list_length(feature_type_list); i++) {
            char rstr[1000] = "";
            token_feature *tf = (token_feature *)default_list_get(feature_type_list,i);
            char *l = (char *)default_list_get(label_list,i);
            if (strcmp("E",l)==0 && tf->char_vector[1] == 0) {
                repr_string(rstr,tf->string);
                printf("%s, %s\n", rstr, l);

                printf(" [");
                j = i - 3;
                if (j<0) {
                    j = 0;
                }
                for (; j < i+7 && j<default_list_length(feature_type_list); j++) {
                    token_feature *tf_j = (token_feature *)default_list_get(feature_type_list,j);
                    strcpy(rstr,"");
                    repr_string(rstr,tf_j->string);
                    printf("%s", rstr);
                }
                printf("]\n");
            }
        }
        default_list_destroy(sentence_list);
        default_list_destroy(feature_type_list);
        default_list_destroy(label_list);
    }
    return 0;
}
