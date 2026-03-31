# SPDX-FileCopyrightText: 2026 Contributors to FOSSology
# SPDX-License-Identifier: GPL-2.0-only

from nirjas.comment_classifier import (
    classify_comment,
    classify_batch,
    filter_license_comments,
    CommentClass,
)

__all__ = [
    "classify_comment",
    "classify_batch",
    "filter_license_comments",
    "CommentClass",
    "extract_classified",
]


def extract_classified(file_path):
    try:
        from nirjas.main import extract
    except ImportError:
        raise ImportError(
            "Full Nirjas package required for extract_classified(). "
            "Install via: pip install nirjas"
        )

    result = extract(file_path)

    all_comments = []
    for comment_obj in (
        result.single_line_comment
        + result.cont_single_line_comment
        + result.multi_line_comment
    ):
        all_comments.append(comment_obj["comment"])

    result.classified_comments = classify_batch(all_comments)
    return result
