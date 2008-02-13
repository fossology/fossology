# Filter_license Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=Filter_License filter_clean
INC=-I../../../ununpack -I../../../$(BUILDINC)
LIB=-L../../../$(BUILDLIB) -lfossrepo -lfossdb -lpq

all: $(EXE)

FLsrc=Filter_License.c 1sl.c wordcheck.c tokholder.c ../../ununpack/checksum.c ../../ununpack/md5.c ../../ununpack/sha1.c wordregex.c

Filter_License: $(FLsrc)
	$(CC) $(CFLAGS) $(FLsrc) -o Filter_License $(INC) $(LIB)

filter_clean: filter_clean.c
	$(CC) $(CFLAGS) $(INC) -o filter_clean filter_clean.c $(LIB)

InstallationCreate:
	$(CP) Filter_License ../../../install/$(AGENTDIR)
	$(CP) filter_clean ../../../install/$(AGENTDIR)

install: all
	$(CP) Filter_License $(AGENTDIR)
	$(CP) filter_clean $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/Filter_License
	$(RM) $(AGENTDIR)/filter_clean

clean:
	$(RM) *.o core $(EXE)

