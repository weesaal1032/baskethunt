<?php
function repo_root() {
    return realpath(__DIR__.'/..');
}

function current_commit() {
    $root = repo_root();
    if (!is_dir($root.'/.git')) { return null; }
    $hash = trim(shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD 2>/dev/null'));
    return preg_match('/^[0-9a-f]{40}$/', $hash) ? $hash : null;
}

function remote_commit($repo) {
    if (!$repo) { return null; }
    $output = trim(shell_exec('git ls-remote '.escapeshellarg($repo).' HEAD 2>/dev/null'));
    $hash = explode("\t", $output)[0] ?? '';
    return preg_match('/^[0-9a-f]{40}$/', $hash) ? $hash : null;
}

function update_check($repo) {
    if (!$repo) { return ['error' => 'Repository URL not configured']; }
    $local = current_commit();
    $remote = remote_commit($repo);
    if (!$remote) { return ['error' => 'Unable to determine versions']; }
    return ['local' => $local ?: 'n/a', 'remote' => $remote, 'up_to_date' => $local === $remote];
}

function ensure_repo($repo) {
    $root = repo_root();
    if (!is_dir($root.'/.git')) {
        shell_exec('git -C '.escapeshellarg($root).' init 2>/dev/null');
    }
    shell_exec('git -C '.escapeshellarg($root).' remote remove origin 2>/dev/null');
    shell_exec('git -C '.escapeshellarg($root).' remote add origin '.escapeshellarg($repo).' 2>/dev/null');
}

function apply_update($repo) {
    $root = repo_root();
    ensure_repo($repo);
    $current = current_commit();
    if ($current) {
        file_put_contents($root.'/last_commit', $current);
    }
    shell_exec('git -C '.escapeshellarg($root).' fetch origin main 2>/dev/null');
    $remote = trim(shell_exec('git -C '.escapeshellarg($root).' rev-parse --verify origin/main 2>/dev/null'));
    if (!preg_match('/^[0-9a-f]{40}$/', $remote)) {
        return false; // abort if remote branch missing
    }
    shell_exec('git -C '.escapeshellarg($root).' checkout -f -B main '.escapeshellarg($remote).' 2>/dev/null');
    shell_exec('git -C '.escapeshellarg($root).' reset --hard '.escapeshellarg($remote).' 2>/dev/null');
    return current_commit();
}

function rollback_update() {
    $root = repo_root();
    $last = @file_get_contents($root.'/last_commit');
    if (!$last) { return false; }
    shell_exec('git -C '.escapeshellarg($root).' reset --hard '.escapeshellarg(trim($last)).' 2>/dev/null');
    return true;
}
?>
