/* **************************************************************
Copyright (C) 2010-2013 Hewlett-Packard Development Company, L.P.

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

************************************************************** */
/**
 * \dir
 * \brief Contains FOSSology C library
 * \file
 * \brief The main FOSSology C library
 * \page libc FOSSology C library
 * \tableofcontents
 *
 * \section libcabout About
 * This is library contains common utility functions for FOSSology agents
 * written in C language.
 * \section libcsource Library source
 * - \link src/lib/c \endlink
 */
#ifndef LIBFOSSOLOGY_H
#define LIBFOSSOLOGY_H

#include <stdio.h>
#include "libfossscheduler.h"
#include "libfossrepo.h"
#include "libfossdb.h"
#include "libfossagent.h"
#include "sqlCopy.h"
#include "fossconfig.h"

#define PERM_NONE 0         ///< User has no permission (not logged in)
#define PERM_READ 1         ///< Read-only permission
#define PERM_WRITE 3        ///< Read-Write permission
#define PERM_ADMIN 10       ///< Administrator

#define PLUGIN_DB_NONE 0    ///< Plugin requires no DB permission
#define PLUGIN_DB_READ 1    ///< Plugin requires read permission on DB
#define PLUGIN_DB_WRITE 3   ///< Plugin requires write permission on DB
#define PLUGIN_DB_ADMIN 10  ///< Plugin requires admin level permission on DB

#endif /* LIBFOSSOLOGY_H */
