#!/usr/bin/env python3
"""MAC-12C. Isolated, source-only activation candidate for preliminary synthetic RC01.

CANDIDATE ONLY: source fuses are enabled on this isolated review branch,
but no approval manifest, fixture, container operation, or worker execution
is authorized. Do not invoke this supervisor without a new explicit one-use
authorization, exact-source and disposable-lab preflight, and signed grant.
Never deploy this candidate into production or use it for certification.
"""
from __future__ import annotations
import hashlib
import json
from datetime import datetime, timezone, timedelta
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import tempfile
import time
import uuid

EXECUTION_ENABLED = False
# Future signer setup is a separate reviewed key-ceremony commit, NOT env input.
# Pin the SHA-256 digest of the signer's trusted RSA public-key PEM after a
# distinct explicit approval. The private signing key must never enter Git.
TRUSTED_APPROVER_PUBLIC_KEY_SHA256 = "d7a8c59c31898eedb4a894769f7c1ddc145d38a560632cfa0d1683a1935e8735"
APPROVAL_CONTRACT = "B12-MAC12C-RC01-ONE-USE-V1"
APPROVAL_SCOPE = "ISOLATED_SYNTHETIC_LAYER_EDIT_EDIT_ONLY"
APPROVAL_DIR_NAME = ".b12-approvals"

PROJECT = "renee_b12_rc_mac"
DATABASE = "renee_b12_disposable"
NETWORK = PROJECT + "_isolated"
VOLUME = PROJECT + "_db_data"
REPO_BRANCH = "c137-gc24-k2p03-b12-mac12c-launch-gate-hardening-r01"
PHP_IMAGE = "sha256:7399b946d775888f85299b9e1597a9ad633cd164cfc816b16f1f77e2da839445"
SOURCE_ROOT = Path(__file__).resolve().parents[1]
LAB = Path.home() / "renee-b12-rc-mac"
HARNESS = "tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php"
SOURCE_BLOBS = {
    HARNESS: "eb76719b1f47805c1206e5112c4688eb09f66c47",
    "lib/daily_record_revision_authority.php": "5fbb90903b0cc801be45c684f6c3db85c660979a",
    "lib/daily_record_revision_cutover_gate.php": "6e88c913ad5e5b371a9d0c92ce08870214ac1268",
    "lib/daily_record_revision_schema_plan.php": "a0ddbec542e891d484b48d1adbdac17442eaadf8",
    "database_schema.sql": "e77f287d62c5812a3d55dc23b5b20f5d167db389",
}
RESULT_LINES = frozenset({
    "RC01_TWO_CONNECTIONS=PASS", "RC01_INDEPENDENT_INNODB_WAIT=PASS",
    "RC01_EXACTLY_ONE_COMMIT=PASS", "RC01_STALE_CONFLICT=PASS",
    "RC01_DURABLE_REVISION=2", "RC01_FIXTURE_TEARDOWN=PASS",
})
READY = re.compile(r"[1-9][0-9]{0,9}\n\Z")
BARRIER = re.compile(r"b12_rc01_[a-f0-9]{20}\Z")
TABLE = re.compile(r"[a-z][a-z0-9_]*\Z")


class Blocked(RuntimeError):
    pass


def require(ok: bool, reason: str) -> None:
    if not ok:
        raise Blocked(reason)


def git_blob(data: bytes) -> str:
    return hashlib.sha1(
        b"blob " + str(len(data)).encode() + b"\0" + data
    ).hexdigest()


def command(argv: list[str], timeout: float = 15) -> str:
    proc = subprocess.run(
        argv, capture_output=True, text=True, check=False, timeout=timeout
    )
    require(proc.returncode == 0, "COMMAND_FAILED_RAW_OUTPUT_SUPPRESSED")
    return proc.stdout.strip()


DOCKER_EXECUTABLE = "/Applications/Docker.app/Contents/Resources/bin/docker"
DOCKER_SOCKET = Path.home() / ".docker/run/docker.sock"

def docker_argv(*argv: str) -> list[str]:
    return [
        DOCKER_EXECUTABLE,
        "--host=unix://" + str(DOCKER_SOCKET),
        *argv
    ]

def docker(*argv: str) -> str:
    return command(docker_argv(*argv))


def inspect(kind: str, identity: str) -> dict:
    obj = json.loads(docker(kind, "inspect", identity))
    require(isinstance(obj, list) and len(obj) == 1, "INSPECT_AMBIGUOUS")
    return obj[0]


def owned_secret(path: Path) -> bool:
    try:
        s = path.lstat()
        return (stat.S_ISREG(s.st_mode) and s.st_uid == os.getuid()
                and stat.S_IMODE(s.st_mode) == 0o600)
    except OSError:
        return False


def verify_source(approval: dict) -> None:
    require(SOURCE_ROOT.is_dir() and not SOURCE_ROOT.is_symlink(),
            "SOURCE_ROOT_UNSAFE")
    require(command(["git", "-C", str(SOURCE_ROOT),
                     "branch", "--show-current"]) == REPO_BRANCH,
            "SOURCE_BRANCH_DRIFT")
    require(command(["git", "-C", str(SOURCE_ROOT),
                     "rev-parse", "HEAD"]) == approval["source_commit"],
            "SOURCE_COMMIT_DRIFT")
    require(command(["git", "-C", str(SOURCE_ROOT),
                     "status", "--porcelain"]) == "",
            "SOURCE_TREE_DIRTY")
    for relative, sha in SOURCE_BLOBS.items():
        path = SOURCE_ROOT / relative
        require(path.is_file() and not path.is_symlink()
                and git_blob(path.read_bytes()) == sha,
                "SOURCE_BLOB_DRIFT")
    require(git_blob(Path(__file__).read_bytes()) == approval["supervisor_blob"],
            "SUPERVISOR_BLOB_DRIFT")
    require(approval["harness_blob"] == SOURCE_BLOBS[HARNESS],
            "SIGNED_HARNESS_BLOB_MISMATCH")


