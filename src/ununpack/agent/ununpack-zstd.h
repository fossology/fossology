/*
 Ununpack-sztd.h: Headers for unpacking zstd compressed files

 SPDX-FileCopyrightText: © 2023 Siemens AG
 SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
#ifndef UNPACK_ZSTD_H
#define UNPACK_ZSTD_H

int     ExtractZstd     (char *Source, const char *OrigName, char *Destination);

#endif
