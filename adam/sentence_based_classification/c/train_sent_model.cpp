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
#include "list.h"
#include "re.h"
#include "token.h"
#include "feature_type.h"
#include <maxent/maxentmodel.hpp>
#include "maxent_utils.h"
#include "file_utils.h"

int main(int argc, char **argv) {
    char *buffer;
    int i,j;
    default_list *sentence_list = NULL;
    default_list *feature_type_list = NULL;
    default_list *label_list = NULL;
    int left_window = 3;
    int right_window = 3;

    // Create a maxent model object and tell it that we will start to add
    // training data.
    MaxentModel m;
    m.begin_add_event();
    for (i = 1; i<argc; i++) {
        printf("Starting on %s...\n", argv[i]);
        buffer = NULL;
        sentence_list = NULL;
        feature_type_list = NULL;
        label_list = NULL;
        openfile(argv[i],&buffer);
        create_sentence_list(buffer,&sentence_list);
        create_features_from_sentences(&sentence_list,&feature_type_list, &label_list);
        // This function adds the training data to the model.
        create_model(m, &feature_type_list, &label_list, left_window, right_window);
        free(buffer);
        default_list_free(&sentence_list,&token_free);
        default_list_free(&feature_type_list,&feature_type_free);
        default_list_free(&label_list,&token_free);
    }
    m.end_add_event();

    printf("Training MaxEnt model...\n");
    m.train(1000, "lbfgs");
    m.save("SentenceModel.dat");

    return(0);
}
