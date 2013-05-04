/*******************************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
 
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
 *******************************************************************/
#ifndef EXTERNS_H
#define EXTERNS_H
extern cmdlist CMD[];
extern int Verbose;
extern int Quiet;
extern int UnlinkSource;
extern int UnlinkAll;
extern int ForceContinue;
extern int ForceDuplicate; /* when using db, should it process duplicates? */
extern int PruneFiles;
extern int SetContainerArtifact; /* should initial container be an artifact? */
extern FILE *ListOutFile;
extern int ReunpackSwitch;

/* for the repository */
extern int UseRepository;
extern char REP_GOLD[16];
extern char REP_FILES[16];

extern char UploadFileName[FILENAME_MAX];  /* upload file name */

/*** For DB queries ***/
extern char *Pfile;
extern char *Pfile_Pk;
extern char *Upload_Pk;
extern PGconn *pgConn;
extern int agent_pk;
extern char SQL[MAXSQL];
extern char uploadtree_tablename[19];  /* upload.uploadtree_tablename */
extern magic_t MagicCookie;

extern unpackqueue Queue[MAXCHILD+1];    /* manage children */
extern int MaxThread; /* value between 1 and MAXCHILD */
extern int Thread;

/*** Global Stats (for summaries) ***/
extern long TotalItems;  /* number of records inserted */
extern int TotalFiles;
extern int TotalCompressedFiles;
extern int TotalDirectories;
extern int TotalContainers;
extern int TotalArtifacts;
#endif

