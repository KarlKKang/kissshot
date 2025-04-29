<?php

$patches = [
    'flash_backup' => '/usr/local/emhttp/plugins/dynamix/scripts/flash_backup',
];

function install(string $patch_file, string $destination): void
{
    echo 'Installing patch for: ' . $destination . PHP_EOL;
    $backup_file = $destination . '.old';
    $patch_file = __DIR__ . '/' . $patch_file;
    if (!is_file($destination)) {
        echo 'Destination file does not exist: ' . $destination . PHP_EOL;
        return;
    }
    if (!is_file($patch_file)) {
        echo 'Patch file does not exist: ' . $patch_file . PHP_EOL;
        return;
    }
    if (is_file($backup_file)) {
        echo 'Backup file already exists, skipping backup: ' . $backup_file . PHP_EOL;
    } else {
        if (copy($destination, $backup_file)) {
            echo 'Backup file created: ' . $backup_file . PHP_EOL;
        } else {
            echo 'Cannot create backup file: ' . $backup_file . PHP_EOL;
            return;
        }
    }
    if (!copy($patch_file, $destination)) {
        echo 'Cannot copy patch file to destination: ' . $destination . PHP_EOL;
        return;
    }
    unlink($patch_file);
    echo 'Patch installed successfully: ' . $destination . PHP_EOL;
}

function uninstall(string $destination): void
{
    echo 'Uninstalling patch for: ' . $destination . PHP_EOL;
    $backup_file = $destination . '.old';
    if (!is_file($backup_file)) {
        echo 'Backup file does not exist: ' . $backup_file . PHP_EOL;
        return;
    }
    if (!copy($backup_file, $destination)) {
        echo 'Cannot restore backup file to destination: ' . $destination . PHP_EOL;
        return;
    }
    unlink($backup_file);
    echo 'Patch uninstalled successfully: ' . $destination . PHP_EOL;
}

function main(array $argv, array $patches): void
{
    if (count($argv) !== 2) {
        echo 'Invalid number of arguments' . PHP_EOL;
        exit(1);
    }
    $action = $argv[1];
    if ($action === 'install') {
        foreach ($patches as $patch_file => $destination) {
            install($patch_file, $destination);
        }
    } elseif ($action === 'uninstall') {
        foreach ($patches as $destination) {
            uninstall($destination);
        }
    } else {
        echo 'Invalid action: ' . $action . PHP_EOL;
        exit(1);
    }
}

main($argv, $patches);
