-- Migration script for C++ Reuser Agent schema

CREATE TABLE IF NOT EXISTS main_licenses (
    upload_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    license_id INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS report_conf_reuse (
    upload_id INTEGER NOT NULL,
    reused_upload_id INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS copyright_events (
    hash TEXT NOT NULL,
    uploadtree_pk TEXT NOT NULL,
    is_enabled BOOLEAN NOT NULL,
    contentedited TEXT,
    upload_id INTEGER NOT NULL,
    agent_fk INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS contained_items (
    id INTEGER PRIMARY KEY,
    file_id INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS pfiles (
    pfile_id INTEGER PRIMARY KEY,
    repo_path TEXT
);

CREATE TABLE IF NOT EXISTS uploadtree (
    uploadtree_pk INTEGER PRIMARY KEY,
    file_id INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS uploads (
    upload_id INTEGER PRIMARY KEY,
    uploadtree_table TEXT
);

CREATE TABLE IF NOT EXISTS agents (
    agent_id INTEGER PRIMARY KEY,
    upload_id INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS copyright_table (
    hash TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    contentedited TEXT
);