def snapshot_entries() -> set[str]:
    """Exactly the five approved files and the directories containing them."""
    names = set(SOURCE_BLOBS)
    for name in SOURCE_BLOBS:
        parent = Path(name).parent
        while parent != Path("."):
            names.add(parent.as_posix())
            parent = parent.parent
    return names


def verify_source_snapshot(directory: Path) -> None:
    """Fail closed if the private, separately mounted source copy has drifted."""
    require(directory.parent == LAB and
            directory.name.startswith("b12_mac12b8_src_") and
            not directory.is_symlink(), "SNAPSHOT_ROOT_UNSAFE")
    info = directory.lstat()
    require(stat.S_ISDIR(info.st_mode) and info.st_uid == os.getuid()
            and stat.S_IMODE(info.st_mode) == 0o700,
            "SNAPSHOT_ROOT_PERMISSIONS")
    entries = {p.relative_to(directory).as_posix(): p
               for p in directory.rglob("*")}
    require(set(entries) == snapshot_entries(),
            "SNAPSHOT_CONTENT_MANIFEST")
    for relative, path in entries.items():
        info = path.lstat()
        require(info.st_uid == os.getuid() and not path.is_symlink(),
                "SNAPSHOT_ENTRY_UNSAFE")
        if relative in SOURCE_BLOBS:
            require(stat.S_ISREG(info.st_mode)
                    and stat.S_IMODE(info.st_mode) == 0o400
                    and git_blob(path.read_bytes()) == SOURCE_BLOBS[relative],
                    "SNAPSHOT_SOURCE_DRIFT")
        else:
            require(stat.S_ISDIR(info.st_mode)
                    and stat.S_IMODE(info.st_mode) == 0o700,
                    "SNAPSHOT_DIRECTORY_UNSAFE")


def stage_source_snapshot(directory: Path) -> None:
    """Copy pinned source into a private sibling, never the writable barrier."""
    require(directory.parent == LAB and
            directory.name.startswith("b12_mac12b8_src_") and
            directory.is_dir() and not directory.is_symlink()
            and directory.stat().st_uid == os.getuid()
            and stat.S_IMODE(directory.stat().st_mode) == 0o700
            and not list(directory.iterdir()), "SNAPSHOT_STAGING_UNSAFE")
    for relative, expected in SOURCE_BLOBS.items():
        original = SOURCE_ROOT / relative
        require(original.is_file() and not original.is_symlink(),
                "SNAPSHOT_ORIGINAL_UNSAFE")
        raw = original.read_bytes()
        require(git_blob(raw) == expected, "SNAPSHOT_ORIGINAL_DRIFT")
        target = directory / relative
        # pathlib.mkdir(parents=True) applies 0777 (masked by umask) to
        # intermediate parents, not the requested 0700. Create every
        # component explicitly and verify it before writing source bytes.
        parent = directory
        for component in Path(relative).parts[:-1]:
            parent = parent / component
            try:
                parent.mkdir(mode=0o700)
            except FileExistsError:
                pass
            info = parent.lstat()
            require(stat.S_ISDIR(info.st_mode)
                    and info.st_uid == os.getuid()
                    and stat.S_IMODE(info.st_mode) == 0o700
                    and not parent.is_symlink(), "SNAPSHOT_DIRECTORY_UNSAFE")
        with target.open("xb") as out:
            out.write(raw)
            out.flush()
            os.fsync(out.fileno())
        target.chmod(0o400)
    verify_source_snapshot(directory)


def remove_verified_source_snapshot(directory: Path) -> None:
    """Remove only the exact owned snapshot after independent success."""
    verify_source_snapshot(directory)
    for relative in SOURCE_BLOBS:
        (directory / relative).unlink()
    folders = snapshot_entries() - set(SOURCE_BLOBS)
    for relative in sorted(folders, key=lambda p: (-p.count("/"), p)):
        (directory / relative).rmdir()
    directory.rmdir()


def approval_directory() -> Path:
    directory = LAB / APPROVAL_DIR_NAME
    info = directory.lstat()
    require(stat.S_ISDIR(info.st_mode) and info.st_uid == os.getuid()
            and stat.S_IMODE(info.st_mode) == 0o700,
            "APPROVAL_DIRECTORY_NOT_PRIVATE")
    return directory


def reject_duplicate_json_keys(pairs: list[tuple[str, object]]) -> dict:
    result = {}
    for key, value in pairs:
        require(key not in result, "DUPLICATE_APPROVAL_JSON_KEY")
        result[key] = value
    return result


def validate_approval_payload(data: dict, now: datetime) -> dict:
    required = {
        "contract", "scope", "run_id", "issued_utc", "expires_utc",
        "repository", "branch", "source_commit", "supervisor_blob",
        "harness_blob", "database", "php_image_id",
        "maximum_runs", "synthetic_only", "production_contact",
    }
    require(isinstance(data, dict) and set(data) == required,
            "APPROVAL_FIELDS_MISMATCH")
    require(data["contract"] == APPROVAL_CONTRACT
            and data["scope"] == APPROVAL_SCOPE
            and data["repository"] == "abeycity4u/Renee-Farms-V2.2.50-Batch3B1"
            and data["branch"] == REPO_BRANCH
            and data["database"] == DATABASE
            and data["php_image_id"] == PHP_IMAGE
            and data["maximum_runs"] == 1
            and type(data["maximum_runs"]) is int
            and data["synthetic_only"] is True
            and data["production_contact"] is False,
            "APPROVAL_SCOPE_INVALID")
    require(isinstance(data["run_id"], str)
            and re.fullmatch(r"[a-f0-9]{32}", data["run_id"]) is not None,
            "APPROVAL_RUN_ID_INVALID")
    for key in ("source_commit", "supervisor_blob", "harness_blob"):
        require(isinstance(data[key], str)
                and re.fullmatch(r"[a-f0-9]{40}", data[key]) is not None,
                "APPROVAL_SHA_INVALID")
    for key in ("issued_utc", "expires_utc"):
        require(isinstance(data[key], str)
                and re.fullmatch(r"20[0-9]{2}-[0-9]{2}-[0-9]{2}T"
                                 r"[0-9]{2}:[0-9]{2}:[0-9]{2}Z",
                                 data[key]) is not None,
                "APPROVAL_TIME_FORMAT_INVALID")
    try:
        issued = datetime.strptime(
            data["issued_utc"], "%Y-%m-%dT%H:%M:%SZ"
        ).replace(tzinfo=timezone.utc)
        expires = datetime.strptime(
            data["expires_utc"], "%Y-%m-%dT%H:%M:%SZ"
        ).replace(tzinfo=timezone.utc)
    except ValueError as error:
        raise Blocked("APPROVAL_TIME_INVALID") from error
    require(issued <= now < expires
            and expires - issued <= timedelta(minutes=15),
            "APPROVAL_EXPIRED_OR_TOO_WIDE")
    return data


