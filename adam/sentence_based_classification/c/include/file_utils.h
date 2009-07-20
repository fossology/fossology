#include <stdio.h>
#include <stdlib.h>
#include <malloc.h>
#include <ctype.h>

#ifndef __FILE_UTILS_H__
#define __FILE_UTILS_H__

#if defined(__cplusplus)
extern "C" {
#endif

void openfile(char *filename, char **buffer);

#if defined(__cplusplus)
}
#endif

#endif
