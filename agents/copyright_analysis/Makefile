## FOSSology Makefile - agents/copyright_analysis
## 
## Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
## 
## This program is free software; you can redistribute it and/or
## modify it under the terms of the GNU General Public License
## version 2 as published by the Free Software Foundation.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
##
## You should have received a copy of the GNU General Public License along
## with this program; if not, write to the Free Software Foundation, Inc.,
## 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
##
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
DEPS=$(TOP)/Makefile.deps

COPYRIGHTPATH=$(DESTDIR)$(AGENTDIR)/copyright

all: model.dat vars.py

model.dat: run.py vars.py copyright.py copyright_library.py data.txt
	./run.py --model model.dat --training data.txt
	
install: all
	mkdir -p $(COPYRIGHTPATH)
	$(INSTALL_PROGRAM) run.py $(COPYRIGHTPATH)/run.py
	$(INSTALL_PROGRAM) vars.py $(COPYRIGHTPATH)/vars.py
	$(INSTALL_PROGRAM) copyright.py $(COPYRIGHTPATH)/copyright.py
	$(INSTALL_PROGRAM) copyright_library.py $(COPYRIGHTPATH)/copyright_library.py
	$(INSTALL_PROGRAM) model.dat $(DATADIR)/model.dat
	make -C ui-plugin install

uninstall: 
	rm -rf $(COPYRIGHTPATH)
	rm -rf $(DATADIR)/model.dat
	make -C ui-plugin uninstall

test: all
	@echo "*** No tests available for agents/copyright_analysis ***"

clean:
	rm -f model.dat training_files vars.py *.pyc

include $(DEPS)

.PHONY: all install uninstall clean test vars.py

vars.py:
	@rm -f $@
	@echo -n "DATADIR = '''" >> $@
	@echo -n $(DATADIR) >> $@
	@echo    "'''" >> $@
	@echo -n "AGENTDIR = '''" >> $@
	@echo -n $(AGENTDIR) >> $@
	@echo    "'''" >> $@
	@echo -n "PYTHONLIBPATH = '''" >> $@
	@echo -n $(PYTHONLIBPATH) >> $@
	@echo    "'''" >> $@
	@echo -n "COPYRIGHTPATH = '''" >> $@
	@echo -n $(COPYRIGHTPATH) >> $@
	@echo    "'''" >> $@
	@echo -n "PYTHONLIBEXECDIR = '''" >> $@
	@echo -n $(PYTHONLIBEXECDIR) >> $@
	@echo    "'''" >> $@