def load_signed_approval() -> dict:
    """Read a fixed external path; no env/argv approval or mutable self-SHA."""
    require(isinstance(TRUSTED_APPROVER_PUBLIC_KEY_SHA256, str)
            and re.fullmatch(r"[a-f0-9]{64}",
                             TRUSTED_APPROVER_PUBLIC_KEY_SHA256) is not None,
            "TRUSTED_APPROVER_NOT_CONFIGURED")
    directory = approval_directory()
    files = {
        "manifest": directory / "mac12c_one_use.json",
        "signature": directory / "mac12c_one_use.sig",
        "public_key": directory / "trusted_approver_public.pem",
    }
    limits = {"manifest": 4096, "signature": 2048, "public_key": 8192}
    snapshot = {}
    for role, path in files.items():
        require(owned_secret(path), "APPROVAL_FILE_NOT_PRIVATE")
        info = path.lstat()
        require(0 < info.st_size <= limits[role],
                "APPROVAL_FILE_SIZE_INVALID")
        raw = path.read_bytes()
        require(len(raw) == info.st_size, "APPROVAL_FILE_CHANGED")
        snapshot[role] = raw
    require(hashlib.sha256(snapshot["public_key"]).hexdigest()
            == TRUSTED_APPROVER_PUBLIC_KEY_SHA256,
            "UNTRUSTED_APPROVAL_SIGNER")
    # Verify a snapshot, not the original files (avoid verify/parse TOCTOU).
    # RSA-SHA256 works with the macOS OpenSSL/LibreSSL dgst verifier.
    with tempfile.TemporaryDirectory(prefix="mac12c_sig_", dir=directory) as tmp:
        base = Path(tmp)
        for role, name in (
            ("manifest", "manifest.json"),
            ("signature", "signature.sig"),
            ("public_key", "approver.pem"),
        ):
            file = base / name
            with file.open("xb") as stream:
                stream.write(snapshot[role])
            file.chmod(0o600)
        output = command([
            "openssl", "dgst", "-sha256", "-verify",
            str(base / "approver.pem"), "-signature",
            str(base / "signature.sig"), str(base / "manifest.json")
        ], timeout=8)
        require(output == "Verified OK", "APPROVAL_SIGNATURE_INVALID")
    try:
        data = json.loads(snapshot["manifest"].decode("utf-8"),
                          object_pairs_hook=reject_duplicate_json_keys)
    except (UnicodeError, json.JSONDecodeError) as error:
        raise Blocked("APPROVAL_JSON_INVALID") from error
    return validate_approval_payload(data, datetime.now(timezone.utc))


HUMAN_LAUNCH_CONTRACT = "B12-MAC12C-RC01-HUMAN-LAUNCH-V1"
HUMAN_LAUNCH_ACTIONS = [
    "SYNTHETIC_FIXTURE_INSERT",
    "TWO_WORKER_LAYER_EDIT_EDIT_RACE",
    "INDEPENDENT_INNODB_WAIT_OBSERVER",
    "EXACT_FIXTURE_TEARDOWN",
    "FAILURE_QUARANTINE",
]


