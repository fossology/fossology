# Changelog of FOSSology


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
