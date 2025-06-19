-- SPDX-FileCopyrightText: © 2022 Siemens AG
-- SPDX-License-Identifier: GPL-2.0-only AND LGPL-2.1-only

-- Register the textphrase agent
INSERT INTO agent (agent_name, agent_rev, agent_desc, agent_enabled, agent_parms, agent_ts) 
VALUES ('textphrase', '1.0.0', 'Text Phrase Scanner', true, NULL, CURRENT_TIMESTAMP)
ON CONFLICT (agent_name) DO NOTHING;

-- Text Phrases Table
CREATE TABLE IF NOT EXISTS text_phrases (
  id SERIAL PRIMARY KEY,
  text TEXT NOT NULL,
  license_fk INTEGER NOT NULL REFERENCES license_ref(rf_pk),
  acknowledgement TEXT,
  comments TEXT,
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_by INTEGER REFERENCES users(user_pk),
  updated_by INTEGER REFERENCES users(user_pk)
);

-- Text Phrase Findings Table
CREATE TABLE IF NOT EXISTS text_phrase_findings (
  id SERIAL PRIMARY KEY,
  pfile_fk INTEGER NOT NULL REFERENCES pfile(pfile_pk),
  phrase_id INTEGER NOT NULL REFERENCES text_phrases(id),
  license_fk INTEGER NOT NULL REFERENCES license_ref(rf_pk),
  match_text TEXT NOT NULL,
  match_offset INTEGER NOT NULL,
  match_length INTEGER NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_text_phrases_license ON text_phrases(license_fk);
CREATE INDEX IF NOT EXISTS idx_text_phrases_active ON text_phrases(is_active);
CREATE INDEX IF NOT EXISTS idx_text_phrase_findings_pfile ON text_phrase_findings(pfile_fk);
CREATE INDEX IF NOT EXISTS idx_text_phrase_findings_phrase ON text_phrase_findings(phrase_id);
CREATE INDEX IF NOT EXISTS idx_text_phrase_findings_license ON text_phrase_findings(license_fk);

-- Trigger for updating timestamp
CREATE OR REPLACE FUNCTION update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_text_phrases_timestamp
  BEFORE UPDATE ON text_phrases
  FOR EACH ROW
  EXECUTE PROCEDURE update_timestamp(); 