def verify_human_launch_decision(approval: dict) -> str:
    """R-A cooperative-operator human gate, separate from RSA grant.

    The initial hold record is historical and never itself an approval.
    An explicit future one-use decision must independently match the
    exact signed grant identity and its short validity window.
    """
    evidence = LAB / ".b12-evidence"
    case = evidence / "mac12c-r01"
    preflight = case / "preflight"

    for directory in (evidence, case, preflight):
        info = directory.lstat()
        require(stat.S_ISDIR(info.st_mode)
                and info.st_uid == os.getuid()
                and stat.S_IMODE(info.st_mode) == 0o700,
                "HUMAN_EVIDENCE_DIRECTORY_UNSAFE")

    role = case / "role_assignment.txt"
    hold = case / "authorization_record.txt"
    require(owned_secret(role) and owned_secret(hold),
            "HUMAN_BASELINE_RECORD_UNSAFE")

    role_text = role.read_text(encoding="utf-8")
    hold_text = hold.read_text(encoding="utf-8")

    require("RESPONSIBLE_PERSON=Abiodun Ayinde\n" in role_text
            and "INDEPENDENT_HUMAN_OVERSIGHT=NO\n" in role_text
            and "HUMAN_ROLE_ASSIGNMENT=CONFIRMED_IN_CHAT\n" in role_text,
            "HUMAN_ROLE_RECORD_DRIFT")
    require("RECORD_TYPE=PREAUTHORIZATION_HOLD_RECORD\n" in hold_text
            and "HUMAN_LAUNCH_APPROVAL=NOT_ISSUED\n" in hold_text,
            "HUMAN_HISTORICAL_HOLD_DRIFT")

    path = preflight / "human_oneuse_launch.json"
    require(owned_secret(path), "HUMAN_DECISION_FILE_UNSAFE")
    info = path.lstat()
    require(0 < info.st_size <= 4096,
            "HUMAN_DECISION_FILE_SIZE")

    raw = path.read_bytes()
    require(len(raw) == info.st_size,
            "HUMAN_DECISION_FILE_CHANGED")

    try:
        decision = json.loads(
            raw.decode("utf-8"),
            object_pairs_hook=reject_duplicate_json_keys
        )
    except (UnicodeError, json.JSONDecodeError) as error:
        raise Blocked("HUMAN_DECISION_JSON_INVALID") from error

    required = {
        "contract", "decision", "decision_id", "decision_utc",
        "authorizer", "operator", "security_model",
        "repository", "branch", "run_id", "source_commit",
        "supervisor_blob", "harness_blob",
        "issued_utc", "expires_utc", "scope",
        "database", "php_image_id",
        "authorized_actions", "production_contact",
        "independent_human_oversight", "launch_approved"
    }
    require(isinstance(decision, dict)
            and set(decision) == required,
            "HUMAN_DECISION_FIELDS_MISMATCH")

    require(
        decision["contract"] == HUMAN_LAUNCH_CONTRACT
        and decision["decision"]
            == "APPROVE_ONE_PRELIMINARY_RC01_LAUNCH"
        and isinstance(decision["decision_id"], str)
        and re.fullmatch(
            r"[A-Z0-9][A-Z0-9_-]{7,63}",
            decision["decision_id"]
        ) is not None
        and decision["authorizer"] == "Abiodun Ayinde"
        and decision["operator"] == "Abiodun Ayinde"
        and decision["security_model"] == "R-A_LIMITED_SYNTHETIC_ONLY"
        and decision["authorized_actions"] == HUMAN_LAUNCH_ACTIONS
        and decision["production_contact"] is False
        and decision["independent_human_oversight"] is False
        and decision["launch_approved"] is True,
        "HUMAN_DECISION_AUTHORITY_INVALID"
    )

    for field in (
        "repository", "branch", "run_id", "source_commit",
        "supervisor_blob", "harness_blob",
        "issued_utc", "expires_utc", "scope",
        "database", "php_image_id"
    ):
        require(
            type(decision[field]) is str
            and decision[field] == approval[field],
            "HUMAN_DECISION_GRANT_MISMATCH"
        )

    stamp = decision["decision_utc"]
    require(
        isinstance(stamp, str)
        and re.fullmatch(
            r"20[0-9]{2}-[0-9]{2}-[0-9]{2}T"
            r"[0-9]{2}:[0-9]{2}:[0-9]{2}Z",
            stamp
        ) is not None,
        "HUMAN_DECISION_TIME_FORMAT_INVALID"
    )

    try:
        decided = datetime.strptime(
            stamp, "%Y-%m-%dT%H:%M:%SZ"
        ).replace(tzinfo=timezone.utc)
        issued = datetime.strptime(
            approval["issued_utc"], "%Y-%m-%dT%H:%M:%SZ"
        ).replace(tzinfo=timezone.utc)
        expires = datetime.strptime(
            approval["expires_utc"], "%Y-%m-%dT%H:%M:%SZ"
        ).replace(tzinfo=timezone.utc)
    except ValueError as error:
        raise Blocked("HUMAN_DECISION_TIME_INVALID") from error

    require(
        issued <= decided <= datetime.now(timezone.utc) < expires,
        "HUMAN_DECISION_NOT_IN_LIVE_GRANT_WINDOW"
    )
    return hashlib.sha256(raw).hexdigest()


def claim_one_use(approval: dict) -> None:
    """Local anti-replay marker; never deleted automatically, even on failure."""
    directory = approval_directory() / "claims"
    info = directory.lstat()
    require(stat.S_ISDIR(info.st_mode) and info.st_uid == os.getuid()
            and stat.S_IMODE(info.st_mode) == 0o700,
            "ONE_USE_CLAIM_DIRECTORY_UNSAFE")
    require(re.fullmatch(r"[a-f0-9]{32}", approval["run_id"]) is not None,
            "ONE_USE_RUN_ID_INVALID")
    file = directory / ("claimed_" + approval["run_id"])
    flags = os.O_CREAT | os.O_EXCL | os.O_WRONLY
    if hasattr(os, "O_NOFOLLOW"):
        flags |= os.O_NOFOLLOW
    try:
        fd = os.open(file, flags, 0o600)
    except FileExistsError as error:
        raise Blocked("ONE_USE_ALREADY_CLAIMED_NO_RETRY") from error
    with os.fdopen(fd, "w", encoding="ascii") as stream:
        stream.write(approval["source_commit"] + "\n")
        stream.flush()
        os.fsync(stream.fileno())
    # Persist the new directory entry as well as its contents. On failure the
    # claimed file remains: never silently permit another attempt.
    flags = os.O_RDONLY | getattr(os, "O_DIRECTORY", 0)
    if hasattr(os, "O_NOFOLLOW"):
        flags |= os.O_NOFOLLOW
    dir_fd = os.open(directory, flags)
    try:
        os.fsync(dir_fd)
    finally:
        os.close(dir_fd)


def normalize_mount_source(source: str) -> str:
    for prefix in ("/run/desktop/mnt/host", "/host_mnt"):
        if source.startswith(prefix + "/"):
            return source[len(prefix):]
    return source


