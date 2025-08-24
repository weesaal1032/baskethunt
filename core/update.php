<?php
function current_commit() {
    return trim(shell_exec('git rev-parse HEAD 2>&1'));
}

function remote_commit() {
    $output = shell_exec('git ls-remote origin HEAD 2>&1');
    if (!$output) { return null; }
    return trim(explode("\t", $output)[0]);
}

function update_check() {
    $local = current_commit();
    $remote = remote_commit();
    if (!$local || !$remote) { return ['error' => 'Unable to determine versions']; }
    return ['local' => $local, 'remote' => $remote, 'up_to_date' => $local === $remote];
}

function apply_update() {
    $current = current_commit();
    file_put_contents(__DIR__.'/../last_commit', $current);
    shell_exec('git fetch origin && git reset --hard origin/main 2>&1');
    return current_commit();
}

function rollback_update() {
    $last = @file_get_contents(__DIR__.'/../last_commit');
    if (!$last) { return false; }
    shell_exec('git reset --hard '.escapeshellarg(trim($last)).' 2>&1');
    return true;
}
?>
