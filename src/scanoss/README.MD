
<!-- SPDX-FileCopyrightText: © SCANOSS.COM

     SPDX-License-Identifier: GPL-2.0-only
-->
![scanoss.com](https://www.openchainproject.org/wp-content/uploads/sites/15/2021/10/scanoss.png)
# [SCANOSS](www.scanoss.com) Agent for FOSSOLOGY
This agent lets the user to search code snippets over more that 250M URLs.
For each snippet or full file, the agent can provide information about version, license and copyrigth from free osskb.org service or by an on premisse server for SCANOSS Platform.

## Usage
1. From Fossology main menu, select upload a file
2. Check *SCANOSS Toolkit* option
3. Wait for the scan to be finished

Each file is fingerprinted and sent to [osskb.org](osskb.org) server. **Only** fingerprints are sent, so your code keeps **CONFIDENTIAL**
Results are organized and placed on Fossology tables and SCANOSS Agent own tables.

To view File information, just click on *info* tab. You will be presented with:
- Type of match (full file or snippet)
- Line ranges
- Matching file and path inside the repository
- PURL
- URL

