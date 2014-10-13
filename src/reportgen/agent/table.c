
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

void table_free(rg_table* table)
{
  for (int i =0; i<table->cells->len; i++)
  {
    gchar** row = g_array_index(table->cells, gchar**, i);
    for (int j =0; j<table->width; j++)
    {
      g_free(row[j]);
    }
  }
  g_array_free(table->cells, TRUE);

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
  result->cells = g_array_new(FALSE, FALSE, sizeof(gchar**));

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
  gchar** row = malloc(sizeof(gchar*) * table->width);

  mxml_node_t* rowNode = createrowproperty(table->node);

  va_list args;
  va_start(args, table);

  for (int i=0; i<table->width; i++) {
    char* arg = va_arg(args, char*);
    row[i] = g_strdup(arg);
    createrowdata(rowNode, table->widths[i], row[i]);
  }
  va_end(args);

  g_array_append_val(table->cells, row);
}