<?php
declare(strict_types=1);
/** B12 source-only PDO stub test. Never connects to a database or app config. */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_reader.php';

final class B12FakeStatement extends PDOStatement {
    public array $received = [];
    public function __construct(private array $rows) {}
    public function execute(?array $params = null): bool {
        $this->received = $params ?? [];
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        if ($mode !== PDO::FETCH_ASSOC) throw new RuntimeException('Unknown fetch mode');
        return $this->rows;
    }
}
final class B12FakePDO extends PDO {
    public bool $active = false;
    public function inTransaction(): bool { return $this->active; }
    public string $sql = '';
    public ?B12FakeStatement $statement = null;
    public function __construct(private array $rows) {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->sql = $query;
        $this->statement = new B12FakeStatement($this->rows);
        return $this->statement;
    }
}
$pass=0;
function ok(bool $actual, string $label): void {
    global $pass;
    if (!$actual) throw new RuntimeException('FAIL ' . $label);
    $pass++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function rejection(callable $fn, string $label): void {
    try {$fn();} catch (DailyRecordRevisionConflict|DailyRecordRevisionNotFound|InvalidArgumentException|LogicException $e) {
        ok(true, $label);
        return;
    }
    ok(false, $label);
}
function fixture(string $type='layer', ?int $cycle=42): array {
    $row = [
        'id' => '99', 'farm_id' => '7', 'cycle_id' => $cycle === null ? null : (string)$cycle,
        'record_date' => '2026-10-12', 'record_revision' => '3',
        'b12_tenant_cycle_verified' => $cycle === null ? null : (string)$cycle,
        'opening_stock' => '25',
    ];
    if ($type === 'ruminant') $row['animal_type'] = ' Goat ';
    return $row;
}
function plan(string $type='layer', mixed $cycle='42'): array {
    return daily_record_revision_reader_exact_plan(
        $type, 7, '99', $cycle, '2026-10-12', $type === 'ruminant' ? 'goat' : null
    );
}
try {
    $expectedTables = ['layer'=>'layer_daily_records','broiler'=>'broiler_daily_records','ruminant'=>'ruminant_daily_records'];
    foreach ($expectedTables as $type=>$table) {
        $p=plan($type);
        ok(str_contains($p['sql'], "FROM $table d LEFT JOIN production_cycles c"), "$type uses closed table map and tenant-qualified cycle join");
        ok(str_contains($p['sql'], 'c.farm_id = d.farm_id'), "$type verifies parent cycle farm");
        ok(str_contains($p['sql'], 'd.id = ? AND d.farm_id = ? LIMIT 2'), "$type selects exact row-id plus farm and caps cardinality");
        ok($p['params'] === [99,7], "$type cannot accept client-supplied farm");
        $s=daily_record_revision_reader_scope_plan($type,7,'42','2026-10-12',$type==='ruminant'?' Goat ':null);
        ok(str_contains($s['sql'],'d.cycle_id = ?') && $s['params'][2]===42, "$type selects an explicit cycle only");
        ok(str_ends_with($s['sql'],'LIMIT 2'), "$type rejects rather than silently picks ambiguous legacy row");
        $legacy=daily_record_revision_reader_scope_plan($type,7,'','2026-10-12',$type==='ruminant'?'GOAT':null);
        ok(str_contains($legacy['sql'],'d.cycle_id IS NULL') && count($legacy['params'])===($type==='ruminant'?3:2), "$type legacy NULL cannot select other cycles");
        if ($type==='ruminant') {
            ok(str_contains($legacy['sql'],'LOWER(TRIM(d.animal_type)) = ?') && $legacy['params'][2] === 'goat', 'ruminant species is scoped and normalized');
        } else {
            ok(!str_contains($legacy['sql'],'animal_type'), "$type query excludes species");
        }
        $loaded=daily_record_revision_reader_resolve_exact($type,7,$p,[fixture($type)]);
        ok($loaded['form_tokens']['write_intent']==='edit' && $loaded['form_tokens']['record_id']==='99' && $loaded['form_tokens']['expected_revision']==='3', "$type returns original browser revision and immutable row id");
        ok(!array_key_exists('b12_tenant_cycle_verified',$loaded['record']), "$type hides joined cycle verification metadata");
        $mock=new B12FakePDO([fixture($type)]);
        $loaded2=daily_record_revision_reader_read_exact($mock,$type,7,'99','42','2026-10-12',$type==='ruminant'?'goat':null);
        ok($loaded2['form_tokens']===$loaded['form_tokens'], "$type PDO facade enforces same exact reader contract");
        ok($mock->statement->received===[99,7] && $mock->sql===$p['sql'], "$type PDO uses tenant-bound parameterized SQL");
        $mockAbsent=new B12FakePDO([]);
        $check=daily_record_revision_reader_check_scope($mockAbsent,$type,7,'','2026-10-12',$type==='ruminant'?'goat':null);
        ok($check===['status'=>'absent','record_id'=>null], "$type cardinality zero means absence, not CREATE authorization");
        ok(!str_contains($mockAbsent->sql,'FOR UPDATE'), "$type ordinary reader probe remains nonlocking");
        $locked=new B12FakePDO([['id'=>'99']]);
        $locked->active=true;
        $current=daily_record_revision_reader_check_scope(
            $locked,$type,7,'','2026-10-12',$type==='ruminant'?'goat':null,true
        );
        ok($current===['status'=>'unique','record_id'=>99] &&
            str_ends_with($locked->sql,' LIMIT 2 FOR UPDATE') &&
            str_contains($locked->sql,'d.cycle_id IS NULL'),
            "$type CREATE scope probe is a current locking read, including NULL-cycle");
        $unlocked=new B12FakePDO([['id'=>'99']]);
        rejection(static fn()=>daily_record_revision_reader_check_scope(
            $unlocked,$type,7,'','2026-10-12',$type==='ruminant'?'goat':null,true
        ), "$type locking scope probe requires a transaction");
        ok($unlocked->sql==='', "$type unowned locking probe cannot query");
        $mockAmbiguous=new B12FakePDO([['id'=>'99'],['id'=>'100']]);
        rejection(static fn()=>daily_record_revision_reader_check_scope($mockAmbiguous,$type,7,'','2026-10-12',$type==='ruminant'?'goat':null), "$type scope with two records fails closed");
        $mockUnique=new B12FakePDO([['id'=>'99']]);
        ok(daily_record_revision_reader_check_scope($mockUnique,$type,7,'','2026-10-12',$type==='ruminant'?'goat':null)===['status'=>'unique','record_id'=>99], "$type cardinality one only supplies immutable ID");
        $legacyRow=fixture($type,null);
        $legacyPlan=plan($type,null);
        $legacyTokens=daily_record_revision_reader_resolve_exact($type,7,$legacyPlan,[$legacyRow])['form_tokens'];
        ok($legacyTokens['original_cycle_id']==='', "$type exact ID on nullable legacy cycle yields empty-original-cycle token");
        foreach ([
            ['id'=>'100'], ['farm_id'=>'8'], ['cycle_id'=>null],
            ['record_date'=>'2026-10-13'], ['record_revision'=>null],
            ['record_revision'=>'0'], ['b12_tenant_cycle_verified'=>null],
            ['is_population_activity_only'=>true],
        ] as $changes) {
            $r=array_replace(fixture($type),$changes);
            rejection(static fn()=>daily_record_revision_reader_resolve_exact($type,7,$p,[$r]), "$type mismatched edit identity/revision/tenant fails closed");
        }
        rejection(static fn()=>daily_record_revision_reader_resolve_exact($type,7,$p,[]), "$type absent exact row fails closed");
        rejection(static fn()=>daily_record_revision_reader_resolve_exact($type,7,$p,[fixture($type),fixture($type)]), "$type extra exact rows fail closed");
        $r=fixture($type);unset($r['b12_tenant_cycle_verified']);
        rejection(static fn()=>daily_record_revision_reader_resolve_exact($type,7,$p,[$r]), "$type cycle integrity metadata required");
        if ($type==='ruminant') {
            rejection(static fn()=>daily_record_revision_reader_resolve_exact($type,7,$p,[array_replace(fixture($type),['animal_type'=>'cattle'])]), 'ruminant cannot reuse identity for another species');
        }
    }
    ok(daily_record_revision_reader_scope_cardinality([])===['status'=>'absent','record_id'=>null], 'zero scope matches does not imply CREATE right');
    ok(daily_record_revision_reader_scope_cardinality([['id'=>'99']])===['status'=>'unique','record_id'=>99], 'one scope match only supplies row ID');
    rejection(static fn()=>daily_record_revision_reader_scope_cardinality([['id'=>'99'],['id'=>'100']]), 'two legacy rows are ambiguous');
    rejection(static fn()=>daily_record_revision_reader_scope_cardinality([['id'=>null]]), 'malformed legacy id rejected');
    rejection(static fn()=>daily_record_revision_reader_scope_cardinality([[]]), 'missing legacy id rejected');
    foreach (['0','01','-1','1e3','abc',str_repeat('9',40)] as $raw) {
        rejection(static fn()=>daily_record_revision_reader_cycle($raw), 'malformed/ambiguous cycle selection rejected');
    }
    rejection(static fn()=>plan('arbitrary_table'), 'untrusted table name rejected');
    rejection(static fn()=>daily_record_revision_reader_exact_plan('layer',0,'99','42','2026-10-12',null), 'nonpositive farm rejected');
    rejection(static fn()=>daily_record_revision_reader_exact_plan('layer',7,'0','42','2026-10-12',null), 'invalid row ID rejected');
    rejection(static fn()=>daily_record_revision_reader_scope_plan('layer',7,42,'2026-02-30',null), 'invalid calendar day rejected');
    rejection(static fn()=>daily_record_revision_reader_scope_plan('layer',7,42,'2026-10-12','goat'), 'poultry unexpected animal type rejected');
    rejection(static fn()=>daily_record_revision_reader_scope_plan('ruminant',7,42,'2026-10-12','  '), 'ruminant missing species rejected');
    rejection(static fn()=>daily_record_revision_reader_scope_plan('ruminant',7,42,'2026-10-12','lion'), 'ruminant unknown species rejected');
    echo 'B12_READER_CARDINALITY_PURE=PASS' . PHP_EOL;
    echo 'PASS_COUNT=' . $pass . PHP_EOL;
} catch(Throwable $e) {
    fwrite(STDERR, 'B12_READER_CARDINALITY_PURE=FAIL' . PHP_EOL . get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
