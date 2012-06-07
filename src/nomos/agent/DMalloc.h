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
