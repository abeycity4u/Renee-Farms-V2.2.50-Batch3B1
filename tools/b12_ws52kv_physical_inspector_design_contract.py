"""WS52-KV: PURE, HARD-DISABLED physical-inspector *design* contract.

This is a fixture-only policy for a future, separately approved independent
Docker/host inspector. It never runs Docker, SQL, network, processes, or IO.
The synthetic observation records below can all be fabricated; even valid
records DO NOT attest a physical resource, authorize a runtime, or clean up.
Reuse the existing WS52-KT one-volume contract instead of copying its rules.
"""
from __future__ import annotations

from dataclasses import dataclass
from typing import Any
import re

from b12_ws52kt_one_volume_identity_cleanup_contract import (
    assess_postcreate_shape,
    assess_cleanup_shape,
)

INSPECTOR_EXECUTION_ENABLED = False
PHYSICAL_INSPECTOR_APPROVED = None
ONE_USE_RUN_APPROVED = None
DOCKER_MUTATIONS_ALLOWED = False
SQL_ALLOWED = False
RC01_ALLOWED = False

REPO = "abeycity4u/Renee-Farms-V2.2.50-Batch3B1"
WS52KU_PARENT = "70817ab14f820e9da8304e8b8dd126ce8bf0a7d0"
HEX_DIGEST = re.compile(r"\Asha256:[0-9a-f]{64}\Z")
STAGE_KEYS = frozenset({
    "order", "stage", "collector", "observation_intent",
    "repository", "reviewed_source_head", "run_id", "engine_id",
    "container_id", "volume_name", "redacted_fingerprint",
    "execution_status",
})
PLAN_KEYS = frozenset({
    "repository", "reviewed_source_head", "design_scope",
    "read_only_operations", "postcreate_fixture", "postcleanup_fixture",
    "observation_stages",
})

# Planning labels, NOT executable shell commands or a Docker API allowlist.
# No privilege, container creation, SQL/DDL, stop, remove, prune or cleanup.
READ_ONLY_INTENTS = (
    "PIN_GITHUB_SOURCE_AND_HARD_OFF_GATES",
    "INVENTORY_ENGINE_CONTAINERS_VOLUMES_AND_LISTENERS",
    "INSPECT_EFFECTIVE_CONTAINER_MOUNTS_AND_VOLUME_IDENTITY",
    "INDEPENDENT_LOOPBACK_AND_EGRESS_POLICY_OBSERVATION",
    "INDEPENDENT_WORKERS_TRANSACTIONS_AND_DB_IDENTITY_OBSERVATION",
    "REINVENTORY_ENGINE_AND_PROVE_EXACT_POSTCLEANUP_ABSENCE",
)
STAGES = (
    ("SOURCE_PIN", "git-source-observer", "PIN_GITHUB_SOURCE_AND_HARD_OFF_GATES"),
    ("ENGINE_PRESTATE", "independent-docker-observer",
     "INVENTORY_ENGINE_CONTAINERS_VOLUMES_AND_LISTENERS"),
    ("POSTCREATE_PRE_SQL", "independent-docker-observer",
     "INSPECT_EFFECTIVE_CONTAINER_MOUNTS_AND_VOLUME_IDENTITY"),
    ("NETWORK_GUARD", "independent-network-observer",
     "INDEPENDENT_LOOPBACK_AND_EGRESS_POLICY_OBSERVATION"),
    ("PRE_TEARDOWN_QUIESCENCE", "independent-worker-observer",
     "INDEPENDENT_WORKERS_TRANSACTIONS_AND_DB_IDENTITY_OBSERVATION"),
    ("POSTCLEANUP", "independent-docker-observer",
     "REINVENTORY_ENGINE_AND_PROVE_EXACT_POSTCLEANUP_ABSENCE"),
)


@dataclass(frozen=True)
class DesignResult:
    shape_valid: bool
    blockers: tuple[str, ...]
    physical_attested: bool = False
    execution_authorized: bool = False
    cleanup_certified: bool = False