def verify_database_mounts(mounts: object) -> None:
    require(isinstance(mounts, list) and len(mounts) == 3,
            "DB_MOUNT_COUNT_DRIFT")
    require(all(isinstance(item, dict) for item in mounts),
            "DB_MOUNT_STRUCTURE_INVALID")
    by_dest = {item.get("Destination"): item for item in mounts}
    require(set(by_dest) == {
        "/var/lib/mysql",
        "/run/secrets/root_password",
        "/run/secrets/test_password"
    }, "DB_MOUNT_DESTINATION_DRIFT")

    data = by_dest["/var/lib/mysql"]
    require(data.get("Type") == "volume"
            and data.get("Name") == VOLUME
            and data.get("RW") is True,
            "DB_DATA_VOLUME_DRIFT")

    for name in ("root_password", "test_password"):
        item = by_dest["/run/secrets/" + name]
        expected = str(LAB / ".b12-secrets" / name)
        require(item.get("Type") == "bind"
                and item.get("RW") is False
                and isinstance(item.get("Source"), str)
                and normalize_mount_source(item["Source"]) == expected,
                "DB_SECRET_MOUNT_DRIFT")


def verify_docker() -> str:
    require(sys.platform == "darwin", "NOT_MACOS")

    for name in ("DOCKER_HOST", "DOCKER_CONTEXT",
                 "DOCKER_TLS_VERIFY", "DOCKER_CERT_PATH"):
        require(not os.environ.get(name),
                "DOCKER_ENDPOINT_OVERRIDE")

    try:
        binary = Path(DOCKER_EXECUTABLE)
        binary_info = binary.lstat()
        socket_info = DOCKER_SOCKET.lstat()
    except OSError as error:
        raise Blocked("PINNED_DOCKER_ENDPOINT_MISSING") from error

    require(stat.S_ISREG(binary_info.st_mode)
            and os.access(binary, os.X_OK),
            "DOCKER_CLIENT_IDENTITY_DRIFT")
    require(stat.S_ISSOCK(socket_info.st_mode)
            and socket_info.st_uid == os.getuid(),
            "DOCKER_SOCKET_IDENTITY_DRIFT")

    require(LAB.is_dir() and not LAB.is_symlink(),
            "LAB_UNSAFE")
    lab_info = LAB.lstat()
    require(lab_info.st_uid == os.getuid()
            and stat.S_IMODE(lab_info.st_mode) == 0o700,
            "LAB_PERMISSIONS_DRIFT")

    secret_dir = LAB / ".b12-secrets"
    secret_info = secret_dir.lstat()
    require(stat.S_ISDIR(secret_info.st_mode)
            and secret_info.st_uid == os.getuid()
            and stat.S_IMODE(secret_info.st_mode) == 0o700,
            "SECRET_DIRECTORY_UNSAFE")

    for name in ("root_password", "test_password"):
        require(owned_secret(secret_dir / name),
                "SECRET_IDENTITY_UNSAFE")

    lock = LAB / "image-id.lock"
    require(owned_secret(lock), "DB_IMAGE_LOCK_UNSAFE")
    db_image = lock.read_text(encoding="ascii").strip()
    require(re.fullmatch(r"sha256:[a-f0-9]{64}", db_image)
            is not None, "DB_IMAGE_LOCK_INVALID")

    image = inspect("image", "mariadb:10.11")
    require(image.get("Id") == db_image
            and image.get("Os") == "linux"
            and image.get("Architecture") == "amd64",
            "DB_IMAGE_DRIFT")

    php = inspect("image", "renee-b12-php82-pdo-mac12a4:local")
    require(php.get("Id") == PHP_IMAGE
            and php.get("Os") == "linux"
            and php.get("Architecture") == "amd64",
            "PHP_IMAGE_DRIFT")

    ids = docker("ps", "-aq", "--filter",
                 "label=com.docker.compose.project=" + PROJECT).splitlines()
    require(len(ids) == 1, "DB_CONTAINER_COUNT")

    db = inspect("container", ids[0])
    host = db.get("HostConfig") or {}
    config = db.get("Config") or {}
    labels = config.get("Labels") or {}
    state = db.get("State") or {}
    settings = db.get("NetworkSettings") or {}
    nets = settings.get("Networks") or {}
    ports = settings.get("Ports") or {}

    require(db.get("Image") == db_image
            and state.get("Running") is True
            and state.get("Paused") is not True
            and state.get("Restarting") is not True
            and labels.get("com.docker.compose.project") == PROJECT
            and labels.get("com.docker.compose.service") == "mariadb"
            and host.get("Memory") == 1073741824
            and host.get("PidsLimit") == 128
            and host.get("Privileged") is False
            and not host.get("CapAdd")
            and not host.get("Devices")
            and not host.get("PortBindings")
            and not any(ports.values()),
            "DB_CONTAINER_POLICY_DRIFT")

    require(set(nets) == {NETWORK}, "DB_NETWORK_DRIFT")
    network_info = inspect("network", NETWORK)
    members = list((network_info.get("Containers") or {}).values())

    require(network_info.get("Internal") is True
            and network_info.get("Driver") == "bridge"
            and (network_info.get("Labels") or {}).get(
                "com.docker.compose.project") == PROJECT
            and nets[NETWORK].get("NetworkID") == network_info.get("Id")
            and len(members) == 1
            and members[0].get("EndpointID")
                == nets[NETWORK].get("EndpointID")
            and members[0].get("Name", "").lstrip("/")
                == db.get("Name", "").lstrip("/"),
            "DB_NETWORK_POLICY_DRIFT")

    volume_info = inspect("volume", VOLUME)
    require((volume_info.get("Labels") or {}).get(
                "com.docker.compose.project") == PROJECT,
            "DB_VOLUME_IDENTITY_DRIFT")

    verify_database_mounts(db.get("Mounts"))

    env = dict(entry.split("=", 1)
               for entry in config.get("Env", []) if "=" in entry)
    require(env.get("MARIADB_DATABASE") == DATABASE
            and env.get("MARIADB_USER") == "b12_test"
            and env.get("MARIADB_PASSWORD_FILE")
                == "/run/secrets/test_password"
            and env.get("MARIADB_ROOT_PASSWORD_FILE")
                == "/run/secrets/root_password"
            and "MARIADB_PASSWORD" not in env
            and "MARIADB_ROOT_PASSWORD" not in env,
            "DB_ENV_DRIFT")

    return ids[0]


