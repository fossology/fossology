# FOSSology CLI makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
#
# VERSION: $Id: Makefile 1587 2007-12-14 02:48:04Z markd $
#
# Makefile for the freshmeat process.

include ../../Makefile.conf
#include ../Makefile.utils

SHELL=/bin/bash

# FM process only
LIBFILES=lib_projxml.h.php
BINFILES="GetFM diffm.php get-projects.php mktop1k.php wrapGFM"
MAN1SRC=
MAN1FILES=
fmPATHFILE=fm-paths.h.php

all: $(fmPATHFILE) MANpages
	for file in ${BINFILES}; do \
		chmod a+x $${file}; \
	done

$(fmPATHFILE): ../Makefile.utils
	@echo "Building $(fmPATHFILE)"
	@echo "<?php" > $(fmPATHFILE)
	@echo "\$$BINDIR=\"$(BINDIR)\";" >> $(fmPATHFILE)
	@echo "\$$LIBDIR=\"$(LIBDIR)\";" >> $(fmPATHFILE)
	@echo "\$$INCLUDEDIR=\"$(INCLUDEDIR)\";" >> $(fmPATHFILE)
	@echo "\$$MAN1DIR=\"$(MAN1DIR)\";" >> $(fmPATHFILE)
	@echo "\$$PROJECT=\"$(PROJECT)\";" >> $(fmPATHFILE)
	@echo "\$$FMDIR=\"$(FMDIR)\";" >> $(fmPATHFILE)
	@echo "\$$PATH=\"\$$LIBEXECDIR:\$$PATH:/usr/bin\";" >> $(fmPATHFILE)
	@echo "?>" >> $(fmPATHFILE)

MANpages:
	echo "MANpages target"; \
 
 # NOTE when man pages exist, uncomment the man entries below.  
InstallationCreate:
	echo "INSTALLATIONCREATE target";
	echo "FM->InstallationCreate" ;
	@ls ../../install/$(BINDIR) ;
	for file in ${BINFILES} ; do \
		chmod a+x $${file} ; \
		echo "Installing $${file} into ../../install/${BINDIR}"; \
		if \
		[[ $${file} == *.php ]] ; then \
			no_extention=$${file%.php} ; \
			cp $${file} ../../install/${BINDIR}/$${no_extention} ; \
		 else \
			cp $${file} ../../install/${BINDIR} ; \
		 fi \
	 done
	$(MKDIR) -p ../../install/$(LIBDIR)
	$(CP) $(LIBFILES) ../../install/$(LIBDIR)
#	$(CP) $(MAN1FILES) ../../install/$(MAN1DIR)
	$(CP) $(fmPATHFILE) ../../install/$(INCLUDEDIR)
	echo "FM->END OF InstallationCreate"
	ls ../../install/$(BINDIR)
	ls ../../install/usr/local 
	
install: all InstallationCreate
	for file in ${BINFILES}; do \
		chmod a+x $${file}; \
	done
	@$(MKDIR) -p $(MAN1DIR)
	@$(MKDIR) -p $(INCLUDEDIR)
	@$(MKDIR) -p $(BINDIR)
	@$(MKDIR) -p $(LIBDIR)
	$(CP) $(BINFILES) $(BINDIR)
	$(CP) $(LIBFILES) $(LIBDIR)
#	$(CP) $(MAN1FILES) $(MAN1DIR)
	$(CP) $(fmPATHFILE) $(INCLUDEDIR)
	
uninstall:
	(cd $(BINDIR) ;     $(RM) $(BINFILES))
	(cd $(LIBDIR) ;     $(RM) $(LIBFILES))
#	(cd $(MAN1FILES);   $(RM) $(MAN1DIR))
	(cd $(INCLUDEDIR) ; $(RM) $(fmPATHFILE))

clean:
	$(RM) $(fmPATHFILE)

