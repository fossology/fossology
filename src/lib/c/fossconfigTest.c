/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Test for fossconfig
 *
 * The test loads all the config files passed, join them and print all the
 * groups and lists using the functions provided by the fossconfig library.
 */

#include <libfossology.h>
#include <stdio.h>
#include <errno.h>
#include <glib.h>

/**
 * \brief Main function for the test
 * \test
 * -# Load the files passed using fo_config_load()
 * -# Join all the config files using fo_config_join()
 * -# Print the group names using fo_config_group_set()
 * -# Print all the list elements in the group using fo_config_get_list() or
 * fo_config_get()
 */
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
  fo_conf* tmp;

  if(argc < 2)
  {
    fprintf(stderr, "Usage: %s ini1 ini2 ... iniN\n", argv[0]);
    return 255;
  }

  config = fo_config_load(argv[1], &error);

  if(error)
  {
    fprintf(stderr, "ERROR: %s\n", error->message);
    return 254;
  }

  for(i = 2; i < argc; i++)
  {
    tmp = fo_config_load(argv[i], &error);

    if(error)
    {
      fprintf(stderr, "ERROR: %s\n", error->message);
      return 254;
    }

    fo_config_join(config, tmp, &error);

    if(error)
    {
      fprintf(stderr, "ERROR: %s\n", error->message);
      return 253;
    }

    fo_config_free(tmp);
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
