"""Offline MAC-12C exact revision-column metadata checks.

No Docker, SQL connection, operational signer or RC01 execution.
"""
import importlib.util
from pathlib import Path
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
PATH = ROOT / "tools/b12_mac12b3_rc01_supervisor_disabled.py"

SPEC = importlib.util.spec_from_file_location(
    "mac12c_revision_gate", PATH
)
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)


class RevisionMetadataOffline(unittest.TestCase):
    def setUp(self):
        self.rows = [
            "broiler_daily_records\tbigint(20) unsigned\tNO\t1",
            "layer_daily_records\tbigint unsigned\tNO\t'1'",
            "ruminant_daily_records\tbigint(20) unsigned\tNO\t1"
        ]

    def check(self, rows):
        M.verify_revision_column_metadata("\n".join(rows))

    def expect_denied(self, rows):
        with self.assertRaises(M.Blocked):
            self.check(rows)

    def test_both_execution_fuses_off(self):
        self.assertIs(M.EXECUTION_ENABLED, False)
        php = (ROOT / M.HARNESS).read_text(encoding="utf-8")
        self.assertIn(
            "const B12_RC01_EXECUTION_ENABLED = false;",
            php
        )

    def test_valid_three_tables(self):
        self.check(self.rows)

    def test_valid_table_order_independent(self):
        self.check(list(reversed(self.rows)))

    def test_missing_revision_column(self):
        self.expect_denied(self.rows[:2])

    def test_extra_revision_column(self):
        self.expect_denied(
            self.rows + [
                "extra_daily_records\tbigint unsigned\tNO\t1"
            ]
        )

    def test_wrong_table(self):
        rows = list(self.rows)
        rows[0] = rows[0].replace(
            "broiler_daily_records",
            "stock_daily_records"
        )
        self.expect_denied(rows)

    def test_duplicate_table(self):
        rows = list(self.rows)
        rows[2] = rows[0]
        self.expect_denied(rows)

    def test_wrong_type_signed(self):
        rows = list(self.rows)
        rows[0] = rows[0].replace(
            "bigint(20) unsigned",
            "bigint(20)"
        )
        self.expect_denied(rows)

    def test_wrong_type_int_unsigned(self):
        rows = list(self.rows)
        rows[0] = rows[0].replace(
            "bigint(20) unsigned",
            "int(10) unsigned"
        )
        self.expect_denied(rows)

    def test_nullable_revision(self):
        rows = list(self.rows)
        rows[0] = rows[0].replace(
            "\tNO\t", "\tYES\t"
        )
        self.expect_denied(rows)

    def test_wrong_default(self):
        rows = list(self.rows)
        rows[0] = rows[0][:-1] + "0"
        self.expect_denied(rows)

    def test_null_default(self):
        rows = list(self.rows)
        rows[0] = rows[0][:-1] + "NULL"
        self.expect_denied(rows)

    def test_malformed_row(self):
        rows = list(self.rows)
        rows[0] = "broiler_daily_records\tbigint unsigned"
        self.expect_denied(rows)

    def test_blank_extra_row(self):
        self.expect_denied(self.rows + [""])

    def test_empty_metadata(self):
        self.expect_denied([])

    def test_live_gate_queries_exact_revision_metadata(self):
        tables = "\n".join(
            "table_" + str(i) for i in range(69)
        )
        responses = [
            "renee_b12_disposable\tb12_test@localhost\t10.11.14-MariaDB\t1",
            "69\t886\t193\t3\t0\t0\t0\t0",
            "\n".join(self.rows),
            tables,
            "0"
        ]

        with mock.patch.object(
            M, "db_select", side_effect=responses
        ) as query:
            M.verify_schema_empty("test-only-container")

        self.assertEqual(query.call_count, 5)
        statements = [
            item.args[1] for item in query.call_args_list
        ]
        self.assertTrue(
            all(sql.startswith("SELECT ") for sql in statements)
        )
        self.assertTrue(
            any(
                "COLUMN_DEFAULT" in sql
                and "COLUMN_TYPE" in sql
                and "IS_NULLABLE" in sql
                for sql in statements
            )
        )

    def test_live_gate_rejects_wrong_revision_before_counts(self):
        rows = list(self.rows)
        rows[0] = rows[0].replace(
            "bigint(20) unsigned", "int unsigned"
        )
        responses = [
            "renee_b12_disposable\tb12_test@localhost\t10.11.14-MariaDB\t1",
            "69\t886\t193\t3\t0\t0\t0\t0",
            "\n".join(rows)
        ]

        with mock.patch.object(
            M, "db_select", side_effect=responses
        ) as query:
            with self.assertRaises(M.Blocked):
                M.verify_schema_empty("test-only-container")

        self.assertEqual(query.call_count, 3)


    def test_db_select_preserves_extra_blank_row(self):
        output = "\n".join(self.rows) + "\n\n"
        with mock.patch.object(
            M.subprocess, "run",
            return_value=mock.Mock(
                returncode=0, stdout=output, stderr=""
            )
        ):
            actual = M.db_select(
                "synthetic-db",
                "SELECT TABLE_NAME FROM information_schema.COLUMNS"
            )
        self.assertEqual(actual, "\n".join(self.rows) + "\n")
        with self.assertRaises(M.Blocked):
            M.verify_revision_column_metadata(actual)

    def test_db_select_scalar_and_pinned_socket(self):
        with mock.patch.object(
            M.subprocess, "run",
            return_value=mock.Mock(
                returncode=0, stdout="0\n", stderr=""
            )
        ) as runner:
            self.assertEqual(
                M.db_select("synthetic-db", "SELECT COUNT(*)"),
                "0"
            )
        argv = runner.call_args.args[0]
        self.assertEqual(
            argv[:2],
            [
                M.DOCKER_EXECUTABLE,
                "--host=unix://" + str(M.DOCKER_SOCKET)
            ]
        )

    def test_db_select_missing_terminator_denied(self):
        with mock.patch.object(
            M.subprocess, "run",
            return_value=mock.Mock(
                returncode=0, stdout="0", stderr=""
            )
        ):
            with self.assertRaises(M.Blocked):
                M.db_select("synthetic-db", "SELECT COUNT(*)")

    def test_db_select_failed_command_denied(self):
        with mock.patch.object(
            M.subprocess, "run",
            return_value=mock.Mock(
                returncode=1, stdout="0\n", stderr=""
            )
        ):
            with self.assertRaises(M.Blocked):
                M.db_select("synthetic-db", "SELECT COUNT(*)")

    def test_db_select_write_rejected_before_process(self):
        with mock.patch.object(
            M.subprocess, "run",
            side_effect=AssertionError("process invoked")
        ) as runner:
            with self.assertRaises(M.Blocked):
                M.db_select(
                    "synthetic-db",
                    "UPDATE layer_daily_records SET mortality=1"
                )
            runner.assert_not_called()


if __name__ == "__main__":
    unittest.main()
