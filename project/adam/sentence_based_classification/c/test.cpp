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
extern "C" {
#include <limits.h>
#include <sparsevect.h>
#include "sentence_type.h"
}

static unsigned long sdbm(unsigned char *str) {
    unsigned long hash = 0;
    int c;

    while (c = *str++)
        hash = c + (hash << 6) + (hash << 16) - hash;

    return hash;
}

int main(int argc, char **argv) {
    unsigned char *buffer;

    MaxentModel m;
    m.load("SentenceModel.dat");

    printf("Load database...\n");

    FILE *file;
    default_list *new_list = NULL;
    
    file = fopen("database.dat", "r");
    if (file==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }
    
    deafult_list_load(&new_list,file,&sentence_type_load);

    fclose(file);

    printf("%d\n", default_list_length(&new_list));
    
    file = fopen("temp.dat", "w");
    if (file==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }

    default_list_dump(&new_list,file,&sentence_type_dump);

    fclose(file);

    printf("Database file loaded.\n");

    default_list_free(&new_list,&sentence_type_free);
    return(0);
}
