"""WS52-KH: fixture-free, source-only RC01 isolation and cleanup policy.

NOT a Docker inspector, database preflight, runner, approval, or validator of
real host provenance. Inputs are untrusted, sanitized, synthetic evidence.
No subprocesses, network calls, imports of application PHP, filesystem writes,
SQL, host/guest discovery, or secret reading. Never enables RC01.
"""
from __future__ import annotations

from dataclasses import dataclass
import re
from typing import Mapping, Any

# These intentional stoppers must not be set by environment/CLI variables.
RC01_EXECUTION_ENABLED = False
APPROVED_MARIADB_IMAGE = None  # Exact reviewed OCI digest is NOT established.
APPROVED_RUNTIME_COMMIT = None  # No reviewed one-use executable branch exists.
APPROVED_ONE_USE_RUN = None

REPO = "abeycity4u/Renee-Farms-V2.2.50-Batch3B1"
CANDIDATE_BRANCH = "c137-gc24-k2p03-b12-ws52kf-github-rc01-candidate-disabled"
CANDIDATE_COMMIT = "99538d136d06228fdff1351b676af828feb9942c"
CANDIDATE_BLOB = "e78318509e270ccd24684bf41f5e854b74765f76"
ORIGINAL_MAC_BLOB = "6f17d1a4ba7e98dbf286d1c8e1a147e6ac3e4aad"
GATE_BLOB = "6e88c913ad5e5b371a9d0c92ce08870214ac1268"
AUTHORITY_BLOB = "5fbb90903b0cc801be45c684f6c3db85c660979a"
SCHEMA_BLOB = "e77f287d62c5812a3d55dc23b5b20f5d167db389"
DB_NAME = "renee_b12_gh_rc01_synthetic_only"
OCI_DIGEST = re.compile(r"\Amariadb@sha256:[0-9a-f]{64}\Z")
SHA256 = re.compile(r"\Asha256:[0-9a-f]{64}\Z")
GIT_SHA = re.compile(r"\A[0-9a-f]{40}\Z")
CONTAINER_ID = re.compile(r"\A[0-9a-f]{64}\Z")
SYNTHETIC_SLUG = re.compile(r"\Ab12-rc01-[0-9a-f]{16}\Z")


@dataclass(frozen=True)
class PolicyResult:
    """Shape checks are NOT physical validation, certification, or permission."""
    structure_valid: bool
    blockers: tuple[str, ...]
    execution_authorized: bool = False


def _is_positive_int(value: Any) -> bool:
    return type(value) is int and 0 < value <= 2147483647


def _is_nonnegative_int(value: Any) -> bool:
    return type(value) is int and 0 <= value <= 2147483647


def _plain_mapping(value: Any) -> bool:
    return isinstance(value, dict) and all(isinstance(k, str) for k in value)


def _exact_keys(value: Any, keys: frozenset[str]) -> bool:
    return _plain_mapping(value) and frozenset(value) == keys


def _result(errors: list[str]) -> PolicyResult:
    # Even synthetic "structure_valid=True" is never permission to run.
    return PolicyResult(not errors, tuple(sorted(set(errors))), False)


_IMAGE_KEYS = frozenset({
    "image_reference", "image_id", "container_id", "docker_engine_sha256",
    "container_privileged", "mounts", "port_bindings", "network_mode",
    "restricted_egress_attested", "source_commit", "candidate_blob",
    "original_mac_blob", "gate_blob", "authority_blob", "schema_blob",
    "runner_environment", "repository", "branch", "run_number",
    "run_attempt", "db_name", "server_id", "server_version",
    "schema_metadata", "approval_scope",
})

_SCHEMA_KEYS = frozenset({
    "base_tables", "total_columns", "revision_columns", "cycle_set_null_fks",
    "nonempty_tables", "views", "triggers", "routines", "events",
})

_PORT_KEYS = frozenset({"host_ip", "host_port", "container_port", "protocol"})


