# SPDX-FileCopyrightText: © 2014 Siemens AG
# 
# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

# -Werror -Wextra
CXXFLAGS_LOCAL = $(FO_CXXFLAGS) -I. -Wall -Wextra -fopenmp \
                 $(shell pkg-config --cflags jsoncpp)
DEF = -DDATADIR='"$(MODDIR)"'
CONFDIR = $(DESTDIR)$(SYSCONFDIR)
CXXFLAGS_LINK = -lboost_regex -lboost_program_options -lboost_system \
                -lboost_filesystem $(ALL_CXXFLAGS) -lm -fopenmp \
                $(FO_CXXLDFLAGS) $(shell pkg-config --libs jsoncpp)

DEF_ID_COP = -DIDENTITY_COPYRIGHT
DEF_ID_ECC = -DIDENTITY_ECC
DEF_ID_KW = -DIDENTITY_KW

EXE_COP = copyright
EXE_ECC = ecc
EXE_KW = keyword
EXE_UNI = fo_unicode_clean
EXE = $(EXE_COP) $(EXE_ECC) $(EXE_KW)
EXE_COV = $(EXE_COP)_cov



OBJECTS = copyright.o regscan.o scanners.o cleanEntries.o regexConfProvider.o regexConfParser.o directoryScan.o
OBJECTS_COP = copyscan_cop.o copyrightUtils_cop.o copyrightState_cop.o database_cop.o
OBJECTS_ECC = copyrightUtils_ecc.o copyrightState_ecc.o database_ecc.o
OBJECTS_KW = copyrightUtils_kw.o copyrightState_kw.o database_kw.o
OBJECTS_UNI = FossologyUnicodeClean.o

HEADERS = $(OBJECTS:%.o=%.hpp) $(OBJECTS_COP:%_cop.o=%.hpp)

COVERAGE = $(OBJECTS:.o=_cov.o)
COVERAGE_COP = $(OBJECTS_COP:.o=_cov.o)

all: $(CXXFOLIB) $(EXE) $(EXE_UNI)

$(EXE_COP): $(CXXFOLIB) $(VARS) $(OBJECTS) $(OBJECTS_COP)
	$(CXX) $(OBJECTS) $(OBJECTS_COP) $(DEF_ID_COP) $(DEF) $(CXXFLAGS_LINK) -o $@
$(EXE_ECC): $(CXXFOLIB) $(VARS) $(OBJECTS) $(OBJECTS_ECC)
	$(CXX) $(OBJECTS) $(OBJECTS_ECC) $(DEF_ID_ECC) $(DEF) $(CXXFLAGS_LINK) -o $@
$(EXE_KW): $(CXXFOLIB) $(VARS) $(OBJECTS) $(OBJECTS_KW)
	$(CXX) $(OBJECTS) $(OBJECTS_KW) $(DEF_ID_KW) $(DEF) $(CXXFLAGS_LINK) -o $@
$(EXE_UNI): $(CXXFOLIB) $(VARS) $(OBJECTS_UNI)
	$(CXX) $(OBJECTS_UNI) $(CXXFLAGS_LINK) -o $@
$(EXE_COV): $(CXXFOLIB) $(VARS) $(COVERAGE) $(COVERAGE_COP)
	$(CXX) $(COVERAGE) $(COVERAGE_COP) $(FLAG_COV) $(DEF_ID_COP) $(DEF) $(CXXFLAGS_LINK) -o $@

#######################
# library build rules #
#######################

$(CXXFOLIB):
	$(MAKE) -C $(CXXFOLIBDIR)

libcopyright.a: $(OBJECTS) $(OBJECTS_COP)
	ar cvr $@ $(OBJECTS) $(OBJECTS_COP)

libcopyright_cov.a: $(COVERAGE) $(COVERAGE_COP)
	ar cvr $@ $(COVERAGE) $(COVERAGE_COP)

######################
# object build rules #
######################

$(OBJECTS): %.o: %.cc $(HEADERS)
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF) $<

$(OBJECTS_COP): %_cop.o: %.cc $(HEADERS)
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF_ID_COP) $(DEF) -o $@ $<

$(OBJECTS_ECC): %_ecc.o: %.cc $(HEADERS)
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF_ID_ECC) $(DEF) -o $@ $<

$(OBJECTS_KW): %_kw.o: %.cc %.hpp
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF_ID_KW) $(DEF) -o $@ $<

$(OBJECTS_UNI): %.o: %.cc %.hpp
	$(CXX) -c $(CXXFLAGS_LOCAL) -o $@ $<

$(COVERAGE_COP): %_cop_cov.o: %.cc $(HEADERS)
	$(CXX) -c $< $(CXXFLAGS_LOCAL) $(FLAG_COV) $(DEF_ID_COP) $(DEF) $(DEFS) -o $@

$(COVERAGE): %_cov.o: %.cc $(HEADERS)
	$(CXX) -c $< $(CXXFLAGS_LOCAL) $(FLAG_COV) $(DEF_ID_COP) $(DEF) $(DEFS) -o $@

#######################
# install build rules #
#######################

install: $(EXE) $(EXE_UNI)
	for mod_name in $(EXE); do \
		$(INSTALL_PROGRAM) $$mod_name $(DESTDIR)$(MODDIR)/$$mod_name/agent/$$mod_name; \
		$(INSTALL_DATA) $$mod_name.conf $(DESTDIR)$(MODDIR)/$$mod_name/agent/$$mod_name.conf; \
	done
	$(INSTALL_PROGRAM) $(EXE_UNI) $(DESTDIR)$(MODDIR)/copyright/agent/$(EXE_UNI);

uninstall:
	for mod_name in $(EXE); do \
		rm -rf $(DESTDIR)$(MODDIR)/$$mod_name/agent; \
	done

clean:
	rm -f $(EXE) $(EXE_UNI) *.o *.a *.gcno *.gcda core

.PHONY: all install uninstall clean
