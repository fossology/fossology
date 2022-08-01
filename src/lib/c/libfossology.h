/*
 SPDX-FileCopyrightText: © 2010-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
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
