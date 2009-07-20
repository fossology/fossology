#include "file_utils.h"

void openfile(char *filename, char **buffer) {
    FILE *pFile;
    long lSize;
    size_t result;
    pFile = fopen(filename, "rb");
    if (pFile==NULL) {
        fputs("File error.\n", stderr);
        exit(1);
    }

    fseek(pFile, 0, SEEK_END);
    lSize = ftell(pFile);
    rewind(pFile);

    *buffer = (char*)malloc(sizeof(char)*lSize);
    if (*buffer == NULL) {
        fputs("Memory error.\n",stderr);
        exit(2);
    }

    result = fread(*buffer, 1, lSize, pFile);
    if (result != lSize) {
        fputs("Reading error",stderr); exit(3);
    }

    fclose(pFile);
}

