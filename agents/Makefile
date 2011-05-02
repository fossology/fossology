# FOSSology Makefile - agents dir
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
#

TOP=..
VARS=$(TOP)/Makefile.conf
include $(VARS)

# the directories we do things in by default
DIRS = Shell ununpack wget_agent mimetype sqlagent delagent adj2nest selftest nomos pkgagent copyright buckets
# not in use: example_wc_agent reuseagent

# create lists of targets for various operations
# these are phony targets (declared at bottom) of convenience so we can
# run 'make $(operation)-$(subdir)'. Yet another convenience, a target of
# '$(subdir)' is equivalent to 'build-$(subdir)'
BUILDDIRS = $(DIRS:%=build-%)
INSTALLDIRS = $(DIRS:%=install-%)
UNINSTALLDIRS = $(DIRS:%=uninstall-%)
CLEANDIRS = $(DIRS:%=clean-%)
TESTDIRS = $(DIRS:%=test-%)
COVDIRS = $(DIRS:%=cov-%)
all: $(BUILDDIRS)
$(DIRS): $(BUILDDIRS)
$(BUILDDIRS):
	$(MAKE) -C $(@:build-%=%)

# install depends on everything being built first
install: all $(INSTALLDIRS)
$(INSTALLDIRS):
	if [ ! -d $(DESTDIR)$(SYSCONFDIR)/fossology/agents.d ] ; then \
	  mkdir -p $(DESTDIR)$(SYSCONFDIR)/fossology/agents.d; \
	fi
	$(MAKE) -C $(@:install-%=%) install

uninstall: $(UNINSTALLDIRS)
$(UNINSTALLDIRS):
	$(MAKE) -C $(@:uninstall-%=%) uninstall

# test depends on everything being built first
test: all $(TESTDIRS)
$(TESTDIRS):
	$(MAKE) -C $(@:test-%=%) test

coverage: $(COVDIRS)
$(COVDIRS):
	$(MAKE) -C $(@:cov-%=%) coverage

clean: $(CLEANDIRS)
$(CLEANDIRS):
	$(MAKE) -C $(@:clean-%=%) clean

.PHONY: subdirs $(BUILDDIRS)
.PHONY: subdirs $(DIRS)
.PHONY: subdirs $(UNINSTALLDIRS)
.PHONY: subdirs $(TESTDIRS)
.PHONY: subdirs $(CLEANDIRS)
.PHONY: all install uninstall clean test
