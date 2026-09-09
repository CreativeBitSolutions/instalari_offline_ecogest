<?php
declare(strict_types=1);

final class DbfCache
{
    private const VERSION = '1';

    private array $config;
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->pdo = new PDO('sqlite:' . $this->databasePath());
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA synchronous=NORMAL');
        $this->pdo->exec('PRAGMA temp_store=MEMORY');
        $this->createSchema();
    }

    public function ensureUsable(): void
    {
        if (!$this->isBuilt()) {
            throw new RuntimeException('Cache-ul SQLite nu este incarcat. Apasa "Reincarca tabele" pentru primul import.');
        }
    }

    public function rebuild(): array
    {
        @set_time_limit(0);

        $started = microtime(true);
        $signature = $this->signature();

        $this->pdo->beginTransaction();
        try {
            $this->dropDataSchema();
            $this->createSchema();
            $this->importNotes();
            $this->importCompnote();
            $this->importBonuri();
            $this->setMeta('cache_version', self::VERSION);
            $this->setMeta('dbf_signature', $signature);
            $this->setMeta('built_at', (string) time());
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $status = $this->status();
        $status['seconds'] = round(microtime(true) - $started, 2);

        return $status;
    }

    public function status(): array
    {
        $builtAt = (int) ($this->getMeta('built_at') ?? 0);

        return [
            'exists' => is_file($this->databasePath()),
            'built' => $builtAt > 0,
            'fresh' => $this->isFresh(),
            'path' => $this->databasePath(),
            'size' => is_file($this->databasePath()) ? (int) filesize($this->databasePath()) : 0,
            'built_at' => $builtAt,
            'notes' => $this->countTable('notes'),
            'compnote' => $this->countTable('compnote_items'),
            'bonuri' => $this->countTable('bonuri_groups'),
            'bon_items' => $this->countTable('bon_items'),
        ];
    }

    public function searchNotesPaginated(string $query, int $page = 1, int $perPage = 30): array
    {
        $this->ensureUsable();

        $where = '';
        $params = [];
        $query = $this->normalizeSearch($query);
        if ($query !== '') {
            $where = 'WHERE search_text LIKE :query';
            $params[':query'] = '%' . $query . '%';
        }

        return $this->sqlPaginate(
            'notes',
            $where,
            $params,
            'record_no DESC',
            $page,
            $perPage,
            static function (array $row): array {
                return [
                    'id' => 'rec-' . (string) $row['record_no'],
                    'nr_nota' => (string) $row['nr_nota'],
                    'contor_not' => (string) $row['contor_not'],
                    'date' => (string) $row['data'],
                    'time' => (string) $row['ora'],
                    'table' => (string) $row['masa'],
                    'waiter' => (string) $row['ospatar'],
                    'total' => (float) $row['total'],
                ];
            }
        );
    }

    public function searchBonuriPaginated(string $query, int $page = 1, int $perPage = 30, bool $includeDeleted = true): array
    {
        $this->ensureUsable();

        $clauses = [];
        $params = [];
        if (!$includeDeleted) {
            $clauses[] = 'deleted = 0';
        }

        $query = $this->normalizeSearch($query);
        if ($query !== '') {
            $clauses[] = 'search_text LIKE :query';
            $params[':query'] = '%' . $query . '%';
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return $this->sqlPaginate(
            'bonuri_groups',
            $where,
            $params,
            'data DESC, ora DESC, record_no DESC',
            $page,
            $perPage,
            static function (array $row): array {
                return [
                    'key' => (string) $row['key'],
                    'nr_bon' => (string) $row['nr_bon'],
                    'contor_bon' => (string) $row['contor_bon'],
                    'contor' => (string) $row['contor'],
                    'nr_nota' => (string) $row['nr_nota'],
                    'contor_not' => (string) $row['contor_not'],
                    'date' => (string) $row['data'],
                    'time' => (string) $row['ora'],
                    'table' => (string) $row['masa'],
                    'waiter' => (string) $row['ospatar'],
                    'total' => (float) $row['total'],
                    'items' => (int) $row['items'],
                    'deleted' => (bool) $row['deleted'],
                    'record' => (int) $row['record_no'],
                ];
            }
        );
    }

    public function getNotaDocument(string $id): ?array
    {
        $this->ensureUsable();

        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $note = null;
        if (str_starts_with($id, 'rec-')) {
            $recordNo = substr($id, 4);
            $note = $this->fetchOne('SELECT * FROM notes WHERE record_no = :id LIMIT 1', [':id' => $recordNo]);
        }

        if ($note === null) {
            $note = $this->fetchOne('SELECT * FROM notes WHERE contor_not = :id ORDER BY record_no ASC LIMIT 1', [':id' => $id]);
        }

        if ($note === null) {
            $note = $this->fetchOne('SELECT * FROM notes WHERE nr_nota = :id ORDER BY record_no ASC LIMIT 1', [':id' => $id]);
        }

        if ($note === null) {
            return null;
        }

        $contor = (string) $note['contor_not'];
        $nrNota = (string) $note['nr_nota'];
        $items = [];

        if ($contor !== '') {
            $rows = $this->fetchAll('SELECT * FROM compnote_items WHERE contor_not = :id ORDER BY record_no ASC', [':id' => $contor]);
        } else {
            $rows = $this->fetchAll('SELECT * FROM compnote_items WHERE nr_nota = :id ORDER BY record_no ASC', [':id' => $nrNota]);
        }

        foreach ($rows as $row) {
            $items[] = $this->itemFromSqlRow($row);
        }

        return [
            'type' => 'nota',
            'restaurant' => (string) ($this->config['restaurant_name'] ?? "TOAN'S"),
            'number' => $nrNota,
            'contor' => $contor,
            'date' => (string) $note['data'],
            'time' => (string) $note['ora'],
            'table' => (string) $note['masa'],
            'waiter' => (string) $note['ospatar'],
            'total' => (float) $note['total'],
            'payments' => $this->paymentsFromNote($note),
            'items' => $items,
        ];
    }

    public function getBonDocument(string $key, bool $includeDeleted = true): ?array
    {
        $this->ensureUsable();

        $key = trim(rawurldecode($key));
        if ($key === '') {
            return null;
        }

        $deletedClause = $includeDeleted ? '' : ' AND deleted = 0';
        if (str_contains($key, '|')) {
            $group = $this->fetchOne('SELECT * FROM bonuri_groups WHERE key = :key' . $deletedClause . ' LIMIT 1', [':key' => $key]);
        } else {
            $group = $this->fetchOne(
                'SELECT * FROM bonuri_groups
                 WHERE (nr_bon = :key OR nr_bon_raw = :key OR contor_bon = :key OR contor_not = :key OR contor = :key)' . $deletedClause . '
                 ORDER BY data DESC, ora DESC, record_no DESC
                 LIMIT 1',
                [':key' => $key]
            );
        }

        if ($group === null) {
            return null;
        }

        $items = [];
        foreach ($this->fetchAll('SELECT * FROM bon_items WHERE key = :key ORDER BY record_no ASC', [':key' => $group['key']]) as $row) {
            $items[] = $this->itemFromSqlRow($row);
        }

        return [
            'type' => 'bon',
            'restaurant' => (string) ($this->config['restaurant_name'] ?? "TOAN'S"),
            'number' => (string) $group['nr_bon'],
            'contor_bon' => (string) $group['contor_bon'],
            'contor' => (string) $group['contor'],
            'note_number' => (string) $group['nr_nota'],
            'contor_note' => (string) $group['contor_not'],
            'date' => (string) $group['data'],
            'time' => (string) $group['ora'],
            'table' => (string) $group['masa'],
            'waiter' => (string) $group['ospatar'],
            'total' => (float) $group['total'],
            'items' => $items,
            'key' => (string) $group['key'],
        ];
    }

    private function sqlPaginate(string $table, string $where, array $params, string $orderBy, int $page, int $perPage, callable $map): array
    {
        $perPage = max(5, min(100, $perPage));

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table $where");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("SELECT * FROM $table $where ORDER BY $orderBy LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $map($row);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($total, $offset + $perPage),
        ];
    }

    private function importNotes(): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notes (
                record_no, nr_nota, contor_not, data, ora, masa, ospatar, total,
                numerar, card, bon_masa, online, bon_val, virament, angajati, search_text
            ) VALUES (
                :record_no, :nr_nota, :contor_not, :data, :ora, :masa, :ospatar, :total,
                :numerar, :card, :bon_masa, :online, :bon_val, :virament, :angajati, :search_text
            )'
        );

        foreach ($this->reader('note_path')->rows(false) as $row) {
            $stmt->execute([
                ':record_no' => (int) ($row['__record'] ?? 0),
                ':nr_nota' => (string) ($row['NR_NOTA'] ?? ''),
                ':contor_not' => (string) ($row['CONTOR_NOT'] ?? ''),
                ':data' => (string) ($row['DATA'] ?? ''),
                ':ora' => (string) ($row['ORA'] ?? ''),
                ':masa' => (string) ($row['MASA'] ?? ''),
                ':ospatar' => (string) ($row['OSPATAR'] ?? ''),
                ':total' => $this->num($row['TOTAL'] ?? 0),
                ':numerar' => $this->num($row['NUMERAR'] ?? 0),
                ':card' => $this->num($row['CARD'] ?? 0),
                ':bon_masa' => $this->num($row['BON_MASA'] ?? 0),
                ':online' => $this->num($row['ONLINE'] ?? 0),
                ':bon_val' => $this->num($row['BON_VAL'] ?? 0),
                ':virament' => $this->num($row['VIRAMENT'] ?? 0),
                ':angajati' => $this->num($row['ANGAJATI'] ?? 0),
                ':search_text' => $this->searchText($row, ['NR_NOTA', 'CONTOR_NOT', 'MASA', 'OSPATAR', 'DATA', 'ORA']),
            ]);
        }
    }

    private function importCompnote(): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO compnote_items (
                record_no, nr_nota, contor_not, produs, cant, pret, valoare, obs
            ) VALUES (
                :record_no, :nr_nota, :contor_not, :produs, :cant, :pret, :valoare, :obs
            )'
        );

        foreach ($this->reader('compnote_path')->rows(false) as $row) {
            $stmt->execute([
                ':record_no' => (int) ($row['__record'] ?? 0),
                ':nr_nota' => (string) ($row['NR_NOTA'] ?? ''),
                ':contor_not' => (string) ($row['CONTOR_NOT'] ?? ''),
                ':produs' => (string) ($row['PRODUS'] ?? ''),
                ':cant' => $this->num($row['CANT'] ?? 0),
                ':pret' => $this->num($row['PRET'] ?? 0),
                ':valoare' => $this->num($row['VALOARE'] ?? 0),
                ':obs' => (string) ($row['OBS'] ?? ''),
            ]);
        }
    }

    private function importBonuri(): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bon_items (
                key, record_no, deleted, nr_bon_raw, contor_bon, contor, nr_nota, contor_not,
                data, ora, masa, ospatar, produs, cant, pret, valoare, obs, search_text
            ) VALUES (
                :key, :record_no, :deleted, :nr_bon_raw, :contor_bon, :contor, :nr_nota, :contor_not,
                :data, :ora, :masa, :ospatar, :produs, :cant, :pret, :valoare, :obs, :search_text
            )'
        );

        foreach ($this->reader('bonuri_path')->rows(true) as $row) {
            $stmt->execute([
                ':key' => $this->bonKey($row),
                ':record_no' => (int) ($row['__record'] ?? 0),
                ':deleted' => !empty($row['__deleted']) ? 1 : 0,
                ':nr_bon_raw' => (string) ($row['NR_BON'] ?? ''),
                ':contor_bon' => (string) ($row['CONTOR_BON'] ?? ''),
                ':contor' => (string) ($row['CONTOR'] ?? ''),
                ':nr_nota' => (string) ($row['NR_NOTA'] ?? ''),
                ':contor_not' => (string) ($row['CONTOR_NOT'] ?? ''),
                ':data' => (string) ($row['DATA'] ?? ''),
                ':ora' => (string) ($row['ORA'] ?? ''),
                ':masa' => (string) ($row['MASA'] ?? ''),
                ':ospatar' => (string) ($row['OSPATAR'] ?? ''),
                ':produs' => (string) ($row['PRODUS'] ?? ''),
                ':cant' => $this->num($row['CANT'] ?? 0),
                ':pret' => $this->num($row['PRET'] ?? 0),
                ':valoare' => $this->num($row['VALOARE'] ?? 0),
                ':obs' => (string) ($row['OBS'] ?? ''),
                ':search_text' => $this->searchText($row, ['NR_BON', 'CONTOR_BON', 'CONTOR', 'NR_NOTA', 'CONTOR_NOT', 'MASA', 'OSPATAR', 'DATA', 'ORA', 'PRODUS', 'OBS']),
            ]);
        }

        $this->pdo->exec(
            'CREATE TEMP TABLE first_bon AS
             SELECT i.*
             FROM bon_items i
             INNER JOIN (
                SELECT key, MIN(id) AS first_id
                FROM bon_items
                GROUP BY key
             ) f ON f.first_id = i.id'
        );

        $this->pdo->exec(
            'INSERT INTO bonuri_groups (
                key, nr_bon, nr_bon_raw, contor_bon, contor, nr_nota, contor_not,
                data, ora, masa, ospatar, total, items, deleted, record_no, search_text
             )
             SELECT
                f.key,
                CASE WHEN f.contor_bon <> "" THEN f.contor_bon ELSE f.nr_bon_raw END AS nr_bon,
                f.nr_bon_raw,
                f.contor_bon,
                f.contor,
                f.nr_nota,
                f.contor_not,
                f.data,
                f.ora,
                f.masa,
                f.ospatar,
                SUM(i.valoare) AS total,
                COUNT(i.id) AS items,
                MIN(i.deleted) AS deleted,
                MAX(i.record_no) AS record_no,
                f.search_text || " " || COALESCE(GROUP_CONCAT(i.search_text, " "), "") AS search_text
             FROM first_bon f
             INNER JOIN bon_items i ON i.key = f.key
             GROUP BY f.key'
        );

        $this->pdo->exec('DROP TABLE first_bon');
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS meta (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                record_no INTEGER NOT NULL,
                nr_nota TEXT NOT NULL DEFAULT "",
                contor_not TEXT NOT NULL DEFAULT "",
                data TEXT NOT NULL DEFAULT "",
                ora TEXT NOT NULL DEFAULT "",
                masa TEXT NOT NULL DEFAULT "",
                ospatar TEXT NOT NULL DEFAULT "",
                total REAL NOT NULL DEFAULT 0,
                numerar REAL NOT NULL DEFAULT 0,
                card REAL NOT NULL DEFAULT 0,
                bon_masa REAL NOT NULL DEFAULT 0,
                online REAL NOT NULL DEFAULT 0,
                bon_val REAL NOT NULL DEFAULT 0,
                virament REAL NOT NULL DEFAULT 0,
                angajati REAL NOT NULL DEFAULT 0,
                search_text TEXT NOT NULL DEFAULT ""
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS compnote_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                record_no INTEGER NOT NULL,
                nr_nota TEXT NOT NULL DEFAULT "",
                contor_not TEXT NOT NULL DEFAULT "",
                produs TEXT NOT NULL DEFAULT "",
                cant REAL NOT NULL DEFAULT 0,
                pret REAL NOT NULL DEFAULT 0,
                valoare REAL NOT NULL DEFAULT 0,
                obs TEXT NOT NULL DEFAULT ""
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS bon_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT NOT NULL,
                record_no INTEGER NOT NULL,
                deleted INTEGER NOT NULL DEFAULT 0,
                nr_bon_raw TEXT NOT NULL DEFAULT "",
                contor_bon TEXT NOT NULL DEFAULT "",
                contor TEXT NOT NULL DEFAULT "",
                nr_nota TEXT NOT NULL DEFAULT "",
                contor_not TEXT NOT NULL DEFAULT "",
                data TEXT NOT NULL DEFAULT "",
                ora TEXT NOT NULL DEFAULT "",
                masa TEXT NOT NULL DEFAULT "",
                ospatar TEXT NOT NULL DEFAULT "",
                produs TEXT NOT NULL DEFAULT "",
                cant REAL NOT NULL DEFAULT 0,
                pret REAL NOT NULL DEFAULT 0,
                valoare REAL NOT NULL DEFAULT 0,
                obs TEXT NOT NULL DEFAULT "",
                search_text TEXT NOT NULL DEFAULT ""
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS bonuri_groups (
                key TEXT PRIMARY KEY,
                nr_bon TEXT NOT NULL DEFAULT "",
                nr_bon_raw TEXT NOT NULL DEFAULT "",
                contor_bon TEXT NOT NULL DEFAULT "",
                contor TEXT NOT NULL DEFAULT "",
                nr_nota TEXT NOT NULL DEFAULT "",
                contor_not TEXT NOT NULL DEFAULT "",
                data TEXT NOT NULL DEFAULT "",
                ora TEXT NOT NULL DEFAULT "",
                masa TEXT NOT NULL DEFAULT "",
                ospatar TEXT NOT NULL DEFAULT "",
                total REAL NOT NULL DEFAULT 0,
                items INTEGER NOT NULL DEFAULT 0,
                deleted INTEGER NOT NULL DEFAULT 0,
                record_no INTEGER NOT NULL DEFAULT 0,
                search_text TEXT NOT NULL DEFAULT ""
            )'
        );

        $this->createIndexes();
    }

    private function createIndexes(): void
    {
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_notes_record ON notes(record_no DESC)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_notes_contor ON notes(contor_not)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_notes_nr ON notes(nr_nota)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_compnote_contor ON compnote_items(contor_not, record_no)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_compnote_nr ON compnote_items(nr_nota, record_no)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_bon_items_key ON bon_items(key, record_no)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_bon_groups_order ON bonuri_groups(data DESC, ora DESC, record_no DESC)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_bon_groups_numbers ON bonuri_groups(nr_bon, nr_bon_raw, contor_bon, contor_not, contor)');
    }

    private function dropDataSchema(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS notes');
        $this->pdo->exec('DROP TABLE IF EXISTS compnote_items');
        $this->pdo->exec('DROP TABLE IF EXISTS bon_items');
        $this->pdo->exec('DROP TABLE IF EXISTS bonuri_groups');
        $this->pdo->exec('DROP TABLE IF EXISTS meta');
    }

    private function databasePath(): string
    {
        $storage = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($storage)) {
            mkdir($storage, 0777, true);
        }

        return $storage . DIRECTORY_SEPARATOR . 'relistare.sqlite';
    }

    private function isFresh(): bool
    {
        return $this->getMeta('cache_version') === self::VERSION
            && $this->getMeta('dbf_signature') === $this->signature();
    }

    private function isBuilt(): bool
    {
        return (int) ($this->getMeta('built_at') ?? 0) > 0
            && $this->getMeta('cache_version') === self::VERSION;
    }

    private function signature(): string
    {
        $state = [
            'encoding' => (string) ($this->config['dbf_encoding'] ?? 'CP1250'),
            'files' => [],
        ];

        foreach (['note_path', 'compnote_path', 'bonuri_path'] as $key) {
            $path = (string) ($this->config[$key] ?? '');
            $effectivePath = $path;
            if ((!is_file($effectivePath) || !is_readable($effectivePath)) && class_exists('DbfReader')) {
                $localMirror = DbfReader::localMirrorPath($path);
                if (is_file($localMirror) && is_readable($localMirror)) {
                    $effectivePath = $localMirror;
                }
            }
            $state['files'][$key] = [
                'path' => $path,
                'effective_path' => $effectivePath,
                'size' => is_file($effectivePath) ? (int) filesize($effectivePath) : -1,
                'mtime' => is_file($effectivePath) ? (int) filemtime($effectivePath) : -1,
            ];
        }

        return hash('sha256', json_encode($state, JSON_UNESCAPED_SLASHES));
    }

    private function getMeta(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM meta WHERE key = :key');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    private function setMeta(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO meta(key, value) VALUES(:key, :value) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
        $stmt->execute([':key' => $key, ':value' => $value]);
    }

    private function countTable(string $table): int
    {
        try {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function reader(string $configKey): DbfReader
    {
        return new DbfReader((string) $this->config[$configKey], (string) ($this->config['dbf_encoding'] ?? 'CP1250'));
    }

    private function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function bonKey(array $row): string
    {
        return implode('|', [
            (string) ($row['NR_BON'] ?? ''),
            (string) ($row['CONTOR_BON'] ?? ''),
            (string) ($row['CONTOR_NOT'] ?? ''),
            (string) ($row['DATA'] ?? ''),
        ]);
    }

    private function itemFromSqlRow(array $row): array
    {
        return [
            'name' => (string) ($row['produs'] ?? ''),
            'quantity' => (float) ($row['cant'] ?? 0),
            'price' => (float) ($row['pret'] ?? 0),
            'value' => (float) ($row['valoare'] ?? 0),
            'obs' => (string) ($row['obs'] ?? ''),
        ];
    }

    private function paymentsFromNote(array $note): array
    {
        $map = [
            'numerar' => 'Numerar',
            'card' => 'Card',
            'bon_masa' => 'Bon masa',
            'online' => 'Online',
            'bon_val' => 'Bon valoric',
            'virament' => 'Virament',
            'angajati' => 'Angajati',
        ];

        $payments = [];
        foreach ($map as $field => $label) {
            $amount = (float) ($note[$field] ?? 0);
            if ($amount > 0) {
                $payments[] = ['label' => $label, 'amount' => $amount];
            }
        }

        if (!$payments && (float) ($note['total'] ?? 0) > 0) {
            $payments[] = ['label' => 'Total', 'amount' => (float) $note['total']];
        }

        return $payments;
    }

    private function searchText(array $row, array $fields): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $parts[] = (string) ($row[$field] ?? '');
        }

        return $this->normalizeSearch(implode(' ', $parts));
    }

    private function normalizeSearch(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if ($value === '') {
            return '';
        }

        return mb_strtoupper($value, 'UTF-8');
    }

    private function num(mixed $value): float
    {
        $text = str_replace(',', '.', trim((string) $value));
        return is_numeric($text) ? (float) $text : 0.0;
    }
}
