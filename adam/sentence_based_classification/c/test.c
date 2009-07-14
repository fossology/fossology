#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <string.h>
#include <ctype.h>
#include "list.h"
#include "re.h"
#include "stem.h"

int main(int argc, char **argv) {
    char *p = "[A-Za-z0-9]+|[^A-Za-z0-9 ]+";
    char * buffer;
    FILE *pFile;
    long lSize;
    size_t result;
    int i;
    default_list *list;
    cre *re;

    pFile = fopen(argv[1], "rb");
    if (pFile==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }

    fseek(pFile, 0, SEEK_END);
    lSize = ftell(pFile);
    rewind(pFile);

    buffer = (char*)malloc(sizeof(char)*lSize);
    if (buffer == NULL) {
        fputs("Memory error.\n",stderr);
        exit(2);
    }

    result = fread(buffer, 1, lSize, pFile);
    if (result != lSize) {
        fputs("Reading error",stderr); exit(3);
    }

    fclose(pFile);

    list = NULL;

    i = re_compile(p,RE_DOTALL,&re);

    if (i!=0) {
        re_print_error(i);
    }

    i = re_find_all(re,buffer,&list,&stem_create_from_string);

    if (i!=0) {
        re_print_error(i);
    }

    default_list_print(&list,&stem_print);

    re_free(re);
    free(buffer);
    default_list_free(&list, &stem_free);
    return(0);
}
