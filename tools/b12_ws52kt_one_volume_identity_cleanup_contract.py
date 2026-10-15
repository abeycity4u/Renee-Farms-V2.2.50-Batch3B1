"""WS52-KT — fixture-only, HARD-DISABLED single-volume ownership contract.

This module is PURE. It never inspects Docker/host/runner, writes files, runs
subprocesses, opens network sockets, executes SQL, or performs cleanup.
Dicts may be fabricated: a structure-valid result is NOT an attestation.
Existing WS52-KH policy, GH RC01 harness and CAS gate remain unmodified.
"""
from __future__ import annotations

from dataclasses import dataclass
import re
from typing import Any

EXECUTION_ENABLED = False
APPROVED_ONE_USE_RUNTIME = None
APPROVED_PHYSICAL_INSPECTOR = None
APPROVED_IMAGE_FOR_EXECUTION = None

REPO = "abeycity4u/Renee-Farms-V2.2.50-Batch3B1"
SOURCE_REVIEW_HEAD = "b59f199747cc6338c20a77e3a3432c3cf6f884f4"
CHILD = "sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c"
CONFIG = "sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f"
CAS_GATE_BLOB = "6e88c913ad5e5b371a9d0c92ce08870214ac1268"
RC01_HARNESS_BLOB = "e78318509e270ccd24684bf41f5e854b74765f76"
MOUNT_TARGET = "/var/lib/mysql"
SYNTHETIC_SCOPE = "b12-rc01-disposable"
HEX64 = re.compile(r"\A[0-9a-f]{64}\Z")
DIGEST = re.compile(r"\Asha256:[0-9a-f]{64}\Z")
RUN = re.compile(r"\A[1-9][0-9]{0,11}\Z")
VOL_NAME = re.compile(r"\Ab12-rc01-vol-r[1-9][0-9]{0,11}-[0-9a-f]{16}\Z")
CONTAINER_NAME = re.compile(r"\Ab12-rc01-db-r[1-9][0-9]{0,11}-[0-9a-f]{16}\Z")
RESOURCE_NAME = re.compile(r"\A[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}\Z")

PRE_KEYS = frozenset({
    "repository", "source_review_head", "runner_kind", "run_id", "run_attempt",
    "engine_id", "image_child", "image_config", "image_id",
    "container_id", "container_name", "volume_name",
    "containers_before", "volumes_before", "containers_after_create",
    "volumes_after_create", "volume_inspect", "effective_mounts",
    "container_ownership_labels", "privileged", "network_mode",
    "published_ports", "restricted_egress_observer",
})
VOLUME_KEYS = frozenset({"name", "driver", "mountpoint_fingerprint", "labels"})
LABEL_KEYS = frozenset({"run_id", "purpose"})
MOUNT_KEYS = frozenset({"type", "name", "driver", "destination", "rw", "source_fingerprint"})
PORT_KEYS = frozenset({"host_ip", "host_port", "container_port", "protocol"})
POST_KEYS = frozenset({
    "repository", "source_review_head", "run_id", "engine_id",
    "container_id", "volume_name", "containers_after_cleanup",
    "volumes_after_cleanup", "container_inspect_not_found",
    "volume_inspect_not_found", "workers_running", "open_transactions",
    "synthetic_rows_remaining", "barrier_artifacts_remaining",
    "independent_postcheck_reported",
})


@dataclass(frozen=True)
class Result:
    shape_valid: bool
    blockers: tuple[str, ...]
    physical_attested: bool = False
    execution_authorized: bool = False


def _output(errors: list[str]) -> Result:
    # A fully matching synthetic dict cannot certify ANY physical resource.
    return Result(not errors, tuple(sorted(set(errors))))


def _keys(obj: Any, expected: frozenset[str]) -> bool:
    return type(obj) is dict and frozenset(obj) == expected


def _match(pattern: re.Pattern, obj: Any) -> bool:
    return isinstance(obj, str) and pattern.fullmatch(obj) is not None


def _inventory(values: Any, pattern: re.Pattern) -> bool:
    return (
        type(values) is list
        and all(_match(pattern, item) for item in values)
        and len(values) == len(set(values))
    )


