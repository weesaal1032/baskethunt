<?php
function current_commit() {
    if (!is_dir(__DIR__.'/../.git')) { return null; }
    return trim(shell_exec('git rev-parse HEAD 2>&1'));
}

function remote_commit($repo) {
    if (!$repo) { return null; }
    $output = shell_exec('git ls-remote '.escapeshellarg($repo).' HEAD 2>&1');
    if (!$output) { return null; }
    return trim(explode("\t", $output)[0]);
}

function update_check($repo) {
    if (!$repo) { return ['error' => 'Repository URL not configured']; }
    $local = current_commit();
    $remote = remote_commit($repo);
    if (!$remote) { return ['error' => 'Unable to determine versions']; }
    return ['local' => $local ?: 'n/a', 'remote' => $remote, 'up_to_date' => $local === $remote];
}

function ensure_repo($repo) {
    if (!is_dir(__DIR__.'/../.git')) {
        shell_exec('git init 2>&1');
        shell_exec('git remote add origin '.escapeshellarg($repo).' 2>&1');
    } else {
        shell_exec('git remote set-url origin '.escapeshellarg($repo).' 2>&1');
    }
}

function apply_update($repo) {
    ensure_repo($repo);
    $current = current_commit();
    file_put_contents(__DIR__.'/../last_commit', $current);
    shell_exec('git fetch origin && git reset --hard origin/main && git clean -df 2>&1');
    return current_commit();
}

function rollback_update() {
    $last = @file_get_contents(__DIR__.'/../last_commit');
    if (!$last) { return false; }
    shell_exec('git reset --hard '.escapeshellarg(trim($last)).' 2>&1');
    return true;
}
?>