def db_select(container: str, sql: str, *, root: bool = False) -> str:
    user, credential = (
        ("root", "root_password") if root else ("b12_test", "test_password")
    )
    shell = (
        'set -eu; MYSQL_PWD="$(cat /run/secrets/' + credential + ')"; '
        'export MYSQL_PWD; exec mariadb --no-defaults --protocol=socket '
        '--host=localhost --database=renee_b12_disposable --user=' + user
        + ' --batch --skip-column-names --raw --local-infile=0 '
        '--connect-timeout=5 --execute "$1"'
    )
    require(
        isinstance(sql, str)
        and re.match(r"\ASELECT[ \t]+", sql, re.IGNORECASE)
            is not None
        and not any(x in sql for x in (";", "\n", "\r", "\x00")),
        "DB_SELECT_ONLY_POLICY"
    )
    proc = subprocess.run(
        docker_argv("exec", container, "sh", "-c", shell,
                    "b12-readonly", sql),
        capture_output=True,
        text=True,
        check=False,
        timeout=10
    )
    require(proc.returncode == 0,
            "DB_SELECT_COMMAND_FAILED")
    require(
        isinstance(proc.stdout, str)
        and proc.stdout.endswith("\n"),
        "DB_SELECT_OUTPUT_TERMINATOR"
    )
    # Remove exactly one MariaDB row terminator.
    # Preserve unexpected trailing blank rows for validation.
    return proc.stdout[:-1]



REVISION_TABLES = frozenset({
    "layer_daily_records",
    "broiler_daily_records",
    "ruminant_daily_records"
})


def verify_revision_column_metadata(raw: str) -> None:
    """Fail closed on exact three-table revision metadata.

    Accept MariaDB's quoted or unquoted numeric default 1.
    Never infer revision compatibility from column count alone.
    """
    require(isinstance(raw, str),
            "REVISION_METADATA_INVALID_TYPE")

    rows = raw.split("\n")
    require(len(rows) == 3,
            "REVISION_METADATA_ROW_COUNT")

    seen = set()

    for row in rows:
        fields = row.split("\t")
        require(len(fields) == 4,
                "REVISION_METADATA_FIELD_COUNT")

        table, column_type, nullable, default = fields

        require(
            table in REVISION_TABLES and table not in seen,
            "REVISION_METADATA_TABLE_IDENTITY"
        )

        require(
            re.fullmatch(
                r"bigint(?:\([0-9]+\))? unsigned",
                column_type.lower().strip()
            ) is not None,
            "REVISION_METADATA_TYPE_DRIFT"
        )

        require(nullable.strip().upper() == "NO",
                "REVISION_METADATA_NULLABILITY_DRIFT")

        require(default.strip() in ("1", "'1'"),
                "REVISION_METADATA_DEFAULT_DRIFT")

        seen.add(table)

    require(seen == REVISION_TABLES,
            "REVISION_METADATA_TABLE_SET_DRIFT")


def verify_schema_empty(container: str) -> None:
    ident = db_select(container,
        "SELECT DATABASE(),CURRENT_USER(),VERSION(),@@GLOBAL.server_id"
    ).split("\t")
    require(len(ident) == 4 and ident[0] == DATABASE
            and ident[1].startswith("b12_test@")
            and ident[2].startswith("10.11.") and ident[3] == "1",
            "DB_SESSION_IDENTITY")
    metadata = db_select(container,
        "SELECT "
        "(SELECT COUNT(*) FROM information_schema.TABLES "
        "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'),"
        "(SELECT COUNT(*) FROM information_schema.COLUMNS "
        "WHERE TABLE_SCHEMA=DATABASE()),"
        "(SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS "
        "WHERE CONSTRAINT_SCHEMA=DATABASE()),"
        "(SELECT COUNT(*) FROM information_schema.COLUMNS "
        "WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='record_revision'),"
        "(SELECT COUNT(*) FROM information_schema.TABLES "
        "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='VIEW'),"
        "(SELECT COUNT(*) FROM information_schema.TRIGGERS "
        "WHERE TRIGGER_SCHEMA=DATABASE()),"
        "(SELECT COUNT(*) FROM information_schema.ROUTINES "
        "WHERE ROUTINE_SCHEMA=DATABASE()),"
        "(SELECT COUNT(*) FROM information_schema.EVENTS "
        "WHERE EVENT_SCHEMA=DATABASE())"
    ).split("\t")
    require(metadata == ["69", "886", "193", "3", "0", "0", "0", "0"],
            "SCHEMA_FINGERPRINT")
    revision_metadata = db_select(
        container,
        "SELECT TABLE_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT "
        "FROM information_schema.COLUMNS "
        "WHERE TABLE_SCHEMA=DATABASE() "
        "AND COLUMN_NAME='record_revision' "
        "ORDER BY TABLE_NAME"
    )
    verify_revision_column_metadata(revision_metadata)
    names = db_select(container,
        "SELECT TABLE_NAME FROM information_schema.TABLES "
        "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' "
        "ORDER BY TABLE_NAME").splitlines()
    require(len(names) == 69 and len(set(names)) == 69
            and all(TABLE.fullmatch(x) for x in names),
            "SCHEMA_TABLE_IDENTIFIERS")
    count = db_select(container,
        "SELECT " + " + ".join("(SELECT COUNT(*) FROM " + x + ")"
                              for x in names))
    require(count == "0", "DISPOSABLE_SCHEMA_NOT_EMPTY")


def parse_session(barrier: Path, role: str) -> int:
    require(role in ("A", "B"), "UNEXPECTED_WORKER_ROLE")
    path = barrier / (role + "_READY")
    require(path.is_file() and not path.is_symlink(), "READY_FILE_UNSAFE")
    raw = path.read_text(encoding="ascii")
    require(READY.fullmatch(raw) is not None, "WORKER_ID_FORMAT")
    return int(raw.strip())


