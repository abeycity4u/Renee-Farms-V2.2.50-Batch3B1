#!/usr/bin/env python3
"""C137 GC24 K2/P02: static continuity signature regression, NO PHP/DB execution.

Run only from the new isolated K2/P02 source checkout, not production/runtime.
"""
import argparse
import ast
import hashlib
import re
import sys
from pathlib import Path

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parent.parent
FILE = "lib/daily_population_continuity.php"
CERT_SHA = "322438870263825f9e739a12c4bd478983e67141"
OLD_BLOB = "acbbae9a9796b8d87e4bb6cd1fc069ed3623aabc"
OLD_SIGNATURE = (
    "        ?string $animalType = null,\n"
    "        bool $forUpdate = false\n"
    "    ): array {"
)
NEW_SIGNATURE = (
    "        ?string $animalType = null,\n"
    "        bool $forUpdate = false,\n"
    "        ?int $sourceId = null\n"
    "    ): array {"
)
DAILY_CALLERS = (
    "poultry/layers_daily_record.php",
    "poultry/broiler_daily_record.php",
    "ruminant/ruminant_daily_record.php",
)
class Blocked(ValueError):
    pass

def check(value, label):
    if not value:
        raise Blocked(label)

def git_blob(raw):
    return hashlib.sha1(
        b"blob " + str(len(raw)).encode() + b"\0" + raw
    ).hexdigest()

def protect_self():
    tree = ast.parse(Path(__file__).read_text(encoding="utf-8"))
    allowed = {"argparse","ast","hashlib","re","sys","pathlib"}
    forbidden = {
        "connect","execute","executemany","commit","rollback",
        "write","write_text","write_bytes","unlink","rename","mkdir",
        "rmdir","remove","popen","Popen","system","urlopen","request",
        "eval","exec"
    }
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            check(all(m.name in allowed for m in node.names),
                  "P02_UNSAFE_IMPORT")
        elif isinstance(node, ast.ImportFrom):
            check(node.module in allowed,"P02_UNSAFE_IMPORT")
        elif isinstance(node, ast.Call):
            f = node.func
            name = (f.id if isinstance(f, ast.Name)
                    else f.attr if isinstance(f, ast.Attribute)
                    else "")
            check(name not in forbidden,"P02_UNSAFE_CALL")

def signature_contract(src):
    start = src.index("function daily_population_continuity_preview(")
    end = src.index("    ): array {", start)
    head = src[start:end]
    preview_signature = src[start:end + len("    ): array {")]
    check(NEW_SIGNATURE in preview_signature
          and preview_signature.count(NEW_SIGNATURE) == 1,
          "P02_OPTIONAL_TRAILING_SOURCE_ID_MISSING")
    check(head.count("$sourceId")==1
          and head.find("bool $forUpdate = false") <
              head.find("?int $sourceId = null")
          and head.rstrip().endswith("?int $sourceId = null"),
          "P02_SOURCE_ID_ORDER_OR_TYPE_DRIFT")
    canon = src.index("function daily_population_continuity_canonical_preview(")
    guard = src.index("if ($sourceId === null || $sourceId <= 0)",canon)
    check(guard>canon,"P02_CANONICAL_SOURCE_ID_GUARD_DRIFT")
    body=src[end:src.index(
        "if (!function_exists('daily_population_continuity_apply'))",end)]
    pattern=(
        r"\$canonicalPlan\s*=\s*"
        r"daily_population_continuity_canonical_preview\s*\("
        r"[\s\S]*?\$rows\s*,\s*\$animalType\s*,"
        r"\s*\$sourceId\s*,\s*\$forUpdate\s*\)"
    )
    check(re.search(pattern,body) is not None,
          "P02_CANONICAL_PREVIEW_NOT_RECEIVING_SOURCE_ID")
    apply=src[src.index("function daily_population_continuity_apply("):]
    check(re.search(
        r"daily_population_continuity_preview\s*\(\s*"
        r"\$pdo\s*,\s*\$farmId\s*,\s*\$cycleId\s*,"
        r"\s*\$recordType\s*,\s*\$recordDate\s*,"
        r"\s*\$openingStock\s*,\s*\$mortality\s*,"
        r"\s*\$animalType\s*,\s*true\s*,\s*\$sourceId\s*\)",
        apply
    ) is not None, "P02_LOCKED_APPLY_DROPS_SOURCE_ID")
    check("if (!$pdo->inTransaction())" in apply,
          "P02_ATOMIC_TRANSACTION_PRECONDITION_CHANGED")

