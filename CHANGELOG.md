<!-- SPDX-FileCopyrightText: © Fossology contributors

     SPDX-License-Identifier: GPL-2.0-only
-->
# Changelog of FOSSology

### 4.4.0 (Jan 12th 2024)

This release adds important corrections to
[4.4.0-rc2](https://github.com/fossology/fossology/releases/tag/4.4.0-rc2)

The release 4.4.0 introduces a number of corrections to
[4.3.0](https://github.com/fossology/fossology/releases/tag/4.3.0)
and major changes to FOSSology, including:

* Major changes from GSoC contributors:
  * During GSoC 2023, FOSSology saw a major influx in REST API endpoints. Now
    there are endpoints for almost all information available on UI.
  * During same operations, we also created the framework changes to allow 2
    versions of REST API (v1 & v2). This will allow us to unify the REST API in
    future while still supporting v1.
  * Another big change was creation of new agent to generate
    [CycloneDX](https://cyclonedx.org) reports.
  * We also changed the integration mechanism with
    [ScanCode](https://scancode-toolkit.readthedocs.io) resulting in major
    speed improvements in the scan.
* With this release, we also bring support for Debian Bookworm (12)
* Support extraction of [Zstandard](https://www.zstd.net) files
* Support GitHub Actions in the scanner image and generate SPDX reports
* Multiple fixes in SPDX reports
* Sync with SPDX License list v3.22

#### Credits to contributors for 4.4.0

From the GIT commit history, we have the following contributors since
[4.3.0](https://github.com/fossology/fossology/releases/tag/4.3.0):

```
> Abdelrahman Jamal <abdelrahmanjamal5565@gmail.com>
> Devesh Negi
> Divij Sharma <divijs75@gmail.com>
> dushimsam <dushsam@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Hero2323 <abdelrahmanjamal5565@gmail.com>
> Igor Mishchuk <igor.mishchuk@carbonhealth.com>
> Kamal Nayan
> Kgitman
> lata <imlata1111@gmail.com>
> Marc-Etienne Vargenau <marc-etienne.vargenau@nokia.com>
> mayank-pathakk <mayank234pathak@gmail.com>
> Nejc Habjan <nejc.habjan@siemens.com>
> Richard Diederen <richard.diederen@ict.nl>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Simran Nigam <nigamsimran14@gmail.com>
> soham4abc <sohambanerjee4abc@hotmail.com>
> srideep-banerjee <banerjee.srideep@gmail.com>
> Sushant Kumar <sushantmishra02102002@gmail.com>
```

#### Corrections

* `5a70fbddf` fix(api): read optional agentId, UploadController
* `24b0e1a67` fix(postinstall): check status of a2ensite

### 4.4.0-rc2 (Jan 8th 2024)

This release adds important corrections to
[4.4.0-rc1](https://github.com/fossology/fossology/releases/tag/4.4.0-rc1)

The release 4.4.0-rc2 introduces a few corrections to
[4.4.0-rc1](https://github.com/fossology/fossology/releases/tag/4.4.0-rc1)
and changes to FOSSology, including:

* fix token generation for user.
* fix dependencies for bookworm.
* check if ScanOSS is installed.

#### Credits to contributors for 4.4.0-rc2

From the GIT commit history, we have the following contributors since
[4.4.0-rc1](https://github.com/fossology/fossology/releases/tag/4.4.0-rc1):

```
> Devesh Negi <@DEVESH-N2>
> Divij Sharma <divijs75@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Kgitman <@Kgitman>
> Richard Diederen <richard.diederen@ict.nl>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>

```

#### Features

* `9e9085b1e` feat(api): make cyclonedx report available via the API

#### Corrections

* `3f2bda48d` fix(api): do not check page for empty response
* `c9b396dc0` fix(view): check if ScanOSS is installed
* `e712da2c6` fix(token): fix token generation for user
* `ad5636fdb` fix(action): Ensure proper handeling of enum values in argparse
* `bef8ca024` fix(licenseRef): make dataype consistent
* `01c073c89` fix(php): Fix null pointer issue in createClearingDecisions() (#2658)
* `dff597d00` fix(deb): fix dependencies for bookworm
* `6761de11d` style(php): Corrected the SQL syntax error in AllDecisionsDao.php

#### Infrastructure

* `9028e7dc8` chore(notice): update both notice and notice.spdx files to latest
* `23be4848c` chore(notice): update third party notices 4.4.0

### 4.4.0-rc1 (Nov 21st 2023)

This release adds important corrections to
[4.3.0](https://github.com/fossology/fossology/releases/tag/4.3.0)

The release 4.4.0-rc1 introduces a number of corrections to
[4.3.0](https://github.com/fossology/fossology/releases/tag/4.3.0)
and major changes to FOSSology, including:

* Major changes from GSoC contributors:
  * During GSoC 2023, FOSSology saw a major influx in REST API endpoints. Now
    there are endpoints for almost all information available on UI.
  * During same operations, we also created the framework changes to allow 2
    versions of REST API (v1 & v2). This will allow us to unify the REST API in
    future while still supporting v1.
  * Another big change was creation of new agent to generate
    [CycloneDX](https://cyclonedx.org) reports.
  * We also changed the integration mechanism with
    [ScanCode](https://scancode-toolkit.readthedocs.io) resulting in major
    speed improvements in the scan.
* With this release, we also bring support for Debian Bookworm (12)
* Support extraction of [Zstandard](https://www.zstd.net) files
* Support GitHub Actions in the scanner image and generate SPDX reports
* Multiple fixes in SPDX reports
* Sync with SPDX License list v3.22

#### Credits to contributors for 4.4.0-rc1

From the GIT commit history, we have the following contributors since
[4.3.0](https://github.com/fossology/fossology/releases/tag/4.3.0):

```
> dushimsam <dushsam@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Hero2323 <abdelrahmanjamal5565@gmail.com>
> Igor Mishchuk <igor.mishchuk@carbonhealth.com>
> Kamal Nayan @legendarykamal
> lata <imlata1111@gmail.com>
> Marc-Etienne Vargenau <marc-etienne.vargenau@nokia.com>
> mayank-pathakk <mayank234pathak@gmail.com>
> Nejc Habjan <nejc.habjan@siemens.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Simran Nigam <nigamsimran14@gmail.com>
> soham4abc <sohambanerjee4abc@hotmail.com>
> srideep-banerjee <banerjee.srideep@gmail.com>
> Sushant Kumar <sushantmishra02102002@gmail.com>
```

#### Features

* `7ed5f9ad9` feat(licenseRef): add new licenses from SPDX
* `572fdaeda` feat(menu): add new button to indicate system load in banner
* `f154bfd53` feat(upload): track assignee and closing events
* `33a581909` feat(bulk): checkbox to select scan for findings only
* `16f8cffce` feat(conf): support CLIXML conf for a upload
* `2f16eef42` feat(os): add support for Debian Bookworm (12)
* `8c28b2f72` feat(api): migrate `/tokens` endpoints to v2
* `16331926b` feat(dashboard): add new page for upload and folder dashboard
* `dc47e29b1` feat(unifiedreport): support json format for rows and also html break
* `bc1cc0d24` feat(api): add 'topitem' endpoint to Upload API
* `c217a3991` feat(schedule agent): add select2 to search for uploads with name
* `88d04ec6d` feat(api): unify cx endpoints
* `4e3e0bfc5` feat(api): added author API endpoints
* `deeb79e20` feat(api): Export Obligation list as CSV
* `54f4859e0` feat(api): delete obligation based on id
* `697960066` feat(api): Import obligation list from CSV
* `a5f29c38c` feat(api): get all obligations details
* `8d8573ff8` feat(api): get details of a particular obligation using id
* `1630e79d0` feat(api): export single license as CSV
* `94d874dcb` feat(api): The REST API to export licenses-list as CSV
* `e0220f921` feat(api): api to get the list of obligations
* `7fb494c4c` feat(api): Get all contents of a specific folder
* `be9d9cba6` feat(api): get Banner message
* `bfff708bc` feat(api): Unlink folder contents
* `524090aed` feat(api): Get removable folder contents
* `4e213d350` feat(api): update conf data endpoint implemented
* `d9223a635` feat(api): update customise endpoint
* `1d6898920` feat(api): Get Customise page data
* `ce3b009e2` feat(api): Run scheduler based on the given operation's option
* `3a48ab052` feat(api): Get scheduler options for a given operation
* `8c45508e6` feat(api): Get active queries for Dashboard overview
* `9af03d271` feat(api): Get database metrics overview for dashboard
* `5da361699` feat(api): Suggest license from reference text
* `f591d2a28` feat(api): Get all server jobs for Admin Dashboard
* `f878673df` feat(api): Get PHP-Info for the Dasbhoard Overview
* `e19384ef3` feat(api): Get disk space usage overview
* `1234f19e0` feat(api): Get the database contents for the overview of Foss. operations
* `e607bcc45` feat(api): Merge a license into an existing one
* `7e7e0ebfa` feat(api): verify license as new or variant
* `7404e6b37` feat(ununpack): support for Zstandard
* `7e51fed2c` feat(api): Add, Edit, toggle standard-license comment
* `34af20910` feat(api): Get the summary statistics for all Jobs
* `f55fed9ac` feat(SETUP-V2): Support Multiple Versions (V1 & V2)
* `a95d77459` feat(api): get-all standard comments
* `eccede06d` feat(api): REST API to schedule the bulk-scan
* `01f73826a` feat(api): Get Customise page data
* `cbee2ee97` feat(api): Add, edit & delete license decision
* `0dd1c3e89` feat(api): Add, Edit & toggle admin license acknowledgement
* `89e7748ae` feat(api): get all agents revisions
* `e82a0cf8a` feat(api): conf info for upload
* `c755ff564` feat(api): get a list of scanned licenses for an upload
* `0bde97682` feat(CycloneDX): Add new agent cyclonedx
* `7e181f87d` feat(api): Get licenses reuse summary API
* `309dd70d5` feat(api): get list of license decisions for an item
* `13d79e23e` feat(api): File info API implemented
* `e4085b07e` feat(api): get edited licenses list
* `bf5a8c569` feat(api): Get all licenses-admin acknowlegments
* `33f75c7f5` feat(api): get the license tree-view of the upload and item
* `e2370c786` feat(nomos): add more regex to nomos to identify different licenses
* `ac1897635` feat(api): Update upload-summary API for additional info
* `08fba9484` feat(api): get all agents for the upload
* `707094c31` feat(api): API to return total number of copyrights for a file
* `87e756c63` feat(api): get licenses histogram
* `670a37de4` feat(api): Get the clearing-progress info for an upload.
* `76d75929b` feat(api): restore deleted copyrights
* `3bb66039b` feat(api): REST API to get keywords and hightlight-entries from content
* `c0e6c8b00` feat(api): Get clearing-history data API
* `ea3adb358` feat(api): Get list of bulk-history API
* `e6b086f8d` feat(api): handle three filters to get prev & next item
* `779e2331a` feat(scanner): generate spdx report
* `ee2b2f703` feat(api): update file copyright api added
* `bec422b64` feat(api): delete copyright
* `22b4c594c` feat(api): copyright info for file
* `b3351361c` feat(scanner): support github in scanner
* `b8a3590f3` feat(api): Remove a particular main license from an upload
* `c36b317cf` feat(api): add the new main license for the upload
* `f34019cae` feat(api): set the clearing decision for a particular item
* `9207e349a` feat(api): content negotiation on /openapi
* `55b06cc2c` feat(api): get the contents of the file
* `5bcb4f5a6` feat(api): openapi.yaml exposed through api
* `3f701df9b` feat(api): add pagination to license browser
* `f4a578b87` feat(api): get main licenses assigned on an upload
* `992c0b2d1` feat(delete-job-endpoint): Added a delete job endpoint to the Fossology API

#### Corrections

* `a943cb4ad` fix(spdx2): avoid license text duplication in rdf
* `145318a5f` fix(spdx2): accept null values for arrays
* `19041f0d9` fix(unifiedreport): replace double quotes with single to fix line breaks
* `ccad99efa` fix(documentation): update README.md with PHP version
* `099fe015c` fix(ci): fix build in Debian Buster
* `6373c574c` fix(api): default values of page and limit
* `4160f35db` fix(user-edit): handle HttpForbiddenException
* `249207f8b` fix(user-edit): compare old email and skip email count check
* `e979e2782` fix(db): change agent_rev to text
* `9af3fcf9c` fix(php): replace array_push by assignment
* `ba6506619` fix(php): add missing semicolon
* `2915b7534` fix(php): remove & to be compatible with PHP 8
* `a882ff932` fix(php): Factor common code
* `df39a6744` fix(cylonedx): update for changes in SPDX
* `e01006b21` fix(spdx): de-duplicate licenses with same SPDX ID
* `8682ab5c5` fix(php): replace deprecated split by explode
* `d871f83d6` fix(php): Using ${var} in strings is deprecated
* `f3b2e0a8b` fix(php) Optional parameter declared before required parameter
* `7370c2bd6` fix(PSR-12): closing ?> tag MUST be omitted
* `c10906db7` fix(test): fix REST API testcases
* `226d38e0d` fix(api): move obligation removal code for rest
* `0eb928490` fix(api): use ObligationMap instead of Model class
* `7e630262f` fix(api): extend obligation model don't create new
* `1e9ef3739` fix(api): use ObligationMap instead of modifying UI
* `8d6ab4550` fix(dao): use DbManager in SysConfigDao
* `cdc011348` fix(api): fix ConfController to accept diff values
* `9ba7b468a` fix(api): fix sysconfig controller and dao
* `b7b611ecd` fix(api): fix lint error and use UTC where possible
* `ea4d682fe` fix(test): fix wrong test according to comment
* `92e1eb44b` fix(cd): Fix release workflow for version
* `7f7a5c362` fix(delagent): Use bcrypt to check password
* `a49f6c8d6` fix(clixml): fix deb package name
* `879e205bf` fix(api): fix linter issues
* `70aca2a63` fix(automation): update copyright
* `fb3a5600a` fix(eyeButtonForPasswords): removed external css usage
* `6bc1ddd05` fix(clixml.php): Fixed the issue of PhP 8 Warning

#### Infrastructure

* `05bf86a9b` deps(composer): update composer/spdx-licenses
* `c356f1b38` chore(lib): refactor code
* `ebeeadbdb` chore(ununpack): drop upx support
* `ce8a51553` test(nomos): add new test files
* `82f169228` chore(ci): tag scanner image on release
* `5a4b9b1ff` refactor(api): introduce error handling
* `8ee16e820` chore(api)!: update minor version; breaking change
* `f143d709d` chore(api): update API version 20231006
* `8d44d989b` chore(api): move obligation endpoints from license
* `7e20bd677` perf(scancode):Improve scanning speed of scancode agent
* `5764e24c7` perf(api): performance optimization for FolderController
* `1b16ed786` chore(dao): update FolderDao::removeContent to return bool
* `1fffdd41b` chore(api): rename endpoint /unlinkable to be unambiguous
* `b3df71d23` chore(api): update API version 20230929
* `0b964c3e1` perf(api): refactor /license/suggest for optimization
* `91a06f86a` doc(php): fix parameter docs
* `3850bde2e` doc(php): fix parameter docs
* `3065d249c` chore(gitpod): update gitpod scripts
* `9847f6ef4` chore(UploadTreeProxy): optimize license file query
* `3251d494a` chore(pythondeps): preserve proxy env with su
* `aa7e5b4e8` chore(api): update API version 20230811
* `b65cb4946` refactor(browse): use join to fetch data from two tables
* `d11e08ec8` chore(api): update version 20230728
* `880ed0114` chore(api): update api version
* `a5afabdc3` refactor(copyright): Refactored some redundant code. Resolved declutter turning text to lowercase. Renamed some variables to be more informative.
* `4d347d911` chore(css): change look for eye button
* `33f123f5e` docs(README.md): adding more details about `docker-compose` cmd

### 4.3.0 (Jun 22nd 2023)

This release adds important corrections to
[4.3.0-rc2](https://github.com/fossology/fossology/releases/tag/4.3.0-rc2)

The release 4.3.0 introduces a number of corrections to
[4.2.1](https://github.com/fossology/fossology/releases/tag/4.2.1)
and major changes to FOSSology, including:

* Integration with [ScanOSS](https://scanoss.com/)
* Add new field SPDX ID for licenses, making FOSSology reports more SPDX
  compliant.
  * Same time, fix SPDX reports and update to v2.3
  * Rename deprecated licenses like GPL-2.0+
* Update build system to CMake from GNU Make.
* New option to export and import FOSSology decisions.
* Several security fixes.
* New list to define predefined acknowledgements for easy reuse.
* Consider folder level and package level bulk.
* Drop Ubuntu Bionic support.

#### Credits to contributors for 4.3.0

From the GIT commit history, we have the following contributors since
[4.2.1](https://github.com/fossology/fossology/releases/tag/4.2.1):

```
> Avinal Kumar <avinal.xlvii@gmail.com>
> dushimsam <dushsam@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> hero2323 <abdelrahmanjamal5565@gmail.com>
> Krishna Mahato <krishhtrishh9304@gmail.com>
> mayank-pathakk <mayank234pathak@gmail.com>
> Sanjay Krishna S R <sanjaykrishna1203@gmail.com>
> scanoss-qg <quique.goni@scanoss.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Simran Nigam <nigamsimran14@gmail.com>
> soham4abc <sohambanerjee4abc@hotmail.com>
> srideep-banerjee <banerjee.srideep@gmail.com>
> Toussaint Nicolas <nicolas1.toussaint@orange.com>
```

#### Features

* `83191c8e9` feat(thirdpartyLicenses): update third notices

#### Corrections

* `753fbbbc9` fix(scanoss): check json-c version for buster

### 4.3.0-rc2 (Jun 13th 2023)

This release adds important corrections to
[4.3.0-rc1](https://github.com/fossology/fossology/releases/tag/4.3.0-rc1)

The release 4.3.0-rc2 introduces following major corrections to
[4.3.0-rc1](https://github.com/fossology/fossology/releases/tag/4.3.0-rc1):

* Consider folder level and package level bulk.
* Drop Ubuntu Bionic support.
* Replace two single quotes to one in escaped string.

#### Credits to contributors for 4.3.0-rc2

From the GIT commit history, we have the following contributors since
[4.3.0-rc1](https://github.com/fossology/fossology/releases/tag/4.3.0-rc1):

```
> Gaurav Mishra <mishra.gaurav@siemens.com>
> hero2323 <abdelrahmanjamal5565@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
```

#### Corrections

* `c9abbe0c7` fix(user-edit.php): Fixed editing emails allows for duplicate emails for multiple users.
* `c9fb01d93` fix(user-add.php): Fixed email can be blank but required.
* `a3f7d469a` fix(bulkReuse): consider folder level and package level bulk
* `3a782ceb0` fix(composer.json.in): update slim/psr7 in .in file
* `97ef64c67` fix(warnings): fix unified report warnings
* `0d175334d` fix(conf): replace two single quotes to one in escaped string
* `a3a022c6b` fix(cd): fix release build action

#### Infrastructure

* `c50da8045` chore(scanoss): remove jq
* `6c393d4e1` chore(composer): update min php to 7.3.31
* `2cc1b4249` chore(os): drop Ubuntu Bionic support
* `b8fbcb4e9` chore(deps): bump slim/psr7 from 1.4 to 1.4.1 in /src

### 4.3.0-rc1 (May 9th 2023)

This release adds important corrections to
[4.2.1](https://github.com/fossology/fossology/releases/tag/4.2.1)

The release 4.3.0-rc1 introduces a number of corrections to
[4.2.1](https://github.com/fossology/fossology/releases/tag/4.2.1)
and major changes to FOSSology, including:

* Integration with [ScanOSS](https://scanoss.com/)
* Add new field SPDX ID for licenses, making FOSSology reports more SPDX
  compliant.
  * Same time, fix SPDX reports and update to v2.3
  * Rename deprecated licenses like GPL-2.0+
* Update build system to CMake from GNU Make.
* New option to export and import FOSSology decisions.
* Several security fixes.
* New list to define predefined acknowledgements for easy reuse.

#### Credits to contributors for 4.3.0-rc1

From the GIT commit history, we have the following contributors since
[4.2.1](https://github.com/fossology/fossology/releases/tag/4.2.1):

```
> Avinal Kumar <avinal.xlvii@gmail.com>
> dushimsam <dushsam@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Krishna Mahato <krishhtrishh9304@gmail.com>
> mayank-pathakk <mayank234pathak@gmail.com>
> Sanjay Krishna S R <sanjaykrishna1203@gmail.com>
> scanoss-qg <quique.goni@scanoss.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Simran Nigam <nigamsimran14@gmail.com>
> soham4abc <sohambanerjee4abc@hotmail.com>
> srideep-banerjee <banerjee.srideep@gmail.com>
> Toussaint Nicolas <nicolas1.toussaint@orange.com>
```

#### Features

* `e826f5141` feat(docker): update images to Debian 11 (bullseye)
* `081bc812c` feat(clixml): introduce LinkScanTool
* `1faf25a60` feat(licenseCsv): export spdx id in license CSV
* `d62e603d9` feat(deleteFileFromBrowse): Ability to delete file from browse page
* `bda57059b` feat(viewPasswordInLogin): Eye button to view Password while logging in
* `0576ef943` feat(scanoss-agent): Initial version of SCANOSS agent for FOSSology
* `987b2774a` feat(API): POST report/import route for initiating a report import job
* `de006de77` feat(decision-dump): export-import IPRA data
* `485bb8856` feat(invertSearch):Added inverse search in Email/Url/Author Page
* `e40e7ae37` feat(API): /jobs/{id}/history GET route to get the history of all the jobs queued based on an upload
* `fec0e60da` feat(highlightRows):Highlighted deleted rows on copyright/URL/Author/Email tables
* `644879dd6` feat(api): update response for candidate delete
* `a4721ab4a` feat(API): delete admin-license candidate
* `7ed947d3c` feat(API): get license candidates
* `0fd6be41c` feat(api): clearing status
* `2ac466b19` feat(api): change API schema for file uploads
* `23ff12e3f` feat(API): change group member's permission
* `d9b2597a7` feat(spdx): validate SPDX ID before adding
* `a113b816c` feat(spdx): update tag:value format to v2.3
* `f844ea1d7` feat(spdx): update to v2.3
* `c173a05ce` feat(nomos): update SPDX license shortnames
* `738c259c2` feat(spdx-tools): update to new repository
* `d6aaaf805` feat(license): use spdx identifiers for licenses
* `c4e702f82` feat(copyright): add new agent IPRA to FOSSology
* `266299f06` feat(copyright): add new keywords for ECC and keyword agent
* `7e1b7a801` feat(cmake): include libraries using cmake style
* `52ac2abad` feat(install): cmake changes for easy-install and vagrantfile
* `df8ddfe41` feat(eximporter): add file path for upload tree
* `f9d7e2156` feat(acknowledgements): add new ack dropdown to select saved ack
* `c5d8c5b78` feat(showjobs): show status link for inprogress jobs
* `de52028e6` feat(newagent): new agent decision export import
* `4b9c941c0` feat(buildsystem): Add CMake Build System

#### Corrections

* `24983d146` fix(dao): getLicenseByCondition set statement name on condition
* `add8abf00` fix(report): check array key exists
* `d4adf4a09` fix(spdx): create LicenseRef for custom license text
* `54562ca00` fix(README): Fix broken Travis SVG
* `fb9d50f8e` fix(api): check if hist has required keys
* `fc34bb660` fix(clixml): add acknowledgement to reports
* `e98e22e15` fix(api): jti not required for oauth tokens
* `9540a9cbd` fix(adminLicensecandidate): replace while loop with foreach and correct variables
* `5ba11350b` fix(rest): swap upload and folder id to create job
* `01019b5f4` fix(dumpExport): create pfile table always
* `8c729eee8` fix(import): ignore missing utree in dump import
* `1295ea11d` fix(clixml): use license full name in clixml report
* `d1bd7b55d` fix(api): unify dump and report import
* `ada5f201a` fix(search): fix search endpoint
* `56ba70bb0` fix(manualCopyright):Made Disabled Manual Copyrights Visible in UI
* `73c471438` fix(api): change response of job history
* `e40e7ae37` feat(API): /jobs/{id}/history GET route to get the history of all the jobs queued based on an upload
* `62212dbed` fix(decisionImporter): deduplicate file
* `5bf20e3ef` fix(obligationsGetter): separate licenses
* `963faaae1` fix(unifiedreport): fix warnings of unified report agent
* `7f4df1597` fix(spdx-rdf): use CDATA for attributionText
* `affc84466` fix(core-schema): fix index to match DB
* `14723b5d3` fix(api): add new model LicenseCandidate for admin endpoint
* `eb5d5e0bd` fix(api): add new model FileLicenses for REST API
* `4c7be95ca` fix(API): merge multiple upload-api calls into one.
* `bd38495bc` fix(api): check user permission before editing groups
* `b7a6a9c15` fix(unifiedReport): fix table distortion for component link
* `523d832fc` fix(ci): add missing dependency to runner image
* `7bd7ecba6` fix(spdx): add license text for valid RDF
* `f5eb9ea13` fix(security) fix inaproppriate encoding for output context Added `ENT_HTML5 | ENT_QUOTES` to ensure that all characters are properly encoded on output
* `d10d972e5` fix(security) fix Reflected XSS vulnerability, where input data was displayed directly on the web page
* `29604025e` fix(security) Sanitized  external command parameter with `escapeshellarg` as untrusted string may contain malicious system-level commands engineered by an attacker
* `bd2fb8f2e` fix(security) Replaced cryptographically insecure PHP rand() function with built-in for PHP random_int() with secure pseudo-random number generator
* `58fec86e2` fix(build): various build fixes
* `47066a32c` fix(oauth): update username if oauth email matches
* `1fcc19be9` fix(licenseRef): show only active licenses in bulk and user decisions
* `5d39fab5a` Fix(api): Fixed filesearch request
* `5dafd15a5` fix(conf): add escape string and fetch raw content

#### Infrastructure

* `3149e444d` chore(deps): bump guzzlehttp/psr7 from 2.4.3 to 2.5.0 in /src
* `df2fb2716` chore(scancode): fix the version to 31.2.4
* `34fd909db` chore(cmake): do not cache git version
* `c443aebca` chore(build): fix building of monkbulk package
* `14f8ea382` chore(Makefile): remove old Makefiles

### 4.2.1 (Nov 15th 2022)

This release is for the quick hot-fix on [4.2.0](https://github.com/fossology/fossology/releases/tag/4.2.0).

This release applies fix for REST API to patch access to User object.
More fixes like importing missing classes and handling 
other PHP Errors and Notices.

#### Credits to contributors for 4.2.1

From the GIT commit history, we have the following contributors since
[4.2.0](https://github.com/fossology/fossology/releases/tag/4.2.0):

```
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
```

#### Features

* `4bcf25682` feat(user-edit): make use of retention period to display expired tokens

#### Corrections

* `53c047bfb` fix(ui): fix PHP error and notices
* `aeceaff6a` hotfix(ui): fix User object accessing

### 4.2.0 (Nov 11th 2022)

This release adds important corrections to
[4.2.0-rc1](https://github.com/fossology/fossology/releases/tag/4.2.0-rc1)

Since RC1, minor updates with dependencies and a fix to unified report has
happened.

The release 4.2.0 introduces a number of corrections to
[4.1.0](https://github.com/fossology/fossology/releases/tag/4.1.0)
and major changes to FOSSology, including:

* Adopting REUSE.software standards to FOSSology source code.
* Detecting copyrights as per REUSE standards.
* Support for Ubuntu Jammy (22.04)
* Display package health according to Licenses folder.
* Update various dependencies.
* Fix line breaks for LibreOffice.
* Multiple new features in REST API.

#### Credits to contributors for 4.2.0

From the GIT commit history, we have the following contributors since
[4.1.0](https://github.com/fossology/fossology/releases/tag/4.1.0):

```
> aman1971 <ak584584@gmail.com>
> Antoine Auger <antoineauger@users.noreply.github.com>
> Avinal Kumar <avinal.xlvii@gmail.com>
> dushimsam <dushsam100@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Karthik Krishna <gkarthikkrishna1@gmail.com>
> Krishna Mahato <krishhtrishh9304@gmail.com>
> Martin Daur <mdaur@gmx.net>
> pret3nti0u5 <vineetvatsal09@gmail.com>
> rohitpandey49 <rohit.pandey4900@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Soham Banerjee <sohambanerjee4abc@hotmail.com>
> Thanvi pendyala <thanvipendyala194@gmail.com>
```

#### Features

* `76dc5801d` chore(php-jwt): use new features from v6.3.0
* `fd8eef901` feat(composer): update composer dependencies

#### Corrections

* `88faee7e7` fix(debian): prevent duplication of bootstrap
* `965552b12` fix(unifiedReport): fix line break issue in libre office
* `f2650a9de` fix(oneShotMonk): convert value to int to fix php fatal
* `28de987d6` fix(licenseView): fix missing comment select

### 4.2.0-rc1 (Sep 30th 2022)

This release adds important corrections to
[4.1.0](https://github.com/fossology/fossology/releases/tag/4.1.0)

The release 4.2.0-rc1 introduces reuse specifications to fossology.

The release 4.2.0-rc1 introduces a number of corrections to
[4.1.0](https://github.com/fossology/fossology/releases/tag/4.1.0)
and major changes to FOSSology, including:

* Support ubuntu jammy 22.04
* Detect SPDX-FileCopyrightText keyword
* Allow user to configure token
* Reuse all report columns
* Detect Licenses Folder

#### Credits to contributors for 4.2.0-rc1

From the GIT commit history, we have following contributors since
[4.1.0](https://github.com/fossology/fossology/releases/tag/4.1.0):

```
> aman1971 <ak584584@gmail.com>
> Antoine Auger @antoineauger
> Avinal Kumar <avinal.xlvii@gmail.com>
> dushimsam <dushsam@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Karthik Krishna <gkarthikkrishna1@gmail.com>
> Krishna Mahato <krishhtrishh9304@gmail.com>
> Martin Daur <mdaur@gmx.net>
> pret3nti0u5 <vineetvatsal09@gmail.com>
> rohitpandey49 <rohit.pandey4900@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> soham4abc <sohambanerjee4abc@hotmail.com>
> Thanvi pendyala <thanvipendyala194@gmail.com>

```
#### Features

* `eb07d7626` feat(reuse): detect Licenses Folder
* `f9f9023a2` feat(ubuntu): support ubuntu jammy 22.04 for fossology
* `afa5fd58a` feat(licenseRef): add/update licenses from spdx.org
* `88025d5a6` feat(copyright): Detect SPDX-FileCopyrightText keyword
* `41674a5bd` feat(API): add user to a group.
* `b154feee9` feat(api): Download file using UploadID
* `7fbbe736c` feat(API): import csv-license file
* `85cf46567` feat(oidc): allow user to configure token
* `54f80533c` feat(api): Set permissions for a upload in a folder for different groups
* `14aba0a4c` feat(API): REST-API to initiate FOSSology mantainance
* `a5d6a18d5` feat(API): get group members with corresponding roles
* `42e7f0c13` feat(API): remove member from group.
* `0c9620e95` feat(api): new endpoint for geting copyright details
* `c2b09f16e` feat(api): jobs/all endpoint added
* `53b043b19` feat(API): delete user group
* `917ee86af` feat(API): jobs returns only logged in user's jobs
* `4038daac1` feat(reuse): ignore text of testdata
* `454c8cede` feat(resue): reuse standard
* `40dfd5833` feat(reuse): implemented REUSE standard
* `f60b09983` feat(reuse): implemented REUSE standard
* `a3e8f235e` feat(reuse): Adopted Reuse.software standard
* `3424028f5` feat(API): Add pagination to search request
* `9c12b6222` feat(copyrightexport): Added copyright export to fo_nomos_license_list
* `262b93954` feat(ui): close banner for a session
* `11f424ac3` feat(API): added a copyright feat in /uploads/{id}/licenses api

#### Corrections

* `cc1f48985` fix(lint): openapi lint corrected
* `f88a614ec` fix(api): add missing variables
* `b8de588a6` fix(reportImport): remove dual check for access and fix array warning
* `6778a6041` refactor(demomod): add missing code in makefile
* `118f29e0f` fix(copyright): fix regex conf files
* `41cd3d446` fix(default_group): exposed deafult_group in /users/self
* `8bde786a7` fix(ui): restore license text for bulk modal
* `fa4964c83` fix(reuser): reuse all report columns
* `b9f727dc4` fix(ci): update spectral-action to fix ci test
* `a9054815a` fix(uploadPermission):introduced error on changing upload permissions
* `20376e602` fix(reuse): perform code fixes on reuse branch
* `75a386bc1` test(ci): Run REUSE compliance check in CI
* `dd873faf6` fix(reuser): add scancode as dependency if sched
* `8c9f8bf92` fix(ui): Fix upload from Srv for parameterize agent
* `13fb71910` fix(make): Fix warnings in make for Ubuntu 20.04.2 LTS
* `d94cced54` fix(readme): typo fixed

#### Infrastructure

* `251be4682` chore(deps): bump twig/twig from 3.3.8 to 3.4.3 in /src
* `03b180355` chore(Dockerfile): add OCI annotations
* `534564bc9` docs(openapi): fix spectral lint warnings/errors
* `045440de8` chore(component-id): use package-url instead purl
* `ff8e440de` chore(deps): bump guzzlehttp/guzzle from 7.4.1 to 7.4.3 in /src
* `42aa7c40d` chore(workflow): update GHA dependencies
* `c7d61ba6d` chore(deps): bump guzzlehttp/guzzle from 7.4.4 to 7.4.5 in /src
* `113253c2d` chore(deps): bump guzzlehttp/guzzle from 7.4.3 to 7.4.4 in /src
* `fe2bd41a0` docs(reuse): reuse badge added

### 4.1.0 (May 12th 2022)

This release adds important corrections to
[4.1.0-rc1](https://github.com/fossology/fossology/releases/tag/4.1.0-rc1)

The release 4.1.0 introduces new agent `ScanCode`, used to scan for licenses,
copyrights etc.

The release 4.1.0 also introduces new feature to automatically deactivate the
copyrights and cutter removal. There is a special note about this feature.
> As this feature can still be improved, we are marking this as `experimental`
and not recomended for productive instances.
> Also this feature requires to install additional dependencies. One needs to
run fo-postinstall with --python-experimental.

The release 4.1.0 introduces a number of corrections to
[4.1.0-rc1](https://github.com/fossology/fossology/releases/tag/4.1.0-rc1)
and major changes to FOSSology, including:

* Security fix for JWT tokens
* Migration fix for copyrights

#### Credits to contributors for 4.1.0

From the GIT commit history, we have following contributors since
[4.0.0](https://github.com/fossology/fossology/releases/tag/4.0.0):

```
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Archisman Dawn <archismandawn7@gmail.com>
> coder-whale @coder-whale
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Ettinger Katharina <katharina.ettinger@siemens.com>
> Karthik Krishna <gkarthikkrishna1@gmail.com>
> Kaushlendra Pratap <kaushlendrapratap.9837@gmail.com>
> krishna9304 <krishna.mahato@precily.com>
> Rohit Pandey <rohit.pandey4900@gmail.com>
> Sarita Singh <saritasingh.0425@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> SvetaInChina <Huaying.Liu@mediatek.com>
> Tassilo Pitrasch <t.pitrasch@gmail.com>
> Toussaint Nicolas <nicolas1.toussaint@orange.com>
```

#### Corrections

* `840479e51` fix(scancode): add missing class name to fix tooltip
* `31ca7525a` fix(scancode): move python dependencies
* `cc5d9d8e5` fix(jwt): explicitly declare jwk algorithm
* `f8a18ae7e` fix(copyright): do not update empty copyrights

#### Infrastructure

* `f431c98c7` chore(scancode): hide scancode UI if not installed

### 4.1.0-rc1 (April 8th 2022)

This release adds important corrections to
[4.0.0](https://github.com/fossology/fossology/releases/tag/4.0.0)

The release 4.1.0-rc1 also introduces new agent `ScanCode`, used to
scan for licenses, copyrights etc.

The release 4.1.0-rc1 also introduces new feature to automatically
deactivate the copyrights and cutter removal. There is a
special note about this feature.
> As this feature can still be improved, we are marking this as
`experimental` and not recomended for productive instances.
> Also this feature requires to install additional dependencies.
One needs to run fo-postinstall with --python-experimental.

The release 4.1.0-rc1 introduces a number of corrections to
[4.0.0](https://github.com/fossology/fossology/releases/tag/4.0.0)
and major changes to FOSSology, including:

* Add a new agent scancode-toolkit
* Deciding copyrights with Spacy
* Add new decision type non-functional
* Admin can delete any upload
* Fix unicode replacement in exportLicenseRef
* Provide server version on REST api
* Update license texts from SPDX
* Clixml-xml based reporting format

#### Credits to contributors for 4.1.0-rc1

From the GIT commit history, we have following contributors since
[4.0.0](https://github.com/fossology/fossology/releases/tag/4.0.0):

```
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Archisman Dawn <archismandawn7@gmail.com>
> coder-whale @coder-whale
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Ettinger Katharina <katharina.ettinger@siemens.com>
> Karthik Krishna <gkarthikkrishna1@gmail.com>
> Kaushlendra Pratap <kaushlendrapratap.9837@gmail.com>
> krishna9304 <krishna.mahato@precily.com>
> Rohit Pandey <rohit.pandey4900@gmail.com>
> Sarita Singh <saritasingh.0425@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> SvetaInChina <Huaying.Liu@mediatek.com>
> Tassilo Pitrasch <t.pitrasch@gmail.com>
> Toussaint Nicolas <nicolas1.toussaint@orange.com>
```

#### Corrections

* `3fb149388` fix(rest): fix CORS issue
* `163866bd6` fix(rest): Added default client ID claim
* `711618199` fix(rest): Allows client ID claim to be configurable
* `0f53f78fd` fix(exportLicenseRef): fix unicode replacement
* `32ed138b2` fix(pkgagent): Fixes FossologyUI unexpected token error
* `13603a0c6` fix(API): Fixes FossologyUI CORS error for localhost
* `346db84fc` fix(openapi.yaml): OpenAPI Description: Fix typo in HeathInfo
* `784bb5782` fix(api): Correct uploadId is returned for UploadFromURL
* `568fe02e3` fix(rest): fix scope for oauth
* `6e6688402` fix(nomos): Improved nomos GPL detection
* `b81696054` feat(nomos): add 'BSD-4-Clauset-Shortened' license
* `4739e6c93` fix(scancode): update load function and fix testcases for scan code
* `d10e3c3f8` hotfix(rest): fix file upload
* `4e26e35b7` fix(deleteupload): Admin can delete any upload
* `d5bdf4acd` fix(installdeps): fix call to external script
* `f15fe07db` fix(global): add statement name for global query to fix reuser
* `b2e1273f6` fix(clixml): fix free text fields in clixml
* `79d604d8b` fix(testcase): fix test cases for lib
* `fc603ef54` fix(rest): Slim fixes for REST API
* `b37948fe5` fix(phpunit): Fix function signature for PHPUnit
* `52e37ca0b` fix(test): fix test failures caused by sysconfig
* `2e9b8f1de` fix(report): Set content type header


#### Features

* `0b65c31b6` feat(licenseRef): add or update license texts from SPDX
* `c00bc94f8` feat(scancode): add scancode to debian packaging
* `bc577a049` feat(report): accept package URL
* `19d6cb2f0` feat(copyright): Deciding copyrights with Spacy
* `cc9f94bd3` feat(dev-ctbutton): Added clean text button at license text field
* `2a7d18e86` feat(rest): oidc based authentication
* `24f666101` feat(upload): Warning on duplicate upload
* `b8e94ac29` feat(scancode):Added scancode API and minor fixes
* `6cf46d450` feat(copyright):Integrating scancode to copyrightUI
* `14437f970` feat(scancode):Add a new agent scancode-toolkit
* `e08eeb71f` feat(version): Update sysconfig release from version
* `cc30d1293` feat(newAgent): clixml-xml based reporting format
* `7fcf09f70` feat(ui): show dropdown for "mark as" decisions
* `bb85d5350` feat(decisions): add new decision type non-functional
* `438f178f2` feat(upload): allow multifile upload from UI
* `d98041925` feat(gdpr) deactivate users + Store last cnx timestamp
* `7445f15bf` feat(keyword): add new word 'stolen from' to keyword agent
* `f9d17c50c` feat(lbtablelength):Added all for license browser table
* `1da36ad53` feat(rest): Provide server version on REST api

#### Infrastructure

* `5d3b01304` chore(deps): bump guzzlehttp/psr7 from 2.1.0 to 2.2.1 in /src
* `5fd23105f` chore(install): update python deps installation
* `01bafc2e2` test(ci): run docker tests in GitHub Actions
* `d70570c56` chore(browse): redirect to license view if empty
* `3fc43965f` chore(composer): update composer form 1.9.0 to 2.2.6 version
* `830608b62` chore(deps): bump twig/twig from 3.3.4 to 3.3.8 in /src
* `8d245be73` chore(composer): Update composer dependencies
* `9b1df8f77` refactor(clearingDao): add few functions to a single one
* `0aeebfe2e` refactor(ui-clearing-view_rhs.html.twig) : Changed tooltip description for "Do not use"
* `10011f039` perf(sysconfig): Setup sysconfig at fossinit

### 4.0.0 (Jan 20th 2022)

This release adds important corrections to
[4.0.0-rc1](https://github.com/fossology/fossology/releases/tag/4.0.0-rc1)

The release 4.0.0 introduces following major changes since
[3.11.0](https://github.com/fossology/fossology/releases/tag/3.11.0):

* Support Debian 11
* Add bootstrap in fossology to beautify ui
* Remove old gold files
* Remove old log files
* Provide custom delimiters for monkbulk scan
* New info and health endpoints for rest
* Update license texts from SPDX
* Add new report format CSV.
* Option to make user details read-only
* Make global decisions configurable while upload

NOTE:
 The release also introduces new look to fossology tool,
 only few pages have changes/classes of new bootstrap UI. Other
 pages still needs corrections.

#### Credits to contributors for 4.0.0

From the GIT commit history, we have following contributors since
[3.11.0](https://github.com/fossology/fossology/releases/tag/3.11.0):

```
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Ettinger Katharina <katharina.ettinger@siemens.com>
> Marion Deveaud <marion.deveaud@siemens.com>
> Piyussshh @Piyussshh
> Sarita Singh <saritasingh.0425@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Shruti3004 <mail2shruti.ag@gmail.com>
> Toussaint Nicolas <nicolas1.toussaint@orange.com>
> Wonjae Park <wonjae.park@lge.com>
```

#### Corrections

* `734f439e4` fix(reportImport): Fix interfaces in report import
* `bc70462a8` fix(ui): Fix bulk modal with selectable bg
* `d4f32b865` fix(reports) fix CSV report action title
* `1287c5723` Fix merge errors

#### Features

* `574c13d1c` feat(reports): fix indent errors
* `2550919a2` feat(reports): Add new CSV report type
* `df3573982` feat(nomos): See file regex to include view
* `44acd2029` feat(nomos): New see-url pattern
* `01273ae78` feat(users) Add option to make user details read-only
* `306260bfc` feat(reports) Fix DEP5 report menu entry
* `144875921` feat(reports): change report names in drop down menu
* `a778c5f68` feat(upload): make global decisions configurable
* `f1c4ed4fa` Add option to make user details read-only

#### Infrastructure

* `0afbb8fe5` chore(cd): Continue release build on failure
* `ff3b7d63a` Update src/www/ui/async/AjaxShowJobs.php
* `ec0a26956` Revert "fix(login): Allow non-admin user to update"
* `5596f78d7` Revert "Add option to make user details read-only"
* `e063beda2` Revert "Fix merge errors"
* `a07ccd939` Merge all GDPR related work

### 4.0.0-rc1 (Dec 21st 2021)

This release adds important corrections to
[3.11.0](https://github.com/fossology/fossology/releases/tag/3.11.0)

The release 4.0.0-rc1 introduces following major changes since
[3.11.0](https://github.com/fossology/fossology/releases/tag/3.11.0):

* Support Debian 11
* Add bootstrap in fossology to beautify ui
* Remove old gold files
* Remove old log files
* Provide custom delimiters for monkbulk scan
* New info and health endpoints for rest
* Update license texts from SPDX

NOTE:
 The release also introduces new look to fossology tool,
 only few pages have changes/classes of new bootstrap UI. Other
 pages still needs corrections.

#### Credits to contributors for 4.0.0-rc1

From the GIT commit history, we have following contributors since
[3.11.0](https://github.com/fossology/fossology/releases/tag/3.11.0):

```
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Marion Deveaud <marion.deveaud@siemens.com>
> Piyussshh @Piyussshh
> Sarita Singh <saritasingh.0425@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Shruti3004 <mail2shruti.ag@gmail.com>
> Toussaint Nicolas <nicolas1.toussaint@orange.com>
> Wonjae Park <wonjae.park@lge.com>
```

#### Corrections

* `b7fca0b45` fix(logrotate): Send SIGHUP
* `272c0c8a3` fix(report): same license text for different shortname
* `31127344e` fix(ui):Fix upload from VCS for parameterize agent
* `93c5fa446` fix(ui): Change folder edit to bootstrap
* `8a52c390d` fix (build): do not fail is /usr/share/man* folders already exist
* `f7dbf3833` fix(rest): fixed the pagination in apis
* `d8b776ba1` fix(unifiedreport): Fix upload link for API
* `a3c92909e` hotfix(api): Add missing auth controller
* `4a978109a` fix(upload): Fix upload description input
* `36a1573ae` fix(login): Allow non-admin user to update
* `6c241d202` fix(test): Fix licenseRef.json
* `e62c84291` fix(ci): Update OpenAPI lint
* `c039b4c37` fix(ui): Allign folder tree
* `7e7cf82db` fix(rest): fix typo in openapi.yaml, s/reuse_uplod/reuse_upload/
* `5b17c4999` fix(ui): add line break in upload name if exceeds 20 chars

#### Features

* `7db93f622` feat(gitpod): Inital contribution
* `2e2b27642` feat(spasht): Show effective score
* `7f117cbe6` feat(ui): add bootstrap in fossology to beautify ui
* `bb9d7f946` feat(licenseText): update license texts from SPDX
* `5f0696095` feat(monkbulk): Custom delimters
* `f42e07318` feat(monk): New delimiters dnl
* `143e20e80` feat(edit-user): Let user can define default folder and use the default
* `b6de455d6` feat(rest): New info and health endpoints
* `50ebaf51f` feat(maintagent): Implement deleteOrphanGold fn
* `9b474be28` feat(ui): Read delimiters for clean text
* `267ef0af4` feat(rest): Filter uploads with 4 new parameters
* `31dd1c44f` feat(ci): GitHub-ci for c-tests
* `3d71a2ca7` feat(os): Support Debian 11
* `4064dfc31` feat(maintagent): Implement removeOrphanedFiles fn
* `d0f7bddcf` feat(maintagent): Remove old gold files
* `856a2c40d` feat(maintagent): Remove old log files

#### Infrastructure

* `6af218c92` chore(os): Drop xenial support for eol
* `2844492a0` docs(openapi): complete OAS spec to pass linting
* `32f707c25` chore(lint): make sure swagger spec is correct
* `a309c814d` ci(actions): Build Docker images in Actions
* `0d5227960` docs: Updated README.md and CONTRIBUTING.md
* `50b3bf168` feat(rest): Update upload information
* `b3d8b4789` feat(unifiedreport): include assigned to in component clearing section

### 3.11.0 (Jul 27th 2021)

This release adds important corrections to
[3.11.0-rc2](https://github.com/fossology/fossology/releases/tag/3.11.0-rc2)

The release 3.11.0 introduces following major changes since
[3.10.0](https://github.com/fossology/fossology/releases/tag/3.10.0):

* Add bulk undo for deactivated copyrights.
* Configurable irrelevant file scan for monkbulk.
* Add job to remove expired tokens from database.
* Add a simple search to get folder.
* Unit test cases for REST API.
* Reuse edited copyright.
* Add scroll to NOTICE file modal.
* Set candidate license creator for ojo.
* Fix external auth.
* Updating the license info files.

NOTE:
 The release 3.11.0 also introduces new agent `reso` which copies
 license findings from OJO based on REUSE.Software standard on
 what license is a binary file licensed under(if available).

#### Credits to contributors for 3.11.0

From the GIT commit history, we have following contributors since
[3.10.0](https://github.com/fossology/fossology/releases/tag/3.10.0):

```
> Aman Dwivedi <aman.dwivedi5@gmail.com>
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Anwar Hashmi @HashmiAS
> bighnesh0404 <saibighneshprusty@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Michael C. Jaeger <michael.c.jaeger@siemens.com>
> Nicolas Toussaint <nicolas1.toussaint@orange.com>
> OmarAbdelSamea <1700903@eng.asu.edu.eg>
> R3da <hash.rkh@gmail.com>
> Rolf Eike Beer <eb@emlix.com>
> Sarita Singh <saritasingh.0425@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> shivamgoyal7 <goyalshivam661@gmail.com>
> Shruti3004 <mail2shruti.ag@gmail.com>
> Wonjae Park <wonjae.park@lge.com>
> Ying-Chun Liu (PaulLiu) <paulliu@debian.org>

```
#### Corrections

* `e4803b8fe` fix(migration): check if uploadtree is empty
* `635d4904a` fix(rules) : adding debian package for reso agent
* `b44249e2d` fix(copyright):Change menu text of copyright page

### 3.11.0-RC2 (Jul 12th 2021)

This release adds important corrections to
[3.11.0-rc1](https://github.com/fossology/fossology/releases/tag/3.11.0-rc1)

The release 3.11.0-rc2 introduces following major changes since
[3.11.0-rc1](https://github.com/fossology/fossology/releases/tag/3.11.0-rc1):

* Set candidate license creator for ojo.
* Fix external auth.
* Updating the license info files.

The release 3.11.0-rc2 also introduces new agent `reso` which copies
license findings from OJO based on REUSE.Software standard on
what license is a binary file licensed under(if available).

#### Credits to contributors for 3.11.0-RC2

From the GIT commit history, we have following contributors since
[3.11.0-rc1](https://github.com/fossology/fossology/releases/tag/3.11.0-rc1):

```
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Michael C. Jaeger <michael.c.jaeger@siemens.com>
> Nicolas Toussaint <nicolas1.toussaint@orange.com>
> Shruti3004 <mail2shruti.ag@gmail.com>

```
#### Corrections

* `f7c7715fa` fix(reso) fix comment
* `8e399f0cd` fix(ojo): Set candidate license creator
* `8031b128b` fix(auth): read default visibility from database rather then config file
* `b3f0d1db0` fix(api): Check for duplicate shortnames
* `2d6d8b187` fix(auth): fix call to add_user() when login from external auth

#### Features

* `df52e53ba` feat(rest): Filter licenses by kind
* `3c98947cf` feat(expose-headers): added the expose headers option for response headers
* `d1031cae3` feat(reso): new agent for REUSE.Software standard
* `2e1d28eb1` feat(rest): Add POST/PATCH license endpoints

#### Infrastructure

* `93a47eab0` docs(licenses): updating the license info files
* `0eacc14af` test(api): Test cases for LicenseController

### 3.11.0-RC1 (Jun 29th 2021)

This release adds important corrections to
[3.10.0](https://github.com/fossology/fossology/releases/tag/3.10.0)

The release 3.11.0-rc1 introduces following major changes since
[3.10.0](https://github.com/fossology/fossology/releases/tag/3.10.0):

* Add bulk undo for deactivated copyrights.
* Configurable irrelevant file scan for monkbulk.
* Add job to remove expired tokens from database.
* Add a simple search to get folder.
* Unit test cases for REST API.
* Reuse edited copyright.
* Add scroll to NOTICE file modal

```
> Aman Dwivedi <aman.dwivedi5@gmail.com>
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Anwar Hashmi @HashmiAS
> bighnesh0404 <saibighneshprusty@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> OmarAbdelSamea <1700903@eng.asu.edu.eg>
> R3da <hash.rkh@gmail.com>
> Rolf Eike Beer <eb@emlix.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> shivamgoyal7 <goyalshivam661@gmail.com>
> Shruti3004 <mail2shruti.ag@gmail.com>
> Wonjae Park <wonjae.park@lge.com>
> Ying-Chun Liu (PaulLiu) <paulliu@debian.org>

```
#### Corrections

* `858a9070d` fix(ui): Fix the modal height for small screens
* `01afe6c2d` fix(reuser): Reuse edited copyright
* `7343edb40` fix(copyright): Make check strict
* `d90541903` fix(nomos): improved nomos MPL-2.0 detection
* `e16588a8c` fix(api): Add missing reuser options
* `7782452a7` fix(ui): Break long lines in copyright table
* `65832e9a0` fix(debian): Add php-gd package as dependency
* `576bf4c79` fix(ui-export-list): Dont add integers
* `c985342f9` fix(ui): License text editor
* `ac86e7e6c` fix(ui): Add scroll to NOTICE file modal
* `06796d149` fix: remove wrong 'extern "C"' guards
* `5365585a6` fix(links): fix broken links
* `c7a2a9ab1` fix(decision): Create ce for folder decisions irr
* `1139443b7` fix(Dockerfile): upgrade debian distribution
* `58160879f` fix(ui): Show error message for invalid license id
* `d9857c6dc` fix(report): Do not merge ack text
* `3a6454b4d` Update ReuserAgent.php
* `c7cfffbdc` fix(UI): fix html errors, css errors and add viewport meta tag
* `f2ff40b6d` fix(username): update session variable on username change
* `d92ee4c0b` fix(email): Update email command for s-nail
* `d5d56f7c2` fix(gcc-10): Fix errors and warnings
* `83e857261` fix(test): Add new assignee attribute to REST
* `c38d57888` fix(cli): Dependency exception
* `858a9070d` fix(ui): Fix the modal height for small screens

#### Features

* `b1ab4d0a0` feat(dbcreate): retry psql check while starting
* `1d7f5f9fc` feat(restAPI): Added options request and verification function
* `ff3816fb3` test(rest): Unit test cases for REST API
* `1c3dab241` feat(licenseExport): include obligation topic in exported CSV
* `61c320418` feat(ui): Remember assignee filter on Browse view
* `1e8764463` feat(rest): Add assignee id to fossology API
* `40b3e2faf` feat(Ui): added Default upload visibility
* `af89659c7` feat(addMetadata): added creationdate,lastModifiedDate,usernameCreated and usernameModified in candidate license
* `2bc632925` feat(migration): general improvements for copyright migration
* `5a7d45708` feat(copyright): add bulk undo for deactivated copyrights
* `86fe8a3f2` feat(browse): add a simple search to get folder
* `1dc44506d` feat(ci): Mark PRs with conflict with Actions
* `47d9cb9be` feat(maintagent): add job to remove expired tokens from database
* `c563e80ce` feat(export): Download results in spreadsheet (xlsx)
* `26fe22e8a` feat(monkbulk): Configurable irrelevant file scan
* `d973e1983` feat(export) : Consolidating results per file or directories
* `02fcb8afd` feat(rest): Add /users/self endpoint

#### Infrastructure

* `ccabb703c` chore(gitignore): add db.cron and fossdash-publish.py to .gitignore
* `4238d7808` chore(dependency): update jquery and select2 version

### 3.10.0 (May 7th 2021)

This release adds important corrections to
[3.10.0-rc2](https://github.com/fossology/fossology/releases/tag/3.10.0-rc2)

The release 3.10.0 introduces following major changes since
[3.9.0](https://github.com/fossology/fossology/releases/tag/3.9.0):

* Change copyright handling add new table copyright_event.
* Drop support for PHP5 and update dependencies for PHP7
* Update password hashing algorithm from SHA1 to more secure bcrypt.
* Advance search and replace for copyrights.
* Ability to enforce password policies.
* Feature to import license acknowledgement from NOTICE file.
* Ununpack agent can be compiled to work in standalone mode.
* Create new licenses as candidate for OJO.
* Read XML in chunks to support large files for ReportImport.
* Add license search based on short name in REST.
* Do not add decisions if the events have no change.

NOTE: This release also adds a migration script which migrates copyright data to new table copyright_event.
      Migration processes is mandatory because without migration, old copyright activation/deactivation may not work.
      also it approximately takes 15 min for 1M records.

#### Credits to contributors for 3.10.0

From the GIT commit history, we have following contributors since
[3.9.0](https://github.com/fossology/fossology/releases/tag/3.9.0):

```
> Alan Hohn <Alan.M.Hohn@lmco.com>
> Aman Dwivedi <aman.dwivedi5@gmail.com>
> Andreas J. Reichel <andreas.reichel@tngtech.com>
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Avinal Kumar <avinal.xlvii@gmail.com>
> Bartłomiej Dróżdż <bartlomiej.drozdz@orange.com>
> Darshan <kansagara.darshan97@gmail.com>
> David Lechner <david@pybricks.com>
> Dineshkumar Devarajan (RBEI/BSF6) <Devarajan.Dineshkumar@in.bosch.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Helio Chissini de Castro <helio@kde.org>
> Mikko Murto <mikko.murto@hhpartners.fi>
> Michael C.Jaeger <michael.c.jaeger@siemens.com>
> Pawan Kumar Meena <Pawank1804@gmail.com>
> Piotr Pszczola <piotr.pszczola@orange.com>
> rlintu <raino.lintulampi@bittium.com>
> Sahil <sjha200000@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Shruti3004 <mail2shruti.ag@gmail.com>
> Toussaint Nicolas <nicolas1.toussaint@orange.com>
> YashJipkate <yashjipkate@gmail.com>

```

#### Corrections

* `58e1e4c9d` fix(docker-compose): Revert container port to 8081
* `d269582a5` fix(actions): Build pages on release

#### Infrastructure

* `e9ca31401` perf(migration): remove offset to make the query faster
* `271287be1` fix(build): Make script compatible with Xenial

### 3.10.0-RC2 (Apr 19th 2021)

This release adds important corrections to
[3.10.0-rc1](https://github.com/fossology/fossology/releases/tag/3.10.0-rc1)

The release 3.10.0-RC2 introduces following major changes:

* Change copyright handling add new table copyright_event.
* Create new licenses as candidate for OJO.
* Read XML in chunks to support large files for ReportImport.
* Show parent folder on *Browser views.
* Add license search based on short name in REST.
* Do not add decisions if the events have no change.
* Migrate github pages deployment to GHA.

NOTE: This release also adds a migration script which migrates copyright data to new table copyright_event.
      Migration processes is mandatory because without migration, old copyright activation/deactivation may not work.
      also it approximately takes 30 mins for 1M records.

#### Credits to contributors for 3.10.0-RC2

From the GIT commit history, we have following contributors since
[3.10.0-rc1](https://github.com/fossology/fossology/releases/tag/3.10.0-rc1):

```
> Alan Hohn <Alan.M.Hohn@lmco.com>
> Aman Dwivedi <aman.dwivedi5@gmail.com>
> Anupam <ag.4ums@gmail.com>
> Avinal Kumar <avinal.xlvii@gmail.com>
> Darshan <kansagara.darshan97@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Mikko Murto <mikko.murto@hhpartners.fi>
> Pawan Kumar Meena <Pawank1804@gmail.com>
> Piotr Pszczola <piotr.pszczola@orange.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Shruti3004 <mail2shruti.ag@gmail.com>
> Toussaint Nicolas <nicolas1.toussaint@orange.com>
> YashJipkate <yashjipkate@gmail.com>
```

#### Corrections

* `53aa058cb` fix(DBMigrate): add rows with same agent_fk for migration
* `8e1e7bfcd` fix(ui): updated the link of scheduler documentation
* `71cc0cea1` fix(copyright): improve reuse and correct update queries
* `23fe64335` fix(link): changed the broken documentation page link
* `3a5eeab03` fix(copyrightevent): General improvements
* `50b9dd5e4` fix(rest): missing Group component in API documentation
* `0bda7e2b7` fix(nomos): Flush stdout in JSON writer
* `44dea1ab0` fix(UploadTreeProxy): Get if candidate license
* `36200a90a` fix(ui): Show parent folder on *Browser views
* `5fc03c699` fix(uploadDao): Fetch status based on group id
* `405e8529e` fix(globalDecision): fix global decision prevent adding history in case of global decision
* `a4e5dd93a` fix(decider): Do not global ojo decisions
* `729e654fb` fix(ojo): Create new licenses as candidate
* `1e9138445` fix(reuser): updated misleading UI label This closes issue 1876
* `81ac2b584` fix(reportImport): Read XML in chunks
* `cf547a77f` fix(reuser): Do not process pfiles with id 0
* `3e4dcdf7b` fix(reuse): correct docstring
* `ca9395bfd` fix(globaldecision): do not add decisions if the events have no change
* `de92148a8` fix(cd): Use published event to build release pkgs
* `1c42760b7` fix(fossdash) : waiting for completing the execuition of find command
* `84d9153fa` bugfix(fossdash) : updated find cmd to clean reported files
* `707b93149` fix(fossdash script):  script file to install fossdash dependencies
* `fae866901` fix(fossdash script) : fix the improper formate of data to influxDB.
* `14e7ee101` other(fossdash config): changed config file link to permanent wiki page.
* `4d2c1791d` other(fossdash.log) : changed fossdash log path
* `a635bc1aa` removed(bootstrap file): removed bootstrap min js and it's related references.
* `870e5fd94` Other(license changed) : License info changed and fossdash UI config changed
* `3c28b7df9` remove(log counter) : remove error counter feat from fossdash. and consider it in the future scope.
* `a6341a21f` fix(fossdash-config): fixed substring find
* `4ff79d6c0` fix(uuid-ossp): Create extension as postgres

#### Features

* `9a1fd6163` feat(static-checks.yml): migrate static checks and analysis to GHA
* `2a90ff34b` feat(copyright): save deleted copyrights in copyright_event table
* `eded1d7d2` feat(deploy-pages.yml): migrate github pages deployment to GHA
* `f91881a7b` feat(swh): Allow API token
* `1e28973b2` feat(rest): get groups and create group functionality
* `41d8e88fa` feat(reuse): Change data type of reuse_group from int to string
* `8aa47c444` feat(rest): add license search based on short name
* `6181f9165` feat(rest): get copyright info for file hash
* `7d1fa425b` feat(fossdash metrics config): using default metrics file, if metric config is empty.
* `14afdd588` feat(beautify error) : Added ERROR and WARNING sign
* `e8da2b880` feat(log counter) : Maintain and push log counter into influxDB.
* `57db5d36f` Test(fossdash-config) : unit-test for fossdash_config.php

#### Infrastructure

* `0dba2364c` docs(deploy-pages.yml): add copyright
* `d434bf7b5` docs(CONTRIBUTING.md): Fixed broken link and typos
* `98311e89d` docs(README): fixed broken links, typos, grammatical errors and added test instance
* `0886d574f` refactor(.travis.yml): remove static checks and analysis
* `f0603b6e1` refactor(.travis.yml): remove github pages deployment
* `cbdb12d73` Revert "feat(copyright): save deleted copyrights in copyright_event table"
* `0c564843c` refac(swh): Move agent configuration to Sysconf
* `5c23a327e` refactor(fossdash UI menu) :  created new menu and new php pages for fossdash.
* `5e39eb87b` refactor(fossdash script) : remove all metric queries from the code, Put them into configuration way.
* `3f1ecc583` add the cron-triggered metrics exporter for FossDash
* `744485fd2` chore(ui): Show candidate licenses from agents
* `50fc42213` chore(reportImport): Make agent immortal

### 3.10.0-RC1 (Jan 8th 2021)

With every new release, FOSSology brings various bug fixes, infrastructure
changes and various new features.

You can check the list of commits in release bellow but few highlights for the
release will be:

* Drop support for PHP5 and update dependencies for PHP7
* Update password hashing algorithm from SHA1 to more secure bcrypt.
* Ability to search file from hash values in REST API.
* New licenses from SPDX 3.10 and many fixes in nomos.
* Advance search and replace for copyrights.
* Ability to enforce password policies.
* Feature to import license acknowledgement from NOTICE file.
* Change the versioning scheme to include patch number (featched from GIT).
* Ununpack agent can be compiled to work in standalone mode.

#### Credits to contributors for 3.10.0-RC1

From the GIT commit history, we have following contributors since
[3.9.0](https://github.com/fossology/fossology/releases/tag/3.9.0):

```
> Aman Dwivedi <aman.dwivedi5@gmail.com>
> Andreas J. Reichel <andreas.reichel@tngtech.com>
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Bartłomiej Dróżdż <bartlomiej.drozdz@orange.com>
> David Lechner <david@pybricks.com>
> Dineshkumar Devarajan (RBEI/BSF6) <Devarajan.Dineshkumar@in.bosch.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Helio Chissini de Castro <helio@kde.org>
> Mikko Murto <mikko.murto@hhpartners.fi>
> Piotr Pszczola <piotr.pszczola@orange.com>
> rlintu <raino.lintulampi@bittium.com>
> Sahil <sjha200000@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Toussaint Nicolas <nicolas1.toussaint@orange.com>
```

#### Corrections

* `9027b8711` fix(login): Do not set group_fk if empty
* `e6b060fbe` fix(db): add indexes to pfile on sha1 and sha256
* `0714946c9` fix(ack): add missing uploadid in getresults function
* `71981e494` fix(API): added container-interop dependency for resolving Internal Server Error
* `c6d1c87a3` fix(twig): Update twig version to preserve spaces
* `d250b735b` fix(globalDecision): make includesubfolders true in case of global to capture previous decisions
* `32cef88e4` fix(readmeoss): unescape contents
* `34486d07d` fix(conf): fix not able to save conf in case of brackets
* `88024fe35` fix(ununpack): Initialize gcrypt
* `40d96ebeb` fix(rest) : Set job status as Failed when any one of the job is failed

#### Infrastructure

* `a0338b740` refactor(login): updated password hashing algorithm
* `30f0b773a` docs(db): remove obsolete comment from schema
* `3d29039b7` chore(cd): Use fo-debuild script to build packages
* `65041f002` debian: Improve deb package building (#1828)
* `855aec69a` update(org): upgraded php version to php7

#### Features

* `932b82d76` feat(ununpack): standalone
* `106a95907` feat(docker): improve database healthcheck command
* `3291eec27` feat(docker): services healthchecks in docker-compose file
* `fc8b111ca` feat(build): Get build version number from git
* `7f36edb90` feat(password): Create password policy
* `a3dad10ab` feat(browser): total files in license browser view
* `4fd3007e4` feat(copyright): Search and replace with regex
* `c1773ec41` feat(conf): make unified report configurable
* `b96010ea1` feat(licenses): New licenses added from SPDX 3.10 to nomos.
* `9b327e80b` Nomos: New licenses from SPDX 3.10 added. Lots of other corrections.
* `323155150` feat(cd): Build Focal packages on release
* `a9ce7e738` feat(nomos): add new license intel-binary
* `bc3e1bf6b` feat(rest): Filter uploads by folder id
* `03f7f53c4` feat(utils): Filter inputs for unicode ctrl chars
* `5e4fae26e` feat(search) - possibility to search in selected upload only
* `d03ed3493` feat(gui): Add Bucket link for license view page
* `f9cdc2d38` feat(conf): add textarea in conf page for notes
* `42ffc492f` feat(nomos): Apache detection
* `5db568481` feat(rest): Get file info from hash
* `d1fdfe4d7` feat(modal): use jquery-ui dailog instead of plain modal
* `d013e0903` feat(noticeImport): add child modal to load notice files
* `d1583ca87` feat(notice_import): Increase size of textarea and fix a max notice preview length
* `0642f8ed2` feat(notice_import): Import notice file content into acknowledgement

### 3.9.0 (November 30th 2020)

This release adds important corrections to
[3.9.0-rc2](https://github.com/fossology/fossology/releases/tag/3.9.0-rc2)

The release 3.9.0 introduces following major changes:
- Introduce support for Ubuntu Focal Fossa (20.04)
- Drop support for Debian 8 Jessie
- Obligations now refer to license conclusions
- Auto deactivation of copyrights for irrelevant files
- REST API now supports upload from URL
- Display time in browser's timezone wherever possible
- Ability to export Copyright CSV

The release 3.9.0 also introduces new agent `Spasht` which connects with
ClearlyDefined server and pulls information like License and Copyrights (if
available).
To use it, upload a package, open it and goto Spasht page from the
top yellow bar. From there, search for the desired package on ClearlyDefined and
schedule the scan. Licenses and copyrights will appear on the same page.

#### Credits to contributors for 3.9.0

From the git commit history, we have following contributors since
[3.8.0](https://github.com/fossology/fossology/releases/tag/3.8.0):
```
> adityabisoi <adityabisoi1999@gmail.com>
> Akash-Sareen <akash7sareen@gmail.com>
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Avneet Singh <Avneet.Singh@sony.com>
> Dineshkumar Devarajan (RBEI/BSF6) <Devarajan.Dineshkumar@in.bosch.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Lakshmi Bhavani <Nagavalli.LakshmiBhavani@in.bosch.com>
> Marion Deveaud <marion.deveaud@siemens.com>
> Michael C. Jaeger <michael.c.jaeger@siemens.com>
> Mikko Murto <mikko.murto@hhpartners.fi>
> Piotr Pszczola <piotr.pszczola@orange.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> sjha2048 <sjha200000@gmail.com>
> vivek kumar <vvksindia@gmail.com>
```

#### Features
* `d9ed388d5` chore(documentation): updating basic license info in UI
* `81e029137` feat(about): add new page for third party licenses
* `a333fb5eb` update(org): added focal-fossa support
* `010f94747` chore(spdx): bump spdx version to 2.2

#### Corrections
* `6a2ce3dee` fix(spasht): Fix advance search
* `be5189da4` fix(swh): Update User-Agent, lowecase SHA256
* `bd65ab70b` fix(ununpack): Correct the mimetype for deb files
* `4f4f311b2` fix(copyrightDao): Change statement in updateTable

#### Infrastructure
* `87829c8e4` feat(cd): Publish release packages with Actions
* `bc2f2eb07` update(org): drop debian 8 support

### 3.9.0-RC2 (Oct 8th 2020)

This pre-release adds important corrections to 3.9.0-RC2.

#### Corrections

* `4df3358c2` perf(ui): Reduce load time for tree view
* `c56ae1733` fix(ClearingDao): Get uploadtree table name

### 3.9.0-RC1 (Aug 31st 2020)

With every new release, FOSSology brings various bug fixes, infrastructure changes and various new features.

You can check the list of commits in release bellow but few highlights for the release will be:

* New agent **Spasht** which searches for decisions from ClearlyDefined.io and bring them to FOSSology.
* New Docker image to use in CI
* PostgreSQL 12 support
* New page to check status of all job in a server
* Using user's time zone to change time in UI
* Ability to specify GIT branch in Upload from VCS
* Reuse of deactivated copyrights
* Remove OpenSSL dependency and use `libgcrypt`
* Removal of redundant MD5 checksum from `licenseRef.json`

#### Credits to contributors for 3.9.0-RC1

From the GIT commit history, we have following contributors since [3.8.1](https://github.com/fossology/fossology/releases/tag/3.8.1):

```
> adityabisoi <adityabisoi1999@gmail.com>
> Akash-Sareen <akash7sareen@gmail.com>
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Avneet Singh <Avneet.Singh@sony.com>
> Dineshkumar Devarajan (RBEI/BSF6) <Devarajan.Dineshkumar@in.bosch.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Lakshmi Bhavani <Nagavalli.LakshmiBhavani@in.bosch.com>
> Marion Deveaud <marion.deveaud@siemens.com>
> Michael <michael.c.jaeger@siemens.com>
> Mikko Murto <mikko.murto@gmail.com>
> Piotr Pszczola <piotr.pszczola@orange.com>
> Sahil <sjha200000@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> vivek kumar <vvksindia@gmail.com>
```

#### Corrections

* `f24547d85` fix(licenseRef): Fix type in array_map
* `f09761d20` fix(licenseref): handle errors license errors
* `2fcbff0b1` fix(Nomos): Added a new License signature
* `a7908a68d` fix(spasht-ui): Removed extension from the spasht search
* `5638337f2` fix(report): Don't group results with custom text
* `865f8ac02` fix(licenseRef): Fix import of licenseRef.json
* `23504c7a2` fix(lib): Correct non-default argument position
* `059ed1cfb` fix(lib): Remove extra parameters
* `485ddc75d` fix(obligation): Refer to license conclusions
* `eb60785b5` fix(rest): fixed ignoreScm flag when input is false
* `7880e856a` fix(delagent): Remove clearing_decision and lrb
* `a41120ad8` fix(spdxReport): add missing artifact to file path in spdx reporting
* `40792c1c2` fix(ui): Use default timezone if not set
* `75c59cde8` fix(bulk): add class to show text highlighted for matched page
* `3931634dd` fix(spdx): Fix duplicate copyrights
* `0728965c8` fix(clearingDao): Copy acknowledgement with event
* `441d224e4` fix(clearingCount): do not add count as cleared in case of to be discussed
* `58562d7da` fix(nomos-standalone): included changes for the PR #1600
* `c07cf2ee0` fix(SCM): fix warnings in apache log if SCM is not selected
* `eb08c0e2b` fix(unifiedreport): Get department from config
* `7b8b5ef77` fix(fo-installdeps): added a missing dependency
* `420903394` fix(upload): Fix the check for expire_action
* `1b062b135` fix(spdx): fix spdx-rdf export.
* `de279e3de` fix(libschema): Schema fix for PostgreSQL 12
* `421a4221f` fix(AdviceLicense):show error message on failed merge
* `c08a54f8c` fix(rest): Get upload summary without UI

#### Infrastructure

* `73c5b6a08` chore(alljobs): Restrict to read
* `b002820e9` perf(license_candidate): Create PRIMARY KEY
* `7fecfc84e` test(GetHashes): Change tests for sha256
* `685e78632` table reference fixed
* `52f4096a0` chore(delagent): Remove OpenSSL dependency
* `926f8540f` chore(ununpack): Remove external checksum code

#### Features

* `e35f31c73` feat(licenseRef): update existing licenses
* `cb06d031e` feat(spasht): Use dialog for details
* `758ed16ab` feat(spasht): Change UI and remove some steps
* `e27a9862d` feat(spasht): Added Agent spasht
* `3aa573e33` feat(reuser): reuse deactivated copyrights
* `dbd411529` feat(showjobs): Show delete file name
* `87f8876f5` feat(ci): Use FOSSology scanners in GitLab CI
* `46c1384fb` feat(decisions): auto deactivate copyrights
* `84185975d` feat(conf): add feature to change all local clearings to global from conf
* `bd5662577` feat(ReportDao): send heartbeat from Dao to keep the agent alive for large files
* `a737c4bcb` feat(jobs): Show all running jobs
* `462591e8c` feat(export): Export Copyrights
* `4e025c1b7` feat(download): Limit source code download only for users with specified access rights
* `00a68a13d` feat(ui): Display Job timings in browser timezone and formatted date time to Y-m-d H:i:s
* `2ca5257cd` feat(rest): Upload from URL and server
* `08132e0c9` feat(rest): extend upload model with filesha1
* `0bc755ad7` feat(globalDecision): show warning if the candidate license is added to license list
* `8f395952a` feat(upload): Add possibility to upload specific Git branch
* `a35edb985` feat(groups): Update default group for user
* `e834b41bd` feat(spdx export): Add sha256 to exported spdx.
* `d90175480` feat(copyright): Enable agent to read authors from ROS catkin package manifest files as per spec
* `bbaf4f071` feat(nomos): Print JSON directly to STDOUT
* `807f6614b` feat(nomos): Optimize JSON output
* `af22a5b21` feat(scanner): ignore files from scanning using mimetype
* `59464a500` feat(maintenance): Remove orphan log files

### 3.8.0 (April 23rd 2020)

This release adds important corrections to
[3.8.0-rc1](https://github.com/fossology/fossology/releases/tag/3.8.0-rc1)

The release 3.8.0 also introduces new agent `Software Heritage`. There is a
special note about this agent.
> Due to rate-limiting from Software Heritage, the agent might run slow. Please
> check the **Geeky Scan Details** of the agent to understand the cause of the
> delay.

Please check https://archive.softwareheritage.org/api/#rate-limiting for more
info.

Some notes about the UTF-8 database. The copyright (and sister) agent now
creates only UTF-8 string. So it is safe to update to Postgres with UTF-8
encoded database. For more information, please refer to the wikipage
[Migration to UTF-8 DB](https://github.com/fossology/fossology/wiki/Migration-to-UTF-8-DB)

#### Credits to contributors for 3.8.0

From the git commit history, we have following contributors since
[3.8.0-rc1](https://github.com/fossology/fossology/releases/tag/3.8.0-rc1):
```
> adityabisoi <adityabisoi1999@gmail.com>
> Anupam <ag.4ums@gmail.com>
> Carmen Bianca Bakker <carmenbianca.bakker@liferay.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Kaushl2208 <kaushlendrapratap.9837@gmail.com>
> Mikko Murto <mikko.murto@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> sjha2048 <sjha200000@gmail.com>
```

#### Features
* `5ca84b7a4` feat(SWH): catch exceptions in case of bad response
* `d8ac396c7` feat(DB): Recode copyright tables to UTF-8
* `3bbb7156a` feat(SWH): add time to reset if X-RateLimit-Limit reached for SWH agent
* `144b81c19` feat(Copyright): Fixed the checking of config file in wrong folder
* `3b6f4fac6` feat(unifiedReport): move obligations to DAO layer remove unused file

#### Corrections
* `148b774e5` fix(delete): Do not remove upload_pk
* `6296b6738` fix(schema): Match schema with schema export
* `c49c5a691` fix(spdx-rdf-report): Fix comments in export.
* `8880d1a98` fix(travis): Fix build config warnings
* `c9c6f3cb9` fix(fo-installdeps): Added missing Fedora dependecies
* `7d905ed6a` fix(AdviceLicense):Show error message on failure
* `c0a4b25b3` fix(package): fix syntax
* `eec0a5faa` fix(rest): Remove hostname from JWT

#### Infrastructure
* `88f6de2e8` fix(travis): Fix page deploy stage
* `a106def1c` fix(packaging): Create apache softlink on source
* `b3abe195b` docs(contributing.md): Fixed broken link in contributing.md
* `018de9705` fix(git) : add php.ini to gitignore
* `09b48ffe5` docs(README): Refer to the correct file for the licenses
* `5a28eabdc` fix(apache): Enable fossology on source install

### 3.8.0-RC1 (Mar 05th 2020)

This release brings a number of corrections (see below) and changes to the infrastructure. But it also adds new features to FOSSology, including:

* A new agent added `Software Heritage Analysis` which searches for file existance in softare heritage
* Reuse of report configuration settings
* New decision type `do not use`
* Consider a particular license for its obligation to be listed in report in conf
* Add external authentification feature
* New dashboard pages with submenu

#### Credits to contributors for 3.8.0-RC1

From the git commit history, we have following contributors since [3.7.0](https://github.com/fossology/fossology/releases/tag/3.7.0):

```
> Andreas J. Reichel <andreas.reichel@tngtech.com>
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Bartłomiej Dróżdż <bartlomiej.drozdz@orange.com>
> dineshr93 <dineshr93@gmail.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Michael <michael.c.jaeger@siemens.com>
> Nicolas Toussaint <nicolas1.toussaint@orange.com>
> Piotr Pszczola <piotr.pszczola@orange.com>
> sandipbhuyan <sandipbhuyan@gmail.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
> Woznicki Pawel <pawel.woznicki@orange.com>
```

#### Corrections

* `31d4c7b39` fix(copyright): Remove non utf8 strings
* `ddcaa8eb9` fix(conf): Update install/defconf/fossology.conf.in
* `50e7cf569` Fix(Dockerfile): make clean install clean
* `2143f6aec` fix(lib): Check group on local decision only
* `5a7bd82a8` fix(reuser): Run decider after reuser
* `d97f9cec9` fix(ext-auth): check that external authentication is configured
* `c20b7fb0f` fix(SHagent): add proxy settings, add SH agent to PHPCS
* `c60150d59` fix(bulk): Fix dropdown bulk on folder level
* `251fd8dfd` fix(ojo): Add lower limit to license length
* `fbc86017c` fix(nomos):test-cases
* `a1b287e06` fix(nomos) : CC-BY-SA identification
* `4c04b59bc` fix(nomos) : segfault for large offset value
* `ffdd07786` fix(highlight): highlight for reference text that exists in different page
* `a3323dac8` fix(log): fix warnings from apache error log
* `415b2ae78` fix(view-license): Browse file without scanner ars
* `efe1301ab` fix(ui): decision and scope for licenses
* `7c9ca59ef` fix(CHANGELOG): Fix the changelog
* `87e709233` fix(build-dep): Add PHP-CLI as build dependency
* `73fe66278` fix(ojo): Handle dual-license and SPDX new naming
* `b84b6d26b` fix(admin): Allow read user to edit user
* `c839f02b6` fix(copyright): Wait for ajax calls
* `ca9a1908c` fix(license-csv): Handle candidate licenses
* `bdaad200d` fix(license-csv): Update license if exists
* `50558dcb5` fix(rest): Hide sensitive user info
* `79d42b791` fix(wget_agent): Fix possible memory corruption and leaks
* `a84db62f8` fix(wget_agent): Archivefs: Prevent possible buffer overflow
* `1c5498f0c` fix(wget_agent): GetURL: Part 3 - Prevent possible buffer overflow
* `afed499a3` fix(wget_agent): GetURL: Part 2 - Prevent possible buffer overflow
* `1db296e2c` fix(wget_agent): GetURL: Part 1 - Prevent possible buffer overflow

#### Infrastructure

* `88d98224f` Revert "Merge pull request #1498 from siemens/feat/rest/provide-group-upload"
* `a55e1e818` chore(wget_agent): Remove redndant code
* `8cb62708e` chore(nomos): Rename test file
* `12b7da1d7` chore(lib): Move agent list to common place
* `f50ff3ca6` refac(wget_agent): DBLoadGold Don't open pipe before checking Fin
* `7237b38b8` refac(wget_agent): DBLoadGold: Prevent possible buffer overflow
* `d9beb426a` refac(wget_agent): Remove superfluous rc_system variable
* `2244a9150` refac(wget_agent): Part 1 - Prevent possible buffer overflow
* `dff78a713` refac(wget_agent): add function for destination of wget command

#### Features

* `c3dca9ae0` feat(migrate): Program to make file UTF-8 compatible
* `b31ba2ff1` feat(unifiedReport): include DNU information in assesment summary
* `80a184dad` feat(SWHagent): add status of request to DB
* `e2b92bc15` feat(auth): Add external authentification feature
* `8f4c63010` feat(ojo): Remove upper limit from license name
* `2a6ab581b` feat(rest): Get the license list for upload
* `164fb898f` feat(reuse): add reuse of report configuration settings
* `28111118e` feat(SHagent): add new table column with Software Heritage Status
* `d15c64d3b` feat(email-smtp-config): Add SMTP User field into Fossology email
* `6f00ed38e` feat(rest): Add group context (groupName param) for REST Api calls
* `9d981d2ce` feat(rest): Send upload summary
* `f4b56e186` feat(upload): add feature to change permission of a all uploads in a folder
* `77d4d8895` feat(decisions): add new decision type do not use
* `74aa499d2` feat(ui): Place DataTables processing at top
* `f3bb51eac` feat(software-heritage): Update the description in debian package
* `a05ac660d` feat(software-heritage): Update the composer.lock file
* `d9fdbd6c1` feat(softwareHeritage): Update software heritage details in debian package
* `1e994d646` feat(softwareHeritageView): Show the details of software heritage in the license list page
* `de6a46b85` feat(softwareHeritageView): Show the details of software heritage in the license list page
* `71d785cda` feat(software-heritage): Make softwareHeritage dao function and add all
* `abb463dd9` feat(software-heritage): Redundancy check while inserting softwareHeritage record
* `6a9786544` feat(software-heritage): Make the ui section of software heritage
* `0869f6c66` feat(software-heritage): Create a software heritage agent
* `bf47edabd` feat(db): Make table of software heritage to store information
* `034c48aa2` feat(dashboard): New dashboard pages with submenu
* `9fe3d90d3` feat(unifiedReport): exclude scanner found copyrights of irrelevent files
* `66a009d83` feat(conf): add obligations to consider a particular license for its obligation

### 3.7.0 (Dec 11th 2019)

This release adds important corrections to
[3.7.0-RC1](https://github.com/fossology/fossology/releases/tag/3.7.0-rc1)

### Contributors

Credits go to the following persons for this release since
[3.7.0-RC1](https://github.com/fossology/fossology/releases/tag/3.7.0-rc1):

```
> Anupam Ghosh <anupam.ghosh@siemens.com>
> Gaurav Mishra <mishra.gaurav@siemens.com>
> Martin Michlmayr <tbm@cyrius.com>
> Maximilian Huber <maximilian.huber@tngtech.com>
> Michael C. Jaeger <michael.c.jaeger@siemens.com>
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>
```

#### Corrections

* `7cdc6b50a` fix(obligation): Move candidate licenses
* `c74f2f4af` fix(obligation): Associate all lic with same name
* `68094159b` fix(copyright): only scanner finding copyrights to unifiedreport
* `23cb2f66a` fix(counter): Optimize clearing counter queries
* `3885ac14d` fix(db): Optimize license browse queries
* `96a4da4c3` refactor(report): edited global license code make it available for unified repot fix php codesniffer
* `08ac47678` fix(decider): remove force dependency of nomos and monk for ojo decider add nomos dependency if required

#### Infrastructure

* `48c0caa14` chore(composer.json): updated symfony/dependency-injection version
* `252bbaeb2` chore(installdeps): remove php-yaml from os level and add it to composer update composer.lock..
* `2e158034e` docs(changelog): fix typo
* `d85038afc` chore(unifiedReport): change phpword to a latest version update composer.lock with new changes
* `a674aa9e3` chore(docker-compose): harmonize versions with sw360chores

### 3.7.0-RC1 (Oct 24th 2019)

This release was created in order to bring important changes for the REST endpoints to a release, so integration, for example with sw360, work on a release but not with latest master. Besides improvement is the extension of the decider agent to allow for decisions based on found SPDX-License-Identifier tags found by the ojo agent.

#### Contributors

There are many ways to commit to the source code, but if you count the commits to master, then the credits go to the following persons for this release since `3.6.0`:

```
> Andreas J. Reichel <andreas.reichel@tngtech.com>,
> Anupam <anupam.ghosh@siemens.com>,
> Bruno Cornec <bruno.cornec@hpe.com>,
> Gaurav Mishra <mishra.gaurav@siemens.com>,
> Maximilian Huber <maximilian.huber@tngtech.com>,
> Michael <michael.c.jaeger@siemens.com>,
> Onyemenam Ndubuisi <onyemenamndu@gmail.com>,
> Piotr Pszczola <piotr.pszczola@orange.com>,
> Shaheem Azmal M MD <shaheem.azmal@siemens.com>,
> Toussaint Nicolas <nicolas1.toussaint@orange.com>,
> vivek kumar <vvksindia@gmail.com>,
> abirw,
```

#### Features

* `8bbe52d2b` feat(rest): add auto conclusion for ojo findings if
* `af3f5738d` feat(license): Provide predefined license comments
* `651a89088` feat(rest): Provide group ID during POST upload
* `1a82e74a2` feat(decider): add auto conclusion for ojo findings if no contradiction with other findings
* `71d1b7871` feat(rest): Provide API version as an endpoint
* `808fa1db2` feat(rest): Upload packages from VCS
* `fa2c27d16` feat(upload_vcs.html.twig) Use HTML <option selected>
* `7887f02ad` feat(spdx): add user found copyrights to SPDX reports
* `0505ca138` feat(upload_vcs.html.twig) make Git the default VCS rather than SVN
* `8a5f14fd3` feat(pbconf): adapt to pb 0.15 and new fossology 3.3+
* `5a9a341be` feat(api): Add pagination to jobs endpoint
* `7a190c110` feat(api): Add OJO analysis to REST API
* `12f064abe` feat(api): Get job status and ETA
* `8989c1e17` feat(copyright): New directory scan and better JSON

#### Corrections

* `49fcfa05a` fix(rest): do not schedule decider if the option is empty
* `1045cf4f6` fix(readmeoss): added edited global license text in readmeoss
* `213222d31` fix(notices): updating notice file, debian copyright and spdx lic info
* `9e524ef52` fix(rest): getUploads - invoke getRows with proper parameters
* `416da0abc` fix: fix formatting as suggested in comment
* `9a3f86d64` fix(groups): add validations and remove CONSTRAINTS
* `e4e811f22` fix(geekyscan): make full job report link more descriptive closes #1346
* `fcc5ef797` fix(deps): Added missing php-pgsql
* `41fe2b4cd` fix(deps): Fix dependencies for Debian Buster
* `f0348b64c` fix(buckets): Prevent possible buffer overflow/-run
* `5f77fe45d` fix(ununpack): Fix compiler warnings for Debian 10/gcc8
* `7beb859d1` fix(pkgagent): Avoid possible buffer overrun with strncpy
* `359ae6101` fix(lib/c): Prevent possible buffer overflow/-run
* `89e461394` fix(delagent): Fix possible buffer overrun
* `7ee6b5955` fix(mimetype): Fix usage of strncpy, remove memset
* `4a2829ef2` fix(testing/db/c): Prevent buffer overflows
* `c1d165af6` fix(ununpack): Increase buffer sizes to prevent overflow
* `7b62b6759` Attempting to fix bug in fo_nomos_license_list

#### Infrastructure

* `e559e388a` chore(control): Remove ninka from debian/control
* `c4df71415` refactor(fossology): Refactor modularity
* `8c3caef81` chore(composer): Bumping composer to 1.9.0
* `ff1aa9fe3` chore(ninka): Remove Ninka packaging from master
* `f0e56b1c5` test(licenseStdCommentDao): Add test cases for DAO

### 3.6.0 (Sep 10th 2019)

After two release candidates, making fixes for migration tests, unified report and
load issues with tree-view, FOSSology is stable enough for a new release. The main features
of the 3.6.0 release can be found under
[RC1](https://github.com/fossology/fossology/releases/tag/3.6.0-rc1). Particular
corrections after RC1 can be found under
[RC2](https://github.com/fossology/fossology/releases/tag/3.6.0-rc2).

Few interesting features in this release are:

* A new agent named `ojo` (eye in Spanish) which does dedicated searches for the 'SPDX-License-Identifier' statements
* Improved handling of manually added copyright statements to files
* Improvements to the SPDX reporting, for example output also of comments
* Calculating the SHA256 values for files from now on, because that is going to be used for integration of, for example, Software Heritage or Clearly defined

#### Credits to 3.6.0

From the git commit history, we have following contributors since 3.5.0:

> @andi8086 <andreas.reichel@tngtech.com>,
>
> @ag4ums <anupam.ghosh@siemens.com>,
>
> @hastagAB <classicayush@gmail.com>,
>
> @chienphamvu <chienphamvu@gmail.com>,
>
> @ChristopheRequillart <christophe.requillart@atos.net>,
>
> @GMishx <mishra.gaurav@siemens.com>,
>
> @maxhbr <maximilian.huber@tngtech.com>,
>
> @mcjaeger <michael.c.jaeger@siemens.com>,
>
> @NicolasToussaint <nicolas1.toussaint@orange.com>,
>
> @PeterDaveHello <hsu@peterdavehello.org>,
>
> @rlintu <raino.lintulampi@bittium.com>,
>
> @sandipbhuyan <sandipbhuyan@gmail.com>,
>
> @shaheemazmalmmd <shaheem.azmal@siemens.com>,
>
> @tiegz <tieg@tidelift.com>,
>
> @vivekaindia <vvksindia@gmail.com>

#### Corrections

* `7a17bc7b6` fix(src/ununpack/agent/utils.c) update SHA256 of existing entries, patch proposed by @fogninid.
* `bdd004e43` fix(src/ununpack/agent/utils.c) remove unused #define
* `ef4820fcd` fix(ajaxExplorer): Reduce view creation
* `f16c0eecb` fix(importReport): update easyRDF to a stable version

### 3.6.0-RC2 (Aug 24th 2019)

This pre-release adds important corrections to 3.6.0-RC2.

#### Corrections

* `f4c2de9df` fix(dbMigrate): Fix PHP syntax error
* `69b03a368` fix(copyright): Check if empty decision sent
* `83897a185` fix(obligation): add default value if the obligation type and classification is empty
* `90b7f551f` feat(unifiedreport): add candidate licenses to the list of obligations
* `49d901c02` fix(ojo): Remove call to omitEndingLineFeed on<0.6

### 3.6.0-RC1 (Aug 12th 2019)

This release brings a number of corrections (see below) and changes to the infrastructure. But it also adds nw features to FOSSology, including:

* A new agent named `ojo` (eye in Spanish) which does dedicated searches for the 'SPDX-License-Identifier' statements
* Improved handling of manually added copyright statements to files
* Improvements to the SPDX reporting, for example output also of comments
* Calculating the SHA256 values for files from now on, because that is going to be used for integration of, for example, Software Heritage or Clearly defined

## Contributors

There are many ways to commit to the source code, but if you count the commits to master, then the credits go to the following persons for this release:

```
ag4ums
shaheemazmalmmd
NicolasToussaint
rlintu
sandipbhuyan (GSOC 2019!)
ChristopheRequillart
GMishx
hastagAB (GSOC 2019!)
vivekaindia (GSOC 2019!)
maxhbr
mcjaeger
PeterDaveHello
tiegz
chienphamvu
```

## Features

* `21bd38428` feat(api): Cache Slim DI container
* `840ba9b8d` feat(ci): Run travis jobs on Xenial
* `62c86b865` feat(codesniffer): check php codesniffer through travis
* `64878b7d7` feat(copyright): Show text findings in copyright
* `1bbc203cc` feat(cp2foss): cp2foss prints out FolderPk as well
* `cc16066ef` feat(datatable): add select plugin of datatable to change paging
* `d3641939e` feat(db): Calculate the sha256 value of the uploading file and store it in database
* `6b705539f` feat(db): Store SHA256 of the uploaded file
* `7bc49eaec` feat(dbmigrate_3.5-3.6): add limit to process number of records
* `4790c6353` feat(licenseRef): add new functionality to add new licenses and update existing licenses from SPDX
* `d8076a088` feat(licenseref): convert licenseref file from sql to json format
* `5ab3fe831` feat(licenses): nomos merge error fixed
* `020595190` feat(licenses): SPDX identifier detection modified to include AND and OR options
* `84cbbbbea` feat(ojo): New license scanner for SPDX
* `fbfdc79fc` feat(spdx2): ignore files with no info in SPDX reports
* `92cbbc2a0` feat(spdx2): SPDX output does not yet show license comments
* `e514dc6d9` feat(ui): Add user description of available user in group management page
* `dcc74a9be` feat(ui): Show both user description and user name in 'Assigned to' list
* `57493d0f1` feat(unifiedReport): separate user and scanner findings of copyrights
* `f3c9e3df7` feat(unifiedReport): update phpword version from v0.13.* to v0.16.*

## Corrections

* `d528e4fb4` fix(obligations): fix UI and connected db to dropdown menu
* `f65397495` fix(admin-license-file): Fix update conclusion to self
* `de2f76fd0` fix(advicelicense): fix double select of risk in advice license remove select2 initialisation in macro
* `db9f8c8fa` fix(api): Adhere to specification
* `5ef99c95b` fix(build): Fix clean build from all dirs
* `61f06e348` fix(codesniffer): Fix errors reported by phpcs
* `1d1b94fbb` fix(copyright): Fix pagination of copyright
* `d6d2fabe3` fix(copyright): fix php notices in copyright hist view
* `53849883c` fix(dbmigrate_3.5-3.6): add single quotes to string and calculate actual minutes
* `0943d97ad` fix(download): Fix a call to non-static function
* `4fb3dd1f0` fix(init.d): Implement missing function
* `1a961298f` fix(migration): Make pfile sha calculation separate script
* `0c6d64741` fix(nomos): nomos crash (#1337)
* `9705b2d64` fix(pfile): Fix warnings in ununpack and wget
* `75fc1252f` fix(pfile): SHA256 is still optional
* `fd1dc495a` fix(reportConf): include correct array from  to fix report conf closes #1377
* `d6f62de15` fix(showjob): General fix after refactor
* `9659ac1b2` fix(showjobs): Check empty for allusers before updating
* `c2585dcb9` fix(showjobs): Fix the pagination
* `3f2117c46` fix(spdx2): remove dependency from upload table
* `de64361f7` fix(strings): correct typo
* `ad9c6d7bc` fix(UI): increased the size of upload to reuse window in upload files
* `40defdb6b` fix(ui): Show license findings for folder with single child
* `65896cd3e` fix(unifiedreport): fixed issue with irrelevant file display
* `5361fefd2` fix(unifiedReport): remove php warnings from job log
* `453da1f13` fix(upload): remove dependency from upload table for SPDX shift the report info to new conf page
* `e304e4e39` fix(vscode): Add vscode editor file to gitignore
* `b6fdf1121` fix(process): Fix the PHP agent installation

## Infrastructure and Testing

* `3bccc4078` test(ojo): Test cases
* `dcc429edc` chore(debian): Fix lintian erros and warnings
* `82653993d` chore(decisions): Store SHA256 of text findings
* `c2a22fb4a` chore(fo-installdeps): drop unsupported distros
* `3b6a06e28` chore(travis): Disable unnecessary addon to speed up tests
* `13e2cfd39` chore(travis): Enable ccache to speed up tests
* `df793036e` chore(travis): Enable composer cache to speed up tests
* `34b8784e0` chore(travis): Enable Fast-Finish to retrieve build result faster
* `0fc133583` chore(travis): Fix Coveralls execution path
* `a45fc6965` chore(travis): Fix Coveralls output json file not writable issue
* `f1676582d` chore(travis): Leverage yaml anchor for phpunit
* `b576acbdc` chore(travis): Remove deprecated Travis CI `sudo` config
* `fcff243fd` chore(travis): Run PHPUnit via phpdbg to speed up tests
* `90e7bdfe4` chore(travis): Set pipefail in Travis CI PHPUnit on PHP 7.0
* `e5f9651c6` chore(travis): Show ccache bins & statistics summary
* `2d24945f2` chore(version): Force the VERSION variable

## Refactorings

* `a7db0edd6` refac(showjobs): Refactor code to send JSON

### 3.5.0 (Apr 11th 2019)

After two release candidates, making fixes for REST API installation and various
migration tests, FOSSology is stable enough for a new release. The main features
of the 3.5.0 release can be found under
[RC1](https://github.com/fossology/fossology/releases/tag/3.5.0-rc1). Particular
corrections after RC1 can be found under
[RC2](https://github.com/fossology/fossology/releases/tag/3.5.0-rc2).

Mainly 3.5.0 adds more documentation, infrastructure improvements and support
for brand new FOSSology REST API.

Moreover, new functionality has improved JSON output for nomos and restructured
license detection for nomos. Last but not the least, FOSSology now have
capabilities to ignore files specific to version control systems from the
scanning improving scan times.

#### Credits

From the git commit history, we have following contributors since 3.4.0:

> @ag4ums,
> @ChristopheRequillart,
> @AMDmi3,
> @GMishx,
> @mcieno,
> @max-wittig,
> @maxhbr,
> @rlintu,
> @sandipbhuyan,
> @shaheemazmalmmd

#### Corrections

* `9c1bf18a9` : chore(docker): bump docker base image to stretch

#### Refactorings, Infrastructure

* `8df86b308` : fixup! chore(docker): bump docker base image to stretch

### 3.5.0-RC2 (Apr 3rd 2019)

This pre-release adds important corrections to 3.5.0-RC1.

#### Corrections

* `262634d99` fix(apache): Add rewrite string to apache conf
* `ba2b25ba6` fix(git): Add ubuntu log file to gitignore

### 3.5.0-RC1 (Mar 29th 2019)

#### Features
* `e63c17534` : feat(tokenExp): Make token max validity configurable
* `16762d5a8` : feat(rest): Use bearer auth instead of basic
* `229860e6f` : feat(nomos): Fix the JSON output
* `e72db0331` : feat(scm): correct  in cli/cp2foss.php and add comment in agent/utils.c
* `3b72db061` : feat(scm): ignore scm data when scanning
* `0f277a1cb` : chore(docker): update dockerignore
* `a4f136ab9` : feat(trac): Fix typo `flase` instead of `false`
* `8e314a12b` : feat(licenseref): add new exception text to fossology database
* `854817d74` : feat(report): Added new endpoint to create reports
* `62b25d07d` : feat(restapi): Post upload and get folders path
* `0f5991a74` : chore(restapi): Use Slim inplace of Silex
* `93dbe3df9` : feat(licenses): creative commons detection rewritten. Bug fixes.
* `94b0f1ee3` : feat(license-admin): Show obligations for license
* `3b3eb3f5e` : refactor(maintagent): refactor maintagent code
* `c46cb3bef` : feat(reused-info): Show reused package in info page
* `47307a87e` : docs(lib-php): Doxygen comments for BusinessRules
* `388bd2245` : refactor(view-page): Use Twig templates for info page
* `e47676311` : feat(maintagent): add feature to delete orphaned files from database
* `041b5770c` : docs(lib-php): Doxygen comments for Auth namespace
* `c9cc5cd01` : docs(lib-php): Added doxygen comments for Application namespace
* `ba2193c3b` : docs(lib-php): Added doxygen comment for Agent class
* `8805b55ca` : docs(libphp): Added doxygen comments for PHP common lib
* `8fda5381b` : docs(libcpp): Added doxygen comments for CPP library
* `a9e862baf` : test(nomos): Added test case for EPL in pom.xml
* `9a30827ee` : docs(templates): Fix minor typos in templates
* `e1608f9e6` : chore(fo-postinstall.in): Give better notification
* `b6645af42` : feat(licenseRef): check flag before updating the license text
* `6b6dbb186` : feat(copyright): select and replace copyright in bulk mode
* `34661939a` : docs(restapi): Option to create API documentation
* `af6ba64f9` : chore(common-job): Remove unnecessery changes
* `87cb1104f` : chore(restapi): Change the path for REST classes
* `bea3cf48c` : chore(restapi): Allocate namespaces to the files
* `07ab61055` : replay 6a1f712, 45f02535 and 8c3a710
* `7aa12b0a7` : add auth
* `8fb000fef` : feat(api): add fossology openapi specification
* `ec5ebeb99` : feat(select2): Use select2 lib for drop-downs
* `ab40ea0f8` : feat(pages): Deploy FOSSology GitHub pages using Travis
* `8fc071da7` : chore: add best practices badge

#### Corrections

* `ec9409ab1` : fix(restApi): Fix for missing plugins
* `5e9433d29` : fix(maintagent): do not delete decisions with scope 1
* `4a3c7cb01` : fix(Vagrantfile): Enable mod rewrite in vagrant for REST
* `14e1a4517` : fix(api): Change back to version 1, remove trailing '/'
* `7623c4436` : fix(schema): Use open api 3.0.0 to describe API documentation
* `93dbe3df9` : feat(licenses): creative commons detection rewritten. Bug fixes.
* `3b01a5333` : fix(fo-installdeps): Add php-mbstring to build deps
* `fa2378625` : fix(delagent): delete existing clearing events using delagent
* `6e26fdde1` : fix(scheduler): add check for empty results from query
* `d9fd5fe4b` : fix(filter): Update the filter in license browser
* `7e6506355` : fix(nomos): Detect EPL-1.0
* `78b41aee3` : fix(constraints): Also clean old constraints
* `36e22784c` : fix(licenses): restore regexp POSIX compatibility
* `2c71518ae` : fix(nomos): Fix license string checks
* `191abff84` : fix(nomos): Use space as separator
* `47b71300f` : fix(restapi): Implement TODOs
* `a115b460a` : fix(restapi): Use FOSSology functions
* `11486eea9` : fix(response): Use JsonResponse instead of plain Response
* `0ccea49a5` : fix(libschema): Remove schema to match PHP strings
* `b71c25696` : fix(bulk-license): Resize the dropdown for bluk license
* `e8bc89878` : fix(web.postinst): Reflect changes from php-conf-fix
* `7e3e9b081` : fix(ScheduleAgent): Prevent multiple agent schedules
* `d05d30aa9` : fix(agent): Reschedule failed agents


### 3.4.0 (Nov 29th 2018)

After two release candidates, compatibility isues with updating from 3.2.0 and 3.3.0 have been resolved. The main features of the 3.4.0 release are found under the release candidate one for the 3.4.0 release (cf. https://github.com/fossology/fossology/releases/tag/3.4.0-rc1). Particular updates compared to the release candidate two (cf. https://github.com/fossology/fossology/releases/tag/3.4.0-rc2) are found below.

Mainly, 3.4.0, including the two release candidates, adds more documentation, infrastructure support and testing. It improves the support for Debian 9 stretch and Ubuntu 18.04 LTS. Moreover, new functionality has been added for running FOSSology from the command line including optimized output in JSON directly from the agent. Last but now least, updates have been applied to incorporate updates at the SPDX License List, such as the support for recognizing license exceptions.

#### Credits

Looking into the git commit history, it shows you all the users who have contributed to this release since 3.3:

> Tatsuo,
> Steve,
> Shaheem,
> Robert,
> rlintu,
> Michael,
> Maximilian,
> Gaurav,
> Dmitry,
> Anupam

#### Corrections

* `ee8b69c` fix(constraints): Remove more faulty constraints
* `c6743d5` fix(unifiedreport): add default count as 0 in result of scan

#### Refactorings, Infrastructure

* `faaaeed` fix(installdeps): Run child terminals interactively
* `6a298ea` fix(debian): Add php7.2 dependencies for Ubuntu Bionic
* `36c8da7` fix(debian): Install composer.phar before running it

### 3.4.0-RC2 (Nov 2nd 2018)

This pre-release adds important corrections to 3.4.0-RC1 and also the commit to update the changelog information and therefore features for the 3.4.0 release are found in the section for the release candidate 1 for 3.4.0 information listed below.

#### Corrections

* `b6cb10d` fix(dashboard): change comparison statements for postgres
* `5c463d1` fix(constraints): Remove faulty constraints
* `6b017b1` fix(resequence): Check the column name from DB
* `1983b29` fix(tests): fix PHPCS and phpunit testcases for deciderjob
* `592e48f` fix(core-schema): drop constraint from clearing_event and license_filter

#### Refactorings, Infrastructure

* `99a56a1` fix(postgresql): Fixed postgresql version to 9.6 and use a volume
* `0ce85bd` chore(copyright): Remove DISABLE_JSON macro
* `31be206` feat(copyright): Use package based dependency for json

#### Documentation

* `714d7f4` docs(changelog): updating changelog files

### 3.4.0-RC1 (Oct 18th 2018)

#### Features

* `114750a` feat(addLicense): Retain previous request values
* `be6e705` feat(adminLicense): Add search to each column
* `de88249` feat(bulk): inclusion of licensetext, acknowledgement and comment
* `e67549b` feat(composer): Updated development dependencies. * Switched to Mockery::pattern for pattern matching.
* `f5c89fa` feat(copyright): allow copyright to run standalone
* `fd302b1` feat(copyright): Enable recursion test
* `aef0070` feat(copyright): New JSON hpp version
* `5dd657a` feat(copyright): refactor copyrightDao check uploadtree table name
* `923982a` feat(docker-compose): Prepared docker-compose Dockerfile to replace the standalone Dockerfile. Changes: docker-compose.docker-entrypoint.sh: * Refactored bash script.
* `4ffe259` feat(docker): Implemented multi-staged build. * Added simple test for standalone copyright.
* `346546d` feat(docker): Replaced standalone Dockerfile with docker-compose. Changes: .dockerignore: * Added some unrelated files for docker.
* `058a41b` feat(emailConfig): Move config settings to sysconfig table
* `215b6d8` feat(fo-installdeps): Drop support for End-of-Life distributions.
* `7b804e1` feat(fo-postinstall): Added flag to omit all database operations.
* `063d5df` feat(fo-postinstall): Implemented best practises for bash script.
* `e9345a2` feat(fossology): Support for Bionic Beaver
* `d89c334` feat(info): change tag from input to textarea refactor ShowReportInfo add missing </tr>
* `843d319` feat(jquery): update jquery, datatable and select2 to latest versions 1) fix delete license color issue 2) fix width issue for user decisions
* `ab30fbf` feat(keyword): new-keyword-agent
* `192b1bb` feat(license_administration): add sorting, update datatable
* `7e22a09` feat(license_administration): Improvements of the existing implementation for the admin license table.
* `320865e` feat(licenses): add license test to licenseref.sql from SPDX
* `1b5f5ee` feat(licenses): exceptions detection restructured
* `6da4823` feat(licenses): gnu-javamail-exception bug corrected
* `baec095` feat(licenses): MPL detection bug corrected
* `0a6436a` feat(monk): add monk knowledgebase serialization
* `4c5c00a` feat(php): Improved PHP 7.2 support.Added support for PHPUnit 6.
* `49ffd73` feat(php): Replaced the class Object by builtin features.
* `0093d1a` feat(phpunit): Migrated to namespaced phpunit.
* `d89539e` feat(pkgagent): Drop support for RPM 4.4.x and RHEL/CentOS 5.
* `d09331f` feat(prepare-test): Print a warning to user for perpare-test
* `b202f93` feat(readmeoss): add license shortname above the license text
* `5033861` feat(serverUpload): Check for wildchar during upload
* `891bb45` feat(test): Bypass API rate limit of github.
* `fd237b0` feat(wget_agent): Mask password in log

#### Corrections

* `0bcd1b7` fix(ars_seq): Reset ars sequence to ars_master
* `7276004` fix(author): Fix multiple entries in author table
* `d764d97` fix(cli): there were minor problems in the variable names
* `cb9f5c0` fix(cliTest): Ununpack and copyright cli test fixes
* `24beb0e` fix(copyright): match copyright statements in full
* `945aad2` fix(copyright): replace ct_pk with table_pk for all copyright sub-agents
* `13898c2` fix(copyright): unify same column selection for both queries
* `4e1acb4` fix(cunit-version): Change script with new syntax
* `33b5ea7` fix(dataTable): Make removed class common
* `d3a1b31` fix(dataTables): Update datatable objects to 1.10
* `edb57fc` fix(decisions): Replace copyright_decision_pk with table_pk
* `8638bd7` fix(delagent): Extra drop statements in test
* `5a041ab` fix(delagent): Prevent unauthorized delete from CLI
* `d277eb7` fix(deps): Add Boost runtime dependencies
* `aac3126` fix(deps): Add boost runtime dependency fix #1175
* `af0d048` fix(docker-compose): Added missing mod_deps in the docker-compose.Dockerfile.
* `8f65e44` fix(ecc-view): Update ecc_decision table to match other schema
* `97647f1` fix(email): Prevent scheduler crashes
* `7813c66` fix(email): Update existing sysconfig values
* `87016ec` fix(fo-installdeps): Allow running without the option '-y'.
* `f88d428` fix(import-csv): syntax error in importing license-csv
* `42ab00a` fix(install_offline): Fix install_offline recipe to run in install folder
* `d2a3b85` fix(license-list): fix handling of getLicensesPerFileNameForAgentId result
* `e79df05` fix(licenseUpdate): update the license parameters with same shortname
* `dcbbff4` fix(mimetype): Quick fix for mimetype test
* `d83507a` fix(monkbulk): check the job status when scheduling multiple monkbulks
* `fa35d2a` fix(ninka): typo for ninka script
* `f77eeaf` fix(nomos): fix nomos crash
* `3ff7487` fix(nomos): fix posix incompatible regular expressions
* `181a9f6` fix(nonzipUpload): change the upload_mode
* `ce8dab0` fix(perpare-test): Give more options to users
* `85573fe` fix(pkgagent): Added support for RPM >= 4.14
* `793eb13` fix(postinstall): Look for compressed man pages also
* `44e2bd6` fix(scheduler): make init script wait for postgresql on startup
* `daa0bd0` fix(scheduler): revert make init script wait for postgresql on startup
* `b4fdf40` fix(schema): Add missing constraints
* `a87c285` fix(schema): check with the table property for current scheme
* `8a8097d` fix(sysconfig): Change structure of values
* `ddab228` fix(test): Remove prepare test from test target
* `4e670f8` fix(testCases): Fix scheduler and ununpack test cases
* `08cfa75` fix(travis): Missing phppcd on travis. * Switched the jobs "Syntax Check", "Static Code Analysis" and "Copy/Paste Detector" to sudoless.
* `051f91e` fix(unitTests): Fixing CUnit and PHPUnit tests
* `196731f` fix(uploadSrv): Copyright statement fix
* `94424d9` fix(user): Update user's current group while removing from group
* `426fdbb` fix(wget_agent): Ignore test_proxy_ftp, because it is flaky on travis.
* `372a308` fix(xenial): Added the missing runtime dependency php7.0-mbstring.

#### Refactorings, Infrastructure

* `51376ae` chore(deps): Implemented best practices for bash scripts.
* `adc9117` chore(doxygen): Add license header to doxygen conf file
* `69ce635` chore(tests): Removed dummy directories for testing.
* `a824fe1` chore(travis): Removed global environment variables usage. * Moved syntax check and static code analysis in separate steps.
* `252f663` chore(travis): Simplified travis.yml * Removed unused dependencies. * Removed caching for apt
* `a11fdfb` chore(unifiedreport): Remove extra space
* `762e9fc` chore(vagrant): Switched to ubuntu/xenial64. * Removed symlinks. * Added missing test dependency. * Added script to configure vagrant for development.
* `c13f06d` perf(copyright): Improve query for pfile on upload
* `ae66e68` perf(copyright): Use prepared statements to fetch pfiles
* `7473a25` perf(Docker): Use Debian Jessie slim variant
* `6e5b21c` refactor(monk): refactor and cleanup code
* `ee154ea` test(monk): add more unit and functional tests for monk

#### Documentation

* `f06006f` doc(screenshots): add wrongly deleted screenshots back into the source code
* `87d2d9c` docs(adj2nest): Added doxygen comments for adj2nest
* `3380961` docs(agents): Added supported CLI options to every agent
* `65764e5` docs(buckets): Added doxygen comments for buckets agent
* `1c6b01d` docs(contributing): Added steps to create PR
* `ce496cc` docs(CONTRIBUTING): Made required changes in note
* `b97f643` docs(copyright): Added doxygen comments for copyright agent
* `d013713` docs(debug): Added doxygen comments for debug plugin
* `b835cb6` docs(decider): Added doxygen comments for decider agent
* `b85cb8c` docs(deciderjob): Added doxygen comments for deciderjob agent
* `3bb9c64` docs(delagent): Added doxygen comments for delagent
* `a11054d` docs(demomod): Added doxygen comments for demomod
* `ddca886` docs(doxygen): Add doxygen conf file
* `a0387d9` docs(issue): Issue, PR template for new requests
* `6793719` docs(libc): Doxygen documentation for C library
* `cf1305c` docs(LICENSE): Create LICENSE to reflect in git
* `c3925cb` docs(main): remove outdated screenshots
* `42f7737` docs(mainpage): Include text from README.md
* `c415a5e` docs(maintagent): Added doxygen comments for maintagent
* `5fa5579` docs(mimetype): Added doxygen comments for mimetype agent
* `d652eb1` docs(nomos): Doxygen documentation for NOMOS agent
* `e0c4ecc` docs(nomos):update call hierarchy notes
* `a125fc4` docs(pkgagent): Added doxygen comments for pkgagnet
* `f1ae113` docs(README): fix readme for docker-compose and version numbers
* `d57c426` docs(README): Show only master build status
* `9d6f865` docs(readmeoss): Added doxygen comments for ReadmeOss
* `bd32a77` docs(regexscan): Added doxygen comments for regexscan
* `fe463a7` docs(reuser): Added doxygen comments for reuser
* `d1d8a6a` docs(scheduler): Added doxygen comments for scheduler
* `8f73e10` docs(sections): Created unique section name for every agent
* `2ca0ea5` docs(spdx2): Added doxygen comments for SPDX2
* `315f4d4` docs(unifiedreport): Added doxygen comments for unifiedreport
* `10d0588` docs(ununpack): Added doxygen comments for ununpack
* `07a8356` docs(ununpack): Fix few spelling mistakes
* `eb30027` docs(wc_agent): Added doxygen comments for wc_agent
* `e79fe95` docs(wget_agent): Added doxygen comments for wget_agent

### 3.3.0 (May 2018)

#### Features

* `4f48227` feat(ui): Color mapping for risk level in the ui.
* `12f5546` feat(nomos): extend unclassified license detection
* `9904b2c` feat(license): add acknowledgements to license clearing include acknowledgements in unified report include acknowledgements in readmeoss add acknowledgement tests
* `05dbf91` feat(licenses): add license text to fossology database from SPDX license text added for Abstyles, Adobe-2006, Adobe-Glyph, Afmparse, AMPAS, APAFML, bzip2-1.0.5, bzip2-1.0.6, CrystalStacker, curl, gnuplot, Intel-ACPI, MIT-CMU, SCEA, TCL, TMate rename license Intel-acpi to Intel-ACPI closes #1052
* `4299a5f` feat(obligation): update csv licesnse changes in the obligation table
* `be6434e` feat(licenses): missing INFILE added, IBM-reciprocal added

#### Corrections

* `e478bbf` fix(reuser): copy license decision in reuse
* `3d0c4b8` fix(schema): check for inherits when drop indexes
* `7bc1c82` fix(copyright): Fix copyright_decision table
* `d7cd66c` fix(upload-file): get distinct of groupid to insert in perm upload table
* `d76a643` fix(reuser): remove warnings and errors with testcases for reuse
* `1d6ff8e` fix(unifiedreport): Global license appears twice in Main license section
* `9269a36` fix(uploadSrvPage): Added feature so that users can update the name of upload manually
* `5b32c69` fix(phptestcase): Remove PHP 5.5 test case
* `8c3a710` fix(search.php): max records per page updated and documentation added
* `45f0253` fix(search.php): Fix the algorithm for total number of files matching the search criteria
* `6a1f712` fix(search.php): Fix the number of files matching the search criteria
* `9b9c214` fix(copyright): read only users should be able to read copyrights
* `3bf9fff` fix(perm): reading license information and browsing should be allowed with annonymous user
* `616d635` fix(delagent): change query which deletes all files with same pfile
* `5bcaa71` fix(browse): ajax browse required login
* `ca7ac1a` fix(obligations): remove extra else cases and fix warnings
* `000fdb3` fix(browseView): change style of checkbox button whole folder | Marked upload change job title as well as upload name if the multi readmeOss or SPDX2 scheduled
* `49d1c37` fix(cp2foss): fix cp2foss -X parameter usage

#### Refactorings, Documentation

* `f5aa2cf` refactor(common-ui): fix spelling mistake
* `24022a5` docs(vagrant): add vagrant setup documentation

### 3.2.0 (February 2018)

#### Features

* `99254a5` feat(unifiedreport): update phpword from v0.12.0 to v0.13.*
* `2aab236` feat(copyright-testcases): test for getallcopyrightentries for report
* `7dd9ac9` feat(unifiedReport): add user findings of copyright and ecc from files with non-agent finding
* `f0f484f` feat(treeView): add remove option for deletion of applied irrelevant decisions through file tree edit
* `ce78359` feat(schema): add new combined indexes to database tables copyright, author, ecc, clearing_decision, license_file, uploadtree_a
* `edaa1ad` feat(unifiedreport): add upload history url to title table add groupname next to username correct warnings in obligation
* `3d0c016` feat(report): report assessment summary checkbox selection

#### Corrections

* `62580c8` fix(delagent): Delete-Folder without deleting duplicate upload/s in other folders
* `85ae4ba` fix(lib): container.php access fix from cache
* `1a7fcde` fix(spdx): make SPDX-rdf and SPDX-tv templates consistend
* `19a4919` fix(unifiedreport): rearrange common and additional obligation text for word report
* `4deb48c` fix(deploy): Fix TimeZone computation when links are used
* `72ce275` fix(common-agents): add check for empty array and false
* `85ae4ba` fix(lib): container.php access fix from cache
* `33d5c2b` fix(ui): checkbox param call more adaptable with php 5.4
* `c48cc64` fix(www): change var name to not be used in RegisterMenus
* `dee3aa2` fix(bulk): separate td for each image and add width for select
* `283352a` fix(lib): decision for future occurrence of files
* `439c496` fix(treeView): removed license through edit, still exists
* `56b47ea` fix(candidateLicense): add a scrollbar to list of files in popup if exceeds 200px
* `b9d595f` fix(obligation): select obligation type and classification by default
* `a9003b1` fix(dep5): add missing endif for deb5 document
* `a9606e9` fix(copyright): fix edit and undo of copyright and ecc
* `90fd1d8` refactor(delagent) use template
* `9b00ca2` Revert "chore(changelog): update to commitlint"

#### Improvements on Infrastructure, Packaging and Testing

* `402ae25` fix(pb): general correction to enable rpm-based packages
* `9995f56` fix(rpm): Fix VERSION delivery under /etc/fossology
* `e431594` fix(rpm): Copy the correct VERSION file in /etc/fossology for spec
* `3b73c0f` fix(pb): smaller corrections to enable build on master
* `15e8645` chore(make): Remove declaration of COMPOSER_PHAR variable
* `33431fa` chore(pb): corrections on the project builder rpm build
* `bf814ff` chore(pb): Provides a working build infrastructure

### 3.2.0-RC1 (October 2017)

#### Features

* `b389a4c` feat(report): new word report
* `05a3061` feat(reportImport): some cleanup and minor improvements
* `cb24345` feat(reportImport): handle `orLaterOperator` correctly
* `025c4fe` feat(reportImport): add imported coyprights as decisions
* `5fdb4ce` feat(reportImport): add corresponding debain definitions
* `09b90a2` feat(reportImport): minor changes to satisfy older PHP versions
* `74f6241` feat(reportImport): parse also xml files
* `0fdba11` feat(spdx2): also export ninka and import data
* `0d46873` feat(reportImport): add option to create real licenses
* `3f95181` feat(reportImport): handle all arguments from UI
* `fa56a96` feat(spdx2Import): splitup to support other formats
* `0bc8788` feat(spdx2Import): refactoring and splitup of files
* `3d469b2` feat(spdx2Import): menu entry at "Upload::..."
* `bd20cc7` feat(spdx2Import): start to make conclusions optional
* `26c4187` feat(spdx2Import): compare only by sha1
* `9e68781` feat(spdx2Import): conclusions
* `26bff73` feat(spdx2Import): also import copyright statements
* `b7bd5b6` feat(spdx2Import): inital commit
* `effb5a2` feat(candidate): add delete feature to candidate licenses
* `3ee22e9` feat(copyright): allow to have multiple copyright decisions
* `7bc2e43` feat(treeView): add operation to make multiple files irrelevant
* `4716837` feat(backup): add s3 backup and restore
* `b69a771` feat(spdx2): add name field to extracted license info
* `6cb3192` feat(copyright): also show deactivated copyrights in the UI
* `eb6f19e` feat(spdx2): bump output version from 2.0 to 2.1
* `27225f4` feat(spdx2): strip invalid characters from non-spdx-compatible licenses
* `fb99c54` feat(docker-compose): increase apache verbosity
* `82356c2` feat(copyright): JSON output
* `956855f` feat(monk): JSON output
* `2a397af` feat(nomos): JSON output
* `5439978` feat(obligations): extend datamodel and obligation management
* `e9a1481` feat(copyright): split tables, separate tables for copyright and email,author,url
* `97fe4c4` feat(dashboard): add PHP info table
* `6fa1479` feat(delete): allow deletion of multiple uploads
* `d88a645` feat(delete): add select2 to folder select
* `c946064` feat(organize): allow searching for folders to copy/move to
* `d5871d7` feat(search): show number of search-results
* `5576025` feat(install): provide easy install script
* `268b689` feat(reuse): search all folders
* `e59ee82` feat(clearing): load clearing history in a model on click
* `919503c` feat(monk): make use of rf_active to detect monk scan for licenses
* `5523b77` feat(clearing): Add dialog box for text and comment feilds in single file clearing view
* `dfcc733` feat(Obligation): add first implementation of obligations and risks management
* `d8b291e` feat(clearingView): add action column in the leftmost position
* `cb582fa` feat(advice-license): add full text search to advice license
* `d19cb3b` feat(licenseList): add clearing decisions as part of license list generation and export in csv
* `64e5ffa` feat(copyright): split copyright histogram to seperate copyrights hist and email,author,url hist
* `21c2787` feat(GUI): yellow flag for files with decision type "To Be Determined"
* `a77cba4` feat(select-searchbar): add select2 searchbar

#### Corrections

* `f5e65fb` fix(reportImport): fix bug in reportImport, refactor file matching
* `29d5a7a` fix(delagent): Delete-Folder without deleting duplicate upload/s in other folders
* `c1f4cdb` fix(install): update packages deps for latest debian and ubuntu
* `55ce2bb` fix(debian9): add compatibility with debian9
* `e7603a5` fix(ui): own css file shoult be loaded last
* `c8af79d` fix(docker): .git should not be excluded via dockerignore
* `773c459` fix(obligations): select only single value for ob_classification and ob_type
* `8115de6` fix(obligations): rename obligation to license map table
* `4eda85d` fix(spdx): adhere file naming convention
* `8ba2d52` fix(travis): do not build multiple times
* `81d3590` fix(licenses): remove special chars from GPL-1.0, CPAL-1.0 and MPL-2.0
* `c49d4c0` fix(docker-compose): do not build twice
* `4616608` fix(www): Undefined index in admin-license-file.php
* `8a66754` fix(obligations): correct php syntax using phpcs
* `40775c5` fix(licenseref): changing shortname of 3DFX license to 'Glide'
* `9570e7f` fix(nomos): fix posix incomparible regular expressions
* `5d01085` fix(license): remove junk characters from LGPL-2.1 license text
* `4e222c8` fix(debian9): fix debian linker error
* `070ee8a` fix(jquery): remove old version of jquery from copyright-hist
* `a1b818a` fix(docker): use debian 8.8 for images
* `ecfefea` fix(delagent): remove unused variables
* `15f748a` fix(obligations): reintegrate lost changes
* `0cf4c7d` fix(folder-deletion): don't delete duplicate files in other folder ...
* `3105198` fix(licenseView): display clearing history for all clearings done on file level
* `e4e6cda` fix(delagent): delete child folder by parent id
* `cbe65df` fix(license-edit): fix regression with broken license edit list
* `6607b13` fix(resolveConflicts): resolve conflicts after merge from master
* `1985be3` fix(licenseExport): change the filename format of export license
* `5c5cb4f` fix(bulk-scan): don't schedule bulk scan, if no license/ref-text
* `35d12a5` feat(nomos): add new license RSA-Cryptoki
* `0b8e58b` fix(nomos): issue #754 (regex error)
* `14f6062` fix(libfoss): make agent processed items counting atomic
* `056a9a8` fix(spdx) typo 'spxd2' in document templates
* `9cdf1d6` refactor(reportImport): spdx2Import -> reportImport

#### Improvements on Infrastructure, Packaging and Testing

* `ca77960` chore(changelog): update to commitlint
* `fcb8357` chore(changelog): removed changelog
* `e268d89` chore(gitignore): add more entries to the blacklist
* `8dfcee6` chore(travis): fix changelog lint
* `833d4ce` chore(travis): enforce changelog
* `40495f7` chore(composer): composer enhancements
* `c77c7ad` chore(copyright): Fetch json.hpp on the fly
* `c655c84` chore(pb): vagrant file and spec file for pb run for centos7
* `ae26006` style(GUI): License Comment column needs line breaks
* `f62a4ec` chore(editorconfig): change indent_style and size
* `ed30641` chore(travis): Add PHP syntax checking to Travis
* `2a4b8d3` chore(jquery): update jQuery to 3.2.0 and jQuery UI to 1.12.1
* `acb62cc` chore(editorconfig): add editorconfig to project

### 3.1.0 (April 2017)

#### Smaller Features

* feat(nomos): add and correct nomos licenses
* feat(users): apply correct email validation
* feat(spdx2): allow licenses to be spdx compatible and adapt the templates enhancement needs review

#### Corrections

* fix(ninka): ninka needs a new dependency
* fix(docker): use a simpler Dockerfile for standalone build
* fix(browsefolder): added a check to see, if the folder is accessible
* fix(copyright): invalid pointer to regex
* fix(copyrightandeccview): added tooltip next to description
* fix(cp2foss): Refactor common perms
* fix(deshboard): Missing quotes around string literal
* fix(docker): change Dockerfile, docker run command
* fix(install): xenial support for postgres  in progress
* fix(make): do not place composer at `/tmp/composer/composer`
* fix(readme): Corrected the issue with mainlicense which was not displayed in readmeoss
* fix(scripts) : update timezone info to php.ini  bug needs review
* fix(setup): PHP warnings
* fix(spdx): fixes a list of SPDX compatibility bugs
* fix(test): fix copyright character
* fix(test): phpunit-bootstrap doesn't find Hamcrest  Category: Testing
* fix(ui): Added recent agent_pk in the place of any agent_pk
* fix(unpacking): fix unpacking of mime-type application/java-archive
* fix(user-creation): email needs to be unique and required
* fix(www): correct ETA in all job view
* fix(www): PHP warnings
* fix(cleanup): remove HACKING, install_locations.xls, build.xml
* fix(spdx): typo in template and bump LicenseListVersion
* fix(spdx): add files with no license found to generated output format

#### Improvements on Infrastructure and Testing

* chore(changelog): rename CHANGES.md to CHANGELOG.md
* chore(doc): update documentation, change releases link to Github
* chore(docker): docker usage information
* chore(docker): refactor dockerfiles, splitting containers, avoid rebuilding, etc.
* chore(gitignore): update gitignore
* chore(make): Fix a typo
* chore(make): Fix target name for stanalone nomos
* chore(php): remove 5.3, set 5.6, add 7.0 to travis-ci
* chore(setup): Set Postgres driver using variable reference
* chore(testing): travis php7.1, phpunit5 for php56
* chore(travis): remove gcc-4.4,clang-3.5, MAKETARGETS for gcc variants

#### Improvements on Packaging

* chore(packaging): first import of a pbconf tree
* chore(packaging): Fix EPEL dependency
* chore(packaging): updating existing debian packaging for current fossology  enhancement needs review
* chore(packaging): vagrant test file and config for httpd 2.4  enhancement
* chore(packaging): various enhancements with project builder

### 3.1.0-RC2 (May 21st, 2016)

#### Corrections

* feat(conf): added header/copyright information
* fix(showjob): Fixed problem with pagination and jobs not shown properly
* fix(showjobs): permission test left function to early and fixed jobs not shown properly
* fix(docker): only wait for postgresql if not on localhost bug

### 3.1.0-RC1 (April 15th, 2016)

#### Refactoring

* refactor(ui) rewrite upload pages
* refactor(ui) rewrite/refactor delagent and fix #273
* refactor(ui) escape strings which become HTML or SQL

#### New Larger Features

* New Dockerfile also used for Docker Hub, including composed containers with separate DB server
* DEP5 / debian-copyright file generation
* Adding tag-value format for the SPDX2 generation
* More efficient UI for bulk scan with multiple licenses at the same time

#### New Smaller Features

* feature(CONTRIBUTING.md) create initial CONTRIBUTING.md to support github feature
* feature(database) add reindexing option to maintenance agent, as turned out necessary
* feature(database) add some indexes and clusters to database
* feature(infrastructure) add coverage coverage, adding badge to README.md
* feature(license-list) improve UI for allowing more agents
* feature(spdx-tools) install spdx-tools script for vagrant and travis
* feature(ui) add security check to `user-edit.php`
* feature(ui) allow users to move and copy their uploads
* feature(vagrant) increase upload size setting
* feature(vagrant) support proxy from host_ip:3128

#### Corrections on the (PHP) UI

* fix(ui) fix ui-view error reporting [#615]
* fix(ui) fo_copyright_list - bad error checking, - bad error message #277 and #276
* fix(ui) handled exception in common-auth.php for incorrect username
* fix(ui) mark decisions as irrelevant from file tree [edit] option for uploads
* fix(ui) password handling for adding users improved
* fix(ui) #635: add parameter to URLs for showjobs
* fix(ui) only admin should be able to create groups
* fix(ui) repair error, which emerges in PHP <= 5.4
* fix(ui) repair issue mentioned in #660
* fix(ui) repair prepared statement in `admin-license-file.php`
* fix(ui-download) add $filenameFallback solve #589
* fix(ui) added branch name and separated version into string
* fix(license-browser) menu order with ECC and other corrected
* fix(upload-browser) visibility issues with selection of "entire folder"

#### Corrections on the Application Functionality

* fix(agents) fossupload_status print usage on error or --help
* fix(agents) repair the calls of `heartbeat` #560
* fix(composer) replace hash with correct one
* fix(copyright) fixing listing of copyrights at Readme export
* fix(copyright) increase maximum length of TLD's
* fix(copyrights) removed extra where condition which leads to miss copyright statements
* fix(dashboard) missing $this-> in method call
* fix(delagent) any user who is not the owner can delete any folder via /delagent -F
* fix(delagent) delagent error message wording
* fix(monk) fix one shot functionality
* fix(nomos) #340 correct path output on command line use
* fix(nomos) Remove extra spaces from the end of usage messages
* fix(reuse) Corrected lrb_ori to lrb_origin in bulkreuser
* fix(security) SQL injection vulnerability in read_permission
* fix(showjobs) correct view  for `&upload=-1` in the URL
* fix(spdx2) Remove control characters from SPDX output #591
* fix(spdx2) fix several bugs in DEP5 and SPDX2 reports
* fix(ununpack) remove extraneous parentheses
* fix(wget_agent) fix issue #298
* fix(wget_agent) fix issue #298

#### Corrections to the Database, Deployment, Tests and Framework

* fix(infrastructure) agent_desc not being initialized in install
* fix(infrastrcuture) add to vagrant support for ninka
* fix(infrastructure) Added DTD to index file to prevent phpunit test case failure
* fix(infrastructure) add fo_chmod and fo_folder to .gitignore
* fix(infrastructure) emoved SVN_REV from files and replaced Commit with commit_hash #331
* fix(infrastructure) error which emerges in PHP <= 5.4
* fix(infrastructure) improved protocol inference #580
* fix(infrastructure) Missing newline in fossupload_status utility
* fix(infrastructure) Missing newlines in fo_chmod error messages
* fix(infrastructure) reading of .fossology.rc for not parsing values
* fix(infrastructure) remove duplicate test and fix #579
* fix(infrastructure) SVN_REV and added branch name in version file #331
* fix(infrastructure) Write correct version of DB-scheme to DB
* fix(travis) `apt-get install -qq ...` times out
* fix(travis) use debian perl instead of cpan

#### Closed Issues for this Release

In order to see the issues that were closed so far for this release candidate, please refer to the github page:

https://github.com/fossology/fossology/issues?q=milestone%3A3.1.0+sort%3Acreated-asc+is%3Aclosed

Please note that you will find some of the issues open for 3.1.0 milestone - the goal of the release candidate is testing and wrapping things up, and as such the issue space for 3.1.0 will be cleaned up soon.

### 3.0.0 (November 5th, 2015)

* Correction of wildcard handling with the wget agent
* Correction of log file path settings in PHP test suite

### 3.0.0-RC1 (October 25th, 2015)

#### New Features

**Feature** : Brief Explanation

**New folder navigation** : Jquery based table UI for downloads including sorting and filtering with more handling attributes per upload.

**New license UI for editing concluded licenses** : Instead of providing a separate UI for license conclusion, now a single file view license UI allows for efficient license situation review: highlighted texts and selected licenses are moving together to one view now.

**Re-use of license decisions** : At uploading a new file, a user can select existing uploads for reusing already applied license decisions, if the file hash is the same.

**Bulk assignment of license decisions based on text phrases** : When identifying a phrase hinting to particular license (e.g. "license info can be found in readme"), the user can define this text as search string and assign a license decision to every matching file.

**Auto-decision of the Monk and Nomos scanner find the same license in the same text area** : If both scanners find the same license by short name, then a license decision can be applied automatically.

**Adding Ninka as optional scanner** : At upload or at scheduling jobs, the user can run Ninka scanner with FOSSology as third license scanner.

**New UI for editing copyrights** : Separate display for URL, E-Mails, copyright statements and authorship notes.

**Adding the concept of candidate licenses, to let users add licenses as candidates for the system** : New licenses must be added carefully to the server database. However, in order not to stop a user a reviewing an upload, candidate licenses can be registered for addition to the server by the server admin later.

**License import and export using a CSV interface** : Using CSV formatted files, licenses with the reference texts can be imported and exported to the FOSSology server.

**Adding readme / copying file generation** : Concluded licenses and copyright statements are written into a text file that is information for the distribution.

**SPDX 2.0 file generation** : Based on the scan results and concluded licenses, SPDX 2.0 XML format is generated (passes verification tool).

#### Issues: Corrections

Issue No. : Issue Title : Resolution

* #508 : Copyright agent fails to show copyrights without license information : Corrected filter value
* #492 : Correcting SPDX-non compliant LicenseRefs : FOSSology license refs contained so characters like single quotes which are not SPDX compliant
* #490 : Missing (report) cache for license overview : Fixed performance issue with separate new view in PHP
* #479 : Correcting Nomos Segmentation fault : That was an issue also shown in testing, corrected
* #472 : Adding escaping to license texts in SPDX output : If a license contains non-std chars, the generated extracted texts could contains also these non-UTF-8 characters. As such, the SPDX was invalid.
* #469 : Adding tooltip to the priority of the browse menu : In order to explain the user what the green and blue arrows in the priority column mean
* #467 : Adding header content of main table in the browse view : In order to tell the user which the current folder is that is displayed
* #465 : using wget_agent can modify files : Fixed an incompatibility with the wget call
* #404 : Error when load the license browse page : Fixed error in migration script
* #401 : At fo_nomos_license_list.php using --user instead of --username : Fixed by corrected commit
* #400 : Upload from File page cannot select folder : Corrected the according FolderDAO (data access object)
* #392 : Error when run cli cp2foss script : Corrected wrong function call
* #384 : Dashboard failure in 2.6.2 : Was a compatibility issue between different postgresql versions, solved for 9.2 and 9.3 now
* #366 : Incomplete scheduler error message  : Loggin missing columns
* #364 : At large number of jobs - performance problem : Correcting the SQL query to be a dimension faster
* #362 : Allow install to skip version (to skip versions at updates) : Changed the fossinit.php accordingly
* #360 : MIT and University of Illinois Open Source licenses not detected : Added licenses
* #359 : Remove hardcoded path in wget_agent : Fixed / removed hardcoced path
* #355 : Password in DBConnection string is printed to Fossology log when connection attempt fails : Password is removed from connection info map before printed to log
* #352 : Copyright agent using uploadtree: is it better now? : Ran analyses on copyright agent which confirmed copyright performance / precision
* #350 : License not found : Not really licenses, but some license references where not found, but they are found now with correction to the Nomos
* #349 : cp2foss fails to upload a directory using '*' option  : Corrected the use of wild cards
* #347 : Copyright agent 2.5.0: support copyright symbol  : Copyright symbol in UTF-8 is supported
* #345 : copyright agent 2.5.0: non-ASCII symbols : Changed copyright agent does cover also non-ASCII symbols
* #339 : A read only user can find none public files : Corrected access rights
* #335 : Scanner dependency: Monk agent rescan link not shown (needed for new licenses) : Adding manual setting to allow for enabling monk rescans
* #323 : Completely remove BSAM : Removed BSAM sub-project and UI references from codebase
* #282 : Need License Admin Documentation : Added documentation to the fossology.org wiki
* #264 : Nomos missing unidentified license ("Tapjoy") : Corrected and Nomos finds it now
* #259 : Documentation fix for copyright agent : Corrected documentation of the copyright agent
* #251 : On Maintenance page, be able to check all checkboxes one time : Corrected issue
* #218 : Edit users forgets users agent selections : Corrected issue
* #213 : Copyright - missed after long year string, for example ten years in a row : Corrected issue
* #212 : Moving an upload folder fails (circle protection) : Corrected issue
* #24 : Migration issue with table license_file_audit  : Corrected issue

#### Issues: Enhancements

Issue No. : Issue Title : Resolution

* #342 : Show Jobs - add estimated completion date/time : New completion time column was added (ETA)
* #319 : Tooltips for UI elements : Added tooltips mechanism and text for many UI elements
* #224 : At listing of copyrights - add text filter : Added a filter field - comes with the new jquery UI
* #214 : Create survey & solicit fossology users to respond to questions about fossology usage : yeay: http://www.fossology.org/projects/fossology/wiki/WhoUsesFOSSology

#### Issues: Closed w/o Particular Commit

Issue No. : Issue Title : Resolution

* #474 : copyright browser file path misplaced : Indeed, but UI needs major correction anyways, unchanged
* #388 : Major Nomos Regression with AGPL : Checked that license finding is acceptable
* #387 : Both Monk and Nomos appear to miss PostgreSQL License : Checked that license are found with reference file
* #338 : License browser regression - schedule link : Checked that link is there
* #318 : Scaling performance issues : Checked that large files seem to work with tables (also referring to #490)
* #216 : ‘(c)' is recognized as a copyright signature wrongly : Retested with current version and does not seem to be a serious problem since false positives have been reduced
* #247 : The maintagent - add feature to remove failed uploads  : Closed because user can remove uploads also with the menu item for organising uploads
* #238 : Browser tab interference when using FOSSology : Changes in the PHP UI do not show this issue anymore
* #225 : Folder selection fails in Edit Uploaded File Properties : Retested and current version does not show issue anymore
* #219 : New regex scanner module : There is on new module in the form of the all-new copyright agent (in c++) which is also generalised and thus extensible for new applications
* #215 : Flag license as possibly proprietary : Closed without modification because it needs to be solved with commercial license options
* #180 : Push continuous integration information to fossology.org : Is going to be moved to TLF
* #26 : View License Audit Link confusing with Edit concluded license  : Covered with changes in the UI anyways
* #25 : Pull SPDX module into master branch : Closed, because SPDX module was there

### 2.6.2 (Jan 15, 2015)

* No changes from 2.6.2-RC1

### 2.6.2-RC1 (Jan 6, 2015)

* Performance enhancements for large uploads
* Several license scanner updates
* Fix for uploading from Git
* Moved source from SourceForge to Github
* License Browser fixes

### 2.6.1 (Oct 10, 2014)

This is the same as 2.6.0-RC1 but with a performance fix that effected large databases.

### 2.6.0-RC1 (September 15, 2014)

* monk.  This is a new license scanner contributed by our friends at Siemens and TNGTech.  Monk looks for complete licenses (as defined in the database) and reports the percentage match (see also License highlighting below).
* License highlighting.  Now when you view a license you can see exactly what was added or removed from a license.  This works especially well with monk since monk scans for complete licenses (stored in the fossology database).  But it also works to show you what snippet nomos matched to identify a license.
* New license browser
* fo_copyright_list can now list files that contain a copyright, or list files that do not contain a copyright.
* fo_license_list has new options to exclude licenses (or directories)
* Many new licenses added
* Old bugs fixed, new ones added. see our "issue tracker": (link outdated)

### 2.5.0 (April 9, 2014)

See the RC1 notes below for what changed.
*If you are upgrading an RPM system* make sure you follow the [[Sysadmin_Documentation|System administration documentation]].  There was a serious bug in our previous rpm packages that can delete your existing repository.  So please follow the updated upgrade instructions.  Debian/Ubuntu systems are not effected by this.

### 2.5.0-RC1 (March 26, 2014)

#### NOTICE

* Be aware that the only supported upgrade path is a sequential one 2.0 > 2.1 > 2.2 > 2.3 > 2.4 > 2.5.
* If you run into any upgrade errors, for example with the copyright table, please let us know.
* Many thanks to all of you who submitted bugs, patches and suggestions.  FOSSology is for everyone, please help make it better.

#### What Changed

* Switched source code repository to GIT (but still on SourceForge)
* Fixed unpack failure when archive asks for password
* Make nightly builds publicly accessible
* Fix Ubuntu 12.04 packaging error
* Improve FOSSology upgrade speed
* New command line program to list buckets (fo_bucket_list)
* Several user interface bugs fixed.

#### License scanner updates:

* Fixed issue detecting Apache 2.0 reference
* Fix for GPL-v3 being labeled GPL-v3+ in certain cases
* Fixed several special cases where GPL was labelled LGPL or missed completely
* Fix problem of embedded quote in license names
* Fix case of GPL-2.0+ being identified as GPL-2.0
* Fix EPL labeled as CPL
* Fix special case of missed Boost software license
* Multiple fixes for special cases where GPL was missed
* Fix missed Sun Legal Notice
* Fix case where upload was failing on directories that contain spaces
* Fix special case where Freetype license was missed
* Fix MIT that should have been MIT-style
* Fix special case of missed CPL-1.0
* Fix cases of missed file references
* Add LIBGCJ license
* Add WordNet (was being detected as MIT/Princeton license
* Add Interbase-1.0 license
* Add KnowledgeTree-1.1
* Add Open Cascade Technology Public License
* Add identifing licenses referenced in .spec files
* Add ACE license
* Add FACE license
* Add Tapjoy license
* Add ClearSilver license
* Add LGPL-2.1+-KDE-exception

All the issues can be seen in our "issue tracker": (link outdated)
