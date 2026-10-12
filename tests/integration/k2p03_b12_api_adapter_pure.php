<?php
declare(strict_types=1);
/** Isolated B12 API contract tests: no app bootstrap, network or MariaDB. */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_api_adapter.php';

$GLOBALS['b12ctx'] = [
    'owner' => false, 'admin' => false, 'module' => true,
    'farm' => 7, 'view' => true, 'edit' => true,
];
function isPlatformOwner(): bool { return $GLOBALS['b12ctx']['owner']; }
function hasRole(string $role): bool { return $role === 'farm_admin' && $GLOBALS['b12ctx']['admin']; }
function checkAccess(string $module): bool {
    return $GLOBALS['b12ctx']['module'] && in_array($module, ['poultry', 'ruminant'], true);
}
function getUserType(): string { return 'poultry_manager'; }
function hasPermission(string $role, string $permission): bool {
    if (str_ends_with($permission, '_edit')) return $GLOBALS['b12ctx']['edit'];
    return $GLOBALS['b12ctx']['view'];
}
function requireCurrentFarmId(): int { return $GLOBALS['b12ctx']['farm']; }

final class B12ApiStmt extends PDOStatement {
    public array $parameters = [];
    public function __construct(private array $rows, private mixed $column = false) {}
    public function execute(?array $params = null): bool {
        $this->parameters = $params ?? [];
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        if ($mode !== PDO::FETCH_ASSOC) throw new RuntimeException('Unexpected fetch');
        return $this->rows;
    }
    public function fetchColumn(int $column = 0): mixed { return $this->column; }
}
final class B12ApiPDO extends PDO {
    public int $queries = 0;
    public int $prepares = 0;
    public string $lastSql = '';
    public ?B12ApiStmt $lastStatement = null;
    public bool $pdoUnavailable = false;
    public function __construct(public array $rows, public int $schema = 3) {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        ++$this->queries;
        $this->lastSql = $query;
        if ($this->pdoUnavailable) throw new PDOException('internal sensitive SQL');
        if (!str_contains($query, 'information_schema.columns')) throw new RuntimeException('Unexpected SQL');
        return new B12ApiStmt([], (string)$this->schema);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        ++$this->prepares;
        $this->lastSql = $query;
        if ($this->pdoUnavailable) throw new PDOException('internal sensitive SQL');
        return $this->lastStatement = new B12ApiStmt($this->rows);
    }
}
function b12ApiRow(string $type='layer', ?string $cycle='42'): array {
    $row = [
        'id'=>'99','farm_id'=>'7','cycle_id'=>$cycle,'record_date'=>'2026-10-12',
        'record_revision'=>'3','b12_tenant_cycle_verified'=>$cycle,
        'user_id'=>441, 'created_at'=>'test-secret',
        'opening_stock'=>200,'mortality'=>1,'remarks'=>'Synth only',
    ];
    if ($type==='ruminant') $row['animal_type']=' Goat ';
    return $row;
}
function b12ApiRequest(string $type='layer', string $mode='exact', string $cycle='42'): array {
    $q=['mode'=>$mode,'type'=>$type,'cycle_id'=>$cycle,'record_date'=>'2026-10-12'];
    if ($type==='ruminant') $q['animal_type']=' Goat ';
    if ($mode==='exact') $q['record_id']='99';
    return $q;
}
$passes=0;
function ok(bool $condition, string $description): void {
    global $passes;
    if (!$condition) throw new RuntimeException('FAIL: '.$description);
    ++$passes;
    echo 'PASS: '.$description.PHP_EOL;
}
function code(PDO $pdo, array $q, int $expected, string $description, string $method='GET'): array {
    $out=daily_record_revision_api_handle($pdo,$method,$q);
    ok($out['http_status']===$expected, $description.' HTTP '. $expected);
    return $out;
}
function resetAuth(): void {
    $GLOBALS['b12ctx']=['owner'=>false,'admin'=>false,'module'=>true,'farm'=>7,'view'=>true,'edit'=>true];
    $_SESSION=['user_id'=>55];
}
try {
    resetAuth();
    foreach (['layer','broiler','ruminant'] as $type) {
        $pdo=new B12ApiPDO([b12ApiRow($type)]);
        $res=code($pdo,b12ApiRequest($type),200,$type.' exact row success');
        $payload=$res['body'];
        ok($payload['success']===true && $payload['mode']==='exact', $type.' exact envelope');
        ok($payload['data']['form_tokens']['record_id']==='99' &&
           $payload['data']['form_tokens']['expected_revision']==='3', $type.' keeps actual original tokens');
        ok($payload['data']['record']['opening_stock']===200, $type.' returns editor data');
        foreach (['farm_id','user_id','created_at','record_revision','b12_tenant_cycle_verified'] as $hidden) {
            ok(!array_key_exists($hidden,$payload['data']['record']), $type.' does not expose internal '. $hidden);
        }
        ok($pdo->queries===1 && $pdo->prepares===1, $type.' schema probe precedes one exact read');
        ok(str_contains($pdo->lastSql,'d.id = ? AND d.farm_id = ?'), $type.' exactly farm and row scoped');
        ok($pdo->lastStatement->parameters===[99,7], $type.' farm is server provided');
        $noRows=new B12ApiPDO([]);
        $absent=code($noRows,b12ApiRequest($type),404,$type.' absent row');
        ok($absent['body']['code']==='record_not_found', $type.' no existence disclosure from another farm');
        code(new B12ApiPDO([b12ApiRow($type),b12ApiRow($type)]),b12ApiRequest($type),409,$type.' ambiguous exact row');
        code(new B12ApiPDO([array_replace(b12ApiRow($type),['farm_id'=>'9'])]),b12ApiRequest($type),409,$type.' row wrong farm blocked');
        code(new B12ApiPDO([array_replace(b12ApiRow($type),['b12_tenant_cycle_verified'=>null])]),b12ApiRequest($type),409,$type.' cross-farm linked cycle blocked');
        code(new B12ApiPDO([array_replace(b12ApiRow($type),['record_revision'=>null])]),b12ApiRequest($type),409,$type.' missing loaded revision blocked');
        $legacy=b12ApiRequest($type,'exact','');
        $legacyResponse=code(new B12ApiPDO([b12ApiRow($type,null)]),$legacy,200,$type.' explicit legacy NULL cycle');
        ok($legacyResponse['body']['data']['form_tokens']['original_cycle_id']==='', $type.' legacy is not all-cycle search');
        $absentScope=code(new B12ApiPDO([]),b12ApiRequest($type,'scope',''),200,$type.' absent scope');
        ok($absentScope['body']['data']===['status'=>'absent','record_id'=>null], $type.' no implicit CREATE permission');
        $presentScope=code(new B12ApiPDO([['id'=>'99']]),b12ApiRequest($type,'scope'),200,$type.' unique scope');
        ok($presentScope['body']['data']===['status'=>'unique','record_id'=>99] &&
           !array_key_exists('form_tokens',$presentScope['body']['data']), $type.' scope never returns edit tokens');
        code(new B12ApiPDO([['id'=>'99'],['id'=>'101']]),b12ApiRequest($type,'scope',''),409,$type.' duplicate legacy scope');
        $bad=new B12ApiPDO([b12ApiRow($type)],0);
        $schema=code($bad,b12ApiRequest($type),503,$type.' schema unavailable');
        ok($bad->prepares===0 && $schema['body']['code']==='schema_unavailable', $type.' cannot read without revision schema');
        $GLOBALS['b12ctx']['module']=false;
        $denied=new B12ApiPDO([b12ApiRow($type)]);
        code($denied,b12ApiRequest($type),403,$type.' module denied');
        ok($denied->queries===0, $type.' no schema read before denial');
        resetAuth();
        $GLOBALS['b12ctx']['view']=false;
        code(new B12ApiPDO([]),b12ApiRequest($type,'scope'),403,$type.' view permission required');
        resetAuth();
        $GLOBALS['b12ctx']['edit']=false;
        code(new B12ApiPDO([]),b12ApiRequest($type),403,$type.' edit permission required');
        code(new B12ApiPDO([['id'=>'99']]),b12ApiRequest($type,'scope'),200,$type.' view-only cardinality allowed');
        resetAuth();
        $GLOBALS['b12ctx']['admin']=true;
        $GLOBALS['b12ctx']['view']=false;$GLOBALS['b12ctx']['edit']=false;$GLOBALS['b12ctx']['module']=false;
        code(new B12ApiPDO([b12ApiRow($type)]),b12ApiRequest($type),200,$type.' farm admin retains authorized bypass');
        resetAuth();
    }
    $invalids=[
        ['farm_id'=>'8'], ['farmId'=>'8'], ['mode'=>'edit'], ['mode'=>'CREATE'],
        ['cycle_id'=>'0'], ['cycle_id'=>'01'], ['cycle_id'=>'-1'], ['cycle_id'=>'  '],
        ['cycle_id'=>[]], ['record_date'=>'2026-02-30'], ['record_date'=>false],
        ['record_id'=>'01'], ['record_id'=>''], ['record_id'=>'0'], ['record_id'=>[]],
        ['unexpected'=>'inject'], ['type'=>'unknown'],
    ];
    foreach ($invalids as $case) {
        code(new B12ApiPDO([]),array_replace(b12ApiRequest(),$case),400,'invalid/forged query rejected');
    }
    foreach (['mode','type','cycle_id','record_date','record_id'] as $key) {
        $r=b12ApiRequest();unset($r[$key]);
        code(new B12ApiPDO([]),$r,400,'missing required '.$key);
    }
    $r=b12ApiRequest('layer','scope');$r['record_id']='99';
    code(new B12ApiPDO([]),$r,400,'scope cannot include row id');
    $r=b12ApiRequest();$r['animal_type']='';
    code(new B12ApiPDO([]),$r,400,'poultry cannot forge species scope');
    $r=b12ApiRequest('ruminant');unset($r['animal_type']);
    code(new B12ApiPDO([]),$r,400,'ruminant needs explicit species');
    $r=b12ApiRequest('ruminant');$r['animal_type']='lion';
    code(new B12ApiPDO([]),$r,400,'ruminant invalid species');
    $r=b12ApiRequest();
    $p=new B12ApiPDO([]);
    code($p,$r,405,'POST is not an edit-read mode','POST');
    ok($p->queries===0, 'method rejected before any SQL');
    $_SESSION=[];
    $guest=new B12ApiPDO([]);
    code($guest,$r,401,'guest denied even with valid exact query');
    ok($guest->queries===0, 'guest cannot probe schema');
    resetAuth();$GLOBALS['b12ctx']['farm']=0;
    code(new B12ApiPDO([]),$r,403,'no current farm denied');
    resetAuth();
    $errorPdo=new B12ApiPDO([b12ApiRow()]);$errorPdo->pdoUnavailable=true;
    $failure=code($errorPdo,$r,503,'read database unavailable');
    ok(!str_contains(json_encode($failure['body']),'sensitive SQL'), 'database internals never in error');
    ok(daily_record_revision_api_permissions('layer') === ['poultry','poultry_daily_layer','poultry_daily_layer_edit'], 'central layer matrix preserved');
    ok(daily_record_revision_api_permissions('broiler') === ['poultry','poultry_daily_broiler','poultry_daily_broiler_edit'], 'central broiler matrix preserved');
    ok(daily_record_revision_api_permissions('ruminant') === ['ruminant','ruminant_daily','ruminant_daily_edit'], 'central ruminant matrix preserved');
    echo 'B12_API_ADAPTER_PURE=PASS'.PHP_EOL;
    echo 'PASS_COUNT='.$passes.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'B12_API_ADAPTER_PURE=FAIL '.get_class($error).' '.$error->getMessage().PHP_EOL);
    exit(1);
}
