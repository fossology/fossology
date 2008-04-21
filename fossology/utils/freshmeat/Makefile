# FOSSology CLI makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
#
# VERSION: $Id: Makefile 1587 2007-12-14 02:48:04Z markd $
#
# Makefile for the freshmeat process.

include ../../Makefile.conf
include ../Makefile.utils

SHELL=/bin/bash

# FM process only
LIBFILES=lib_projxml.h.php
BINFILES="GetFM diffm.php get-projects.php mktop1k.php wrapGFM"
MAN1SRC=
MAN1FILES=
fmPATHFILE=fm-paths.php
FMDir=FMDir

all: $(fmPATHFILE) MANpages

$(fmPATHFILE): ../Makefile.utils
	@echo "Building $(fmPATHFILE)"
	@echo "<?php" > $(fmPATHFILE)
	@echo "\$$BINDIR=\"$(BINDIR)\";" >> $(fmPATHFILE)
	@echo "\$$LIBDIR=\"$(LIBDIR)\";" >> $(fmPATHFILE)
	@echo "\$$INCLUDEDIR=\"$(INCLUDEDIR)\";" >> $(fmPATHFILE)
	@echo "\$$MAN1DIR=\"$(MAN1DIR)\";" >> $(fmPATHFILE)
	@echo "\$$PROJECT=\"$(PROJECT)\";" >> $(fmPATHFILE)
	@echo "\$$FMDIR=\"$(FMDIR)\";" >> $(fmPATHFILE)
	@echo "\$$FMDIR=\"$(FMDIR)\"" >> $(FMDir)
	@echo "\$$PATH=\"\$$LIBEXECDIR:\$$PATH:/usr/bin\";" >> $(fmPATHFILE)
	@echo "?>" >> $(fmPATHFILE)

MANpages:
	@echo "MANpages target"
# NOTE when man pages exist, add the man entries.  

InstallationCreate:
	for file in "${BINFILES}" ; do \
		chmod a+x $${file} ; \
		if \
		[[ "$${file}" == *.php ]] ; then \
			no_extention=$${file%.php} ; \
			echo "Installing $${file} into ../../install/${BINDIR}/$${no_extention}"; \
			cp $${file} ../../install/${BINDIR}/$${no_extention} ; \
		 else \
		 	echo "Installing $${file} into ../../install/${BINDIR}"; \
			cp $${file} ../../install/${BINDIR} ; \
		 fi \
	 done
	$(MKDIR) -p ../../install/$(LIBDIR)
	$(MKDIR) -p ../../install/$(INCLUDEDIR)
	$(CP) $(LIBFILES) ../../install/$(LIBDIR)
#	$(CP) $(MAN1FILES) ../../install/$(MAN1DIR)
	$(CP) $(fmPATHFILE) ../../install/$(INCLUDEDIR)
	@echo "copy FMDir to the include dir";
	$(CP) $(FMDir) ../../install/$(INCLUDEDIR)
	
install: all InstallationCreate
	for file in ${BINFILES}; do \
		chmod a+x $${file}; \
	done
	@$(MKDIR) -p $(MAN1DIR)
	@$(MKDIR) -p $(INCLUDEDIR)
	@$(MKDIR) -p $(BINDIR)
	@$(MKDIR) -p $(LIBDIR)
	@echo "FM->install target"
	$(CP) $(BINFILES) $(BINDIR)
	$(CP) $(LIBFILES) $(LIBDIR)
#	$(CP) $(MAN1FILES) $(MAN1DIR)
	$(CP) $(fmPATHFILE) $(INCLUDEDIR)
	$(CP) $(FMDir) $(INCLUDEDIR)
	
uninstall:
	(cd $(BINDIR) ;     $(RM) $(BINFILES))
	(cd $(LIBDIR) ;     $(RM) $(LIBFILES))
#	(cd $(MAN1FILES);   $(RM) $(MAN1DIR))
	(cd $(INCLUDEDIR) ; $(RM) $(fmPATHFILE))
	(cd $(INCLUDEDIR) ; $(RM) $(FMDir))

clean:
	$(RM) $(fmPATHFILE)
	$(RM) $(FMDir)	

