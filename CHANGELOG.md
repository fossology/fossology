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
