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

int readline(FILE *pFile, char **line) {
    int max = 256;
    int nch = 0;
    int c;

    *line = (char*)malloc(sizeof(char)*max);
    if (*line == NULL) {
        fputs("Memory error.\n",stderr);
        exit(2);
    }

    while((c = getc(pFile)) != EOF) {
        if (c == '\n') {
            break;
        }
        if (nch < max) {
            line[0][nch] = c;
            nch++;
        }
    }

    if (c == EOF && nch == 0) {
        return EOF;
    }

    line[0][nch] = '\0';
    return nch;
}

