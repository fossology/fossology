<?php
/***********************************************************
 Copyright (C) 2018 Siemens AG

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/


/**
 * \brief Drop all sequence and constrains and resequence the author table and build them again
 */

function ResequenceAuthorTablePKey($dbManager)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $sql = "
BEGIN;
DROP SEQUENCE IF EXISTS public.author_ct_pk_seq CASCADE;

ALTER TABLE ONLY public.author
    DROP CONSTRAINT IF EXISTS author_agent_fk_fkey CASCADE;
ALTER TABLE ONLY public.author
    DROP CONSTRAINT IF EXISTS author_pfile_fk_fkey CASCADE;
ALTER TABLE ONLY public.author
    DROP CONSTRAINT IF EXISTS author_pkey CASCADE;
";
  $dbManager->queryOnce($sql);
  $sql = "
CREATE SEQUENCE public.author_ct_pk_seq;
SELECT setval('public.author_ct_pk_seq',(SELECT greatest(1,max(ct_pk)) val FROM author));
ALTER TABLE public.author ALTER COLUMN ct_pk SET DEFAULT nextval('author_ct_pk_seq'::regclass);

DROP INDEX IF EXISTS author_agent_fk_idx;
DROP INDEX IF EXISTS author_pfile_fk_index;
DROP INDEX IF EXISTS author_pfile_hash_idx;
DROP INDEX IF EXISTS author_pkey;

ALTER SEQUENCE public.author_ct_pk_seq RESTART WITH 1;
UPDATE public.author SET ct_pk=nextval('public.author_ct_pk_seq');

ALTER TABLE ONLY public.author
    ADD CONSTRAINT author_pkey PRIMARY KEY (ct_pk);

CREATE INDEX author_agent_fk_idx ON public.author USING btree (agent_fk);
CREATE INDEX author_pfile_fk_index ON public.author USING btree (pfile_fk);
CREATE INDEX author_pfile_hash_idx ON public.author USING btree (hash, pfile_fk);
ALTER TABLE ONLY public.author
    ADD CONSTRAINT author_agent_fk_fkey FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk) ON DELETE CASCADE;
ALTER TABLE ONLY public.author
    ADD CONSTRAINT author_pfile_fk_fkey FOREIGN KEY (pfile_fk) REFERENCES public.pfile(pfile_pk) ON DELETE CASCADE;

COMMIT;
";
  $dbManager->queryOnce($sql);
}

/**
 * \brief Drop primary key constrains and resequence the copyright table and build them again
 */

function ResequenceCopyrightTablePKey($dbManager)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $sql = "
BEGIN;
ALTER TABLE ONLY public.copyright
    DROP CONSTRAINT IF EXISTS copyright_pkey CASCADE;
ALTER TABLE ONLY public.copyright
    DROP CONSTRAINT IF EXISTS copyright_agent_fk_fkey CASCADE;
DROP INDEX IF EXISTS copyright_pkey;
";
  $dbManager->queryOnce($sql);
  $sql = "
ALTER SEQUENCE public.copyright_ct_pk_seq RESTART WITH 1;
UPDATE public.copyright SET ct_pk=nextval('public.copyright_ct_pk_seq');

ALTER TABLE ONLY public.copyright
    ADD CONSTRAINT copyright_pkey PRIMARY KEY (ct_pk);
ALTER TABLE ONLY public.copyright
    ADD CONSTRAINT copyright_agent_fk_fkey FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk) ON DELETE CASCADE;

COMMIT;
";
  $dbManager->queryOnce($sql);
}


/**
 * \brief Remove reduntant entries from author table
 */

function CleanAuthorTable($dbManager)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $sql = "
BEGIN;
DELETE FROM public.author
USING public.author AS a LEFT OUTER JOIN public.pfile AS p ON p.pfile_pk = a.pfile_fk
WHERE public.author.ct_pk = a.ct_pk AND p.pfile_pk IS NULL;

DELETE FROM public.author
USING public.author AS au LEFT OUTER JOIN public.agent AS ag ON au.agent_fk = ag.agent_pk
WHERE public.author.ct_pk = au.ct_pk AND ag.agent_pk IS NULL;

DELETE FROM public.author
WHERE ct_pk IN (SELECT ct_pk
FROM (SELECT ct_pk,
      ROW_NUMBER() OVER (PARTITION BY hash, pfile_fk, agent_fk, copy_startbyte, copy_endbyte, type
                         ORDER BY ct_pk) AS rnum
      FROM public.author) a
      WHERE a.rnum > 1);
COMMIT;
";
  $dbManager->queryOnce($sql);
}

/**
 * \brief Remove invalid entries from copyright table
 */

function CleanCopyrightTable($dbManager)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $sql = "
BEGIN;

DELETE FROM public.copyright
USING public.copyright AS cp LEFT OUTER JOIN public.agent AS ag ON cp.agent_fk = ag.agent_pk
WHERE public.copyright.ct_pk = cp.ct_pk AND ag.agent_pk IS NULL;

COMMIT;
";
  $dbManager->queryOnce($sql);
}


$result = $dbManager->getSingleRow("SELECT count(*) FROM pg_class WHERE relname = 'author_ct_pk_seq';",
  array(), 'checkAuthorCtPkSequence');
if($result['count'] == 0)
{
  try {
    echo "*** Cleaning author table ***\n";
    CleanAuthorTable($dbManager);
    echo "*** Cleaning copyright table ***\n";
    CleanCopyrightTable($dbManager);
    echo "*** Resequencing author table ***\n";
    ResequenceAuthorTablePKey($dbManager);
    echo "*** Resequencing copyright table ***\n";
    ResequenceCopyrightTablePKey($dbManager);
  } catch (Exception $e) {
    echo "Something went wrong. Try running postinstall again!\n";
    $dbManager->queryOnce("ROLLBACK;");
  }
}