def find_barrier(run_root: Path) -> Path | None:
    require(run_root.is_dir() and not run_root.is_symlink(),
            "RUN_ROOT_CHANGED")
    entries = list(run_root.iterdir())
    require(len(entries) <= 1, "UNEXPECTED_RUN_ROOT_ENTRIES")
    if not entries:
        return None
    item = entries[0]
    require(BARRIER.fullmatch(item.name) is not None
            and item.is_dir() and not item.is_symlink(),
            "BARRIER_IDENTITY")
    return item


def actual_server_wait(container: str, a: int, b: int) -> bool:
    require(0 < a <= 9999999999 and 0 < b <= 9999999999 and a != b,
            "INVALID_WORKER_SESSIONS")
    sql = (
        "SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w "
        "JOIN information_schema.INNODB_TRX waiting "
        "ON waiting.trx_id=w.requesting_trx_id "
        "JOIN information_schema.INNODB_TRX blocking "
        "ON blocking.trx_id=w.blocking_trx_id "
        "WHERE waiting.trx_mysql_thread_id=" + str(b)
        + " AND blocking.trx_mysql_thread_id=" + str(a)
    )
    count = db_select(container, sql, root=True)
    require(re.fullmatch(r"[0-9]+", count) is not None,
            "INNODB_WAIT_METADATA_INVALID")
    return count == "1"


def publish_observer_proof(barrier: Path, a: int, b: int) -> None:
    require(BARRIER.fullmatch(barrier.name) is not None
            and barrier.is_dir() and not barrier.is_symlink(),
            "OBSERVER_BARRIER_CHANGED")
    marker = barrier / "OBSERVER_PASS"
    temp = barrier / "OBSERVER_PASS.tmp"
    require(not marker.exists() and not marker.is_symlink()
            and not temp.exists() and not temp.is_symlink(),
            "PREEXISTING_OBSERVER_PROOF")
    flags = os.O_CREAT | os.O_EXCL | os.O_WRONLY
    if hasattr(os, "O_NOFOLLOW"):
        flags |= os.O_NOFOLLOW
    fd = os.open(temp, flags, 0o600)
    with os.fdopen(fd, "w", encoding="ascii") as stream:
        stream.write(str(a) + ":" + str(b) + "\n")
        stream.flush()
        os.fsync(stream.fileno())
    require(not marker.exists() and not marker.is_symlink(),
            "OBSERVER_PROOF_RACE")
    os.rename(temp, marker)


def independently_observe(container: str, run_root: Path,
                          php_process: subprocess.Popen,
                          deadline: float) -> None:
    while time.monotonic() < deadline:
        require(php_process.poll() is None, "PHP_CONTROLLER_TERMINATED")
        barrier = find_barrier(run_root)
        if barrier is not None:
            signals = ("A_READY", "B_READY", "A_LOCKED", "B_ATTEMPT")
            if all((barrier / n).is_file()
                   and not (barrier / n).is_symlink() for n in signals):
                a, b = parse_session(barrier, "A"), parse_session(barrier, "B")
                require(a != b, "WORKER_SESSIONS_NOT_INDEPENDENT")
                for n in ("A_LOCKED", "B_ATTEMPT"):
                    require((barrier / n).read_bytes() == b"1\n",
                            "WORKER_SIGNAL_INVALID")
                require(not (barrier / "OBSERVER_PASS").exists(),
                        "FORGED_OR_STALE_OBSERVER_MARKER")
                if actual_server_wait(container, a, b):
                    publish_observer_proof(barrier, a, b)
                    return
        time.sleep(0.03)
    raise Blocked("REAL_B_WAIT_ON_A_NOT_OBSERVED")


def bind(source: Path, target: str, readonly: bool = True) -> str:
    s = str(source.resolve(strict=True))
    require(not any(c in s for c in (",", "\n", "\r", "\x00")),
            "BIND_MOUNT_PATH_UNSAFE")
    return "type=bind,src=" + s + ",dst=" + target + (
        ",readonly" if readonly else ""
    )


def php_command(container: str, run_name: str, run_root: Path,
                source_snapshot: Path) -> list[str]:
    verify_source_snapshot(source_snapshot)
    require(re.fullmatch(r"b12-mac12b3-[a-f0-9]{16}", run_name) is not None,
            "PHP_CONTAINER_NAME_INVALID")
    args = docker_argv(
        "run", "-i", "--rm", "--pull=never",
        "--name", run_name, "--label=b12.owner=mac12b3-rc01",
        "--network=container:" + container, "--read-only",
        "--cap-drop=ALL", "--security-opt=no-new-privileges",
        "--pids-limit=64", "--memory=536870912", "--cpus=1",
        "--user=" + str(os.getuid()) + ":" + str(os.getgid()),
        "--tmpfs=/b12-private:rw,nosuid,nodev,noexec,size=65536",
        "--mount", bind(run_root, "/b12-run", False)
    )
    for name in SOURCE_BLOBS:
        args += ["--mount", bind(source_snapshot / name,
                               "/b12-source/" + name)]
    for key, val in (
        ("B12_RC01_ISOLATED_MAC_ATTESTED", "YES"),
        ("B12_RC01_TCP_ATTESTED", "YES"),
        ("B12_RC01_DB_NAME", DATABASE),
        ("B12_RC01_BARRIER_PARENT", "/b12-run"),
        ("B12_RC01_DB_PASSWORD_FILE", "/b12-private/test_password"),
        ("B12_RC01_APPROVED_HARNESS_BLOB", SOURCE_BLOBS[HARNESS]),
    ):
        args += ["--env", key + "=" + val]
    # Secret transferred only by DB-container-local read -> stdin pipe ->
    # private PHP tmpfs, never through env/argv/inspect or root mount.
    bootstrap = (
        "set -eu; umask 077; cat > /b12-private/test_password; "
        "chmod 600 /b12-private/test_password; "
        "exec php /b12-source/" + HARNESS + " --controller"
    )
    return args + ["--entrypoint=sh", PHP_IMAGE, "-ceu", bootstrap]


