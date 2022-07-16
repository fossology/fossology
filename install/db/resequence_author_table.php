<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file resequence_author_table.php
 * Resequence the author and copyright table, remove duplicates in author table,
 * remove dependency of author table on copyright sequence.
 */

/**
 * \brief Drop all sequence and constrains and resequence the author table and build them again
 * \param DbManager $dbManager DB Manager to be used
 * \param string $authorColumn Primary column name
 */

function ResequenceAuthorTablePKey($dbManager, $authorColumn)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $sql = "
BEGIN;
DROP SEQUENCE IF EXISTS public.author_pk_seq CASCADE;

ALTER TABLE ONLY public.author
    DROP CONSTRAINT IF EXISTS author_agent_fk_fkey CASCADE;
ALTER TABLE ONLY public.author
    DROP CONSTRAINT IF EXISTS author_pfile_fk_fkey CASCADE;
ALTER TABLE ONLY public.author
    DROP CONSTRAINT IF EXISTS author_pkey CASCADE;
";
  $dbManager->queryOnce($sql);
  $sql = "
CREATE SEQUENCE public.author_pk_seq;
SELECT setval('public.author_pk_seq',(SELECT greatest(1,max($authorColumn)) val FROM author));
ALTER TABLE public.author ALTER COLUMN $authorColumn SET DEFAULT nextval('author_pk_seq'::regclass);

DROP INDEX IF EXISTS author_agent_fk_idx;
DROP INDEX IF EXISTS author_pfile_fk_index;
DROP INDEX IF EXISTS author_pfile_hash_idx;
DROP INDEX IF EXISTS author_pkey;

ALTER SEQUENCE public.author_pk_seq RESTART WITH 1;
UPDATE public.author SET $authorColumn=nextval('public.author_pk_seq');

ALTER TABLE ONLY public.author
    ADD CONSTRAINT author_pkey PRIMARY KEY ($authorColumn);

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
 * \param DbManager $dbManager DB Manager to be used
 * \param string $copyrightColumn Primary column name
 */

function ResequenceCopyrightTablePKey($dbManager, $copyrightColumn)
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
DROP SEQUENCE IF EXISTS public.copyright_pk_seq CASCADE;
";
  $dbManager->queryOnce($sql);
  $sql = "
CREATE SEQUENCE public.copyright_pk_seq;
SELECT setval('public.copyright_pk_seq',(SELECT greatest(1,max($copyrightColumn)) val FROM copyright));
ALTER TABLE public.copyright ALTER COLUMN $copyrightColumn SET DEFAULT nextval('copyright_pk_seq'::regclass);
ALTER SEQUENCE public.copyright_pk_seq RESTART WITH 1;
UPDATE public.copyright SET $copyrightColumn=nextval('public.copyright_pk_seq');

ALTER TABLE ONLY public.copyright
    ADD CONSTRAINT copyright_pkey PRIMARY KEY ($copyrightColumn);
ALTER TABLE ONLY public.copyright
    ADD CONSTRAINT copyright_agent_fk_fkey FOREIGN KEY (agent_fk) REFERENCES public.agent(agent_pk) ON DELETE CASCADE;

COMMIT;
";
  $dbManager->queryOnce($sql);
}


/**
 * \brief Remove redundant entries from author table
 * \param DbManager $dbManager DB Manager to be used
 * \param string $authorColumn Primary column name
 */

function CleanAuthorTable($dbManager, $authorColumn)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $sql = "
BEGIN;
DELETE FROM public.author
USING public.author AS a LEFT OUTER JOIN public.pfile AS p ON p.pfile_pk = a.pfile_fk
WHERE public.author.$authorColumn = a.$authorColumn AND p.pfile_pk IS NULL;

DELETE FROM public.author
USING public.author AS au LEFT OUTER JOIN public.agent AS ag ON au.agent_fk = ag.agent_pk
WHERE public.author.$authorColumn = au.$authorColumn AND ag.agent_pk IS NULL;

DELETE FROM public.author
WHERE $authorColumn IN (SELECT $authorColumn
FROM (SELECT $authorColumn,
      ROW_NUMBER() OVER (PARTITION BY hash, pfile_fk, agent_fk, copy_startbyte, copy_endbyte, type
                         ORDER BY $authorColumn) AS rnum
      FROM public.author) a
      WHERE a.rnum > 1);
COMMIT;
";
  $dbManager->queryOnce($sql);
}

/**
 * \brief Remove invalid entries from copyright table
 * \param DbManager $dbManager DB Manager to be used
 * \param string $copyrightColumn Primary column name
 */

function CleanCopyrightTable($dbManager, $copyrightColumn)
{
  if($dbManager == NULL){
    echo "No connection object passed!\n";
    return false;
  }

  $sql = "
BEGIN;

DELETE FROM public.copyright
USING public.copyright AS cp LEFT OUTER JOIN public.agent AS ag ON cp.agent_fk = ag.agent_pk
WHERE public.copyright.$copyrightColumn = cp.$copyrightColumn AND ag.agent_pk IS NULL;

COMMIT;
";
  $dbManager->queryOnce($sql);
}

$result = $dbManager->getSingleRow("SELECT count(*) FROM pg_class WHERE relname = 'author_pk_seq';",
  array(), 'checkAuthorCtPkSequence');
if($result['count'] == 0)
{
  $DatabaseName = $GLOBALS["SysConf"]["DBCONF"]["dbname"];
  $authorColumn = DB_ColExists("author", "ct_pk", $DatabaseName) == 1 ? "ct_pk" : "author_pk";
  $copyrightColumn = DB_ColExists("copyright", "ct_pk", $DatabaseName) == 1 ? "ct_pk" : "copyright_pk";

  try {
    echo "*** Cleaning author table ***\n";
    CleanAuthorTable($dbManager, $authorColumn);
    echo "*** Cleaning copyright table ***\n";
    CleanCopyrightTable($dbManager, $copyrightColumn);
    echo "*** Resequencing author table ***\n";
    ResequenceAuthorTablePKey($dbManager, $authorColumn);
    echo "*** Resequencing copyright table ***\n";
    ResequenceCopyrightTablePKey($dbManager, $copyrightColumn);
  } catch (Exception $e) {
    echo "Something went wrong. Try running postinstall again!\n";
    $dbManager->queryOnce("ROLLBACK;");
  }
}

