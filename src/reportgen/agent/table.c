/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini

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
 */

#include <stdarg.h>
#include <stdlib.h>
#include <mxml.h>
#include <glib.h>
#include "utils.h"

typedef struct {
  gchar** widths;
  gchar* stringWidth;
  mxml_node_t* node;
  int width;
} rg_table;

void table_free(rg_table* table)
{
  for (int j =0; j<table->width; j++)
    g_free(table->widths[j]);

  g_free(table->stringWidth);
  free(table->widths);
  free(table);
}

rg_table* table_new(mxml_node_t* parent, int width, ...)
{
  rg_table* result = malloc(sizeof(rg_table));
  gchar** widths = malloc(sizeof(gchar*) * width);

  result->width = width;

  int pixelWidth = 0;

  va_list args;
  va_start(args, width);

  for (int i=0; i<width; i++) {
    char* arg = va_arg(args, char*);
    pixelWidth += atoi(arg);
    widths[i] = g_strdup(arg);
  }
  va_end(args);

  result->stringWidth = g_strdup_printf("%d", pixelWidth);

  result->widths = widths;
  result->node = createtable(parent, result->stringWidth);

  createtablegrid(result->node, widths, width);

  return result;
}

void table_addRow(rg_table* table, ...)
{
  mxml_node_t* rowNode = createrowproperty(table->node);

  va_list args;
  va_start(args, table);

  for (int i=0; i<table->width; i++) {
    char* arg = va_arg(args, char*);
    createrowdata(rowNode, table->widths[i], arg);
  }
  va_end(args);
}