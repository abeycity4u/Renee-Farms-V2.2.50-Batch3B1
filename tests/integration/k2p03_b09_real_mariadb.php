<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 B09 — source-linked Daily Feed inventory atomicity.
 *
 * Test-only real MariaDB fixture; no application config, external network,
 * protected database, tenant or reused production credentials.
 * All writes are scoped to one new disposable GitHub Actions farm.
 */
require_once __DIR__ . '/k2p03_ci_isolation.php';

$stage='CI_ISOLATION';
$pdo=null;

function b09_snapshot(PDO $pdo, int $farmId, string $table): array
{
    $tables=[
        'inventory_categories','stock_items','stock_transactions',
        'stock_consumption_allocations',
        'stock_consumption_allocation_revisions',
        'layer_daily_records','production_cycles',
    ];
    if (!in_array($table,$tables,true)) {
        throw new RuntimeException('B09 snapshot table not allowlisted.');
    }
    $stmt=$pdo->prepare(
        'SELECT * FROM '.$table.' WHERE farm_id=? ORDER BY id'
    );
    $stmt->execute([$farmId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function b09_assert_snapshots(
    PDO $pdo,int $farmId,array $expected,string $label
): void {
    foreach($expected as $table=>$rows) {
        k2p03_ci_equal(
            $rows,b09_snapshot($pdo,$farmId,$table),
            $label.' — '.$table.' unchanged'
        );
    }
}

function b09_capture(PDO $pdo, int $farmId): array
{
    $result=[];
    foreach([
        'inventory_categories','stock_items','stock_transactions',
        'stock_consumption_allocations',
        'stock_consumption_allocation_revisions',
        'layer_daily_records','production_cycles',
    ] as $table) {
        $result[$table]=b09_snapshot($pdo,$farmId,$table);
    }
    return $result;
}

function b09_item(PDO $pdo,int $farmId,int $itemId): array
{
    $stmt=$pdo->prepare(
        'SELECT * FROM stock_items WHERE id=? AND farm_id=?'
    );
    $stmt->execute([$itemId,$farmId]);
    $r=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!is_array($r)) {
        throw new RuntimeException('B09 synthetic inventory item absent.');
    }
    return $r;
}

function b09_source(PDO $pdo,int $farmId,int $recordId): array
{
    $stmt=$pdo->prepare(
        'SELECT * FROM layer_daily_records WHERE id=? AND farm_id=?'
    );
    $stmt->execute([$recordId,$farmId]);
    $r=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!is_array($r)) {
        throw new RuntimeException('B09 synthetic Daily Record absent.');
    }
    return $r;
}

function b09_active_used(PDO $pdo,int $farmId,int $recordId): array
{
    $stmt=$pdo->prepare(
        "SELECT * FROM stock_transactions
          WHERE farm_id=? AND source_type='daily_layer_record'
            AND source_id=? AND transaction_type='used'
            AND is_reversed=0 ORDER BY id"
    );
    $stmt->execute([$farmId,$recordId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function b09_reversals(PDO $pdo,int $farmId,int $recordId): array
{
    $stmt=$pdo->prepare(
        "SELECT * FROM stock_transactions
          WHERE farm_id=? AND source_type='daily_layer_record_reversal'
            AND source_id=? ORDER BY id"
    );
    $stmt->execute([$farmId,$recordId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function b09_reconcile(PDO $pdo,int $farmId,int $itemId,
    string $quantity,int $ledgerCount): void
{
    k2p03_ci_equal($quantity,
        (string)b09_item($pdo,$farmId,$itemId)['current_stock'],
        'physical item quantity matches expected');
    k2p03_ci_equal((float)$quantity,
        stock_expected_balance($pdo,$farmId,$itemId),
        'append-only receipts, USED and reversals net to the physical stock');
    $result=stock_reconciliation($pdo,$farmId,$itemId);
    k2p03_ci_equal(1,count($result),'one stock item in reconciliation');
    k2p03_ci_equal('reconciled',$result[0]['status'],
        'canonical inventory reconciliation passes');
    k2p03_ci_equal($ledgerCount,(int)$result[0]['ledger_transaction_count'],
        'expected full immutable ledger row count');
    k2p03_ci_equal(0.0,(float)$result[0]['difference'],
        'no physical/ledger stock discrepancy');
}

try {
    k2p03_ci_require_environment(
        'c137-gc24-k2p03-ci-mariadb-b09','B09','renee_k2p03_ci_b09'
    );
    $root=dirname(__DIR__,2);
    k2p03_ci_equal(
        '9503771b47c6c2fe26bb3237da69a17874e724a3',
        k2p03_ci_blob($root.'/lib/daily_population_continuity.php'),
        'frozen canonical application continuity blob'
    );
    k2p03_ci_equal(
        'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        k2p03_ci_blob($root.'/database_schema.sql'),
        'frozen 69-table MariaDB schema blob'
    );
    $stage='FRESH_DISPOSABLE_DATABASE';
    $pdo=k2p03_ci_connection('renee_k2p03_ci_b09');
    require_once $root.'/lib/daily_feed_sync.php';

    $stage='SYNTHETIC_FARM_CYCLE_AND_FEED';
    $pdo->exec(
        "INSERT INTO farms(name,slug)
         VALUES ('K2P03 B09 feed stock atomicity','k2p03-b09-synthetic')"
    );
    $farmId=(int)$pdo->lastInsertId();
    k2p03_ci_check($farmId>0,'one synthetic B09 farm');

    $pdo->prepare(
        "INSERT INTO production_cycles
         (farm_id,cycle_code,farm_type,production_type,status,
          start_date,opening_headcount)
         VALUES (?,'b09-layer','poultry','layer','active','2026-09-21',30)"
    )->execute([$farmId]);
    $cycleId=(int)$pdo->lastInsertId();
    k2p03_ci_check($cycleId>0,'one synthetic Layer cycle');

    $pdo->prepare(
        "INSERT INTO inventory_categories
         (farm_id,category_name,farm_type,unit,financial_type,inventory_role)
         VALUES (?,'B09 synthetic Layer Feed','poultry','bag','feed','operational')"
    )->execute([$farmId]);
    $categoryId=(int)$pdo->lastInsertId();
    k2p03_ci_check($categoryId>0,'one operational feed category');

    $pdo->prepare(
        "INSERT INTO stock_items
         (farm_id,item_name,category_id,current_stock,unit_cost,unit,
          farm_type,feed_category,default_production_type,
          financial_classification)
         VALUES (?,'B09 Layer Mash',?,0,0,'bag','poultry','layer','layer','feed')"
    )->execute([$farmId,$categoryId]);
    $itemId=(int)$pdo->lastInsertId();
    k2p03_ci_check($itemId>0,'one synthetic Layer feed stock item');

    $pdo->prepare(
        'INSERT INTO layer_daily_records
         (farm_id,cycle_id,record_date,opening_stock,mortality,
          feed_consumption_bags,feed_item_id,egg_production,crates_count,
          laying_rate,birds_age)
         VALUES (?,?,"2026-09-21",30,0,0,?,0,0,0,25)'
    )->execute([$farmId,$cycleId,$itemId]);
    $recordId=(int)$pdo->lastInsertId();
    k2p03_ci_check($recordId>0,'one durable linked Layer Daily Record');
    k2p03_ci_equal('0.00',
        (string)b09_source($pdo,$farmId,$recordId)['feed_consumption_bags'],
        'initial saved feed quantity is zero');

    $stage='VALUED_SOURCE_RECEIPTS';
    $pdo->beginTransaction();
    try {
        $receiptA=stock_apply_movement(
            $pdo,$farmId,$itemId,'received',20.0,'2026-09-21',
            'B09 first feed receipt 20 at 100',null,'poultry','layer',
            null,'inventory_api',null,100.0
        );
        $receiptB=stock_apply_movement(
            $pdo,$farmId,$itemId,'received',10.0,'2026-09-21',
            'B09 second feed receipt 10 at 200',null,'poultry','layer',
            null,'inventory_api',null,200.0
        );
        k2p03_ci_check($receiptA>0&&$receiptB>$receiptA,
            'two unique source-linked valued stock receipts');
        $pdo->commit();
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    b09_reconcile($pdo,$farmId,$itemId,'30.00',2);
    $receipts=b09_snapshot($pdo,$farmId,'stock_transactions');
    k2p03_ci_equal(2,count($receipts),'two durable opening receipts');
    foreach([[100.0,2000.0],[200.0,2000.0]] as $i=>$cost) {
        k2p03_ci_equal('received',$receipts[$i]['transaction_type'],
            'receipt '.$i.' is incoming physical stock');
        k2p03_ci_equal($cost[0],(float)$receipts[$i]['unit_cost'],
            'receipt '.$i.' preserves immutable unit-cost snapshot');
        k2p03_ci_equal($cost[1],(float)$receipts[$i]['total_cost'],
            'receipt '.$i.' preserves immutable monetary value');
        k2p03_ci_equal('feed',$receipts[$i]['financial_classification'],
            'receipt '.$i.' immutable financial classification is feed');
    }
    $weighted=(float)b09_item($pdo,$farmId,$itemId)['unit_cost'];
    k2p03_ci_check($weighted>133.32&&$weighted<133.35,
        'on-hand valuation reflects mixed-price receipts');

    $stage='FIRST_DAILY_FEED_SOURCE_USED';
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE layer_daily_records SET feed_consumption_bags=2
             WHERE id=? AND farm_id=?'
        )->execute([$recordId,$farmId]);
        sync_daily_feed_usage(
            $pdo,$farmId,$recordId,$itemId,2.0,$cycleId,
            '2026-09-21','poultry','layer','daily_layer_record'
        );
        $pdo->commit();
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    b09_reconcile($pdo,$farmId,$itemId,'28.00',3);
    $initial=b09_active_used($pdo,$farmId,$recordId);
    k2p03_ci_equal(1,count($initial),
        'one active USED inventory transaction owns the Daily Record');
    $firstUsed=$initial[0];
    k2p03_ci_equal($itemId,(int)$firstUsed['stock_item_id'],
        'initial USED linked to authoritative feed item');
    k2p03_ci_equal($cycleId,(int)$firstUsed['cycle_id'],
        'initial USED linked to authoritative saved cycle');
    k2p03_ci_equal('2.00',(string)$firstUsed['quantity'],
        'initial physical feed deduction exactly two bags');
    k2p03_ci_equal('feed',$firstUsed['financial_classification'],
        'USED cost attribution has immutable Feed classification');
    k2p03_ci_check((float)$firstUsed['total_cost']>0,
        'USED movement records a positive historic feed valuation');

    $stage='IDENTICAL_REPLAY_AND_GUARDS';
    $unchanged=b09_capture($pdo,$farmId);
    $pdo->beginTransaction();
    try {
        sync_daily_feed_usage(
            $pdo,$farmId,$recordId,$itemId,2.0,$cycleId,
            '2026-09-21','poultry','layer','daily_layer_record'
        );
        $pdo->commit();
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    b09_assert_snapshots($pdo,$farmId,$unchanged,
        'identical feed replay');
    b09_reconcile($pdo,$farmId,$itemId,'28.00',3);

    $wrongCycle=false;
    $pdo->beginTransaction();
    try {
        sync_daily_feed_usage(
            $pdo,$farmId,$recordId,$itemId,2.0,$cycleId+101,
            '2026-09-21','poultry','layer','daily_layer_record'
        );
    } catch(RuntimeException $e) {
        $wrongCycle=str_contains(
            $e->getMessage(),'saved Daily Record cycle'
        );
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    k2p03_ci_check($wrongCycle,
        'authoritative Daily Record cycle rejects forged feed attribution');
    b09_assert_snapshots($pdo,$farmId,$unchanged,
        'wrong cycle rejected without reversal');

    $noTransaction=false;
    try {
        sync_daily_feed_usage(
            $pdo,$farmId,$recordId,$itemId,2.0,$cycleId,
            '2026-09-21','poultry','layer','daily_layer_record'
        );
    } catch(RuntimeException $e) {
        $noTransaction=str_contains(
            strtolower($e->getMessage()),'active transaction'
        );
    }
    k2p03_ci_check($noTransaction,
        'feed synchronization refuses missing caller transaction');
    b09_assert_snapshots($pdo,$farmId,$unchanged,
        'no-transaction call has no ledger side effects');

    $stage='SOURCE_OWNED_REVERSAL_AND_CORRECTION';
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE layer_daily_records SET feed_consumption_bags=3
             WHERE id=? AND farm_id=?'
        )->execute([$recordId,$farmId]);
        sync_daily_feed_usage(
            $pdo,$farmId,$recordId,$itemId,3.0,$cycleId,
            '2026-09-21','poultry','layer','daily_layer_record'
        );
        $pdo->commit();
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    b09_reconcile($pdo,$farmId,$itemId,'27.00',5);
    $current=b09_active_used($pdo,$farmId,$recordId);
    $reversals=b09_reversals($pdo,$farmId,$recordId);
    k2p03_ci_equal(1,count($current),
        'quantity edit retains exactly one active source-linked USED');
    k2p03_ci_equal(1,count($reversals),
        'quantity edit creates exactly one source-linked reversal');
    k2p03_ci_equal('3.00',(string)$current[0]['quantity'],
        'corrected active USED quantity is three bags');
    k2p03_ci_equal((int)$firstUsed['id'],
        (int)$reversals[0]['reversal_of_id'],
        'compensating RECEIVED points at original USED ID');
    k2p03_ci_equal('received',$reversals[0]['transaction_type'],
        'reversal restores original USED quantity');
    k2p03_ci_equal('2.00',(string)$reversals[0]['quantity'],
        'reversal restores exactly two earlier bags');
    $txs=b09_snapshot($pdo,$farmId,'stock_transactions');
    $orig=array_values(array_filter(
        $txs,static fn(array $r):bool=>
            (int)$r['id']===(int)$firstUsed['id']
    ));
    k2p03_ci_equal(1,count($orig),
        'original USED remains durable rather than deleted');
    k2p03_ci_equal(1,(int)$orig[0]['is_reversed'],
        'original USED marked reversed after source correction');
    k2p03_ci_equal((int)$reversals[0]['id'],
        (int)$orig[0]['reversal_of_id'],
        'original USED points to its unique compensating reversal');
    k2p03_ci_equal('3.00',
        (string)b09_source($pdo,$farmId,$recordId)['feed_consumption_bags'],
        'saved Daily Record fact matches authoritative three-bag USED');
    k2p03_ci_check((float)b09_item($pdo,$farmId,$itemId)['unit_cost']>0,
        'corrected stock retains positive valued cost basis');

    $stage='INSUFFICIENT_STOCK_AFTER_PROVISIONAL_REVERSAL';
    $beforeFailure=b09_capture($pdo,$farmId);
    $insufficient=false;
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE layer_daily_records SET feed_consumption_bags=40
             WHERE id=? AND farm_id=?'
        )->execute([$recordId,$farmId]);
        sync_daily_feed_usage(
            $pdo,$farmId,$recordId,$itemId,40.0,$cycleId,
            '2026-09-21','poultry','layer','daily_layer_record'
        );
    } catch(RuntimeException $e) {
        $insufficient=str_contains($e->getMessage(),'Insufficient stock');
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    k2p03_ci_check($insufficient,
        'insufficient USED after provisional reversal explicitly rejected');
    b09_assert_snapshots($pdo,$farmId,$beforeFailure,
        'insufficient-stock rollback undoes provisional feed reversal');
    b09_reconcile($pdo,$farmId,$itemId,'27.00',5);
    k2p03_ci_equal(1,count(b09_active_used($pdo,$farmId,$recordId)),
        'failed correction keeps original three-bag USED active');

    $stage='FORCED_ABORT_AFTER_SUCCESSFUL_FEED_WRITERS';
    $forced=false;
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE layer_daily_records SET feed_consumption_bags=4
             WHERE id=? AND farm_id=?'
        )->execute([$recordId,$farmId]);
        sync_daily_feed_usage(
            $pdo,$farmId,$recordId,$itemId,4.0,$cycleId,
            '2026-09-21','poultry','layer','daily_layer_record'
        );
        k2p03_ci_equal('26.00',
            (string)b09_item($pdo,$farmId,$itemId)['current_stock'],
            'provisional four-bag USED and reversal applied inside transaction');
        k2p03_ci_equal(1,count(b09_active_used(
            $pdo,$farmId,$recordId
        )),'provisional edit has one active USED before injected failure');
        throw new RuntimeException('B09_SYNTHETIC_FAULT_AFTER_FEED_WRITERS');
    } catch(RuntimeException $e) {
        $forced=$e->getMessage()==='B09_SYNTHETIC_FAULT_AFTER_FEED_WRITERS';
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    k2p03_ci_check($forced,
        'injected post-feed fault triggers one caller-owned rollback');
    b09_assert_snapshots($pdo,$farmId,$beforeFailure,
        'post-writer failure leaves no orphan reversal or USED');
    b09_reconcile($pdo,$farmId,$itemId,'27.00',5);

    $stage='FINAL_DELETION_REVERSAL_IDEMPOTENCE';
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE layer_daily_records
             SET feed_consumption_bags=0,feed_item_id=NULL
             WHERE id=? AND farm_id=?'
        )->execute([$recordId,$farmId]);
        delete_daily_feed_usage(
            $pdo,$farmId,$recordId,'daily_layer_record'
        );
        $pdo->commit();
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    b09_reconcile($pdo,$farmId,$itemId,'30.00',6);
    k2p03_ci_equal(0,count(b09_active_used(
        $pdo,$farmId,$recordId
    )),'no active source USED after successful deletion');
    k2p03_ci_equal(2,count(b09_reversals(
        $pdo,$farmId,$recordId
    )),'two historical USED entries have separate owned reversals');
    k2p03_ci_equal('0.00',
        (string)b09_source($pdo,$farmId,$recordId)['feed_consumption_bags'],
        'saved Daily Record no longer claims consumed bags');
    k2p03_ci_equal(null,
        b09_source($pdo,$farmId,$recordId)['feed_item_id'],
        'saved feed source is now intentionally empty');

    $deleted=b09_capture($pdo,$farmId);
    $pdo->beginTransaction();
    try {
        delete_daily_feed_usage(
            $pdo,$farmId,$recordId,'daily_layer_record'
        );
        sync_daily_feed_usage(
            $pdo,$farmId,$recordId,null,0.0,$cycleId,
            '2026-09-21','poultry','layer','daily_layer_record'
        );
        $pdo->commit();
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    b09_assert_snapshots($pdo,$farmId,$deleted,
        'repeat delete and zero-use replay are idempotent');
    b09_reconcile($pdo,$farmId,$itemId,'30.00',6);

    $stage='VALUATION_AND_LEDGER_FINAL';
    $finalReceipts=b09_snapshot($pdo,$farmId,'stock_transactions');
    k2p03_ci_equal(6,count($finalReceipts),
        'two RECEIVED, two USED and two compensating RECEIVED remain');
    k2p03_ci_equal(2,count(array_filter(
        $finalReceipts,static fn(array $r):bool=>
            $r['source_type']==='inventory_api'
    )),'both valued original receipt source records survive');
    k2p03_ci_equal(0,count(b09_snapshot(
        $pdo,$farmId,'stock_consumption_allocations'
    )),'no phantom stock cost allocation records introduced');
    k2p03_ci_equal(0,count(b09_snapshot(
        $pdo,$farmId,'stock_consumption_allocation_revisions'
    )),'no phantom stock allocation revisions introduced');
    k2p03_ci_check(
        abs((float)b09_item($pdo,$farmId,$itemId)['unit_cost']-$weighted)<0.001,
        'full consumption reversal restores receipt-based current unit valuation'
    );
    echo 'K2_P03_B09_REAL_MARIADB=PASS'.PHP_EOL;
} catch(Throwable $e) {
    if($pdo instanceof PDO&&$pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'FAILURE_STAGE='.$stage.PHP_EOL);
    fwrite(STDERR,'FAILURE_CLASS='.get_class($e).PHP_EOL);
    fwrite(STDERR,'K2_P03_B09_REAL_MARIADB=FAIL'.PHP_EOL);
    exit(1);
}
