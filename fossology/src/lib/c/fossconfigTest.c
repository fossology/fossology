/* **************************************************************
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

************************************************************** */

#include <libfossology.h>
#include <stdio.h>
#include <errno.h>
#include <glib.h>

int main(int argc, char** argv)
{
  GError* error = NULL;
  char** groups;
  char** keys;
  gchar* temp;
  int i, ngrps;
  int j, nkeys;
  int k, nlist;
  fo_conf* config;

  config = fo_config_load(argv[1], &error);

  if(error)
  {
    fprintf(stderr, "ERROR: %s\n", error->message);
    fprintf(stderr, "ERROR: errno = %s\n", strerror(errno));
    return -1;
  }


  groups = fo_config_group_set(config, &ngrps);
  for(i = 0; i < ngrps; i++)
  {
    printf("[%s]\n", groups[i]);

    keys = fo_config_key_set(config, groups[i], &nkeys);
    for(j = 0; j < nkeys; j++)
    {
      if(fo_config_is_list(config, groups[i], keys[j], &error))
      {
        nlist = fo_config_list_length(config, groups[i], keys[j], &error);
        printf("  %s:\n", keys[j]);
        for(k = 0; k < nlist; k++)
        {
          printf("    [%d] = %s\n", k,
              (temp = fo_config_get_list(config, groups[i], keys[j], k, &error)));
          g_free(temp);
        }
      }
      else
      {
        printf("  %s = %s\n", keys[j],
            fo_config_get(config, groups[i], keys[j], &error));
      }
    }
  }

  fo_config_free(config);
  return 0;
}
