#include <stdarg.h>
#include <stdlib.h>
#include <mxml.h>
#include <glib.h>
#include "utils.h"

typedef struct {
  GArray* cells;
  gchar** widths;
  gchar* stringWidth;
  mxml_node_t* node;
  int width;
} rg_table;

void table_free(rg_table* table);

rg_table* table_new(mxml_node_t* parent, int width, ...);

void table_addRow(rg_table* table, ...);
