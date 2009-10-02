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
#include "tokenizer.h"
#include "default_list.h"
#include "re.h"
#include "token.h"
#include "file_utils.h"
#include "config.h"
#include "repr.h"
#include <maxent/maxentmodel.hpp>
#include <sparsevect.h>
#include "sentence.h"
#include "maxent_utils.h"

int main(int argc, char **argv) {
    int i,j;

    MaxentModel m;
    m.load("../maxent.dat");
    
    char *buffer;
    default_list sentence_list = default_list_create(default_list_type_sentence());
    default_list feature_type_list = default_list_create(default_list_type_token_feature());
    default_list label_list = default_list_create(default_list_type_string());

    openfile(argv[1],&buffer);
    create_features_from_buffer(buffer,feature_type_list);
    label_sentences(m,feature_type_list,label_list,left_window,right_window);
    create_sentences(m, sentence_list, buffer, feature_type_list, label_list, "", "", 0);
    
    free(buffer);
    default_list_destroy(feature_type_list);
    default_list_destroy(label_list);
    default_list_destroy(sentence_list);

    for (i = 0; i < default_list_length(sentence_list); i++) {
        sentence *st = (sentence*)default_list_get(sentence_list, i);

        printf("%d\n",st->end);
    }

    return 0;
}

