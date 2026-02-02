-- CMU to CMU-Mach License Migration
--
-- FOSSology previously contained two license entries with identical
-- license terms: "CMU" and "CMU-Mach".
--
-- SPDX does not define a generic "CMU" license identifier.
-- The correct SPDX identifier is "CMU-Mach".
--
-- The legacy "CMU" license entry has been removed from licenseRef.json.
--
-- In existing installations, license names may be stored directly
-- in database tables such as report_info.
--
-- If required, existing data can be updated manually using the
-- statement below.

UPDATE report_info
SET license = 'CMU-Mach'
WHERE license = 'CMU';