def caller_contract():
    for path in DAILY_CALLERS:
        src=(ROOT/path).read_text(encoding="utf-8")
        check(re.search(
            r"daily_population_continuity_apply\s*\(\s*"
            r"\$pdo\s*,\s*\$tenantFarmId\s*,\s*\$cycleIdForSave\s*,"
            r"\s*'(?:layer|broiler|ruminant)'\s*,\s*\$recordDate\s*,"
            r"\s*\$openingStock\s*,\s*\$mortality\s*,"
            r"\s*(?:null|\$animalType)\s*,\s*\$dailyRecordId\s*\)",
            src
        ) is not None,
            "P02_DAILY_CALLER_SOURCE_ID_CHANGED_"+path)
        record=src.index("daily_population_continuity_apply(")
        feed=src.index("sync_daily_feed_usage(", record)
        mortal=src.index("daily_population_sync_mortality(", feed)
        commit=src.index("$pdo->commit();", mortal)
        check(record<feed<mortal<commit,
              "P02_DAILY_WRITER_ORDER_DRIFT_"+path)
    layer=(ROOT/DAILY_CALLERS[0]).read_text(encoding="utf-8")
    broiler=(ROOT/DAILY_CALLERS[1]).read_text(encoding="utf-8")
    check("sales_rebuild_layer_egg_allocations(" in layer
          and "sales_rebuild_layer_egg_allocations(" not in broiler,
          "P02_LAYER_SALE_REBUILD_SCOPE_DRIFT")

def negative_cases(fixed):
    preview_at = fixed.index("function daily_population_continuity_preview(")
    prefix = fixed[:preview_at]
    preview_and_following = fixed[preview_at:]
    altered=[
        ("OPTIONAL_ID_REMOVAL",
         prefix + preview_and_following.replace(
             NEW_SIGNATURE, OLD_SIGNATURE, 1)),
        ("SOURCE_ID_WRONG_TYPE",
         prefix + preview_and_following.replace(
             "?int $sourceId = null\n    ): array {",
             "int $sourceId = null\n    ): array {", 1
         )),
        ("CANONICAL_FORWARD_LOSS",fixed.replace(
            "$rows,\n            $animalType,\n            $sourceId,\n            $forUpdate",
            "$rows,\n            $animalType,\n            null,\n            $forUpdate",1
        )),
        ("APPLY_FORWARD_LOSS",fixed.replace(
            "$animalType,\n            true,\n            $sourceId",
            "$animalType,\n            true,\n            null",1
        )),
    ]
    for label,bad in altered:
        check(bad != fixed,"P02_NEGATIVE_SETUP_"+label)
        try:
            signature_contract(bad)
        except Blocked:
            pass
        else:
            raise Blocked("P02_TAMPER_ACCEPTED_"+label)

def main():
    ap=argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--dry-run",required=True,action="store_true")
    ap.add_argument("--certified-source-root",required=True)
    ap.add_argument("--expected-certified-head",required=True)
    ap.add_argument("--year",type=int,required=True)
    a=ap.parse_args()
    certified=Path(a.certified_source_root)
    if (str(certified)!="/opt/renee-agrisuite-c137-gc"
        or a.expected_certified_head!=CERT_SHA or a.year!=2025):
        ap.error("P02_OFFLINE_SCOPE_GUARD")
    protect_self()
    baseline=certified/FILE
    patched=ROOT/FILE
    check(baseline.is_file() and not baseline.is_symlink(),
          "P02_CERTIFIED_SOURCE_ABSENT")
    check(patched.is_file() and not patched.is_symlink(),
          "P02_PATCHED_SOURCE_ABSENT")
    raw=baseline.read_bytes()
    check(git_blob(raw)==OLD_BLOB,"P02_CERTIFIED_BLOB_DRIFT")
    before=raw.decode("utf-8")
    after=patched.read_text(encoding="utf-8")
    check(before.count(OLD_SIGNATURE)==1,
          "P02_BASE_SIGNATURE_CHANGED")
    check(after==before.replace(OLD_SIGNATURE,NEW_SIGNATURE,1),
          "P02_PHP_PATCH_NOT_NARROW")
    signature_contract(after)
    caller_contract()
    negative_cases(after)
    print("K2_P02_STATIC_READONLY=PASS")
    print("K2_P02_PATCHED_PHP_FILES=1")
    print("K2_P02_PHP_PATCH_SCOPE=TRAILING_OPTIONAL_SOURCE_ID_ONLY")
    print("K2_P02_CERTIFIED_SOURCE_BLOB_GUARD=PASS")
    print("K2_P02_LAYER_BROILER_RUMINANT_CALLERS=PASS")
    print("K2_P02_LOCKED_CANONICAL_FORWARDING=PASS")
    print("K2_P02_NEGATIVE_TESTS=PASS")
    print("K2_P02_PHP_EXECUTED=NO")
    print("K2_P02_DATABASE_CONNECTIONS=0")
    print("K2_P02_DATABASE_WRITES=0")
    print("K2_P02_RUNTIME_CERTIFICATION=NOT_RUN")
    print("K2_P02_FIXTURE_IMPORT=NOT_AUTHORIZED")
    print("K2_P02_150_VU=HOLD")
    print("K2_P02_200_VU=PROHIBITED")
    print("GC24CG_K2_P02_OFFLINE_VERIFIER=PASS")
if __name__=="__main__":
    main()