def assess_fixture_evidence(
    evidence: Mapping[str, Any],
    *,
    simulated_reviewed_image: str | None = None,
) -> PolicyResult:
    """Evaluate closed-shape *untrusted* metadata without inspecting hardware.

    simulated_reviewed_image is for fixture-free negative/positive unit tests.
    It cannot enable execution. With the default None, the unresolved image
    digest blocker MUST remain present, including for otherwise valid inputs.
    """
    errors: list[str] = []
    if not _exact_keys(evidence, _IMAGE_KEYS):
        return _result(["MISSING_OR_UNEXPECTED_FIXTURE_METADATA_KEYS"])

    approved_image = (
        simulated_reviewed_image
        if simulated_reviewed_image is not None
        else APPROVED_MARIADB_IMAGE
    )
    if not isinstance(approved_image, str) or not OCI_DIGEST.fullmatch(approved_image):
        errors.append("IMMUTABLE_MARIADB_IMAGE_UNREVIEWED")
    if not isinstance(evidence["image_reference"], str) or not OCI_DIGEST.fullmatch(
        evidence["image_reference"]
    ):
        errors.append("IMAGE_IS_NOT_DIGEST_PINNED")
    elif approved_image != evidence["image_reference"]:
        errors.append("IMAGE_DIFFERS_FROM_REVIEWED_DIGEST")
    if not isinstance(evidence["image_id"], str) or not SHA256.fullmatch(
        evidence["image_id"]
    ):
        errors.append("IMAGE_ID_FORMAT")
    if not isinstance(evidence["container_id"], str) or not CONTAINER_ID.fullmatch(
        evidence["container_id"]
    ):
        errors.append("CONTAINER_ID_FORMAT")
    if not isinstance(evidence["docker_engine_sha256"], str) or not SHA256.fullmatch(
        evidence["docker_engine_sha256"]
    ):
        errors.append("ENGINE_IDENTITY_MISSING")
    if evidence["container_privileged"] is not False:
        errors.append("PRIVILEGED_CONTAINER")
    if evidence["mounts"] != []:
        errors.append("MOUNTED_HOST_OR_SHARED_VOLUMES")
    if evidence["network_mode"] != "bridge":
        errors.append("UNREVIEWED_NETWORK_MODE")
    if evidence["restricted_egress_attested"] is not True:
        errors.append("EGRESS_NOT_ATTESTED")
    ports = evidence["port_bindings"]
    if not isinstance(ports, list) or len(ports) != 1 or not _exact_keys(
        ports[0], _PORT_KEYS
    ) or ports[0] != {
        "host_ip": "127.0.0.1", "host_port": 3306,
        "container_port": 3306, "protocol": "tcp",
    }:
        errors.append("NOT_EXACT_SINGLE_LOOPBACK_PORT")

    for key, expected in (
        ("repository", REPO), ("branch", CANDIDATE_BRANCH),
        ("source_commit", CANDIDATE_COMMIT), ("candidate_blob", CANDIDATE_BLOB),
        ("original_mac_blob", ORIGINAL_MAC_BLOB), ("gate_blob", GATE_BLOB),
        ("authority_blob", AUTHORITY_BLOB), ("schema_blob", SCHEMA_BLOB),
        ("runner_environment", "github-hosted"),
        ("db_name", DB_NAME), ("approval_scope", "PRELIMINARY_NONCERTIFYING"),
    ):
        if evidence[key] != expected:
            errors.append("PINNED_SOURCE_OR_SCOPE_DRIFT_" + key.upper())
    if evidence["run_number"] != 1 or type(evidence["run_number"]) is not int or (
        evidence["run_attempt"] != 1 or type(evidence["run_attempt"]) is not int
    ):
        errors.append("NOT_FIRST_ONE_USE_RUN")
    if not _is_positive_int(evidence["server_id"]):
        errors.append("NO_VALID_SERVER_ID")
    version = evidence["server_version"]
    if not isinstance(version, str) or not version.startswith("10.11.") or (
        "mariadb" not in version.lower()
    ):
        errors.append("NOT_MARIADB_10_11")
    schema = evidence["schema_metadata"]
    if not _exact_keys(schema, _SCHEMA_KEYS):
        errors.append("INCOMPLETE_SCHEMA_METADATA")
    else:
        expected_counts = {
            "base_tables": 69, "total_columns": 886, "revision_columns": 3,
            "cycle_set_null_fks": 3, "nonempty_tables": 0, "views": 0,
            "triggers": 0, "routines": 0, "events": 0,
        }
        if any(type(schema[key]) is not int or schema[key] != value
               for key, value in expected_counts.items()):
            errors.append("SYNTHETIC_SCHEMA_NOT_EXACT_EMPTY_BASELINE")

    return _result(errors)


