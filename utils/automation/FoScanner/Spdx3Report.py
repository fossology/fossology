#!/usr/bin/env python3

#
# SPDX-FileCopyrightText: © 2026 Siemens AG
# SPDX-FileContributor: Shatakshi Tiwari <shatakshi.tiwari@siemens.com>
#
# SPDX-License-Identifier: GPL-2.0-only

"""
SPDX 3.0 Report class.

"""

import hashlib
import json
import logging
import os
import re
import uuid
from datetime import datetime, timezone

import rdflib
from rdflib import BNode, Literal, Namespace, RDF, RDFS, URIRef
from rdflib.util import SUFFIX_FORMAT_MAP
from semantic_version import Version

from spdx_tools.spdx3.model import (
    CreationInfo,
    Hash,
    HashAlgorithm,
    Organization,
    ProfileIdentifierType,
    Tool,
    Relationship,
    RelationshipType,
    SpdxDocument,
)
from spdx_tools.spdx3.model.software import (
    File as SpdxFile,
    Package,
    SoftwarePurpose,
)
from license_expression import get_spdx_licensing
from spdx_tools.spdx3.model.licensing import (
    AnyLicenseInfo,
    ConjunctiveLicenseSet,
    CustomLicense,
    DisjunctiveLicenseSet,
    NoAssertionLicense,
)
from spdx_tools.spdx3.payload import Payload
from spdx_tools.spdx3.writer.json_ld import json_ld_writer as _json_ld_writer_mod
from spdx_tools.spdx3.writer.json_ld.json_ld_converter import (
    convert_payload_to_json_ld_list_of_elements,
)

from .CliOptions import CliOptions
from .ReportBase import ReportBase
from .Scanners import Scanners, ScanResultList

SPDX3_SPEC_VERSION = Version("3.0.1")
TOOL_NAME = "FOSSology CI Scanner"


class LicenseExpression(AnyLicenseInfo):

  def __init__(self, license_expression: str):
    self.license_expression = license_expression


