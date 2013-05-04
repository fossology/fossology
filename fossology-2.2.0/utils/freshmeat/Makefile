# FOSSology Makefile - utils/freshmeat
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

LIBFILES=lib_projxml.h.php
BINFILES=GetFM diffm.php get-projects.php mktop1k.php wrapGFM
fmPATHFILE=fm-paths.php
FMDir=FMDir

all: $(fmPATHFILE)

$(fmPATHFILE): ../Makefile.utils
	@echo "Building $(fmPATHFILE)"
	@echo "<?php" > $(fmPATHFILE)
	@echo "\$$BINDIR=\"$(BINDIR)\";" >> $(fmPATHFILE)
	@echo "\$$LIBDIR=\"$(LIBDIR)\";" >> $(fmPATHFILE)
	@echo "\$$INCLUDEDIR=\"$(INCLUDEDIR)\";" >> $(fmPATHFILE)
	@echo "\$$MAN1DIR=\"$(MAN1DIR)\";" >> $(fmPATHFILE)
	@echo "\$$PROJECT=\"$(PROJECT)\";" >> $(fmPATHFILE)
	@echo "\$$FMDIR=\"$(FMDIR)\";" >> $(fmPATHFILE)
	@echo "FMDIR=\"$(FMDIR)\"" >> $(FMDir)
	@echo "\$$PATH=\"\$$LIBEXECDIR:\$$PATH:/usr/bin\";" >> $(fmPATHFILE)
	@echo "?>" >> $(fmPATHFILE)

install: all
	for file in $(BINFILES); do \
	   $(INSTALL_PROGRAM) $$file $(DESTDIR)$(BINDIR)/$${file%.php}; \
	done

	$(INSTALL_DATA) $(LIBFILES) $(DESTDIR)$(LIBDIR)/$(LIBFILES)

	$(INSTALL_DATA) $(fmPATHFILE) $(DESTDIR)$(LIBDIR)/$(fmPATHFILE)
	$(INSTALL_DATA) $(FMDir) $(DESTDIR)$(INCLUDEDIR)/$(FMDir)


uninstall:
	for file in $(BINFILES); do \
	   rm -f $(DESTDIR)$(BINDIR)/$${file%.php}; \
	done

	rm -f $(DESTDIR)$(LIBDIR)/$(LIBFILES)

	rm -f $(DESTDIR)$(LIBDIR)/$(fmPATHFILE)
	rm -f $(DESTDIR)$(INCLUDEDIR)/$(FMDir)

test: all
	@echo "*** No tests available for agents/$(EXE) ***"

clean:
	rm -f $(fmPATHFILE) $(FMDir) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test
