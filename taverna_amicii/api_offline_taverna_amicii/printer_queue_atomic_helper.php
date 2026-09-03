<?php
declare(strict_types=1);

function agecs_printer_queue_lock(string $queuePath)
{
    $handle = fopen($queuePath . '.lock', 'c+b');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('Coada imprimantei nu a putut fi blocată pentru actualizare.');
    }
    return $handle;
}

function agecs_printer_queue_unlock($handle): void
{
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function agecs_printer_queue_token(): string
{
    try {
        return bin2hex(random_bytes(6));
    } catch (Throwable $error) {
        return str_replace('.', '', uniqid('', true));
    }
}

function agecs_printer_queue_append_documents(string $queuePath, array $documents, string $message): bool
{
    if ($documents === []) {
        return true;
    }

    $directory = dirname($queuePath);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Directorul cozii imprimantei nu a putut fi creat.');
    }

    $lock = agecs_printer_queue_lock($queuePath);
    $temporaryPath = $queuePath . '.tmp.' . getmypid() . '.' . agecs_printer_queue_token();
    $backupPath = '';

    try {
        $existingDocuments = [];
        if (is_file($queuePath)) {
            $raw = file_get_contents($queuePath);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
                throw new RuntimeException('Documentul existent din coada imprimantei nu este valid.');
            }
            $existingDocuments = $decoded['data'];
        }

        $payload = [
            'status' => 'success',
            'message' => $message,
            'data' => array_values(array_merge($existingDocuments, $documents)),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Fișierul temporar al cozii nu a putut fi scris.');
        }

        if (@rename($temporaryPath, $queuePath)) {
            return true;
        }

        if (is_file($queuePath)) {
            $backupPath = $queuePath . '.replace.' . agecs_printer_queue_token();
            if (!@rename($queuePath, $backupPath)) {
                throw new RuntimeException('Documentul existent nu a putut fi pregătit pentru combinare.');
            }
        }

        if (!@rename($temporaryPath, $queuePath)) {
            if ($backupPath !== '' && is_file($backupPath) && !is_file($queuePath)) {
                @rename($backupPath, $queuePath);
            }
            throw new RuntimeException('Coada combinată nu a putut fi publicată.');
        }

        if ($backupPath !== '' && is_file($backupPath)) {
            @unlink($backupPath);
        }
        return true;
    } finally {
        if (is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }
        agecs_printer_queue_unlock($lock);
    }
}

function agecs_printer_queue_claim(string $queuePath): array
{
    if (!is_file($queuePath)) {
        return ['status' => 'empty', 'path' => ''];
    }

    $lock = agecs_printer_queue_lock($queuePath);
    try {
        if (!is_file($queuePath)) {
            return ['status' => 'empty', 'path' => ''];
        }

        $claimedPath = $queuePath . '.processing.' . getmypid() . '.' . agecs_printer_queue_token();
        if (!@rename($queuePath, $claimedPath)) {
            return ['status' => 'error', 'path' => ''];
        }

        return ['status' => 'claimed', 'path' => $claimedPath];
    } finally {
        agecs_printer_queue_unlock($lock);
    }
}

function agecs_printer_queue_restore_claim(string $claimedPath, string $queuePath): void
{
    if (!is_file($claimedPath)) {
        return;
    }

    $lock = agecs_printer_queue_lock($queuePath);
    try {
        if (!is_file($queuePath)) {
            @rename($claimedPath, $queuePath);
        }
    } finally {
        agecs_printer_queue_unlock($lock);
    }
}
