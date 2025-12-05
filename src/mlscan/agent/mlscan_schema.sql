-- SPDX-FileCopyrightText: © Fossology contributors
-- SPDX-License-Identifier: GPL-2.0-only

-- Database schema for ML License Scanner (mlscan)

-- Table to store ML license findings
CREATE TABLE IF NOT EXISTS mlscan_license (
  ml_pk SERIAL PRIMARY KEY,
  pfile_fk INTEGER NOT NULL REFERENCES pfile(pfile_pk) ON DELETE CASCADE,
  rf_fk INTEGER REFERENCES license_ref(rf_pk) ON DELETE CASCADE,
  agent_fk INTEGER NOT NULL REFERENCES agent(agent_pk) ON DELETE CASCADE,
  confidence REAL NOT NULL,  -- 0.0 to 1.0
  detection_method VARCHAR(50),  -- 'rule', 'ml-tfidf', 'ml-bert', 'hybrid'
  UNIQUE(pfile_fk, rf_fk, agent_fk)
);

CREATE INDEX IF NOT EXISTS mlscan_license_pfile_idx ON mlscan_license(pfile_fk);
CREATE INDEX IF NOT EXISTS mlscan_license_agent_idx ON mlscan_license(agent_fk);

-- Table for ML scan audit trail (ARS)
CREATE TABLE IF NOT EXISTS mlscan_ars (
  ars_pk SERIAL PRIMARY KEY,
  upload_fk INTEGER NOT NULL REFERENCES upload(upload_pk) ON DELETE CASCADE,
  agent_fk INTEGER NOT NULL REFERENCES agent(agent_pk) ON DELETE CASCADE,
  ars_success BOOLEAN NOT NULL DEFAULT FALSE,
  ars_starttime TIMESTAMP DEFAULT NOW(),
  ars_endtime TIMESTAMP,
  UNIQUE(upload_fk, agent_fk, ars_success)
);

CREATE INDEX IF NOT EXISTS mlscan_ars_upload_idx ON mlscan_ars(upload_fk);
CREATE INDEX IF NOT EXISTS mlscan_ars_agent_idx ON mlscan_ars(agent_fk);
