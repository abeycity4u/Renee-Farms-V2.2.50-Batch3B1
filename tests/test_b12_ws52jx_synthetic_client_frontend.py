"""WS52-JX fabricated-only preflight corpus; no real dump or MariaDB client."""
from __future__ import annotations

import importlib.util
from pathlib import Path
import subprocess
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tools'))
SPEC = importlib.util.spec_from_file_location(
    'b12_ws52jx_synthetic_client_frontend',
    ROOT / 'tools' / 'b12_ws52jx_synthetic_client_frontend.py',
)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

GOOD = [
    ('CREATE TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
    ('CREATE TABLE `demo` (`v` VARCHAR(255));', ('CREATE_TABLE',)),
    ('DROP TABLE IF EXISTS `demo`;', ('DROP_TABLE',)),
    ('INSERT INTO `demo` VALUES (1);', ('INSERT_VALUES',)),
    ("INSERT INTO `demo` VALUES ('a;b');", ('INSERT_VALUES',)),
    ("INSERT INTO `demo` VALUES ('-- ; /* irrelevant */');", ('INSERT_VALUES',)),
    ("INSERT INTO `demo` VALUES ('it''s OK');", ('INSERT_VALUES',)),
    ("INSERT INTO `demo` VALUES ('a\\n');", ('INSERT_VALUES',)),
    ('ALTER TABLE `demo` ENABLE KEYS;', ('ALTER_KEYS',)),
    ('LOCK TABLES `demo` READ; UNLOCK TABLES;', ('LOCK_TABLES','UNLOCK_TABLES')),
    ("SET SESSION SQL_MODE='';", ('SET_SQL_MODE',)),
    ("SET SESSION SQL_MODE='STRICT_ALL_TABLES';", ('SET_SQL_MODE',)),
    ('-- ordinary safe comment\nCREATE TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
    ('# SELECT * INTO OUTFILE ;\nDROP TABLE `demo`;', ('DROP_TABLE',)),
    ('/* normal ; comment */ CREATE TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
    ('CREATE/*separator*/ TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
    ('CREATE TABLE `demo` (`id` INT); -- trailing comment', ('CREATE_TABLE',)),
    ('/*!40101 CREATE TABLE `demo` (`id` INT) */;', ('CREATE_TABLE',)),
    ('/*M!100000 CREATE TABLE `demo` (`id` INT) */;', ('CREATE_TABLE',)),
    ("/*!40101 SET SESSION SQL_MODE='' */;", ('SET_SQL_MODE',)),
    ('/*!50700 GRANT ALL ON *.* TO attacker */; CREATE TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
    ('/*M!999999 DROP DATABASE system */; CREATE TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
    ('/*!999999 DROP DATABASE system */; DROP TABLE `demo`;', ('DROP_TABLE',)),
    (r'/*M!999999\- enable the sandbox mode */' + '\nCREATE TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
    ('  ' + r'/*M!999999\- enable the sandbox mode */' + '\nDROP TABLE `demo`;', ('DROP_TABLE',)),
    ('CREATE TABLE `demo` (`id` INT); INSERT INTO `demo` VALUES (1);', ('CREATE_TABLE','INSERT_VALUES')),
    ('DROP TABLE `demo`;\nCREATE TABLE `demo` (`id` INT);\nINSERT INTO `demo` VALUES (9);', ('DROP_TABLE','CREATE_TABLE','INSERT_VALUES')),
    ('/* harmless */\n# safe\n-- safe\nCREATE TABLE `demo` (`id` INT);', ('CREATE_TABLE',)),
    ('CREATE\nTABLE\t`demo` (`id` INT);', ('CREATE_TABLE',)),
    ('CREATE TABLE `SELECT` (`ORDER` INT);', ('CREATE_TABLE',)),
]

BAD = [
    'source /tmp/private.sql;',
    '\\. /tmp/private.sql;',
    '\\! echo hello;',
    '\\q;',
    'DELIMITER $$\nCREATE TABLE `demo` (`id` INT)$$',
    'delimiter //;',
    'USE production;',
    'DROP DATABASE production;',
    'CREATE DATABASE production;',
    'DROP TABLE production.`demo`;',
    'DROP TABLE `production`.`demo`;',
    'CREATE TABLE production.`demo` (`id` INT);',
    'DROP TABLE `demo`, `other`;',
    'CREATE USER attacker IDENTIFIED BY \'secret\';',
    'GRANT ALL ON *.* TO attacker;',
    'REVOKE ALL ON *.* FROM attacker;',
    'SET GLOBAL sql_mode=\'\';',
    "SET SESSION SQL_MODE='NO_BACKSLASH_ESCAPES';",
    "SET SESSION SQL_MODE='ANSI_QUOTES';",
    'SELECT * FROM `demo`;',
    'SELECT LOAD_FILE(\'/etc/passwd\');',
    'LOAD DATA LOCAL INFILE \'/tmp/foo\' INTO TABLE `demo`;',
    'SELECT 1 INTO OUTFILE \'/tmp/out\';',
    'CREATE VIEW `demo` AS SELECT 1;',
    'CREATE PROCEDURE p() SELECT 1;',
    'CREATE TRIGGER t BEFORE INSERT ON `demo` FOR EACH ROW SELECT 1;',
    'PREPARE s FROM \'DROP TABLE x\';',
    'EXECUTE s;',
    '/*!40101 GRANT ALL ON *.* TO attacker */;',
    '/*M!100000 DROP DATABASE production */;',
    '/*!40101 source /tmp/private.sql */;',
    '/*!40101 SET GLOBAL max_connections=1 */;',
    '/*!50700 CREATE TABLE `demo` (`id` INT) */;',  # ignored only, no effective statement
    '/*M!999999 GRANT ALL ON *.* TO attacker */;', # ignored only
    '/*!40101 CREATE TABLE `demo` (`id` INT); DROP TABLE `demo` */;', # guarded multi SQL
    '/*M!100000 /*nested*/ DROP TABLE `demo` */;',
    '/* unterminated',
    'CREATE TABLE `demo` (`id` INT)',
    'CREATE TABLE `demo` (`id` INT); DROP DATABASE production;',
    'CREATE TABLE `demo` (`id` INT);;',
    'CREATE TABLE `demo` (`id` INT); /*M!999999\\- enable the sandbox mode */',
    '/*M!999999\\- enable the sandbox mode changed */ CREATE TABLE `demo` (`id` INT);',
    '/*!40101 CREATE TABLE `demo` (`id` INT) */ DROP TABLE `other`;',
    'DROP TABLE `demo` /*!40101 DROP TABLE `other` */;',
    '/*M!999999 DROP DATABASE prod */ DROP TABLE `demo`;',
    '/*!50700 GRANT ALL ON *.* TO attacker */ DROP TABLE `demo`;',
    'CREATE TABLE `demo` (`id` INT) /*!40101 DROP TABLE `other` */;',
    'DROP TABLE `demo`; source /tmp/foo.sql;',
    'CREATE TABLE `demo` (`id` INT); \\. /tmp/foo.sql;',
    "INSERT INTO `demo` VALUES ('broken);",
    'CREATE TABLE `demo` (`id` INT);\r',
    'CREATE TABLE `demo` (`id` INT);\x00',
    'CREATE TABLE `demó` (`id` INT);',
    'CREATE TABLE `demo` (`id` INT);' * 65,
    '/*M!12345 */;CREATE TABLE `demo` (`id` INT);',
    '/*!1234 CREATE TABLE `demo` (`id` INT) */;',
    '/*!00000 CREATE TABLE `demo` (`id` INT) */;',
    '/*!40101 CREATE TABLE `demo` (`id` INT) */;\n--comment\n\\. /tmp/foo.sql;',
    "CREATE TABLE `demo` (`id` INT); INSERT INTO `demo` VALUES ('a\\q');",
    "INSERT INTO `demo` VALUES ('a\\;b');",
    'INSERT INTO `demo` VALUES (NOW());',
    'CREATE TABLE SELECT (`id` INT);',
    'CREATE TABLE `demo` (`a` VARCHAR(255), `b` VARCHAR(255));',
]

class FabricatedFrontendCases(unittest.TestCase):
    def test_selected_safe_fabrications(self):
        self.assertEqual(len(GOOD), 30)
        for i, (script, categories) in enumerate(GOOD):
            with self.subTest(case=i):
                result = MODULE.classify_fabricated_script(script)
                self.assertEqual(result.classes, categories)
                self.assertEqual(result.bytes_accounted, len(script))
                self.assertEqual(len(result.policy_digest), 64)

    def test_fail_closed_fabrications(self):
        self.assertEqual(len(BAD), 63)
        for i, script in enumerate(BAD):
            with self.subTest(case=i):
                with self.assertRaises(MODULE.Blocked) as cm:
                    MODULE.classify_fabricated_script(script)
                self.assertEqual(str(cm.exception), MODULE.ERROR)

    def test_type_bounds_fail_closed(self):
        for value in (None, 5, b'DROP TABLE `demo`;', '', ' ', 'x' * 32769):
            with self.subTest(category=type(value).__name__):
                with self.assertRaises(MODULE.Blocked):
                    MODULE.classify_fabricated_script(value)

    def test_comments_are_accounted_as_inert_or_guarded(self):
        script = (r'/*M!999999\- enable the sandbox mode */' + '\n'
                  + '/* ordinary */\n'
                  + '/*!50700 DROP DATABASE forbidden */;\n'
                  + '/*!40101 CREATE TABLE `demo` (`id` INT) */;')
        result = MODULE.classify_fabricated_script(script)
        self.assertTrue(result.sandbox_directive)
        self.assertEqual((result.active_guards, result.skipped_guards, result.regular_comments), (1, 1, 1))
        self.assertEqual(result.classes, ('CREATE_TABLE',))
        self.assertEqual(result.bytes_accounted, len(script))
        self.assertEqual(result, MODULE.classify_fabricated_script(script))

    def test_no_sql_input_option(self):
        # No database, Docker or file-input execution; CLI advertises only a
        # hardcoded self-test, not user-provided script text or file paths.
        import argparse
        import inspect
        source = inspect.getsource(MODULE.main)
        self.assertIn('--self-test', source)
        self.assertNotIn('--file', source)
        self.assertNotIn('--sql', source)
        self.assertNotIn('stdin', source)

if __name__ == '__main__':
    unittest.main()