def _result(errors: list[str]) -> DesignResult:
    return DesignResult(not errors, tuple(sorted(set(errors))))


def _exact_keys(obj: Any, keys: frozenset[str]) -> bool:
    return type(obj) is dict and frozenset(obj) == keys


def assess_design(plan: Any) -> DesignResult:
    """Check only closed-shape fictional source-review data; NEVER attest IO.

    The pinned WS52-KT model validates volume identity, exact-new additions,
    mount linkage, removal and inventory return. This adapter validates the
    separate OBSERVATION STAGE DESIGN only; it cannot independently prove the
    collector labels, fingerprints, credentials, network/egress or snapshots.
    """
    if not _exact_keys(plan, PLAN_KEYS):
        return _result(["PLAN_KEYS_INVALID"])
    errors: list[str] = []
    if (plan["repository"] != REPO or plan["reviewed_source_head"] != WS52KU_PARENT
            or plan["design_scope"] != "SOURCE_ONLY_NOT_PHYSICAL"):
        errors.append("UNPINNED_DESIGN_SCOPE")
    if type(plan["read_only_operations"]) is not list or tuple(plan["read_only_operations"]) != READ_ONLY_INTENTS:
        errors.append("READ_ONLY_INTENT_ORDER_INVALID")

    pre = plan["postcreate_fixture"]
    post = plan["postcleanup_fixture"]
    preshape = assess_postcreate_shape(pre)
    if not preshape.shape_valid:
        errors.append("WS52KT_POSTCREATE_SHAPE_REJECTED")
    postshape = assess_cleanup_shape(pre, post)
    if not postshape.shape_valid:
        errors.append("WS52KT_POSTCLEANUP_SHAPE_REJECTED")

    observations = plan["observation_stages"]
    if type(observations) is not list or len(observations) != len(STAGES):
        errors.append("OBSERVATION_STAGES_NOT_EXACT")
    else:
        for order, (expected_stage, collector, intent) in enumerate(STAGES):
            rec = observations[order]
            if not _exact_keys(rec, STAGE_KEYS):
                errors.append("OBSERVATION_RECORD_KEYS_INVALID")
                continue
            if (type(rec["order"]) is not int or rec["order"] != order or
                    rec["stage"] != expected_stage or
                    rec["collector"] != collector or
                    rec["observation_intent"] != intent):
                errors.append("STAGE_ORDER_OR_OBSERVER_INVALID")
            if rec["execution_status"] != "NOT_EXECUTED_SOURCE_ONLY":
                errors.append("UNAUTHORIZED_PHYSICAL_EXECUTION_CLAIM")
            if rec["repository"] != REPO or rec["reviewed_source_head"] != WS52KU_PARENT:
                errors.append("OBSERVATION_SOURCE_DRIFT")
            if type(pre) is not dict:
                errors.append("OBSERVATION_FIXTURE_UNAVAILABLE")
                continue
            if (rec["run_id"] != pre.get("run_id") or
                    rec["engine_id"] != pre.get("engine_id")):
                errors.append("OBSERVATION_RUN_ENGINE_DRIFT")
            expected_container = "" if order < 2 else pre.get("container_id")
            expected_volume = "" if order < 2 else pre.get("volume_name")
            if (rec["container_id"] != expected_container or
                    rec["volume_name"] != expected_volume):
                errors.append("OBSERVATION_RESOURCE_ID_DRIFT")
            # Merely a nonsecret syntactic fingerprint; not signed provenance.
            if not isinstance(rec["redacted_fingerprint"], str) or not HEX_DIGEST.fullmatch(
                    rec["redacted_fingerprint"]):
                errors.append("OBSERVATION_FINGERPRINT_INVALID")
    return _result(errors)


def safe_decision(result: Any) -> str:
    if not isinstance(result, DesignResult) or not result.shape_valid:
        return "BLOCKED_MANUAL_REVIEW"
    # Always non-authorizing, including when every fictional check passes.
    return "SOURCE_INSPECTOR_DESIGN_ONLY_NO_PHYSICAL_PROOF"
