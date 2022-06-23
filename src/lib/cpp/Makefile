# SPDX-FileCopyrightText: © 2014 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

CLIBDIR = ../c/
CXXFLAGS_LOCAL = -fPIC $(FO_CXXFLAGS) -Wextra -Weffc++ \
                 $(shell pkg-config --cflags icu-uc)

CONFDEF = -DFOSSDB_CONF='"$(SYSCONFDIR)/Db.conf"' \
          -DFOSSGROUP='"$(PROJECTGROUP)"' \
          -DDEFAULT_SETUP='"$(SYSCONFDIR)"'
EXE =
LIB = libfossologyCPP.a
DEPENDENCY = $(CLIBDIR)/libfossology.a
OBJS = libfossAgentDatabaseHandler.o files.o libfossdbmanagerclass.o libfossdbQueryResult.o libfossUtils.o

COVERAGE = $(OBJS:%.o=%_cov.o)

all: $(LIB) $(VARS) $(EXE)
	echo "make $(LIB)"

$(LIB): $(OBJS) $(VARS) $(DEPENDENCY)
	$(AR) -x $(DEPENDENCY)
	$(AR) cr $@ $(OBJS) $$( $(AR) -t $(DEPENDENCY) )

$(DEPENDENCY):
	$(MAKE) -C $(CLIBDIR)

$(EXE): %: %.cc $(LIB) $(VARS)
	$(CXX) $< $(CONFDEF) $(FO_CXXLDFLAGS) $(LIB) -o $@

$(OBJS): %.o: %.cc %.hpp
	$(CXX) -c $< $(CONFDEF) $(CXXFLAGS_LOCAL) -o $@

libfossologyCPP_cov.a: $(COVERAGE) $(VARS) $(DEPENDENCY)
	$(AR) -x $(DEPENDENCY)
	$(AR) cr $@ $(COVERAGE) $$( $(AR) -t $(DEPENDENCY) )

$(COVERAGE): %_cov.o: %.cc %.hpp
	$(CXX) -c $< $(CONFDEF) $(CXXFLAGS_LOCAL) $(FLAG_COV) -o $@

install: install-dev install-lib install-exe
	@echo "make $(LIB) install"

install-dev: $(LIB)
	$(INSTALL_DATA) libfossologyCPP.a $(DESTDIR)$(LIBEXECDIR)/libfossologyCPP.a
	$(INSTALL_DATA) libfossologyCPP.hpp $(DESTDIR)$(INCLUDEDIR)/libfossologyCPP.hpp

install-exe: $(EXE)
	for program in $(EXE); do \
		$(INSTALL_PROGRAM) $$program $(DESTDIR)$(LIBEXECDIR)/$$program; \
	done

# If we ever do shared libs, they will go here
install-lib:

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall: uninstall-dev uninstall-lib uninstall-exe
	@echo "make $(LIB) uninstall"

uninstall-dev:
	rm -f $(DESTDIR)$(LIBEXECDIR)/libfossology.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossology.h

uninstall-exe:
	for program in $(EXE); do \
		rm -f $(DESTDIR)$(LIBEXECDIR)/$$program; \
	done

uninstall-lib:

test: all
	$(MAKE) -C tests test

coverage: $(COVERAGE)
	$(MAKE) -C tests coverage

clean:
	@echo "make $(LIB) clean "
	rm -f *.a $(EXE) *.o core *.gc*

.PHONY: all clean test
.PHONY: install-dev install-lib install-exe
.PHONY: uninstall-dev uninstall-lib uninstall-exe
