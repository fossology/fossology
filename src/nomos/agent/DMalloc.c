/***************************************************************
 Copyright (C) 2007-2011 Hewlett-Packard Development Company, L.P.

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

 ***************************************************************/
/**
 * \file DMalloc.c
 *
 * \brief 
 * - Several global variables control actions taken by the memory check
 * routines.  These are provided also as a convenient interface to
 * run-time debuggers.
 *   - DMverbose (default = 1) \n
 *     If != 0, each memory allocation/free is printed
 *   - DMtriggeraddr (default = 0) \n
 *     If != 0, then any allocation/free operation with \n
 *     a pointer argument or return == DMtrigger causes \n
 *     a message to be printed.
 *   - DMtrigger() \n
 *     Function is called whenever a trigger occurs for \n
 *     setting breakpoints in a debugger.
 * .
 * - Possible combinations:
 *   - DMverbose = 0; DMtriggeraddr = 0x12345 \n
 *     Print messages only when 0x12345 is involved
 *   - DMverbose = 0; DMtriggeraddr = 0 \n
 *     Print messages only when an error occurs
 */
#include <stdio.h>
#include <stdlib.h>


/* GLOBALS */
int     DMverbose = 1;
char   *DMtriggeraddr = NULL;

#define TRIGGER(p) if( (p) == DMtriggeraddr ) DMtrigger();
#define GUARD 0x73
#define MC68000

#define TABSIZE (16*1024)
static char *__memtab[TABSIZE];

#define HDRSIZE (2 * sizeof (unsigned long))

#undef BRAINDEADABORT
#ifdef BRAINDEADABORT
static void abort(void *s)
{
  exit(6);
}
#endif

static malloced(char *ptr);
static freed(char *ptr, char *fname, int line);

/**
 * \brief Add guard word encoding size on start of memory area and a guard byte
 * just past the end of the area.
 *
 * \return pointer to user's area
 */
static char   *guardit(char *ptr, int size)
{
  unsigned long *lptr = (unsigned long *) ptr;

  /* add a guard on the beginning */
  lptr[0] = lptr[1] = size;

  /* and a guard byte on the end */
  ptr += HDRSIZE;
  *(ptr + size) = GUARD;

  return ptr;
}

/**
 * \brief Check the validity of allocated memory areas and report any
 * problems.  Called by DMmemcheck().
 */
static char   *memorycheck(char *ptr, char *fname, int line)
{
  unsigned long size, *lptr = (unsigned long *) ptr;

  /* check guard word on start */
  ptr -= HDRSIZE;
  lptr = (unsigned long *) ptr;
  if (lptr[0] != lptr[1]) {
    if (lptr[0] == (lptr[1] ^ 0x00ff)) {
      fprintf(stderr, "%s[%d]: memcheck(0x%x) already freed - exit\n",
          fname, line, ptr + HDRSIZE);
    } else {
      fprintf(stderr,
          "%s[%d]: memcheck(0x%x) start pointer corrupt - exit\n",
          fname, line, ptr + HDRSIZE);
    }
    abort();
  }
  size = lptr[0];
  if (*(ptr + HDRSIZE + size) != GUARD) {
    fprintf(stderr,
        "%s[%d]: memcheck(0x%x) end overwritten - exit\n",
        fname, line, ptr + HDRSIZE);
    abort();
  }
  return(ptr);
}


char   *DMmemcheck(char *ptr, char *fname, int line)
{
  int    i;

  if (ptr != NULL) {
    ptr = memorycheck(ptr, fname, line);
  }

  for (i = 0; i < TABSIZE; i++) {
    if (__memtab[i] != NULL) {
      memorycheck(__memtab[i], fname, line);
    }
  }
  return(ptr);
}

DMfree(char *ptr, char *fname, int line)
{
  unsigned long size;

  if (ptr == NULL)
    return;

  if (DMverbose || (ptr == DMtriggeraddr)) {
    size = ((unsigned long *)ptr)[-2];
    fprintf(stderr, "%s[%d]: free(0x%x) (%ld bytes)\n",
        fname, line, ptr, size);
    TRIGGER(ptr);
  }
  ptr = DMmemcheck(ptr, fname, line);

  /* Negate the last byte of the header guard to signify freed */
  ((unsigned long *)ptr)[1] ^= 0x00ff;

  /* all's well so free it */
  freed(ptr + HDRSIZE, fname, line);
  free(ptr);
}


