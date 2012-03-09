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

#ifndef INTERFACE_H_INCLUDE
#define INTERFACE_H_INCLUDE

#include <glib.h>

/* ************************************************************************** */
/* **** Constructor Destructor ********************************************** */
/* ************************************************************************** */

void interface_init();
void interface_destroy();

/* ************************************************************************** */
/* **** Access Functions **************************************************** */
/* ************************************************************************** */

void set_port(int port_n);
int  is_port_set();

GIOStream* connect_to(gchar* host, guint16 port, GError** in_error);
void send_email(gchar* to, gchar* from, gchar* subject, gchar* message,
    GError** error);

#endif /* INTERFACE_H_INCLUDE */
