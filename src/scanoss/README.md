
<!-- SPDX-FileCopyrightText: © SCANOSS.COM

     SPDX-License-Identifier: GPL-2.0-only
-->
![scanoss.com](https://www.openchainproject.org/wp-content/uploads/sites/15/2021/10/scanoss.png)
# [SCANOSS](https://www.scanoss.com) Agent for FOSSOLOGY
This agent lets the user to search code snippets over more that 250M URLs.
For each snippet or full file, the agent can provide information about version, license and copyright from free osskb.org service or by an on-premise server for SCANOSS Platform.

## Usage
1. From Fossology main menu, select *Upload* > *From File*
2. Check the *SCANOSS Toolkit* option, on item 7 *Select optional analysis*
![Screenshot](pics/screenshot1.png)
3. Wait for the scan to be finished
4. Browse to the desired file and select the *info* tab
5. You will be able to visualize SCANOSS scan results under *SCANOSS Matching Info*
![Screenshot](pics/screenshot2.png)

The SCANOSS scan returns this information as a summary:
- Type of match (full file or snippet)
- Line ranges
- URL
- PURL
- Path inside the repository 

Each file is fingerprinted and sent to [osskb.org](https://www.osskb.org) server. **Only** fingerprints are sent, so your code keeps **CONFIDENTIAL**
Results are organized and placed on Fossology tables and SCANOSS Agent own tables.

## Configuration 

There are 2 options available for configuration (accesible via Admin > Customize > SCANOSS config):

- Scanoss API url: this field defines which endpoint you will be scanning against, by default is set to osskb.org (the free Open Source Software Knowledge Base distributed by the Software Transparency Foundation), but it can also be set to the SCANOSS Premium Service URL https://api.scanoss.com/scan/direct
- Access token: this field refers to the API Key related to the SCANOSS Premium Service (optional - not needed for osskb.org)

![Screenshot](pics/screenshot3.png)
