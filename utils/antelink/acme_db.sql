DROP TABLE acme_pfile;
DROP TABLE acme_project;
DROP TABLE acme_upload;
DROP TABLE acme_project_hierarchy;
CREATE TABLE acme_pfile
(
  acme_pfile_pk serial NOT NULL,
  acme_project_fk integer NOT NULL,
  pfile_fk integer NOT NULL,
  CONSTRAINT acme_pfile_pkey PRIMARY KEY (acme_pfile_pk )
);

CREATE TABLE acme_project
(
  acme_project_pk serial NOT NULL,
  project_name text NOT NULL,
  url text,
  description text,
  licenses text,
  releasedate text,
  version text,
  CONSTRAINT acme_project_pkey PRIMARY KEY (acme_project_pk )
);

CREATE TABLE acme_upload
(
  acme_upload_pk serial NOT NULL,
  upload_fk integer NOT NULL,
  acme_project_fk integer NOT NULL,
  include text NOT NULL,
  detail integer NOT NULL,
  count integer NOT NULL,
  CONSTRAINT acme_upload_pkey PRIMARY KEY (acme_upload_pk )
)

CREATE TABLE acme_project_hierarchy
(
  acme_project_hierarchy_pk serial NOT NULL,
  upload_fk integer NOT NULL,
  uploadtree_fk integer NOT NULL,
  pfile_fk integer NOT NULL,
  acme_project_fk integer,
  parent integer,
  contains_subproject_flag integer NOT NULL,
  contained_subprojects text,
  count integer NOT NULL,
  CONSTRAINT acme_project_hierarchy_pkey PRIMARY KEY (acme_project_hierarchy_pk )
)