_CLEANUP_KEYS = frozenset({
    "db_name", "container_id", "farm_id", "cycle_id", "layer_record_id",
    "farm_slug", "farm_name", "deleted_layer_rows", "deleted_cycle_rows",
    "deleted_farm_rows", "remaining_business_rows", "workers_running",
    "open_transactions", "container_removed", "ephemeral_volume_removed",
    "barrier_artifacts_disposed", "cleanup_status",
})


def assess_cleanup_evidence(
    evidence: Mapping[str, Any], *,
    expected_container_id: str,
    expected_farm_id: int,
    expected_cycle_id: int,
    expected_layer_record_id: int,
    expected_slug: str,
) -> PolicyResult:
    """Purely check planned, redacted, exact-ID *post-run* evidence.

    This does not issue DELETE/DROP/stop/kill/rm actions, even on "success".
    Any partial or untrusted data must remain MANUAL_REVIEW / FAIL_CLOSED.
    """
    if not _exact_keys(evidence, _CLEANUP_KEYS):
        return _result(["MISSING_OR_UNEXPECTED_CLEANUP_KEYS"])
    errors: list[str] = []
    if not isinstance(expected_container_id, str) or not CONTAINER_ID.fullmatch(
        expected_container_id
    ):
        errors.append("INVALID_EXPECTED_CONTAINER_ID")
    elif evidence["container_id"] != expected_container_id:
        errors.append("CONTAINER_ID_CHANGED")
    if evidence["db_name"] != DB_NAME:
        errors.append("WRONG_SYNTHETIC_DATABASE")
    for key, expected in (
        ("farm_id", expected_farm_id),
        ("cycle_id", expected_cycle_id),
        ("layer_record_id", expected_layer_record_id),
    ):
        if not _is_positive_int(expected) or type(evidence[key]) is not int or (
            evidence[key] != expected
        ):
            errors.append("EXACT_FIXTURE_ID_NOT_PROVEN_" + key.upper())
    if not isinstance(expected_slug, str) or not SYNTHETIC_SLUG.fullmatch(
        expected_slug
    ) or evidence["farm_slug"] != expected_slug:
        errors.append("SYNTHETIC_SLUG_MISMATCH")
    if evidence["farm_name"] != "B12 RC01 disposable synthetic farm":
        errors.append("NOT_SYNTHETIC_FARM_NAME")
    for name in ("deleted_layer_rows", "deleted_cycle_rows", "deleted_farm_rows"):
        if type(evidence[name]) is not int or evidence[name] != 1:
            errors.append("TEARDOWN_NOT_EXACTLY_ONE_" + name.upper())
    for name in ("remaining_business_rows", "workers_running", "open_transactions"):
        if not _is_nonnegative_int(evidence[name]) or evidence[name] != 0:
            errors.append("RESIDUAL_RESOURCE_" + name.upper())
    for name in (
        "container_removed", "ephemeral_volume_removed",
        "barrier_artifacts_disposed",
    ):
        if evidence[name] is not True:
            errors.append("UNVERIFIED_DISPOSAL_" + name.upper())
    if evidence["cleanup_status"] != "INDEPENDENT_POSTCHECK_PASS":
        errors.append("INDEPENDENT_POSTCHECK_REQUIRED")

    return _result(errors)


def status_for_reporting(preflight: PolicyResult, cleanup: PolicyResult) -> str:
    """Return only safe labels. NEVER claim runtime certification or approval."""
    if not preflight.structure_valid or not cleanup.structure_valid:
        return "BLOCKED_MANUAL_REVIEW"
    return "SOURCE_CONTRACT_SHAPE_ONLY_NOT_ATTESTED"
