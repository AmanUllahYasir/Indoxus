<?php
/**
 * Simple file-based cache helper
 * Usage:
 *  - cache_set($key, $value, $ttl_seconds)
 *  - $value = cache_get($key)
 *  - cache_delete($key)
 *  - cache_delete_prefix($prefix)
 */

$INDOXUS_CACHE_DIR = __DIR__ . '/../cache';
if (!is_dir($INDOXUS_CACHE_DIR)) {
    @mkdir($INDOXUS_CACHE_DIR, 0755, true);
}

function _cache_filename($key) {
    global $INDOXUS_CACHE_DIR;
    $safe = preg_replace('/[^a-z0-9_\-]/i', '_', $key);
    return $INDOXUS_CACHE_DIR . DIRECTORY_SEPARATOR . $safe . '_' . md5($key) . '.cache';
}

function cache_set($key, $value, $ttl = 10) {
    $file = _cache_filename($key);
    $payload = json_encode([
        'expires' => time() + (int)$ttl,
        'data' => $value
    ]);
    @file_put_contents($file, $payload, LOCK_EX);
}

function cache_get($key) {
    $file = _cache_filename($key);
    if (!file_exists($file)) return null;
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    $obj = json_decode($raw, true);
    if (!is_array($obj) || !isset($obj['expires'])) return null;
    if ($obj['expires'] < time()) {
        @unlink($file);
        return null;
    }
    return $obj['data'];
}

function cache_delete($key) {
    $file = _cache_filename($key);
    if (file_exists($file)) @unlink($file);
}

function cache_delete_prefix($prefix) {
    global $INDOXUS_CACHE_DIR;
    $files = glob($INDOXUS_CACHE_DIR . DIRECTORY_SEPARATOR . preg_replace('/[^a-z0-9_\-]/i', '_', $prefix) . "*" . '_*.cache');
    if (!is_array($files)) return;
    foreach ($files as $f) {
        @unlink($f);
    }
}

?>