<!-- SPDX-FileCopyrightText: © Fossology contributors

     SPDX-License-Identifier: GPL-2.0-only
-->

# Importing OSADL compatibility matrix

1. Update all licenses and assign them correct License Type.
2. Install script dependencies
    ```shell
    python3 -m pip install -r utils/requirements.osadl.txt
    ```
3. Then run the script with following parameters. It will create a YAML file
  which contains all the rules from
  [OSADL compatibility matrix](https://www.osadl.org/Access-to-raw-data.oss-compliance-raw-data-access.0.html)
  and reduce the rule list size as much as possible with help of license
  grouping (for which it needs read access to DB).
    ```shell
    python3 utils/osadl_convertor.py [--user USER] --password PASSWORD [--database DATABASE] [--host HOST] [--port PORT] --yaml YAML.yml [-d]
    ```
4. Open the UI and import the generated YAML file.

