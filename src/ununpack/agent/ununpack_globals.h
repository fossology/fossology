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
#ifndef UNUNPACK_GLOBALS_H
#define UNUNPACK_GLOBALS_H

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
char Version[]=SVN_REV;
#else
char Version[]="0.9.9";
#endif

int Verbose=0;
int Quiet=0;
int UnlinkSource=0;
int UnlinkAll=0;
int ForceContinue=0;
int ForceDuplicate=0;	/* when using db, should it process duplicates? */
int PruneFiles=0;
int SetContainerArtifact=1;	/* should initial container be an artifact? */
FILE *ListOutFile=NULL;
int ReunpackSwitch=0;

/* for the repository */
int UseRepository=0;
char REP_GOLD[16]="gold";
char REP_FILES[16]="files";

char UploadFileName[FILENAME_MAX];  /* upload file name */

/*** For DB queries ***/
char *Pfile = NULL;
char *Pfile_Pk = NULL; /* PK for *Pfile */
char *Upload_Pk = NULL; /* PK for upload table */
PGconn *pgConn = NULL; /* PGconn from DB */
int agent_pk=-1;	/* agent ID */
char SQL[MAXSQL];
magic_t MagicCookie = 0;

unpackqueue Queue[MAXCHILD+1];    /* manage children */
int MaxThread=1; /* value between 1 and MAXCHILD */
int Thread=0;

/*** Global Stats (for summaries) ***/
long TotalItems=0;	/* number of records inserted */
int TotalFiles=0;
int TotalCompressedFiles=0;
int TotalDirectories=0;
int TotalContainers=0;
int TotalArtifacts=0;

/***  Command table ***/
cmdlist CMD[] =
{
/* 0 */ { "","","","","",CMD_NULL,0,0177000,0177000, },
/* 1 */ { "application/x-gzip","zcat","","> '%s' 2>/dev/null","",CMD_PACK,1,0177000,0177000, },
/* 2 */ { "application/x-compress","zcat","","> '%s' 2>/dev/null","",CMD_PACK,1,0177000,0177000, },
/* 3 */ { "application/x-bzip","bzcat","","> '%s' 2>/dev/null","",CMD_PACK,1,0177000,0177000, },
/* 4 */ { "application/x-bzip2","bzcat","","> '%s' 2>/dev/null","",CMD_PACK,1,0177000,0177000, },
/* 5 */ { "application/x-upx","upx","-d -o'%s'",">/dev/null 2>&1","",CMD_PACK,1,0177000,0177000, },
/* 6 */ { "application/pdf","pdftotext","-htmlmeta","'%s' >/dev/null 2>&1","",CMD_PACK,1,0100000,0100000, },
/* 7 */ { "application/x-pdf","pdftotext","-htmlmeta","'%s' >/dev/null 2>&1","",CMD_PACK,1,0100000,0100000, },
/* 8 */ { "application/x-zip","unzip","-q -P none -o","-x / >/dev/null 2>&1","unzip -Zhzv '%s' > '%s'",CMD_ARC,1,0177000,0177000, },
/* 9 */ { "application/zip","unzip","-q -P none -o","-x / >/dev/null 2>&1","unzip -Zhzv '%s' > '%s'",CMD_ARC,1,0177000,0177000, },
/* 10 */{ "application/x-tar","tar","-xSf","2>&1 ; echo ''","",CMD_ARC,1,0177000,0177777, },
/* 11 */{ "application/x-gtar","tar","-xSf","2>&1 ; echo ''","",CMD_ARC,1,0177000,0177777, },
/* 12 */{ "application/x-cpio","cpio","--no-absolute-filenames -i -d <",">/dev/null 2>&1","",CMD_ARC,1,0177777,0177777, },
/* 13 */{ "application/x-rar","unrar","x -o+ -p-",">/dev/null 2>&1","",CMD_ARC,1,0177000,0177000, },
/* 14 */{ "application/x-cab","cabextract","",">/dev/null 2>&1","",CMD_ARC,1,0177000,0177000, },
/* 15 */{ "application/x-7z-compressed","7zr","x -y",">/dev/null 2>&1","",CMD_ARC,1,0177000,0177000, },
/* 16 */{ "application/x-7z-w-compressed","7z","x -y -pjunk",">/dev/null 2>&1","",CMD_ARC,1,0177000,0177000, },
/* 17 */{ "application/x-rpm","rpm2cpio","","> '%s' 2> /dev/null","rpm -qip '%s' > '%s' 2>&1",CMD_RPM,1,0177000,0177000, },
/* 18 */{ "application/x-archive","ar","x",">/dev/null 2>&1","",CMD_AR,1,0177000,0177777, },
/* 19 */{ "application/x-debian-package","ar","x",">/dev/null 2>&1","dpkg -I '%s' > '%s'",CMD_AR,1,0177000,0177777, },
/* 20 */{ "application/x-iso","","","","isoinfo -d -i '%s' > '%s'",CMD_ISO,1,0177777,0177777, },
/* 21 */{ "application/x-iso9660-image","","","","isoinfo -d -i '%s' > '%s'",CMD_ISO,1,0177777,0177777, },
/* 22 */{ "application/x-fat","fat","","","",CMD_DISK,1,0177700,0177777, },
/* 23 */{ "application/x-ntfs","ntfs","","","",CMD_DISK,1,0177700,0177777, },
/* 24 */{ "application/x-ext2","linux-ext","","","",CMD_DISK,1,0177777,0177777, },
/* 25 */{ "application/x-ext3","linux-ext","","","",CMD_DISK,1,0177777,0177777, },
/* 26 */{ "application/x-x86_boot","departition","","> /dev/null 2>&1","",CMD_PARTITION,1,0177000,0177000, },
/* 27 */{ "application/x-debian-source","dpkg-source","-x","'%s' >/dev/null 2>&1","",CMD_DEB,1,0177000,0177000, },
/* 28 */{ "","","",">/dev/null 2>&1","",CMD_DEFAULT,1,0177000,0177000, },
  { NULL,NULL,NULL,NULL,NULL,-1,-1,0177000,0177000, },
};
#endif
