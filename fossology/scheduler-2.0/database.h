/* **************************************************************
Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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

#ifndef DATABASE_H_INCLUDE
#define DATABASE_H_INCLUDE

/* postgresql library */
#include <libpq-fe.h>

/* ************************************************************************** */
/* **** constructor destructor ********************************************** */
/* ************************************************************************** */

void database_init();
void database_destroy();

/* ************************************************************************** */
/* **** event and functions ************************************************* */
/* ************************************************************************** */

void database_reset_queue();
void database_update_event(void* unused);
PGresult* database_exec(char* sql);


#endif /* DATABASE_H_INCLUDE */
