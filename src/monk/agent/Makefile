# SPDX-FileCopyrightText: © 2014-2015 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCAL_CFLAGS = -std=c99 -I. -Werror -Wall -Wextra -fopenmp $(FO_CFLAGS)
LOCAL_LDFLAGS = -fopenmp $(FO_LDFLAGS)

ifeq (,$(shell pkg-config --exists uchardet || echo no))
LOCAL_CFLAGS += $(shell pkg-config --cflags uchardet) -DHAVE_CHARDET
LOCAL_LDFLAGS += $(shell pkg-config --libs uchardet)
else
LOCAL_LDFLAGS += -lmagic
endif

EXE = monk monkbulk
OBJECTS = string_operations.o file_operations.o database.o encoding.o \
          license.o highlight.o match.o hash.o diff.o common.o \
          cli.o scheduler.o serialize.o \
          _squareVisitor.o
COVERAGE = string_operations_cov.o file_operations_cov.o encoding_cov.o \
           database_cov.o license_cov.o highlight_cov.o match_cov.o \
           hash_cov.o diff_cov.o common_cov.o \
           cli_cov.o scheduler_cov.o \
           _squareVisitor_cov.o

all: _squareVisitor.h $(EXE)

all_cov: _squareVisitor.h $(EXE:%=%_cov)

$(EXE): % : %.c $(FOLIB) $(VARS) $(OBJECTS)
	$(CC) -c $< $(LOCAL_CFLAGS) $(DEF) -o $@.o
	$(CC) $@.o $(OBJECTS) $(DEF) $(LOCAL_LDFLAGS) -o $@

$(EXE:%=%_cov): %_cov : %.c $(FOLIB) $(VARS) $(COVERAGE)
	$(CC) -c $< $(LOCAL_CFLAGS) $(FLAG_COV) $(DEF) $(DEFS) -o $@.o
	$(CC) $@.o $(COVERAGE) $(FLAG_COV) $(DEF) $(LOCAL_LDFLAGS) -o $@

#######################
# library build rules #
#######################

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

libmonk.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libmonk_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

######################
# object build rules #
######################

_squareVisitor.c _squareVisitor.h: squareBuilder
	sh genSquareVisitor

squareBuilder: buildSquareVisitor.c $(FOLIB)
	$(CC) buildSquareVisitor.c $(LOCAL_CFLAGS) $(LOCAL_LDFLAGS) -lm -o $@

$(OBJECTS): %.o: %.c _squareVisitor.c
	$(CC) -c $< $(LOCAL_CFLAGS) -o $@

$(COVERAGE): %_cov.o: %.c _squareVisitor.c
	$(CC) -c $< $(LOCAL_CFLAGS) $(FLAG_COV) -o $@

#######################
# install build rules #
#######################

install: all $(EXE:%=install-%)

$(EXE:%=install-%): install-% : %
	$(INSTALL_PROGRAM) $< $(DESTDIR)$(MODDIR)/$</agent/$<

uninstall: $(EXE:%=uninstall-%)

$(EXE:%=uninstall-%): uninstall-% :
	rm -rf $(DESTDIR)$(MODDIR)/$</agent

clean:
	rm -f $(EXE) $(EXE:%=%_cov) *.o *.a *.gcno *.gcda core _squareVisitor.h _squareVisitor.c \
	squareBuilder _squareVisitor.h.gen

.PHONY: all install uninstall clean