char   *DMmalloc(int size, char *fname, int line)
{
  char   *ptr;

  DMmemcheck(NULL, fname, line);

  if ((ptr = (char *) malloc(size + HDRSIZE + 1)) == NULL) {
    fprintf(stderr, "%s[%d]: malloc(%d) OUT OF MEMORY\n", fname, line,
        size);
    abort();
  }

  ptr = guardit(ptr, size);


  if (DMverbose || (DMtriggeraddr == ptr)) {
    fprintf(stderr, "%s[%d]: malloc(%d) = 0x%x\n",
        fname, line, size, ptr);
    TRIGGER(ptr);
  }
  malloced(ptr);
  return(ptr);
}

char   *DMcalloc(int size, int nitems, char *fname, int line)
{
  char   *ptr;
  int     totalsize;
  int    i;
  char   *tempptr;

  DMmemcheck(NULL, fname, line);

  totalsize = size * nitems;
  if ((ptr = (char *) malloc(totalsize + HDRSIZE + 1)) == NULL) {
    fprintf(stderr, "%s[%d]: calloc(%d,%d) OUT OF MEMORY\n",
        fname, line, size, nitems);
    abort();
  }
  ptr = guardit(ptr, totalsize);

  /* initialize to zeros */
  tempptr = ptr;
  for (i = 0; i < totalsize; i++) {
    *tempptr++ = 0;
  }

  if (DMverbose || (ptr == DMtriggeraddr)) {
    fprintf(stderr, "%s[%d]: calloc(%d,%d) = 0x%x\n", fname, line,
        size, nitems, ptr);
    TRIGGER(ptr);
  }
  malloced(ptr);
  return(ptr);
}



/**
 * \brief record 'ptr's value in a list of malloc-ed memory
 */
static malloced(char *ptr)
            {
  int    i;

  for (i = 0; i < TABSIZE; i++) {
    if (__memtab[i] == NULL) {
      __memtab[i] = ptr;
      break;
    }
  }

  if (i >= TABSIZE) {
    /* table overflow */
    fprintf(stderr, "Memory table record overflow\n");
  }
            }


/**
 * \brief remove 'ptr's value from a list of malloc-ed memory - print
 * error and die if it's not in the list at all.
 */
static freed(char *ptr, char *fname, int line)
{
  int    i;

  for (i = 0; i < TABSIZE; i++) {
    if (__memtab[i] == ptr) {
      __memtab[i] = NULL;
      break;
    }
  }

  if (i >= TABSIZE) {
    /* not found */
    fprintf(stderr, "%s[%d]: freed(0x%x) NOT MALLOCED\n", fname, line,
        ptr);
    abort();
  }
}


char   *DMrealloc(char *ptr, int size, char *fname, int line)
{
  char   *saveptr;

  saveptr = ptr;
  ptr = DMmemcheck(ptr, fname, line);

  if ((ptr = (char *) realloc(ptr, size + HDRSIZE + 1)) == NULL) {
    fprintf(stderr, "%s[%d]: realloc(0x%x,%d) OUT OF MEMORY\n",
        fname, line,
        saveptr,
        size);
    abort();
  }
  ptr = guardit(ptr, size);
  if (DMverbose || (DMtriggeraddr == ptr) || (DMtriggeraddr == saveptr)) {
    fprintf(stderr, "%s[%d]: realloc(0x%x,%d) = 0x%x\n",
        fname, line, saveptr, size, ptr);
    TRIGGER(saveptr);
    TRIGGER(ptr);
  }
  freed(saveptr, fname, line);
  malloced(ptr);
  return(ptr);
}


/**
 * \brief Print a list of memory pointers not freed - one per line
 */
DMnotfreed()
{
  int i;

  for (i = 0; i < TABSIZE; i++) {
    if (__memtab[i] != NULL) {
      printf("0x%x\n", __memtab[i]);
    }
  }
}

/**
 * \brief Dummy routine with the sole purpose of being available for setting
 * breakpoints from a debugger.
 */
DMtrigger()
{
  int i;
  i++;
}

