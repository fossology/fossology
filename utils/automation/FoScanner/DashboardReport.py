#!/usr/bin/env python3
 
# SPDX-FileCopyrightText: © 2026 Siemens AG
# SPDX-FileContributor: Shatakshi Tiwari <shatakshi.tiwari@siemens.com>
#
# SPDX-License-Identifier: GPL-2.0-only
 
"""
Dashboard report generator for FOSSology CI scanners.
 
Generates a GitHub Actions job summary (Markdown) directly from scanner
results (nomos, ojo)
"""
 
import logging
import os
from collections import Counter
 
from .CliOptions import CliOptions
from .ReportBase import ReportBase
from .Scanners import Scanners, ScanResult, ScanResultList
 
_NO_LICENSE = frozenset({
  'No_license_found', 'NOASSERTION', 'NONE', 'UnclassifiedLicense',
})
 
 
def _license_display_name(license_id: str) -> str:
  """Return human-readable license name for dashboard output."""
  return (license_id.removeprefix('LicenseRef-')
          if license_id.startswith('LicenseRef-') else license_id)
 
 
# ---------------------------------------------------------------------------
# DashboardReport
# ---------------------------------------------------------------------------
 
class DashboardReport(ReportBase):
  """
  Build a Markdown compliance dashboard from scanner result objects
  already held by the ``Scanners`` instance.
 
  Lifecycle (same as SpdxReport / Spdx3Report):
      1. ``fossologyscanner.perform_scans()`` populates
         ``scanner.scan_packages`` with NOMOS/OJO results.
      2. ``finalize_document()`` reads those results and builds the
         Markdown string.
      3. ``write_report(path)`` writes the Markdown to *path* **and**,
         when running in GitHub Actions, appends it to
         ``$GITHUB_STEP_SUMMARY``.
  """
 
  def __init__(self, cli_options: CliOptions, scanner: Scanners):
    super().__init__(cli_options, scanner)
    self._markdown: str = ''
    self._stats: dict = {}
 
    # Dashboard feature flags (env-driven, all default to True)
    self.include_charts = _parse_bool_env('DASHBOARD_CHARTS', True)
    self.include_unknown = _parse_bool_env('DASHBOARD_UNKNOWN', True)
 
  # -----------------------------------------------------------------
  # ReportBase interface
  # -----------------------------------------------------------------
 
  def finalize_document(self) -> None:
    """Collect scanner results and assemble the Markdown dashboard."""
    license_results = self._collect_license_results()
    failed_results = self.scanner.results_are_allow_listed(whole=False)
 
    self._markdown, self._stats = self._build_markdown(
      license_results, failed_results
    )
    logging.info("Dashboard document finalized.")
 
  def write_report(self, file_name: str) -> None:
    """Write the dashboard Markdown to *file_name* and GITHUB_STEP_SUMMARY."""
    if not self._markdown:
      logging.warning("Dashboard is empty — nothing to write.")
      return
 
    # Write to file
    with open(file_name, 'w', encoding='utf-8') as fh:
      fh.write(self._markdown)
    logging.info(f"Dashboard written to {file_name}")
 
    # Append to GitHub step summary when running in Actions
    summary_path = os.getenv('GITHUB_STEP_SUMMARY')
    if summary_path:
      try:
        with open(summary_path, 'a', encoding='utf-8') as fh:
          fh.write(self._markdown)
        logging.info("Dashboard appended to GITHUB_STEP_SUMMARY.")
      except OSError as exc:
        logging.warning(f"Could not write to GITHUB_STEP_SUMMARY: {exc}")
 
  # -----------------------------------------------------------------
  # Result collection helpers
  # -----------------------------------------------------------------
 
  def _collect_license_results(self) -> list[ScanResult | ScanResultList]:
    """Return merged nomos+ojo results from all packages."""
    results: list[ScanResult | ScanResultList] = []
    parent = self.scanner.get_scan_packages().parent_package
    results.extend(parent.get('SCANNER_RESULTS', []))
    for dep in self.scanner.get_scan_packages().dependencies.values():
      results.extend(dep.get('SCANNER_RESULTS', []))
    return results
 
  def _get_counts(self) -> tuple[int, int]:
    """Return (total_components, dependency_count)."""
    dep_count = len(self.scanner.get_scan_packages().dependencies)
    return 1 + dep_count, dep_count
 
  # -----------------------------------------------------------------
  # Markdown builder
  # -----------------------------------------------------------------
 
  def _finalise_data(
      self,
      license_results: list,
      failed_results: list,
  ) -> dict:
    """
    Compute all statistics from raw scanner results.

    :return: A dict with keys: sorted_licenses, failed_by_license,
             unknown_files, unique_licenses, total_files,
             component_count, dep_count.
    """
    license_counter: Counter = Counter()
    files_with_license: set[str] = set()
    all_files: set[str] = set()

    for res in license_results:
      all_files.add(res.file)
      licenses = set()
      if isinstance(res, ScanResultList):
        for item in res.result:
          lic_raw = item.get('license', '') if isinstance(item, dict) else str(item)
          if lic_raw and lic_raw not in _NO_LICENSE:
            licenses.add(_license_display_name(lic_raw))
      elif isinstance(res, ScanResult):
        for lic_raw in res.result:
          if lic_raw and lic_raw not in _NO_LICENSE:
            licenses.add(_license_display_name(lic_raw))

      for lic in licenses:
        license_counter[lic] += 1
        files_with_license.add(res.file)

    failed_by_license: dict[str, list[str]] = {}
    for res in failed_results:
      for lic_raw in res.result:
        lic = _license_display_name(lic_raw)
        failed_by_license.setdefault(lic, []).append(res.file)

    component_count, dep_count = self._get_counts()
    return {
      'sorted_licenses': sorted(
        license_counter.items(), key=lambda x: x[1], reverse=True
      ),
      'failed_by_license': failed_by_license,
      'unknown_files': sorted(all_files - files_with_license),
      'unique_licenses': len(license_counter),
      'total_files': len(all_files),
      'component_count': component_count,
      'dep_count': dep_count,
    }

  def _markdown_builder(self, data: dict) -> str:
    """
    Assemble the full Markdown string from pre-computed *data*.

    :param data: Dict returned by :meth:`_finalise_data`.
    :return: Markdown string.
    """
    sorted_licenses = data['sorted_licenses']
    failed_by_license = data['failed_by_license']
    unknown_files = data['unknown_files']
    component_count = data['component_count']
    dep_count = data['dep_count']
    total_files = data['total_files']
    unique_licenses = data['unique_licenses']

    md = "# License Compliance Dashboard\n\n"

    # KPI table
    md += "## Summary\n\n"
    md += "| Metric | Value |\n|--------|-------|\n"
    md += f"| Components Scanned | {component_count} (1 parent + {dep_count} dependencies) |\n"
    md += f"| Total Files Scanned | {total_files} |\n"
    md += f"| Unique Licenses Found | {unique_licenses} |\n"
    md += f"| Licenses with Violations | {len(failed_by_license)} |\n"
    md += f"| Files Without License | {len(unknown_files)} |\n"
    md += "\n---\n\n"

    # Charts
    if self.include_charts and sorted_licenses:
      top_10 = sorted_licenses[:10]
      md += "## License Distribution\n\n"
      md += ("```mermaid\n"
             "%%{init: {'theme': 'base', 'themeVariables': {"
             "'pie1': '#E63946', 'pie2': '#457B9D', "
             "'pie3': '#2A9D8F', 'pie4': '#E9C46A', "
             "'pie5': '#F4A261', 'pie6': '#264653', "
             "'pie7': '#6A0572', 'pie8': '#1B998B', "
             "'pie9': '#FF6B6B', 'pie10': '#4ECDC4', "
             "'pieTitleTextSize': '18px', "
             "'pieSectionTextSize': '14px'"
             "}}}%%\n"
             "pie showData title License Distribution\n")
      for lic, count in top_10:
        md += f'    "{lic}" : {count}\n'
      md += "```\n\n---\n\n"

    # License inventory
    md += "## License Inventory\n\n"
    md += "| # | License | Files |\n|---|---------|-------|\n"
    for i, (lic, count) in enumerate(sorted_licenses[:30], 1):
      md += f"| {i} | `{lic}` | {count} |\n"
    if len(sorted_licenses) > 30:
      md += f"\n*Showing top 30 of {len(sorted_licenses)} unique licenses*\n"
    md += "\n---\n\n"

    # License violations (not in allowlist)
    if failed_by_license:
      md += "## License Violations\n\n"
      md += "The following licenses are not in the allowlist.\n\n"
      md += "| License | Violation Count | Files |\n"
      md += "|---------|-----------------|-------|"
      for lic, files in sorted(
          failed_by_license.items(), key=lambda x: len(x[1]), reverse=True
      ):
        file_list = ', '.join(f'`{f}`' for f in files[:5])
        if len(files) > 5:
          file_list += f' *+{len(files) - 5} more*'
        md += f"\n| `{lic}` | {len(files)} | {file_list} |"
      md += "\n\n---\n\n"

    # Unknown-license files
    if self.include_unknown and unknown_files:
      md += "## Files Without License\n\n"
      md += (f"Found **{len(unknown_files)}** files with no license "
             "detected by any scanner.\n\n")
      md += "<details>\n<summary>Click to expand file list</summary>\n\n"
      md += "| File |\n|------|\n"
      for fp in unknown_files[:100]:
        md += f"| `{fp}` |\n"
      if len(unknown_files) > 100:
        md += f"\n*...and {len(unknown_files) - 100} more files*\n"
      md += "\n</details>\n\n---\n\n"

    # Footer
    md += ("\n---\n\n*Generated by "
           "[FOSSology Action](https://github.com/fossology/fossology) "
           "\u2014 direct scanner mode (nomos + ojo)*\n")
    return md

  def _build_markdown(
      self,
      license_results: list,
      failed_results: list,
  ) -> tuple[str, dict]:
    """Finalise data, assemble the Markdown dashboard, and return both."""
    data = self._finalise_data(license_results, failed_results)
    md = self._markdown_builder(data)
    stats = {
      'components_scanned': data['component_count'],
      'dependencies': data['dep_count'],
      'files_scanned': data['total_files'],
      'unique_licenses': data['unique_licenses'],
      'license_violations': len(data['failed_by_license']),
      'files_without_license': len(data['unknown_files']),
    }
    return md, stats
 
 
# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
 
def _parse_bool_env(name: str, default: bool = True) -> bool:
  value = os.getenv(name, str(default)).lower()
  return value in ('true', '1', 'yes', 'on')