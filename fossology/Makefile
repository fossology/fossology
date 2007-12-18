# FOSSology Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
#
# List directories in order of dependencies!
include Makefile.conf

DIRS=devel/libfossrepo devel/libfossdb scheduler agents/foss_license_agent/bSAM agents/Shell agents/foss_license_agent/Filter_License agents/PkgMetaGetta agents/ununpack agents/wget_agent agents/foss_license_agent/Licenses agents/foss_license_agent/PkgMetaGetta agents/mimetype agents/specagent agents/sqlagent agents/delagent ui cli

all:
	@echo "Project $(PROJECT) $(SVN_REV)"
	@for i in $(DIRS) ; do if [ -d $$i ] ; then echo "Making $$i" ; (cd $$i ; make) ; fi ; done

MakeBuildDirs:
	@for i in $(BUILDINC) $(BUILDLIB) ; do if [ ! -d $$i ] ; then echo "Making directory $$i" ; $(MKDIR) -p $$i ; fi ; done

CreateInstallScript:
	echo "Creating install script"
	if [ ! -x ./mkinstall.sh ] ; then chmod u+x ./mkinstall.sh ; fi
	./mkinstall.sh > install.sh
	chmod a+x ./install.sh
	if [ ! -x ./mkuninstall.sh ] ; then chmod u+x ./mkuninstall.sh ; fi
	./mkuninstall.sh > uninstall.sh
	chmod a+x ./uninstall.sh
	if [ ! -x ./mkcheck.sh ] ; then chmod u+x ./mkcheck.sh ; fi
	./mkcheck.sh > check.sh
	chmod a+x ./check.sh

InstallationRemove:
	if [ -d install ] ; then $(RM) -rf install ; fi
	$(RM) install.sh uninstall.sh check.sh

InstallationCreate: all InstallationRemove
	if [ ! -d install ] ; then $(MKDIR) install ; fi
	# Create directories under the install tree.
	$(MKDIR) -p install/$(LIBEXECDIR)
	$(MKDIR) -p install/$(BINDIR)
	$(MKDIR) -p install/$(LIBDIR)
	$(MKDIR) -p install/$(DATADIR)
	$(MKDIR) -p install/$(VARDATADIR)
	$(MKDIR) -p install/$(MANDIR)
	$(MKDIR) -p install/$(MAN1DIR)
	$(MKDIR) -p install/$(WEBDIR)
	$(MKDIR) -p install/$(PHPDIR)
	$(MKDIR) -p install/$(AGENTDIR)
	$(MKDIR) -p install/$(AGENTDATADIR)
	$(MKDIR) -p install/$(AGENTTESTDDIR)
	$(MKDIR) -p install/$(DATADIR)/dbconnect
	$(MKDIR) -p install/$(DATADIR)/repository
	# Populate directories
	@for i in $(DIRS) ; do if [ -d $$i ] ; then echo "Installing template $$i" ; (cd $$i ; make InstallationCreate) ; fi ; done

clean: InstallationRemove
	@for i in $(DIRS) ; do if [ -d $$i ] ; then echo "Cleaning $$i" ; (cd $$i ; make clean) ; fi ; done
	@for i in $(BUILDINC) $(BUILDLIB) ; do if [ -d $$i ] ; then echo "Cleaning directory $$i" ; $(RM) -rf $$i/* ; fi ; done

install: InstallationCreate CreateInstallScript
	@echo Project $(PROJECT)
	./install.sh

uninstall:


Test:
	# Debug the environment
	@echo "SVNID=$(SVNID)"
	@echo "SVN_REV=$(SVN_REV)"

tar:
	# Package into a tar file.
	chmod a+rw ./mktar.sh
	./mktar.sh

