<?php
/** Farm Platform V2 API helpers. */
if (!function_exists('send_json')) {
    function send_json(array $payload, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}
if (!function_exists('require_http_method')) {
    function require_http_method(string $method): void {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
            send_json(['success'=>false,'error'=>'Method not allowed'],405);
        }
    }
}
if (!function_exists('json_input')) {
    function json_input(): array {
        $raw=file_get_contents('php://input');
        if ($raw===false || $raw==='') return [];
        $decoded=json_decode($raw,true);
        return is_array($decoded)?$decoded:[];
    }
}
if (!function_exists('require_csrf_token')) {
    function require_csrf_token(): void {
        if (!function_exists('csrf_request_is_valid') || !csrf_request_is_valid()) {
            send_json(['success'=>false,'error'=>'Invalid CSRF token'],419);
        }
    }
}
if (!function_exists('log_app_error')) {
    function log_app_error(string $message,array $context=[]): void {
        $root=defined('PROJECT_ROOT')?PROJECT_ROOT:dirname(__DIR__);
        $logDir=$root.'/logs';
        if(!is_dir($logDir)) @mkdir($logDir,0775,true);
        @file_put_contents($logDir.'/app.log',json_encode(['time'=>date('c'),'message'=>$message,'context'=>$context],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX);
    }
}
if (!function_exists('safe_api_exception_message')) {
    function safe_api_exception_message(Throwable $exception, string $fallback = 'The requested action could not be completed.'): string {
        if ($exception instanceof PDOException) return $fallback;
        $message = trim($exception->getMessage());
        return $message !== '' ? $message : $fallback;
    }
}
if (!function_exists('rate_limit_guest_bucket_path')) {
    /**
     * Shared guest limiter bucket. Keeping guest counters outside PHP sessions
     * prevents a fresh session cookie from resetting login/recovery throttles.
     */
    function rate_limit_guest_bucket_path(string $identity): ?string {
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'renee-rate-limit';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return null;
        return $dir . DIRECTORY_SEPARATOR . hash('sha256', $identity) . '.json';
    }
}
if (!function_exists('rate_limit_increment_guest_bucket')) {
    function rate_limit_increment_guest_bucket(string $identity, int $windowSeconds): ?int {
        $path = rate_limit_guest_bucket_path($identity);
        if ($path === null) return null;

        $handle = @fopen($path, 'c+');
        if ($handle === false) return null;

        try {
            if (!flock($handle, LOCK_EX)) return null;
            rewind($handle);
            $raw = stream_get_contents($handle);
            $bucket = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
            $now = time();
            if (!is_array($bucket) || !isset($bucket['count'], $bucket['start']) || ($now - (int)$bucket['start']) >= $windowSeconds) {
                $bucket = ['count' => 0, 'start' => $now];
            }
            $bucket['count'] = (int)$bucket['count'] + 1;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($bucket, JSON_UNESCAPED_SLASHES));
            fflush($handle);
            flock($handle, LOCK_UN);
            return (int)$bucket['count'];
        } finally {
            fclose($handle);
        }
    }
}
if (!function_exists('require_rate_limit')) {
    function require_rate_limit(string $key,int $maxRequests=60,int $windowSeconds=60): void {
        if(session_status()!==PHP_SESSION_ACTIVE) return;

        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $userId = (int)($_SESSION['user_id'] ?? 0);

        // Authenticated requests retain the inexpensive per-session limiter.
        // Guest authentication attempts use a server-side IP bucket so clearing
        // or replacing PHPSESSID does not reset the login/recovery throttle.
        if ($userId > 0) {
            $identity=hash('sha256',$key.'|'.$ip.'|'.$userId);
            $bucketKey='v2_rate_'.$identity;
            $now=time(); $bucket=$_SESSION[$bucketKey]??['count'=>0,'start'=>$now];
            if(!is_array($bucket)||($now-(int)$bucket['start'])>=$windowSeconds) $bucket=['count'=>0,'start'=>$now];
            $bucket['count']++; $_SESSION[$bucketKey]=$bucket;
            $count=(int)$bucket['count'];
        } else {
            $guestIdentity=$key.'|guest|'.$ip;
            $count=rate_limit_increment_guest_bucket($guestIdentity,$windowSeconds);
            if ($count === null) {
                // Availability fallback if the shared temp directory is not writable.
                $identity=hash('sha256',$guestIdentity);
                $bucketKey='v2_rate_'.$identity;
                $now=time(); $bucket=$_SESSION[$bucketKey]??['count'=>0,'start'=>$now];
                if(!is_array($bucket)||($now-(int)$bucket['start'])>=$windowSeconds) $bucket=['count'=>0,'start'=>$now];
                $bucket['count']++; $_SESSION[$bucketKey]=$bucket;
                $count=(int)$bucket['count'];
            }
        }

        if($count>$maxRequests) send_json(['success'=>false,'error'=>'Too many requests. Please try again shortly.'],429);
    }
}
?>
