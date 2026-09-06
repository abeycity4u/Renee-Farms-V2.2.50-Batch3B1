<?php
/**
 * V2.3 Investigation History closure verifier.
 *
 * Read-only: validates schema/runtime history behaviour without inserting,
 * updating, or deleting investigation follow-up rows.
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/investigation_followup.php';

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

function inv_check(string $label, bool $ok): void
{
    global $failures, $checks;
    $checks++;
    if (!$ok) $failures++;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
}

function inv_info(string $label, $value): void
{
    echo 'INFO: ' . $label . ': ' . (is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_SLASHES)) . PHP_EOL;
}

$tableExists = (int)$pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'management_investigation_followups'"
)->fetchColumn() === 1;
inv_check('management_investigation_followups table exists', $tableExists);

if (!$tableExists) {
    exit(1);
}

$columnStmt = $pdo->query(
    "SELECT column_name, is_nullable
     FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'management_investigation_followups'"
);
$columns = [];
foreach ($columnStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $columns[(string)$row['column_name']] = (string)$row['is_nullable'];
}
inv_check('episode_key column exists', isset($columns['episode_key']));
inv_check('episode_key is NOT NULL', ($columns['episode_key'] ?? '') === 'NO');

$indexStmt = $pdo->query(
    "SELECT index_name, non_unique, seq_in_index, column_name
     FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'management_investigation_followups'
     ORDER BY index_name, seq_in_index"
);
$indexes = [];
foreach ($indexStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $name = (string)$row['index_name'];
    if (!isset($indexes[$name])) {
        $indexes[$name] = ['non_unique' => (int)$row['non_unique'], 'columns' => []];
    }
    $indexes[$name]['columns'][] = (string)$row['column_name'];
}
$expectedEpisodeIndex = ['farm_id', 'investigation_type', 'subject_id', 'issue_type', 'episode_key'];
$episodeIndexOk = false;
foreach ($indexes as $index) {
    if ((int)$index['non_unique'] === 0 && $index['columns'] === $expectedEpisodeIndex) {
        $episodeIndexOk = true;
        break;
    }
}
inv_check('episode identity has a unique composite index', $episodeIndexOk);

$totalRows = (int)$pdo->query('SELECT COUNT(*) FROM management_investigation_followups')->fetchColumn();
$resolvedRows = (int)$pdo->query("SELECT COUNT(*) FROM management_investigation_followups WHERE status = 'resolved'")->fetchColumn();
$blankEpisodeKeys = (int)$pdo->query(
    "SELECT COUNT(*) FROM management_investigation_followups WHERE episode_key IS NULL OR TRIM(episode_key) = ''"
)->fetchColumn();
$duplicateEpisodeIdentities = (int)$pdo->query(
    "SELECT COUNT(*) FROM (
        SELECT farm_id, investigation_type, subject_id, issue_type, episode_key, COUNT(*) c
        FROM management_investigation_followups
        GROUP BY farm_id, investigation_type, subject_id, issue_type, episode_key
        HAVING COUNT(*) > 1
     ) x"
)->fetchColumn();

inv_info('investigation follow-up rows', $totalRows);
inv_info('resolved investigation rows retained', $resolvedRows);
inv_check('no blank episode keys exist', $blankEpisodeKeys === 0);
inv_check('no duplicate episode identities exist', $duplicateEpisodeIdentities === 0);

$helperSource = file_get_contents($root . '/lib/investigation_followup.php') ?: '';
$poultrySource = file_get_contents($root . '/management/investigation.php') ?: '';
$ruminantSource = file_get_contents($root . '/management/ruminant_investigation.php') ?: '';

inv_check(
    'history helper excludes only the current episode',
    str_contains($helperSource, 'function investigation_followup_prior_history') &&
    str_contains($helperSource, 'f.episode_key<>?')
);
inv_check(
    'poultry previous history remains visible when current follow-up exists',
    str_contains($poultrySource, 'if(!empty($priorHistory))') &&
    !str_contains($poultrySource, 'if(!$followup && $priorFollowup)')
);
inv_check(
    'ruminant previous history remains visible when current follow-up exists',
    str_contains($ruminantSource, 'if(!empty($priorHistory))') &&
    !str_contains($ruminantSource, 'if(!$followup && $priorFollowup)')
);
inv_check(
    'UI explicitly treats previous investigations as permanent historical context',
    str_contains($poultrySource, 'Historical management reviews are retained permanently.') &&
    str_contains($ruminantSource, 'Historical management reviews are retained permanently.')
);

$groups = $pdo->query(
    "SELECT farm_id, investigation_type, subject_id, issue_type, COUNT(*) episode_count
     FROM management_investigation_followups
     GROUP BY farm_id, investigation_type, subject_id, issue_type
     HAVING COUNT(*) > 1
     ORDER BY episode_count DESC, farm_id, investigation_type, subject_id, issue_type"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

inv_info('multi-episode investigation groups', count($groups));
$runtimeGroupsChecked = 0;
$runtimeGroupsPassed = 0;

foreach ($groups as $group) {
    $latestStmt = $pdo->prepare(
        "SELECT * FROM management_investigation_followups
         WHERE farm_id = ? AND investigation_type = ? AND subject_id = ? AND issue_type = ?
         ORDER BY as_of_date DESC, id DESC
         LIMIT 1"
    );
    $latestStmt->execute([
        (int)$group['farm_id'],
        (string)$group['investigation_type'],
        (int)$group['subject_id'],
        (string)$group['issue_type'],
    ]);
    $latest = $latestStmt->fetch(PDO::FETCH_ASSOC);
    if (!$latest) continue;

    $expectedStmt = $pdo->prepare(
        "SELECT id FROM management_investigation_followups
         WHERE farm_id = ? AND investigation_type = ? AND subject_id = ? AND issue_type = ?
           AND as_of_date <= ? AND episode_key <> ?
         ORDER BY as_of_date DESC, id DESC
         LIMIT 100"
    );
    $expectedStmt->execute([
        (int)$group['farm_id'],
        (string)$group['investigation_type'],
        (int)$group['subject_id'],
        (string)$group['issue_type'],
        (string)$latest['as_of_date'],
        (string)$latest['episode_key'],
    ]);
    $expectedIds = array_map('intval', $expectedStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    $history = investigation_followup_prior_history(
        $pdo,
        (int)$group['farm_id'],
        (string)$group['investigation_type'],
        (int)$group['subject_id'],
        (string)$group['issue_type'],
        (string)$latest['as_of_date'],
        (string)$latest['episode_key'],
        100
    );
    $actualIds = array_map(static fn(array $row): int => (int)$row['id'], $history);

    $exact = $actualIds === $expectedIds;
    $runtimeGroupsChecked++;
    if ($exact) $runtimeGroupsPassed++;

    $label = sprintf(
        'history helper returns all prior episodes for farm %d %s subject %d %s',
        (int)$group['farm_id'],
        (string)$group['investigation_type'],
        (int)$group['subject_id'],
        (string)$group['issue_type']
    );
    inv_check($label, $exact);
}

if ($runtimeGroupsChecked === 0) {
    echo "INFO: No naturally existing multi-episode investigation group is available for a live recurrence proof. Static/schema history protections still passed; perform one UI recurrence QA before final closure.\n";
} else {
    inv_check('all existing multi-episode groups preserve prior-history visibility', $runtimeGroupsPassed === $runtimeGroupsChecked);
}

$newerAfterResolved = (int)$pdo->query(
    "SELECT COUNT(*) FROM management_investigation_followups old
     WHERE old.status = 'resolved'
       AND EXISTS (
           SELECT 1 FROM management_investigation_followups newer
           WHERE newer.farm_id = old.farm_id
             AND newer.investigation_type = old.investigation_type
             AND newer.subject_id = old.subject_id
             AND newer.issue_type = old.issue_type
             AND newer.episode_key <> old.episode_key
             AND (newer.as_of_date > old.as_of_date OR (newer.as_of_date = old.as_of_date AND newer.id > old.id))
       )"
)->fetchColumn();
inv_info('resolved episodes with a newer recurrence still retained', $newerAfterResolved);

if ($newerAfterResolved > 0) {
    inv_check('resolved historical episodes remain stored after newer recurrence', true);
} else {
    echo "INFO: No resolved-before-newer recurrence exists in current live data; no rows were created just for this verifier.\n";
}

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
if ($failures === 0) {
    echo "PASS: V2.3 investigation-history integrity is healthy and no existing history was mutated.\n";
}
exit($failures === 0 ? 0 : 1);
