/*
 SPDX-FileCopyrightText: © 2006-2009 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

#ifndef _UTIL_H
#define _UTIL_H

#include <stdio.h>
#include <sys/stat.h>
int isDIR(char *dpath);
int isEMPTYDIR(char *dpath);
int isEMPTYFILE(char *fpath);
int isBLOCK(char *bpath);
int isCHAR(char *cpath);
int isPIPE(char *ppath);
int isSYMLINK(char *spath);
int isINODE(char *ipath, int typ);
char *newReloTarget(char *basename);
char *pluralName(char *s, int count);

#ifdef	MEMORY_TRACING
char *memAllocTagged(int size, char *name);
void memFreeTagged(void *ptr, char *note);
void memCacheDump(char *s);
#endif	/* MEMORY_TRACING */

char *findBol(char *s, char *upperLimit);
char *findEol(char *s);
void changeDir(char *pathname);
void renameInode(char *oldpath, char *newpath);
void unlinkFile(char *pathname);
void chmodInode(char *pathname, int mode);
FILE *fopenFile(char *pathname, char *mode);
FILE *popenProc(char *command, char *mode);
char *wordCount(char *textp);
char *copyString(char *s, char *label);
char *pathBasename(char *path);
char *getInstances(char *textp, int size, int nBefore, int nAfter, char *regex, int recordOffsets);
char *curDate();

#ifdef	MEMSTATS
void memStats(char *s);
#endif	/* MEMSTATS */

void makeSymlink(char *path);

//void freeAndClearScan(struct curScan *thisScan);
void printRegexMatch(int n, int cached);
char *mmapFile(char *pathname);
void mmapOpenListing();
void munmapFile(void *ptr);
int bufferLineCount(char *p, int len);
void appendFile(char *pathname, char *str);
int nftwFileFilter(char *pathname, struct stat *st, int onlySingleLink);
void makePath(char *dirpath);
void makeDir(char *dirpath);
void removeDir(char *dir);
int mySystem(const char *fmt, ...);
int iMadeThis(char *textp);
int isFILE(char *pathname);
int addEntry(char *pathname, int forceFlag, const char *fmt, ...);
void Msg(const char *fmt, ...);
void Log(const char *fmt, ...);
void MsgLog(const char *fmt, ...);
void Note(const char *fmt, ...);
void Warn(const char *fmt, ...);
void Assert(int fatalFlag, const char *fmt, ...);
void Error(const char *fmt, ...);
void Fatal(const char *fmt, ...);
void traceFunc(char *fmtStr, ...);

#endif /* _UTIL_H */
