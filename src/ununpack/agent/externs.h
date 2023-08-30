/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Stores all extern variables used by the agent
 */
#ifndef EXTERNS_H
#define EXTERNS_H
extern cmdlist CMD[];           ///< Global command table
extern int Verbose;             ///< Global verbose level
extern int Quiet;               ///< Run in quiet mode?
extern int UnlinkSource;        ///< Remove recursive sources after unpacking?
extern int UnlinkAll;           ///< Remove ALL unpacked files when done (clean up)?
extern int ForceContinue;       ///< Force continue when unpack tool fails?
extern int ForceDuplicate;      ///< When using db, should it process duplicates?
extern int IgnoreSCMData;       ///< 1: Ignore SCM data, 0: dont ignore it.
extern int PruneFiles;          ///< Remove links? >1 hard links, zero files, etc
extern int SetContainerArtifact;  ///< Should initial container be an artifact?
extern FILE *ListOutFile;       ///< File to store unpack list
extern int ReunpackSwitch;      ///< Set if the uploadtree records are missing from db

/* for the repository */
extern int UseRepository;       ///< Using files from the repository?
extern char REP_GOLD[16];       ///< Gold repository name
extern char REP_FILES[16];      ///< Files repository name

extern char UploadFileName[FILENAME_MAX]; ///< Upload file name

/*** For DB queries ***/
extern char *Pfile;             ///< Pfile name (SHA1.MD5.Size)
extern char *Pfile_Pk;          ///< Pfile pk in DB
extern char *Upload_Pk;         ///< Upload pk in DB
extern PGconn *pgConn;          ///< DB connection
extern int agent_pk;            ///< Agent pk in DB
extern char SQL[MAXSQL];        ///< SQL query to execute
extern char uploadtree_tablename[19]; ///< upload.uploadtree_tablename
extern magic_t MagicCookie;     ///< Magic Cookie

extern unpackqueue Queue[MAXCHILD+1]; ///< Manage children
extern int MaxThread;           ///< Value between 1 and MAXCHILD
extern int Thread;              ///< Number of threads in execution

/*** Global Stats (for summaries) ***/
extern long TotalItems;         ///< Number of records inserted
extern int TotalFiles;          ///< Number of regular files
extern int TotalCompressedFiles;  ///< Number of compressed files
extern int TotalDirectories;    ///< Number of directories
extern int TotalContainers;     ///< Number of containers
extern int TotalArtifacts;      ///< Number of artifacts
#endif

