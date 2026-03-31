# SPDX-FileCopyrightText: 2026 Contributors to FOSSology
# SPDX-License-Identifier: GPL-2.0-only

import re
from enum import Enum
from typing import List, Tuple


class CommentClass(str, Enum):
    LICENSE = "LICENSE"
    DEV_COMMENT = "DEV_COMMENT"
    NOISE = "NOISE"


_SPDX_PATTERN = re.compile(
    r"spdx[- _]license[- _]identifier\s*:", re.IGNORECASE
)

_LICENSE_PHRASES = [
    re.compile(p, re.IGNORECASE) for p in [
        r"licensed\s+under",
        r"permission\s+(is\s+)?hereby\s+granted",
        r"distributed\s+under\s+(the\s+)?terms",
        r"you\s+may\s+(not\s+)?use\s+this\s+file\s+except",
        r"this\s+(file|software|program|library|code)\s+is\s+(free|distributed|released)",
        r"see\s+the\s+(gnu\s+)?general\s+public\s+license",
        r"(gnu\s+)?(lesser\s+)?general\s+public\s+license",
        r"redistribution\s+and\s+use\s+in\s+source",
        r"without\s+warranty\s*(of\s+any\s+kind)?",
        r"as\s+published\s+by\s+the\s+free\s+software\s+foundation",
        r"all\s+rights\s+reserved",
        r"(mit|bsd|apache|artistic)\s+license",
        r"copyright\s*(\(c\)|©)\s*\d{4}",
        r"under\s+the\s+(apache|mit|bsd|gpl|lgpl|mpl)",
        r"\.org/licens",
    ]
]

_NOISE_PATTERNS = [
    re.compile(p, re.IGNORECASE) for p in [
        r"^\s*(//|#|/\*|\*|/\*\*)\s*$",
        r"\b(TODO|FIXME|HACK|XXX|NOSONAR)\b",
        r"^\s*(//|#)\s*-{3,}\s*$",
        r"^\s*(//|#)\s*={3,}\s*$",
        r"^\s*\*{3,}\s*$",
        r"@(param|returns?|throws?|deprecated|since|version|see|author)\b",
        r"^\s*(//|#)\s*vim:|^\s*(//|#)\s*-\*-",
        r"auto[- ]?generated|do\s+not\s+(edit|modify)",
    ]
]

_FALSE_POSITIVE_GUARDS = [
    re.compile(p, re.IGNORECASE) for p in [
        r"\b\w*(license|copyright)\w+\s*[=:(]",
        r"(get|set|check|has|is|validate|load|fetch|parse)(license|copyright)",
        r"\.(license|copyright)\b",
        r"import\s+.*license",
    ]
]


def classify_comment(text: str) -> CommentClass:
    stripped = text.strip()
    if not stripped:
        return CommentClass.NOISE

    for guard in _FALSE_POSITIVE_GUARDS:
        if guard.search(stripped):
            return CommentClass.DEV_COMMENT

    if _SPDX_PATTERN.search(stripped):
        return CommentClass.LICENSE

    for pattern in _LICENSE_PHRASES:
        if pattern.search(stripped):
            return CommentClass.LICENSE

    for pattern in _NOISE_PATTERNS:
        if pattern.search(stripped):
            return CommentClass.NOISE

    return CommentClass.DEV_COMMENT


def classify_batch(comments: List[str]) -> List[Tuple[str, CommentClass]]:
    """Classify a list of comments. Returns (comment, label) tuples."""
    return [(c, classify_comment(c)) for c in comments]


def filter_license_comments(comments: List[str]) -> List[str]:
    """Return only comments classified as LICENSE. Primary integration API."""
    return [c for c, label in classify_batch(comments) if label == CommentClass.LICENSE]