def quarantine_or_stop_only_owned_container(name: str) -> bool:
    if name not in docker("ps", "-a", "--format", "{{.Names}}").splitlines():
        return True
    obj = inspect("container", name)
    require(re.fullmatch(r"b12-mac12b3-[a-f0-9]{16}", name) is not None
            and (obj.get("Config") or {}).get("Labels", {}).get("b12.owner")
                == "mac12b3-rc01"
            and obj.get("Name", "").lstrip("/") == name,
            "REFUSE_TO_STOP_UNOWNED_CONTAINER")
    if obj.get("State", {}).get("Running"):
        command(docker_argv("stop", "--time=2", name), timeout=10)
    return name not in docker("ps", "-a", "--format",
                              "{{.Names}}").splitlines()


def execute_one_use() -> None:
    approval = load_signed_approval()
    decision_digest = verify_human_launch_decision(approval)
    verify_source(approval)
    db = verify_docker()
    verify_schema_empty(db)
    # Recheck the short signed window after potentially slow preflight.
    validate_approval_payload(approval, datetime.now(timezone.utc))
    require(verify_human_launch_decision(approval) == decision_digest,
            "HUMAN_DECISION_CHANGED_BEFORE_CLAIM")
    # Permanently claim the signed nonce BEFORE creating fixtures/workers.
    claim_one_use(approval)
    name = "b12-mac12b3-" + uuid.uuid4().hex[:16]
    require(name not in docker("ps", "-a", "--format",
                               "{{.Names}}").splitlines(),
            "TEMPORARY_PHP_NAME_COLLISION")
    root = Path(tempfile.mkdtemp(prefix="b12_mac12b3_", dir=LAB))
    require(not root.is_symlink()
            and stat.S_IMODE(root.stat().st_mode) == 0o700,
            "RUN_ROOT_NOT_PRIVATE")
    snapshot = Path(tempfile.mkdtemp(prefix="b12_mac12b8_src_", dir=LAB))
    secret_proc = None
    php_proc = None
    succeeded = False
    stopped = False
    try:
        stage_source_snapshot(snapshot)
        # Expiration after claiming must fail closed; preserve the claim.
        validate_approval_payload(approval, datetime.now(timezone.utc))
        require(verify_human_launch_decision(approval) == decision_digest,
                "HUMAN_DECISION_CHANGED_BEFORE_WORKERS")
        secret_proc = subprocess.Popen(
            docker_argv("exec", db, "sh", "-c",
                        "set -eu; exec cat /run/secrets/test_password"),
            stdout=subprocess.PIPE, stderr=subprocess.DEVNULL
        )
        php_proc = subprocess.Popen(
            php_command(db, name, root, snapshot),
            stdin=secret_proc.stdout, stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL
        )
        secret_proc.stdout.close()
        independently_observe(db, root, php_proc, time.monotonic() + 3.0)
        stdout, _ = php_proc.communicate(timeout=32)
        require(php_proc.returncode == 0, "PHP_CONTROLLER_FAILED")
        require(set(stdout.decode("utf-8", "strict").splitlines())
                == RESULT_LINES, "REPAIRED_RC01_RESULT_MISMATCH")
        require(secret_proc.wait(timeout=2) == 0,
                "TEST_CREDENTIAL_PIPE_FAILED")
        verify_schema_empty(db)
        # PHP cleanup must already have removed its private barrier and
        # fixture; never report PASS with pending barrier artifacts.
        require(find_barrier(root) is None and not list(root.iterdir()),
                "PHP_BARRIER_CLEANUP_INCOMPLETE")
        succeeded = True
    finally:
        try:
            stopped = quarantine_or_stop_only_owned_container(name)
        except Exception:
            stopped = False
        if php_proc is not None and php_proc.poll() is None:
            php_proc.terminate()
            try:
                php_proc.communicate(timeout=3)
            except subprocess.TimeoutExpired:
                php_proc.kill()
                php_proc.communicate(timeout=3)
        if secret_proc is not None and secret_proc.poll() is None:
            secret_proc.terminate()
            try:
                secret_proc.wait(timeout=3)
            except subprocess.TimeoutExpired:
                secret_proc.kill()
                secret_proc.wait(timeout=3)
        # Preserve barrier and diagnostics if anything is uncertain.
        if not (succeeded and stopped):
            sys.stderr.write("B12_MAC12C=QUARANTINE_MANUAL_REVIEW\n")
    require(succeeded and stopped, "FINAL_RESULT_OR_CLEANUP_INCOMPLETE")
    # The private run directory is empty and is the only owned host path.
    # Verify its identity before disposing of it.
    require(root.parent == LAB and root.name.startswith("b12_mac12b3_")
            and root.is_dir() and not root.is_symlink()
            and not list(root.iterdir()), "RUN_ROOT_CLEANUP_UNVERIFIED")
    root.rmdir()
    try:
        remove_verified_source_snapshot(snapshot)
    except Exception:
        sys.stderr.write("B12_MAC12C=SNAPSHOT_QUARANTINE_MANUAL_REVIEW\n")
        raise
    print("B12_MAC12C=PRELIMINARY_NONCERTIFYING_PASS")


def main() -> int:
    # This check precedes even read-only Docker or filesystem inspections.
    if not EXECUTION_ENABLED:
        sys.stderr.write("B12_MAC12B3=SOURCE_ONLY_HARD_DISABLED\n")
        return 90
    try:
        execute_one_use()
        return 0
    except Exception:
        sys.stderr.write("B12_MAC12B3=FAIL_CLOSED_MANUAL_REVIEW\n")
        return 1


if __name__ == "__main__":
    sys.exit(main())
