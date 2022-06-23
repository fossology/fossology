/*
 SPDX-FileCopyrightText: © 2007-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef _DMALLOC_H_
#define _DMALLOC_H_


#define free(p) DMfree(p,__FILE__,__LINE__)
#define malloc(s) DMmalloc(s,__FILE__,__LINE__)
#define calloc(s,i) DMcalloc(s,i,__FILE__,__LINE__)
#define memcheck(p) DMmemcheck(p,__FILE__,__LINE__)
#define realloc(p,s) DMrealloc(p,s,__FILE__,__LINE__)

extern int  DMverbose;
extern char *DMtriggeraddr;

#ifdef __cplusplus
extern "C" {
  extern int
  DMnotfreed(),
  DMtrigger(),
  DMfree(char *ptr, char *fname, int line);
  extern char
  *DMmemcheck(char *ptr, char *fname, int line),
  *DMmalloc(int size, char *fname, int line),
  *DMcalloc(int size, int nitems, char *fname, int line),
  *DMrealloc(char *ptr, int size, char *fname, int line);
}
#else /* C */

extern int
DMnotfreed(),
DMtrigger(),
DMfree();
extern char
*DMmemcheck(),
*DMmalloc(),
*DMcalloc(),
*DMrealloc();

#endif /* C */

#endif /* _DMALLOC_H_ */
