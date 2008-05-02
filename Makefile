# FOSSology Makefile
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

# pull in all our default variables
include Makefile.conf

# the directories we do things in by default
DIRS=devel scheduler agents ui cli
# utils is a separate target, since it isn't built by default yet
utils: build-utils

# create lists of targets for various operations
# these are phony targets (declared at bottom) of convenience so we can
# run 'make $(operation)-$(subdir)'. Yet another convenience, a target of
# '$(subdir)' is equivalent to 'build-$(subdir)'
BUILDDIRS = $(DIRS:%=build-%)
INSTALLDIRS = $(DIRS:%=install-%)
UNINSTALLDIRS = $(DIRS:%=uninstall-%)
CLEANDIRS = $(DIRS:%=clean-%)
TESTDIRS = $(DIRS:%=test-%)

# dependencies:
# the scheduler and agents need the devel stuff built first
build-scheduler: build-devel
build-agents: build-devel

## Targets
# build
all: $(BUILDDIRS)
$(DIRS): $(BUILDDIRS)
$(BUILDDIRS):
	$(MAKE) -C $(@:build-%=%)

# install depends on everything being built first
install: all $(INSTALLDIRS)
$(INSTALLDIRS):
	$(MAKE) -C $(@:install-%=%) install

uninstall: $(UNINSTALLDIRS)
$(UNINSTALLDIRS):
	$(MAKE) -C $(@:uninstall-%=%) uninstall

# test depends on everything being built first
test: all $(TESTDIRS)
$(TESTDIRS):
	$(MAKE) -C $(@:test-%=%) test

clean: $(CLEANDIRS)
$(CLEANDIRS):
	$(MAKE) -C $(@:clean-%=%) clean

# release stuff
tar: dist-testing
dist-testing:
	# Package into a tar file.
	chmod a+x ./mktar.sh
	./mktar.sh -s

tar-release: dist
dist:
	# Package into a tar file.
	chmod a+x ./mktar.sh
	./mktar.sh


.PHONY: subdirs $(BUILDDIRS)
.PHONY: subdirs $(DIRS)
.PHONY: subdirs $(INSTALLDIRS)
.PHONY: subdirs $(UNINSTALLDIRS)
.PHONY: subdirs $(TESTDIRS)
.PHONY: subdirs $(CLEANDIRS)
.PHONY: all install uninstall clean test utils
.PHONY: dist dist-testing tar tar-release
