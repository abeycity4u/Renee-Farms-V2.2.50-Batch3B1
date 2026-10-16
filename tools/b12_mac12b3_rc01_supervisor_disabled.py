#!/usr/bin/env python3
"""MAC-12B3. Source-only Mac host supervisor for preliminary synthetic B12 RC01.

HARD OFF: execution requires a later reviewed commit setting the literal fuse
and explicit commit/blob/one-use approval sentinels. No current authorization
to create SQL fixtures, run workers, stop a container, or generate a proof.
PHP also has its own independent OFF fuse.
"""
from __future__ import annotations
import hashlib
import json
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
APPROVED_ONE_USE_RUN = None
APPROVED_RUNTIME_COMMIT = None
APPROVED_SUPERVISOR_BLOB = None

PROJECT = "renee_b12_rc_mac"
DATABASE = "renee_b12_disposable"
NETWORK = PROJECT + "_isolated"
VOLUME = PROJECT + "_db_data"
REPO_BRANCH = "c137-gc24-k2p03-b12-mac12b1-disabled-rc01"
PHP_IMAGE = "sha256:7399b946d775888f85299b9e1597a9ad633cd164cfc816b16f1f77e2da839445"
SOURCE_ROOT = Path(__file__).resolve().parents[1]
LAB = Path.home() / "renee-b12-rc-mac"
HARNESS = "tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php"
SOURCE_BLOBS = {
    HARNESS: "2e288c611e0b43217eb1b8e15234ee823b65f940",
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


def docker(*argv: str) -> str:
    return command(["docker", *argv])


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


def verify_source() -> None:
    require(SOURCE_ROOT.is_dir() and not SOURCE_ROOT.is_symlink(),
            "SOURCE_ROOT_UNSAFE")
    require(command(["git", "-C", str(SOURCE_ROOT),
                     "branch", "--show-current"]) == REPO_BRANCH,
            "SOURCE_BRANCH_DRIFT")
    require(command(["git", "-C", str(SOURCE_ROOT),
                     "rev-parse", "HEAD"]) == APPROVED_RUNTIME_COMMIT,
            "SOURCE_COMMIT_DRIFT")
    require(command(["git", "-C", str(SOURCE_ROOT),
                     "status", "--porcelain"]) == "",
            "SOURCE_TREE_DIRTY")
    for relative, sha in SOURCE_BLOBS.items():
        path = SOURCE_ROOT / relative
        require(path.is_file() and not path.is_symlink()
                and git_blob(path.read_bytes()) == sha,
                "SOURCE_BLOB_DRIFT")
    require(git_blob(Path(__file__).read_bytes()) == APPROVED_SUPERVISOR_BLOB,
            "SUPERVISOR_BLOB_DRIFT")


def verify_docker() -> str:
    require(sys.platform == "darwin", "NOT_MACOS")
    require(not os.environ.get("DOCKER_HOST")
            and not os.environ.get("DOCKER_CONTEXT"),
            "NONSTANDARD_DOCKER_ENDPOINT")
    ctx = docker("context", "show")
    endpoint = docker("context", "inspect", ctx,
                      "--format", "{{.Endpoints.docker.Host}}")
    require(endpoint.startswith("unix://"), "NONLOCAL_DOCKER_CONTEXT")
    require(LAB.is_dir() and not LAB.is_symlink(), "LAB_UNSAFE")
    for secret in ("root_password", "test_password"):
        require(owned_secret(LAB / ".b12-secrets" / secret),
                "SECRET_IDENTITY_UNSAFE")
    lock = LAB / "image-id.lock"
    require(owned_secret(lock), "DB_IMAGE_LOCK_UNSAFE")
    db_image = lock.read_text(encoding="utf-8").strip()
    img = inspect("image", "mariadb:10.11")
    require(img.get("Id") == db_image and img.get("Os") == "linux"
            and img.get("Architecture") == "amd64",
            "DB_IMAGE_DRIFT")
    php = inspect("image", "renee-b12-php82-pdo-mac12a4:local")
    require(php.get("Id") == PHP_IMAGE and php.get("Os") == "linux"
            and php.get("Architecture") == "amd64",
            "PHP_IMAGE_DRIFT")
    ids = docker("ps", "-aq", "--filter",
                 "label=com.docker.compose.project=" + PROJECT).splitlines()
    require(len(ids) == 1, "DB_CONTAINER_COUNT")
    db = inspect("container", ids[0])
    host = db.get("HostConfig") or {}
    labels = (db.get("Config") or {}).get("Labels") or {}
    nets = (db.get("NetworkSettings") or {}).get("Networks") or {}
    ports = (db.get("NetworkSettings") or {}).get("Ports") or {}
    require(db.get("Image") == db_image
            and db.get("State", {}).get("Running") is True
            and labels.get("com.docker.compose.project") == PROJECT
            and labels.get("com.docker.compose.service") == "mariadb"
            and host.get("Memory") == 1073741824
            and host.get("PidsLimit") == 128
            and not host.get("Privileged") and not host.get("CapAdd")
            and not host.get("PortBindings") and not any(ports.values()),
            "DB_CONTAINER_POLICY_DRIFT")
    require(set(nets) == {NETWORK}, "DB_NETWORK_DRIFT")
    net = inspect("network", NETWORK)
    members = list((net.get("Containers") or {}).values())
    require(net.get("Internal") is True and net.get("Driver") == "bridge"
            and net.get("Labels", {}).get("com.docker.compose.project") == PROJECT
            and nets[NETWORK].get("NetworkID") == net.get("Id")
            and len(members) == 1
            and members[0].get("EndpointID") == nets[NETWORK].get("EndpointID")
            and members[0].get("Name", "").lstrip("/")
               == db.get("Name", "").lstrip("/"),
            "DB_NETWORK_IDENTITY_DRIFT")
    vol = inspect("volume", VOLUME)
    require(vol.get("Labels", {}).get("com.docker.compose.project") == PROJECT
            and any(x.get("Type") == "volume" and x.get("Name") == VOLUME
                    and x.get("Destination") == "/var/lib/mysql"
                    for x in db.get("Mounts", [])),
            "DB_VOLUME_DRIFT")
    env = dict(x.split("=", 1) for x in db["Config"]["Env"] if "=" in x)
    require(env.get("MARIADB_DATABASE") == DATABASE
            and env.get("MARIADB_USER") == "b12_test"
            and env.get("MARIADB_PASSWORD_FILE")
                == "/run/secrets/test_password"
            and env.get("MARIADB_ROOT_PASSWORD_FILE")
                == "/run/secrets/root_password",
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
    return command(
        ["docker", "exec", container, "sh", "-c", shell,
         "b12-readonly", sql], timeout=10
    )


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


def php_command(container: str, run_name: str, run_root: Path) -> list[str]:
    require(re.fullmatch(r"b12-mac12b3-[a-f0-9]{16}", run_name) is not None,
            "PHP_CONTAINER_NAME_INVALID")
    args = [
        "docker", "run", "-i", "--rm", "--pull=never",
        "--name", run_name, "--label=b12.owner=mac12b3-rc01",
        "--network=container:" + container, "--read-only",
        "--cap-drop=ALL", "--security-opt=no-new-privileges",
        "--pids-limit=64", "--memory=536870912", "--cpus=1",
        "--user=" + str(os.getuid()) + ":" + str(os.getgid()),
        "--tmpfs=/b12-private:rw,nosuid,nodev,noexec,size=65536",
        "--mount", bind(run_root, "/b12-run", False)
    ]
    for name in SOURCE_BLOBS:
        args += ["--mount", bind(SOURCE_ROOT / name,
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
        command(["docker", "stop", "--time=2", name], timeout=10)
    return name not in docker("ps", "-a", "--format",
                              "{{.Names}}").splitlines()


def execute_one_use() -> None:
    require(APPROVED_RUNTIME_COMMIT is not None
            and APPROVED_SUPERVISOR_BLOB is not None
            and APPROVED_ONE_USE_RUN == "MAC12C-EXPLICIT-ONE-USE",
            "ONE_USE_COMMIT_AND_RUN_APPROVAL_MISSING")
    require(re.fullmatch(r"[a-f0-9]{40}", APPROVED_RUNTIME_COMMIT) is not None
            and re.fullmatch(r"[a-f0-9]{40}", APPROVED_SUPERVISOR_BLOB)
                is not None,
            "APPROVED_SOURCE_FORMAT_INVALID")
    verify_source()
    db = verify_docker()
    verify_schema_empty(db)
    name = "b12-mac12b3-" + uuid.uuid4().hex[:16]
    require(name not in docker("ps", "-a", "--format",
                               "{{.Names}}").splitlines(),
            "TEMPORARY_PHP_NAME_COLLISION")
    root = Path(tempfile.mkdtemp(prefix="b12_mac12b3_", dir=LAB))
    require(not root.is_symlink()
            and stat.S_IMODE(root.stat().st_mode) == 0o700,
            "RUN_ROOT_NOT_PRIVATE")
    secret_proc = None
    php_proc = None
    succeeded = False
    stopped = False
    try:
        secret_proc = subprocess.Popen(
            ["docker", "exec", db, "sh", "-c",
             "set -eu; exec cat /run/secrets/test_password"],
            stdout=subprocess.PIPE, stderr=subprocess.DEVNULL
        )
        php_proc = subprocess.Popen(
            php_command(db, name, root),
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
    # Preserve run directory for independent second-stage disposal review.
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
