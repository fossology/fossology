/*
 ununpack-disk.h - Ununpack-disk Header file

 SPDX-FileCopyrightText: © 2007 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
#ifndef UNPACK_DISK_H
#define UNPACK_DISK_H

int	 ExtractDisk(char *Source, char *FStype, char *Destination);
void FatDiskName(char *Name);


#endif