class Spdx3Report(ReportBase):
  """
  Handle SPDX 3.0 reports.

  :ivar cli_options: CliOptions object
  :ivar scanner: Scanners object
  :ivar payload: spdx_tools Payload holding all SPDX 3.0 elements
  :ivar root_package: Root Package element for the scanned project
  :ivar creation_info: Shared CreationInfo for all elements
  """

  def __init__(self, cli_options: CliOptions, scanner: Scanners):
    """
    Initialise the SPDX 3.0 report builder.

    :param cli_options: CliOptions to use
    :param scanner:     Scanners to use
    """
    self.cli_options = cli_options
    self.scanner = scanner
    self.payload = Payload()
    self._rel_idx = 0
    self._file_idx = 0
    self._spdx_licensing = get_spdx_licensing()
    self._license_cache: dict[str, object] = {}

    # --- Derive project metadata from scan packages ---
    parent_package = self.scanner.get_scan_packages().parent_package
    project_name = (parent_package.get('name') or '').strip()
    if not project_name:
      if self.cli_options.parser is not None:
        project_name = getattr(
          self.cli_options.parser, 'root_component_name', ''
        ) or ''
      if not project_name:
        project_name = "NA"

    org_name = (parent_package.get('author') or 'Unknown').strip() or 'Unknown'

    # --- Base URI ---
    self._base = self._make_base_uri(project_name)

    # --- CreationInfo (shared by every element) ---
    org_id = (
      f"{self._base}/Agent/"
      f"{re.sub(r'[^a-zA-Z0-9]', '', org_name)}"
    )
    tool_id = f"{self._base}/Tool/fossology-scanner"

    self.creation_info = CreationInfo(
      spec_version=SPDX3_SPEC_VERSION,
      created=datetime.now(timezone.utc),
      created_by=[org_id],
      created_using=[tool_id],
      profile=[
        ProfileIdentifierType.CORE,
        ProfileIdentifierType.SOFTWARE,
        ProfileIdentifierType.LICENSING,
      ],
    )

    # --- Agent / Tool elements ---
    org_elem = Organization(
      spdx_id=org_id,
      creation_info=self.creation_info,
      name=org_name,
    )
    tool_elem = Tool(
      spdx_id=tool_id,
      creation_info=self.creation_info,
      name=TOOL_NAME,
    )
    self.payload.add_element(org_elem)
    self.payload.add_element(tool_elem)

    # --- Root Package ---
    safe_pkg = re.sub(r"[^a-zA-Z0-9._-]", "-", project_name)
    root_pkg_id = f"{self._base}/Package/{safe_pkg}"
    self.root_package = Package(
      spdx_id=root_pkg_id,
      name=project_name,
      creation_info=self.creation_info,
      primary_purpose=SoftwarePurpose.APPLICATION,
    )
    self.payload.add_element(self.root_package)

  # ------------------------------------------------------------------
  # Public API
  # ------------------------------------------------------------------

  def finalize_document(self):
    """
    Process all scan results (parent + dependencies), create File elements
    and Relationships, then build the SpdxDocument.
    """
    # Process parent package
    parent = self.scanner.get_scan_packages().parent_package
    self._process_component(parent, self.root_package.spdx_id)

    # Process each dependency
    for _purl, component in (
        self.scanner.get_scan_packages().dependencies.items()
    ):
      dep_pkg = self._get_or_create_dep_package(component)
      self._process_component(component, dep_pkg.spdx_id)

      # DEPENDS_ON relationship from root → dependency
      dep_rel = Relationship(
        spdx_id=f"{self._base}/Relationship/{self._rel_idx}",
        from_element=self.root_package.spdx_id,
        relationship_type=RelationshipType.DEPENDS_ON,
        to=[dep_pkg.spdx_id],
        creation_info=self.creation_info,
      )
      self.payload.add_element(dep_rel)
      self._rel_idx += 1

    # --- Build SpdxDocument ---
    all_ids = [e.spdx_id for e in self.payload.get_full_map().values()]

    describes_rel_id = f"{self._base}/Relationship/{self._rel_idx}"
    all_ids.append(describes_rel_id)

    doc_id = f"{self._base}/Document"
    spdx_doc = SpdxDocument(
      spdx_id=doc_id,
      name=self.root_package.name,
      element=all_ids,
      root_element=[self.root_package.spdx_id],
      creation_info=self.creation_info,
    )
    self.payload.add_element(spdx_doc)

    describes_rel = Relationship(
      spdx_id=describes_rel_id,
      from_element=doc_id,
      relationship_type=RelationshipType.DESCRIBES,
      to=[self.root_package.spdx_id],
      creation_info=self.creation_info,
    )
    self.payload.add_element(describes_rel)
    self._rel_idx += 1

    logging.info("SPDX 3.0 document finalized with %d elements.",
                 len(self.payload.get_full_map()))

  def write_report(self, file_name: str):

    os.makedirs(os.path.dirname(os.path.abspath(file_name)), exist_ok=True)

    # --- Build RDF graph from spdx-tools model objects ---
    element_list = convert_payload_to_json_ld_list_of_elements(self.payload)
    context_path = os.path.join(
      os.path.dirname(_json_ld_writer_mod.__file__), "context.json",
    )
    with open(context_path, "r", encoding="utf-8") as ctx_file:
      context = json.load(ctx_file)

    # Inject LicenseExpression type and property into the JSON-LD context.
    # The spdx-tools library does not yet implement the SimpleLicensing
    # LicenseExpression class, so we add the mappings here.
    ctx_inner = context if "@context" not in context else context["@context"]
    ctx_inner["LicenseExpression"] = "licensing:LicenseExpression"
    ctx_inner["licenseExpression"] = {
      "@id": "licensing:licenseExpression",
      "@type": "xsd:string"
    }

    json_ld_dict = {"@context": context, "@graph": element_list}
    json_ld_str = json.dumps(json_ld_dict)

    g = rdflib.Graph()
    g.parse(data=json_ld_str, format="json-ld")

    # --- Apply fixups ---
    self._deduplicate_creation_info(g, rdflib)
    self._fixup_rdf_graph(g, rdflib)

    # --- Serialize to target format ---
    out_format = self._rdf_format_for_file(file_name)

    if out_format in ("xml", "pretty-xml"):
      self._sanitize_xml_literals(g, rdflib)

    g.serialize(destination=file_name, format=out_format)
    logging.info("SPDX 3.0 %s written to: %s (%d bytes)",
                 out_format.upper(), file_name, os.path.getsize(file_name))

    # --- Validate ---
    self._validate_report(file_name)

  # ------------------------------------------------------------------
  # Internal helpers
  # ------------------------------------------------------------------

  @staticmethod
  def _load_spdx_shacl_model():
    """
    Load the SPDX SHACL model bundled with spdx-tools.

    :return: (rdflib.Graph, shacl_path_str)
    """
    shacl_path = os.path.join(os.path.dirname(_json_ld_writer_mod.__file__), "model.ttl")
    model = rdflib.Graph()
    with open(shacl_path, "r", encoding="utf-8") as f:
      model.parse(data=f.read(), format="turtle")
    return model, shacl_path

  def _deduplicate_creation_info(self, g, rdflib):
    """
    Merge identical CreationInfo blank nodes into a single named node.

    The spdx-tools JSON-LD serializer inlines CreationInfo as blank nodes,
    causing duplication. This replaces all of them with one shared URI node.
    """
    SPDX_CORE = Namespace("https://spdx.org/rdf/Core/")
    ci_type = SPDX_CORE.CreationInfo

    # Collect all blank nodes of type CreationInfo
    ci_bnodes = [
      s for s in g.subjects(RDF.type, ci_type) if isinstance(s, BNode)
    ]
    if len(ci_bnodes) <= 1:
      return

    # Create a single named node for the shared CreationInfo
    ci_uri = URIRef(f"{self._base}/CreationInfo/shared")

    # Copy all triples from the first blank node to the named node
    first = ci_bnodes[0]
    for p, o in g.predicate_objects(first):
      g.add((ci_uri, p, o))

    # Replace all references and remove old blank node triples
    for bnode in ci_bnodes:
      for s, p in list(g.subject_predicates(bnode)):
        g.remove((s, p, bnode))
        g.add((s, p, ci_uri))
      for p, o in list(g.predicate_objects(bnode)):
        g.remove((bnode, p, o))

    logging.info("Deduplicated %d CreationInfo nodes into 1", len(ci_bnodes))

  def _fixup_rdf_graph(self, g, rdflib):
    
    SH = Namespace("http://www.w3.org/ns/shacl#")

    model, _ = self._load_spdx_shacl_model()

    # ------------------------------------------------------------------
    # 1. Build transitive rdfs:subClassOf closure from the SHACL ontology
    # ------------------------------------------------------------------
    subclass_map: dict[URIRef, set[URIRef]] = {}
    for child, parent in model.subject_objects(RDFS.subClassOf):
      if isinstance(child, URIRef) and isinstance(parent, URIRef):
        subclass_map.setdefault(child, set()).add(parent)

    # Inject LicenseExpression type hierarchy (not in bundled SHACL model).
    # Per the SPDX 3.0.1 spec, LicenseExpression is:
    #   LicenseExpression → AnyLicenseInfo → LicenseField
    SPDX_LIC = Namespace("https://spdx.org/rdf/Licensing/")
    subclass_map.setdefault(
      SPDX_LIC.LicenseExpression, set()
    ).add(SPDX_LIC.AnyLicenseInfo)

    # Compute transitive closure (child → all ancestors)
    changed = True
    while changed:
      changed = False
      for child, parents in list(subclass_map.items()):
        for parent in list(parents):
          grandparents = subclass_map.get(parent, set())
          new_ancestors = grandparents - parents
          if new_ancestors:
            parents.update(new_ancestors)
            changed = True

    # ------------------------------------------------------------------
    # 2. Collect sh:property shapes with sh:class constraints
    # ------------------------------------------------------------------
    # property_path → expected sh:class
    class_constraints: dict[URIRef, URIRef] = {}
    for shape in model.subjects(SH.property, None):
      for prop_node in model.objects(shape, SH.property):
        sh_path = model.value(prop_node, SH.path)
        sh_class = model.value(prop_node, SH["class"])
        if sh_path and sh_class and isinstance(sh_path, URIRef):
          class_constraints[sh_path] = sh_class

    # ------------------------------------------------------------------
    # 3. Fix typed literals → IRI references for sh:class properties
    #    The spdx-tools JSON-LD context.json mis-declares some properties
    #    with @type set to a class name instead of @id, producing typed
    #    literals (e.g. "file"^^SoftwarePurpose) where IRIs are expected.
    # ------------------------------------------------------------------
    for prop_path, expected_class in class_constraints.items():
      for s, o in list(g.subject_objects(prop_path)):
        if isinstance(o, Literal):
          val = str(o)
          if val.startswith(("http://", "https://", "urn:")):
            # Value is already a valid URI string → promote to IRI
            iri = URIRef(val)
          else:
            # Enum-style value → construct <ClassIRI/value>
            iri = URIRef(f"{expected_class}/{val}")
          g.remove((s, prop_path, o))
          g.add((s, prop_path, iri))

    # ------------------------------------------------------------------
    # 4. Add superclass types to all typed instances in the data graph
    # ------------------------------------------------------------------
    for child_class, ancestors in subclass_map.items():
      for subject in list(g.subjects(RDF.type, child_class)):
        for ancestor in ancestors:
          g.add((subject, RDF.type, ancestor))

    # ------------------------------------------------------------------
    # 5. Declare enum / class instances for IRI-valued sh:class properties
    #    If a value is a URIRef but has no rdf:type matching the expected
    #    sh:class (or any of its subclasses), add the type declaration.
    # ------------------------------------------------------------------
    for prop_path, expected_class in class_constraints.items():
      for _, o in g.subject_objects(prop_path):
        if isinstance(o, URIRef):
          existing_types = set(g.objects(o, RDF.type))
          # Check if any existing type is the expected class or a subclass
          if not self._type_satisfies(
              existing_types, expected_class, subclass_map
          ):
            g.add((o, RDF.type, expected_class))

  @staticmethod
  def _type_satisfies(existing_types, expected_class, subclass_map):
    """
    Check whether any of *existing_types* equals *expected_class* or is
    a known subclass of it (according to *subclass_map*).
    """
    for t in existing_types:
      if t == expected_class:
        return True
      if expected_class in subclass_map.get(t, set()):
        return True
    return False

  def _process_component(self, component: dict, parent_pkg_id: str):
    """
    Read SCANNER_RESULTS and COPYRIGHT_RESULT from *component* dict,
    create File elements, and wire CONTAINS relationships.
    """
    # Merge license + copyright results by file path
    file_data: dict[str, dict] = {}

    for scan_result in component.get('SCANNER_RESULTS', []):
      path = scan_result.file
      entry = file_data.setdefault(path, {
        'scan_result': scan_result, 'licenses': [], 'copyrights': []
      })
      for lic in scan_result.result:
        lic_str = lic.get('license', '') if isinstance(lic, dict) else str(lic)
        if lic_str:
          entry['licenses'].append(lic_str)

    for cr_result in component.get('COPYRIGHT_RESULT', []):
      path = cr_result.file
      entry = file_data.setdefault(path, {
        'scan_result': cr_result, 'licenses': [], 'copyrights': []
      })
      for cpy in cr_result.result:
        text = cpy.get('content', '') if isinstance(cpy, dict) else str(cpy)
        if text:
          entry['copyrights'].append(text)

    for path, data in file_data.items():
      # Skip .git/ directory contents
      if path.startswith('.git/') or '/.git/' in path:
        continue

      scan_result = data['scan_result']
      file_id = f"{self._base}/File/{self._file_idx}"
      self._file_idx += 1

      # Hashes
      hashes = []
      try:
        sha256_hash = hashlib.sha256()
        with open(scan_result.path, "rb") as fh:
          for chunk in iter(lambda: fh.read(4096), b""):
            sha256_hash.update(chunk)
        hashes.append(
          Hash(algorithm=HashAlgorithm.SHA256,
               hash_value=sha256_hash.hexdigest())
        )
      except (OSError, AttributeError):
        pass

      # Copyright text
      copyright_text = "\n".join(data['copyrights']) or None

      # Concluded license
      licenses = sorted(set(data['licenses']))
      concluded_license = self._resolve_licenses(licenses)

      f_elem = SpdxFile(
        spdx_id=file_id,
        name=path,
        creation_info=self.creation_info,
        copyright_text=copyright_text,
        verified_using=hashes if hashes else None,
        primary_purpose=SoftwarePurpose.FILE,
        concluded_license=concluded_license,
      )
      self.payload.add_element(f_elem)

      # CONTAINS relationship
      contains_rel = Relationship(
        spdx_id=f"{self._base}/Relationship/{self._rel_idx}",
        from_element=parent_pkg_id,
        relationship_type=RelationshipType.CONTAINS,
        to=[file_id],
        creation_info=self.creation_info,
      )
      self.payload.add_element(contains_rel)
      self._rel_idx += 1

  def _resolve_licenses(self, licenses: list[str]):
    """
    Resolve a list of license identifiers to the correct SPDX 3.0 model
    objects.

    - SPDX-listed licenses → ListedLicense (ExpandedLicensing)
    - Non-SPDX licenses → CustomLicense with LicenseRef- prefix
    - Multiple licenses → ConjunctiveLicenseSet (AND semantics)
    - No licenses → NoAssertionLicense

    Objects are cached so the same license ID reuses one instance.
    """
    if not licenses:
      return NoAssertionLicense()

    resolved = [self._get_license_object(lic) for lic in licenses]

    if len(resolved) == 1:
      return resolved[0]
    return ConjunctiveLicenseSet(member=resolved)

  def _get_license_object(self, lic_id: str):
    """Return a cached LicenseExpression or CustomLicense for a single ID.

    For SPDX-listed licenses, creates a LicenseExpression with just the
    spdx expression string (no full license object needed per the spec).
    For custom/unknown licenses, creates a CustomLicense with licenseText
    as required by the SPDX 3.0 spec (minCount=1).
    """
    if lic_id in self._license_cache:
      return self._license_cache[lic_id]

    if not self._spdx_licensing.validate(lic_id).invalid_symbols:
      # Known SPDX-listed license — use LicenseExpression with just the
      # spdx identifier string. No need for a full License object.
      obj = LicenseExpression(
        license_expression=lic_id,
      )
    else:
      # Non-SPDX / custom license — requires licenseText (minCount=1)
      safe = re.sub(r"[^a-zA-Z0-9._-]", "-", lic_id)
      ref_id = lic_id if lic_id.startswith("LicenseRef-") else f"LicenseRef-fossology-{safe}"
      # Strip LicenseRef- prefix for the human-readable name
      # (matches SPDX 2.3 behaviour where license_name = original scanner value)
      display_name = lic_id.removeprefix("LicenseRef-") if lic_id.startswith("LicenseRef-") else lic_id
      obj = CustomLicense(
        license_id=ref_id,
        license_name=display_name,
        license_text=f"The license text for {ref_id} has to be entered.",
      )

    self._license_cache[lic_id] = obj
    return obj

  def _get_or_create_dep_package(self, component: dict) -> Package:
    """
    Create (or retrieve cached) a dependency Package element.
    """
    pkg_name = component.get('name', 'UNKNOWN')
    pkg_version = component.get('version', 'UNKNOWN')
    safe = re.sub(r"[^a-zA-Z0-9._-]", "-", f"{pkg_name}-{pkg_version}")
    pkg_id = f"{self._base}/Package/{safe}"

    # Check if already in payload
    existing = self.payload.get_full_map().get(pkg_id)
    if existing is not None:
      return existing

    dep_pkg = Package(
      spdx_id=pkg_id,
      name=pkg_name,
      creation_info=self.creation_info,
      primary_purpose=SoftwarePurpose.LIBRARY,
    )
    self.payload.add_element(dep_pkg)
    return dep_pkg

  @staticmethod
  def _make_base_uri(doc_name: str) -> str:
    """Generate a unique base URI for the document."""
    unique = uuid.uuid4().hex[:12]
    safe = re.sub(r"[^a-zA-Z0-9._-]", "-", doc_name)
    return f"urn:spdx:{safe}/{unique}"

  # Regex matching characters forbidden in XML 1.0.
  # Allowed: #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
  _XML_INVALID_RE = re.compile(
    '[\x00-\x08\x0b\x0c\x0e-\x1f\x7f-\x84\x86-\x9f'
    '\ud800-\udfff\ufdd0-\ufdef\ufffe\uffff]'
  )

  @staticmethod
  def _sanitize_xml_literals(g, rdflib):
    """
    Remove characters that are invalid in XML 1.0 from all Literal values.

    rdflib's RDF/XML serializer writes these characters verbatim, but
    XML parsers (expat) reject them on re-read, causing 'not well-formed'
    errors.  Only needed for XML-based output formats.
    """
    for s, p, o in list(g):
      if isinstance(o, Literal):
        val = str(o)
        clean = Spdx3Report._XML_INVALID_RE.sub('', val)
        if clean != val:
          g.remove((s, p, o))
          g.add((s, p, Literal(clean, datatype=o.datatype, lang=o.language)))

  @staticmethod
  def _rdf_format_for_file(file_name: str) -> str:
    """
    Determine the rdflib serialization format from a file extension.

    Uses rdflib's own ``SUFFIX_FORMAT_MAP`` so that new formats are
    supported automatically without code changes. Falls back to
    ``json-ld`` for unknown extensions.
    """
    ext = os.path.splitext(file_name)[1].lstrip(".").lower()
    return SUFFIX_FORMAT_MAP.get(ext, "json-ld")

  def _validate_report(self, file_name: str) -> None:
    """
    Validate the written SPDX 3.0 report using pyshacl against the
    SHACL schema bundled with spdx-tools.

    Works for any RDF serialization format (Turtle, JSON-LD, RDF/XML, etc.).
    """
    data_format = self._rdf_format_for_file(file_name)

    logging.info("Validating SPDX 3.0 report against SHACL schema...")
    try:
      import pyshacl

      shacl_graph, _ = self._load_spdx_shacl_model()

      data_graph = rdflib.Graph()
      data_graph.parse(file_name, format=data_format)

      conforms, results_graph, results_text = pyshacl.validate(
        data_graph=data_graph,
        shacl_graph=shacl_graph,
      )
    except ImportError as e:
      logging.warning("SHACL validation skipped (missing dependency: %s)", e)
      return
    except Exception as e:
      logging.warning("SHACL validation could not run: %s", e)
      return

    if conforms:
      logging.info("SPDX 3.0 report conforms to SHACL schema.")
    else:
      lines = results_text.strip().split('\n')
      violation_count = len(
        [l for l in lines if 'Violation' in l]
      )
      logging.warning(
        "SHACL validation reported %d violation(s).",
        violation_count,
      )
      for line in lines:
        logging.debug("SHACL: %s", line)
      logging.info("Report saved despite SHACL warnings (non-blocking).")
