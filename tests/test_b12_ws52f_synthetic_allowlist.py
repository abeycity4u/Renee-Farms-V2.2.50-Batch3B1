"""WS52-F 97 synthetic fixtures; no external SQL/backup input or DB operations."""
from __future__ import annotations
import importlib.util
from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "tools" / "b12_ws52f_synthetic_allowlist.py"
SPEC = importlib.util.spec_from_file_location("b12_ws52f_synthetic_allowlist", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

ALLOW = [('CREATE TABLE `demo`(`id` INT);', ('CREATE_TABLE',)),
 ('CREATE TABLE `demo` (`id` INT, `name` VARCHAR(30));', ('CREATE_TABLE',)),
 ('CREATE TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
 ('CREATE TABLE `_demo2` (`a_2` VARCHAR(255));', ('CREATE_TABLE',)),
 ('DROP TABLE `demo`;', ('DROP_TABLE',)),
 ('DROP TABLE IF EXISTS `demo`;', ('DROP_TABLE',)),
 ('DROP TABLE `Demo`;', ('DROP_TABLE',)),
 ('INSERT INTO `demo` VALUES (1);', ('INSERT_VALUES',)),
 ("INSERT INTO `demo` VALUES (1, NULL, 'a;b');", ('INSERT_VALUES',)),
 ("INSERT INTO `demo` VALUES ('/*not comment*/');", ('INSERT_VALUES',)),
 ("INSERT INTO `demo` VALUES ('--not comment');", ('INSERT_VALUES',)),
 ("INSERT INTO `demo` VALUES ('a\\n');", ('INSERT_VALUES',)),
 ("INSERT INTO `demo` VALUES ('it''s fine');", ('INSERT_VALUES',)),
 ('ALTER TABLE `demo` ENABLE KEYS;', ('ALTER_KEYS',)),
 ('ALTER TABLE `demo` DISABLE KEYS;', ('ALTER_KEYS',)),
 ('LOCK TABLES `demo` READ;', ('LOCK_TABLES',)),
 ('LOCK TABLES `demo` WRITE;', ('LOCK_TABLES',)),
 ('UNLOCK TABLES;', ('UNLOCK_TABLES',)),
 ("SET SESSION sql_mode='';", ('SET_SQL_MODE',)),
 ("SET SESSION sql_mode='STRICT_ALL_TABLES';", ('SET_SQL_MODE',)),
 ("/*!40101 SET SESSION sql_mode='' */;", ('SET_SQL_MODE',)),
 ('/*M!100000 ALTER TABLE `demo` ENABLE KEYS */;', ('ALTER_KEYS',)),
 ('/* normal comment */ CREATE TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
 ('# fake CREATE USER\nCREATE TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
 ('-- valid comment\nDROP TABLE `demo`;', ('DROP_TABLE',)),
 ('INSERT INTO `demo` VALUES (1); DROP TABLE `demo`;', ('INSERT_VALUES', 'DROP_TABLE')),
 ('/*M!999999\\- enable the sandbox mode */\nCREATE TABLE `demo`(`id` INT);',
  ('CREATE_TABLE',)),
 ('   /*M!999999\\- enable the sandbox mode */\nCREATE TABLE `demo`(`id` INT);',
  ('CREATE_TABLE',)),
 ('CREATE TABLE `demo`(`id` INT); -- trailing comment', ('CREATE_TABLE',)),
 ("INSERT INTO `demo` VALUES ('a\\\\b');", ('INSERT_VALUES',)),
 ('CREATE TABLE `SELECT` (`id` INT);', ('CREATE_TABLE',)),
 ('CREATE TABLE `ORDER` (`id` INT);', ('CREATE_TABLE',))
]

REJECT = ['DROP TABLE other.demo;',
 'DROP TABLE `other`.`demo`;',
 'CREATE TABLE other.demo (id INT);',
 'INSERT INTO other.demo VALUES (1);',
 'ALTER TABLE other.demo DISABLE KEYS;',
 'LOCK TABLES other.demo READ;',
 'USE other;',
 'CREATE DATABASE other;',
 'DROP DATABASE other;',
 "GRANT SELECT ON *.* TO 'a'@'b';",
 'REVOKE SELECT ON demo FROM u;',
 "CREATE USER u IDENTIFIED BY 's';",
 "ALTER USER u IDENTIFIED BY 's';",
 'SET GLOBAL max_connections=100;',
 "SET SESSION sql_mode='NO_BACKSLASH_ESCAPES';",
 "SET SESSION sql_mode='ANSI_QUOTES';",
 'SET SESSION sql_mode=NO_BACKSLASH_ESCAPES;',
 "LOAD DATA LOCAL INFILE '/tmp/a' INTO TABLE demo;",
 "SELECT * INTO OUTFILE '/tmp/a' FROM demo;",
 'source /tmp/a.sql;',
 'DELIMITER $$;',
 '\\. /tmp/x.sql;',
 '\\! echo hi;',
 'SELECT 1;',
 'SELECT id FROM demo;',
 'UPDATE demo SET id=1;',
 'DELETE FROM demo;',
 'DROP TABLE demo,other;',
 'DROP TABLE;',
 'DROP TABLE demo',
 'CREATE TABLE demo AS SELECT 1;',
 'CREATE TABLE demo (id INT, );',
 'CREATE TABLE demo (id VARCHAR(0));',
 'CREATE TABLE demo (id VARCHAR(65536));',
 'CREATE TABLE demo (id BLOB);',
 'INSERT INTO demo VALUES (NOW());',
 'INSERT INTO demo VALUES (1),(2);',
 'INSERT INTO demo VALUES (-2);',
 'INSERT INTO demo VALUES (1 + 2);',
 'CREATE TABLE `other.name` (id INT);',
 'CREATE TABLE `weird name` (id INT);',
 'CREATE TABLE demo (id INT); USE other;',
 'CREATE TABLE demo (id INT);;DROP TABLE demo;',
 '/*M!999999\\- enable the sandbox mode */\n'
 '/*M!999999\\- enable the sandbox mode */\n'
 'DROP TABLE demo;',
 'CREATE TABLE demo(id INT);\n/*M!999999\\- enable the sandbox mode */',
 '/*M!999999\\- enable the sandbox mode changed */\nDROP TABLE demo;',
 '/*!999999 DROP TABLE demo */;',
 '/*M!999999 DROP TABLE demo */;',
 '/*!50700 DROP TABLE demo */;',
 '/*!40101 DROP TABLE other.demo */;',
 '/*M!100000 USE other */;',
 '/*!40101 DROP TABLE demo; */;',
 '/*M!100000 /*nested*/ DROP TABLE demo */;',
 '/* unterminated',
 "INSERT INTO demo VALUES ('unterminated);",
 'INSERT INTO demo VALUES ("double-quote");',
 'CREATE TABLE demó (id INT);',
 'CREATE TABLE demo(id INT);\x00',
 "INSERT INTO demo VALUES ('bad\\q');",
 'CREATE TABLE `bad\\name` (id INT);',
 "INSERT INTO demo VALUES ('a\\'b');--comment\nDROP TABLE demo;",
 "/*M!100000 SET SESSION sql_mode='NO_BACKSLASH_ESCAPES' */;",
 'CREATE PROCEDURE p() SELECT 1;',
 'CREATE VIEW v AS SELECT 1;',
 "LOAD_FILE('/tmp/secret');",
 '/*regular /* nested*/ DROP TABLE demo;',
 'CREATE TABLE demo(id INT) /* regular */; USE other;']

# WS52-G targeted reserved-identifier and fail-closed unquoted-name regression.
REJECT.extend([
    "CREATE TABLE SELECT (`id` INT);",
    "CREATE TABLE ORDER (`id` INT);",
    "CREATE TABLE demo (`id` INT);",
    "CREATE TABLE `demo` (id INT);",
    "DROP TABLE demo;",
    "INSERT INTO demo VALUES (1);",
    "ALTER TABLE demo ENABLE KEYS;",
    "LOCK TABLES demo READ;",
    "CREATE TABLE `demo` (`id` INT, name VARCHAR(30));",
    "CREATE TABLE `demo` (`id` INT, `name` VARCHAR(30), a_2 INT);",
    "CREATE TABLE `SELECT` (id INT);",
    "INSERT INTO `demo` VALUES (1); DROP TABLE demo;",
])


class SyntheticCorpus(unittest.TestCase):
    def test_approved_subset(self):
        self.assertEqual(len(ALLOW), 32)
        for i, (statement, expected) in enumerate(ALLOW):
            with self.subTest(case=i):
                self.assertEqual(MODULE.classify_synthetic(statement), expected)

    def test_rejected_cases(self):
        self.assertEqual(len(REJECT), 79)
        for i, statement in enumerate(REJECT):
            with self.subTest(case=i):
                with self.assertRaises(MODULE.Rejected):
                    MODULE.classify_synthetic(statement)

    def test_identifier_and_lexer_boundary_regressions(self):
        # MariaDB's table/column identifier length is limited to 64;
        # this conservative prototype permits only ASCII backtick names.
        valid = [
            "CREATE TABLE `" + "t" * 64 + "` (`id` INT);",
            "CREATE TABLE `demo` (`" + "c" * 64 + "` INT);",
        ]
        for i, sql in enumerate(valid):
            with self.subTest(kind="valid_64", case=i):
                self.assertEqual(MODULE.classify_synthetic(sql), ("CREATE_TABLE",))

        invalid = [
            "CREATE TABLE `" + "t" * 65 + "` (`id` INT);",
            "CREATE TABLE `demo` (`" + "c" * 65 + "` INT);",
            "CREATE TABLE `demo` (`id` VARCHAR(" + "9" * 5000 + "));",
            "CREATE TABLE `demo` (`id` VARCHAR(" + "0" * 100 + "1));",
            "CREATE TABLE `demo` (`id` VARCHAR(999999));",
            "CREATE TABLE `demo` (`id` INT); -- hidden\rDROP TABLE `demo`;",
            "CREATE TABLE `demo` (`id` INT); -- hidden\r\nDROP TABLE `demo`;",
            "INSERT INTO `demo` VALUES ('contains\rreturn');",
            "CREATE TABLE `demo` (`id` INT);\r",
        ]
        for i, sql in enumerate(invalid):
            with self.subTest(kind="invalid", case=i):
                with self.assertRaises(MODULE.Rejected) as ctx:
                    MODULE.classify_synthetic(sql)
                self.assertEqual(str(ctx.exception), "UNSUPPORTED_OR_UNSAFE_SYNTHETIC_SQL")

    def test_fail_closed_input_contract(self):
        for val in (None, b"DROP TABLE x;", "", " ", "DROP TABLE x;\x00", "DROP TABLE x;" * 1000):
            with self.subTest(type=type(val).__name__):
                with self.assertRaises(MODULE.Rejected):
                    MODULE.classify_synthetic(val)


if __name__ == "__main__":
    unittest.main()