def assess_postcreate_shape(evidence: Any) -> Result:
    """Untrusted future-host metadata. Must be obtained by separate inspector.

    No simulated data, including a passing result, permits a container/SQL run.
    The candidate deliberately models the actual Mounts list rather than the
    requested run flags. Exactly ONE fresh Docker-managed volume is allowed.
    """
    if not _keys(evidence, PRE_KEYS):
        return _output(["PRE_SHAPE_KEYS_INVALID"])
    errors: list[str] = []
    run = evidence["run_id"]
    vol = evidence["volume_name"]
    cid = evidence["container_id"]
    cname = evidence["container_name"]
    engine = evidence["engine_id"]

    for key, expected in (
        ("repository", REPO),
        ("source_review_head", SOURCE_REVIEW_HEAD),
        ("runner_kind", "github-hosted-ephemeral"),
        ("image_child", CHILD),
        ("image_config", CONFIG),
    ):
        if evidence[key] != expected:
            errors.append("UNPINNED_" + key.upper())
    if not _match(RUN, run) or type(evidence["run_attempt"]) is not int or evidence["run_attempt"] != 1:
        errors.append("ONE_USE_RUN_IDENTITY_INVALID")
    if not _match(DIGEST, engine) or not _match(DIGEST, evidence["image_id"]):
        errors.append("ENGINE_OR_IMAGE_ID_INVALID")
    if not _match(HEX64, cid) or not _match(CONTAINER_NAME, cname):
        errors.append("CONTAINER_ID_OR_NAME_INVALID")
    elif _match(RUN, run) and not cname.startswith("b12-rc01-db-r" + run + "-"):
        errors.append("CONTAINER_NAME_RUN_MISMATCH")
    if not _match(VOL_NAME, vol):
        errors.append("VOLUME_IDENTITY_INVALID")
    elif _match(RUN, run) and not vol.startswith("b12-rc01-vol-r" + run + "-"):
        errors.append("VOLUME_NAME_RUN_MISMATCH")

    vb = evidence["volumes_before"]
    va = evidence["volumes_after_create"]
    cb = evidence["containers_before"]
    ca = evidence["containers_after_create"]
    if not _inventory(vb, RESOURCE_NAME) or not _inventory(va, RESOURCE_NAME):
        errors.append("VOLUME_INVENTORY_MALFORMED")
    elif not _match(VOL_NAME, vol) or vol in vb or set(va) != set(vb) | {vol}:
        errors.append("VOLUME_NOT_EXACT_NEW_ADDITION")
    if not _inventory(cb, HEX64) or not _inventory(ca, HEX64):
        errors.append("CONTAINER_INVENTORY_MALFORMED")
    elif not _match(HEX64, cid) or cid in cb or set(ca) != set(cb) | {cid}:
        errors.append("CONTAINER_NOT_EXACT_NEW_ADDITION")

    labels = {"run_id": run, "purpose": SYNTHETIC_SCOPE}
    actual_labels = evidence["container_ownership_labels"]
    if not _keys(actual_labels, LABEL_KEYS) or actual_labels != labels:
        errors.append("CONTAINER_LABELS_NOT_RUN_BOUND")

    inspect = evidence["volume_inspect"]
    fingerprint = None
    if not _keys(inspect, VOLUME_KEYS):
        errors.append("VOLUME_INSPECT_SHAPE_INVALID")
    else:
        fingerprint = inspect["mountpoint_fingerprint"]
        if (inspect["name"] != vol or inspect["driver"] != "local"
                or not _match(DIGEST, fingerprint)
                or not _keys(inspect["labels"], LABEL_KEYS)
                or inspect["labels"] != labels):
            errors.append("VOLUME_INSPECT_IDENTITY_DRIFT")

    mounts = evidence["effective_mounts"]
    if type(mounts) is not list or len(mounts) != 1 or not _keys(mounts[0], MOUNT_KEYS):
        errors.append("EFFECTIVE_MOUNTS_NOT_EXACTLY_ONE")
    else:
        mount = mounts[0]
        if (mount["type"] != "volume" or mount["name"] != vol
                or mount["driver"] != "local"
                or mount["destination"] != MOUNT_TARGET
                or mount["rw"] is not True or fingerprint is None
                or mount["source_fingerprint"] != fingerprint):
            errors.append("EFFECTIVE_MOUNT_UNREVIEWED_OR_UNLINKED")
    if evidence["privileged"] is not False:
        errors.append("PRIVILEGED_CONTAINER_REJECTED")
    if evidence["network_mode"] != "bridge" or evidence["published_ports"] != [{
        "host_ip": "127.0.0.1", "host_port": 3306,
        "container_port": 3306, "protocol": "tcp",
    }]:
        errors.append("NETWORK_OR_EXPOSED_PORTS_UNREVIEWED")
    # This is still merely an untrusted string: separate runtime proof needed.
    if evidence["restricted_egress_observer"] != "INDEPENDENT_POLICY_OBSERVATION_PENDING_REVIEW":
        errors.append("EGRESS_OBSERVER_STATUS_INVALID")
    return _output(errors)


def assess_cleanup_shape(pre: Any, post: Any) -> Result:
    """Model identity-bound postcleanup, without touching a real resource."""
    if not _keys(pre, PRE_KEYS) or not _keys(post, POST_KEYS):
        return _output(["CLEANUP_SHAPE_KEYS_INVALID"])
    errors = list(assess_postcreate_shape(pre).blockers)
    if errors:
        errors.append("PREFLIGHT_CONTRACT_NOT_MET")
    for key in ("repository", "source_review_head", "run_id",
                "engine_id", "container_id", "volume_name"):
        if pre[key] != post[key]:
            errors.append("POST_IDENTITY_DRIFT_" + key.upper())
    for key, before, postkey, pattern in (
        ("VOLUME", pre["volumes_before"], "volumes_after_cleanup", RESOURCE_NAME),
        ("CONTAINER", pre["containers_before"], "containers_after_cleanup", HEX64),
    ):
        after = post[postkey]
        if not _inventory(after, pattern) or not _inventory(before, pattern):
            errors.append("POST_" + key + "_INVENTORY_INVALID")
        elif set(after) != set(before):
            errors.append("POST_" + key + "_INVENTORY_NOT_RESTORED")

    for key in ("container_inspect_not_found", "volume_inspect_not_found",
                "independent_postcheck_reported"):
        if post[key] is not True:
            errors.append("POST_PROOF_MISSING_" + key.upper())
    for key in ("workers_running", "open_transactions",
                "synthetic_rows_remaining", "barrier_artifacts_remaining"):
        if type(post[key]) is not int or post[key] != 0:
            errors.append("POST_RESIDUE_" + key.upper())
    return _output(errors)


def safe_status(pre: Result, post: Result) -> str:
    """Shape-valid is NEVER attestation, permission, cleanup certification."""
    if not isinstance(pre, Result) or not isinstance(post, Result):
        return "BLOCKED_MANUAL_REVIEW"
    if not pre.shape_valid or not post.shape_valid:
        return "BLOCKED_MANUAL_REVIEW"
    return "SOURCE_SHAPE_ONLY_PHYSICAL_NOT_ATTESTED"
