### 3.1.0-RC1 (March 31st, 2016)

#### Refactoring

* refactor(ui) rewrite upload pages 
* refactor(ui) rewrite/refactor delagent and fix #273 
* refactor(ui) escape strings which become HTML or SQL 

#### New Larger Features

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
