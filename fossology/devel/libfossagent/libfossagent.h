/**************************************************************
 Set of generic functions handy for agent development.

 Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
  
 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 **************************************************************/
void    InitHeartbeat ();
void    Heartbeat     (long NewItemsProcessed);
void    ShowHeartbeat (int Sig);
int     ReadLine      (FILE *Fin, char *Line, int MaxLine);
int     IsFile        (char *Fname, int Link);
int     GetAgentKey   (void *DB, long Upload_pk, char *svn_rev);

