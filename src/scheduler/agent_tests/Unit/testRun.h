/*********************************************************************
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
*********************************************************************/

#pragma once

#include <libfocunit.h>

#include <glib.h>

/* used to disable logging */
extern FILE* log_file;

/* suite init and clean */
int init_suite(void);
int clean_suite(void);

/* test case sets */
extern CU_TestInfo tests_agent[];
extern CU_TestInfo tests_host[];
extern CU_TestInfo tests_event[];
