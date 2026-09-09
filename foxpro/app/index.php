<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = Config::load();
$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_config') {
    $config = Config::saveFromPost($_POST);
    $notice = 'Configuratia a fost salvata.';
}

$cache = new DbfCache($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rebuild_cache') {
    try {
        $rebuilt = $cache->rebuild();
        $notice = 'Tabelele au fost reincarcate in cache in ' . app_money($rebuilt['seconds']) . ' secunde.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$document = (string) ($_GET['document'] ?? 'nota');
if (!in_array($document, ['nota', 'bon'], true)) {
    $document = 'nota';
}

$query = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 30);
if (!in_array($perPage, [15, 30, 50, 100], true)) {
    $perPage = 30;
}

$repo = new RelistareRepository($config, $cache);
$results = [];
$pagination = [
    'items' => [],
    'total' => 0,
    'page' => 1,
    'per_page' => $perPage,
    'pages' => 1,
    'from' => 0,
    'to' => 0,
];

try {
    $pagination = $document === 'nota'
        ? $repo->searchNotesPaginated($query, $page, $perPage)
        : $repo->searchBonuriPaginated($query, $page, $perPage);
    $results = $pagination['items'];
} catch (Throwable $exception) {
    if ($error === null) {
        $error = $exception->getMessage();
    }
}

$pathStatus = Config::pathStatus($config);
$cacheStatus = $cache->status();
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relistare Local</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<main class="app-shell">
    <header class="topbar">
        <div>
            <p class="eyebrow">XAMPP / PHP</p>
            <h1>Relistare Local</h1>
        </div>
        <div class="status-strip" aria-label="Status tabele">
            <?php foreach ($pathStatus as $status): ?>
                <span class="status-pill <?= $status['available'] ? 'is-ok' : 'is-bad' ?>">
                    <?= h($status['label']) ?>
                </span>
            <?php endforeach; ?>
            <span class="status-pill <?= $cacheStatus['built'] && $cacheStatus['fresh'] ? 'is-ok' : 'is-bad' ?>">
                Cache SQLite
            </span>
        </div>
</header>

    <?php $activeNav = 'documents'; require __DIR__ . '/partials/navigation.php'; ?>

    <?php if ($notice): ?>
        <div class="notice"><?= h($notice) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice is-error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($cacheStatus['built'] && !$cacheStatus['fresh']): ?>
        <div class="notice is-warning">DBF-urile par modificate fata de ultimul cache. Aplicatia foloseste in continuare cache-ul vechi pana apesi manual Reincarca tabele.</div>
    <?php endif; ?>

    <section class="workspace">
        <aside class="config-panel">
            <div class="section-title">
                <span>Config</span>
                <strong>Path-uri DBF</strong>
            </div>

            <form method="post" class="config-form">
                <input type="hidden" name="action" value="save_config">

                <label>
                    <span>Nume local</span>
                    <input type="text" name="restaurant_name" value="<?= h($config['restaurant_name'] ?? '') ?>">
                </label>

                <label>
                    <span>note.dbf</span>
                    <div class="path-input-row">
                        <input type="text" name="note_path" value="<?= h($config['note_path'] ?? '') ?>">
                        <button type="button" class="ghost-button picker-button" data-target="note_path">Alege</button>
                    </div>
                </label>

                <label>
                    <span>COMPNOTE.DBF</span>
                    <div class="path-input-row">
                        <input type="text" name="compnote_path" value="<?= h($config['compnote_path'] ?? '') ?>">
                        <button type="button" class="ghost-button picker-button" data-target="compnote_path">Alege</button>
                    </div>
                </label>

                <label>
                    <span>temp_bonuri.dbf</span>
                    <div class="path-input-row">
                        <input type="text" name="bonuri_path" value="<?= h($config['bonuri_path'] ?? '') ?>">
                        <button type="button" class="ghost-button picker-button" data-target="bonuri_path">Alege</button>
                    </div>
                </label>

                <label>
                    <span>totaluri.dbf</span>
                    <div class="path-input-row">
                        <input type="text" name="totaluri_path" value="<?= h($config['totaluri_path'] ?? '') ?>">
                        <button type="button" class="ghost-button picker-button" data-target="totaluri_path">Alege</button>
                    </div>
                </label>

                <label>
                    <span>comp_total.dbf</span>
                    <div class="path-input-row">
                        <input type="text" name="comp_total_path" value="<?= h($config['comp_total_path'] ?? '') ?>">
                        <button type="button" class="ghost-button picker-button" data-target="comp_total_path">Alege</button>
                    </div>
                </label>

                <label>
                    <span>disponibilitati.dbf</span>
                    <div class="path-input-row">
                        <input type="text" name="disponibilitati_path" value="<?= h($config['disponibilitati_path'] ?? '') ?>">
                        <button type="button" class="ghost-button picker-button" data-target="disponibilitati_path">Alege</button>
                    </div>
                </label>

                <label>
                    <span>prod_mat.dbf</span>
                    <div class="path-input-row">
                        <input type="text" name="prod_mat_path" value="<?= h($config['prod_mat_path'] ?? '') ?>">
                        <button type="button" class="ghost-button picker-button" data-target="prod_mat_path">Alege</button>
                    </div>
                </label>

                <label>
                    <span>Encoding DBF</span>
                    <input type="text" name="dbf_encoding" value="<?= h($config['dbf_encoding'] ?? 'CP1250') ?>">
                </label>

                <label class="check-row">
                    <input type="checkbox" name="include_deleted_bonuri" value="1" <?= !empty($config['include_deleted_bonuri']) ? 'checked' : '' ?>>
                    <span>Include bonuri arhivate</span>
                </label>

                <button type="submit" class="primary-button">Salveaza path-uri</button>
            </form>

            <dl class="path-list">
                <?php foreach ($pathStatus as $status): ?>
                    <div>
                        <dt><?= h($status['label']) ?></dt>
                        <dd class="<?= $status['available'] ? 'ok' : 'bad' ?>">
                            <?= $status['available'] ? h(number_format((float) $status['size'] / 1024 / 1024, 2)) . ' MB, ' . h($status['message']) : h($status['message']) ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <div class="cache-box <?= !$cacheStatus['built'] || !$cacheStatus['fresh'] ? 'needs-refresh' : '' ?>">
                <div class="section-title compact">
                    <span>SQLite</span>
                    <strong>Cache local</strong>
                </div>
                <dl class="path-list">
                    <div>
                        <dt>Status</dt>
                        <dd class="<?= $cacheStatus['built'] && $cacheStatus['fresh'] ? 'ok' : 'bad' ?>">
                            <?php if (!$cacheStatus['built']): ?>
                                Neincarcat
                            <?php elseif (!$cacheStatus['fresh']): ?>
                                Tabele schimbate
                            <?php else: ?>
                                Actualizat
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Ultima incarcare</dt>
                        <dd><?= $cacheStatus['built_at'] ? h(date('d.m.Y H:i:s', (int) $cacheStatus['built_at'])) : 'Niciodata' ?></dd>
                    </div>
                    <div>
                        <dt>Note / bonuri</dt>
                        <dd><?= h($cacheStatus['notes']) ?> / <?= h($cacheStatus['bonuri']) ?></dd>
                    </div>
                </dl>
                <form method="post" class="cache-form">
                    <input type="hidden" name="action" value="rebuild_cache">
                    <button type="submit" class="primary-button">Reincarca tabele</button>
                </form>
            </div>
        </aside>

        <section class="list-panel">
            <nav class="tabs" aria-label="Tip listare">
                <a class="<?= $document === 'nota' ? 'active' : '' ?>" href="<?= h(app_base_url('index.php', ['document' => 'nota'])) ?>">Nota de plata</a>
                <a class="<?= $document === 'bon' ? 'active' : '' ?>" href="<?= h(app_base_url('index.php', ['document' => 'bon'])) ?>">Bon comanda</a>
            </nav>

            <form method="get" class="search-form">
                <input type="hidden" name="document" value="<?= h($document) ?>">
                <div class="search-field">
                    <label for="search-query"><span><?= $document === 'nota' ? 'Cauta dupa nr. nota, contor, masa sau ospatar' : 'Cauta dupa nr. bon, nr. nota, contor, produs sau masa' ?></span></label>
                    <div class="search-input-row">
                        <input id="search-query" type="search" name="q" value="<?= h($query) ?>" autofocus autocomplete="off" autocapitalize="characters" spellcheck="false" inputmode="none" data-touch-keyboard-input>
                        <button type="button" class="keyboard-toggle" data-keyboard-open aria-label="Deschide tastatura pentru cautare" title="Tastatura">
                            <svg class="keyboard-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                                <path d="M6 8h.01"></path>
                                <path d="M10 8h.01"></path>
                                <path d="M14 8h.01"></path>
                                <path d="M18 8h.01"></path>
                                <path d="M8 12h.01"></path>
                                <path d="M12 12h.01"></path>
                                <path d="M16 12h.01"></path>
                                <path d="M7 16h10"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <label>
                    <span>Pe pagina</span>
                    <select name="per_page">
                        <?php foreach ([15, 30, 50, 100] as $option): ?>
                            <option value="<?= h($option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="primary-button">Cauta</button>
                <a class="ghost-button" href="<?= h(app_base_url('index.php', ['document' => $document])) ?>">Curata</a>
                <div class="touch-keyboard-modal" data-touch-keyboard-modal hidden>
                    <div class="touch-keyboard-backdrop" data-keyboard-close></div>
                    <section class="touch-keyboard-dialog" role="dialog" aria-modal="true" aria-label="Tastatura pentru cautare">
                        <div class="touch-keyboard-header">
                            <strong class="touch-keyboard-preview" data-keyboard-preview aria-live="polite"><?= h($query) ?></strong>
                            <button type="button" class="touch-keyboard-close" data-keyboard-close aria-label="Inchide tastatura">X</button>
                        </div>
                        <div class="touch-keyboard" data-touch-keyboard aria-label="Tastatura pentru cautare">
                            <?php foreach ([
                                ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
                                ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P'],
                                ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L'],
                                ['Z', 'X', 'C', 'V', 'B', 'N', 'M'],
                                ['.', '-', '/', ',', 'A', 'A', 'I', 'S', 'T'],
                            ] as $keyboardRow): ?>
                                <div class="touch-keyboard-row">
                                    <?php foreach ($keyboardRow as $key): ?>
                                        <button type="button" class="touch-key" data-key="<?= h($key) ?>"><?= h($key) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="touch-keyboard-actions">
                                <button type="button" class="touch-key is-wide" data-action="space">Spatiu</button>
                                <button type="button" class="touch-key" data-action="backspace">Sterge</button>
                                <button type="button" class="touch-key" data-action="clear">Sterge tot</button>
                                <button type="button" class="touch-key is-submit" data-action="submit">Cauta</button>
                            </div>
                        </div>
                    </section>
                </div>
            </form>

            <div class="result-bar">
                <span>
                    <?= h($pagination['from']) ?>-<?= h($pagination['to']) ?> din <?= h($pagination['total']) ?>
                    <?= $document === 'nota' ? 'note' : 'bonuri' ?>
                </span>
                <span>Pagina <?= h($pagination['page']) ?> / <?= h($pagination['pages']) ?></span>
            </div>

            <div class="table-wrap">
                <?php if ($document === 'nota'): ?>
                    <table>
                        <thead>
                        <tr>
                            <th>Nr. nota</th>
                            <th>Contor</th>
                            <th>Data</th>
                            <th>Masa</th>
                            <th>Ospatar</th>
                            <th class="num">Total</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td class="strong"><?= h($row['nr_nota']) ?></td>
                                <td><?= h($row['contor_not']) ?></td>
                                <td><?= h(app_ro_date($row['date'])) ?> <?= h($row['time']) ?></td>
                                <td><?= h($row['table']) ?></td>
                                <td><?= h($row['waiter']) ?></td>
                                <td class="num strong"><?= h(app_money($row['total'])) ?></td>
                                <td class="actions">
                                    <a target="_blank" href="<?= h(app_base_url('document.php', ['type' => 'nota', 'id' => $row['id']])) ?>">Print</a>
                                    <a target="_blank" href="<?= h(app_base_url('pdf.php', ['type' => 'nota', 'id' => $row['id']])) ?>">PDF</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>Nr. bon</th>
                            <th>Nr. nota</th>
                            <th>Contor</th>
                            <th>Data</th>
                            <th>Masa</th>
                            <th>Ospatar</th>
                            <th class="num">Total</th>
                            <th class="num">Linii</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td class="strong"><?= h($row['nr_bon']) ?></td>
                                <td><?= h($row['nr_nota']) ?></td>
                                <td><?= h($row['contor'] ?: $row['contor_bon']) ?></td>
                                <td><?= h(app_ro_date($row['date'])) ?> <?= h($row['time']) ?></td>
                                <td><?= h($row['table']) ?></td>
                                <td><?= h($row['waiter']) ?></td>
                                <td class="num strong"><?= h(app_money($row['total'])) ?></td>
                                <td class="num"><?= h($row['items']) ?></td>
                                <td class="actions">
                                    <a target="_blank" href="<?= h(app_base_url('document.php', ['type' => 'bon', 'key' => $row['key']])) ?>">Print</a>
                                    <a target="_blank" href="<?= h(app_base_url('pdf.php', ['type' => 'bon', 'key' => $row['key']])) ?>">PDF</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if (!$results && !$error): ?>
                    <div class="empty-state">Nu exista rezultate pentru filtrul curent.</div>
                <?php endif; ?>
            </div>

            <?php if (!$error && $pagination['pages'] > 1): ?>
                <nav class="pagination" aria-label="Paginare">
                    <?php
                    $baseParams = ['document' => $document, 'q' => $query, 'per_page' => $perPage];
                    $prevParams = array_merge($baseParams, ['page' => max(1, $pagination['page'] - 1)]);
                    $nextParams = array_merge($baseParams, ['page' => min($pagination['pages'], $pagination['page'] + 1)]);
                    ?>
                    <?php if ($pagination['page'] > 1): ?>
                        <a href="<?= h(app_base_url('index.php', $prevParams)) ?>">Inapoi</a>
                    <?php else: ?>
                        <span class="disabled">Inapoi</span>
                    <?php endif; ?>

                    <?php foreach (app_page_window((int) $pagination['page'], (int) $pagination['pages']) as $pageLink): ?>
                        <?php if (is_string($pageLink)): ?>
                            <span class="gap">...</span>
                        <?php elseif ($pageLink === $pagination['page']): ?>
                            <span class="active"><?= h($pageLink) ?></span>
                        <?php else: ?>
                            <a href="<?= h(app_base_url('index.php', array_merge($baseParams, ['page' => $pageLink]))) ?>"><?= h($pageLink) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($pagination['page'] < $pagination['pages']): ?>
                        <a href="<?= h(app_base_url('index.php', $nextParams)) ?>">Inainte</a>
                    <?php else: ?>
                        <span class="disabled">Inainte</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    </section>

    <div class="cache-prompt" data-cache-prompt hidden>
        <div class="cache-prompt-backdrop" data-cache-prompt-backdrop></div>
        <section class="cache-prompt-dialog" role="dialog" aria-modal="true" aria-labelledby="cache-prompt-title">
            <button class="cache-prompt-close" type="button" data-cache-prompt-close aria-label="Inchide">X</button>
            <p class="eyebrow">Actualizare necesara</p>
            <h2 id="cache-prompt-title">Cache-ul trebuie reincarcat</h2>
            <p data-cache-prompt-message>Cache-ul nu este incarcat sau nu mai este actual. Apasa butonul pentru reincarcare.</p>
            <div class="cache-prompt-actions">
                <button class="primary-button" type="button" data-cache-prompt-rebuild>Reincarca cache</button>
                <button class="ghost-button" type="button" data-cache-prompt-close>Mai tarziu</button>
            </div>
        </section>
    </div>
</main>
<script>
document.querySelectorAll('.picker-button').forEach((button) => {
    button.addEventListener('click', () => {
        const target = button.dataset.target;
        const input = document.querySelector(`[name="${target}"]`);
        const current = input ? input.value : '';
        const url = 'file_picker.php?target=' + encodeURIComponent(target) + '&current=' + encodeURIComponent(current);
        window.open(url, 'dbfPicker', 'width=980,height=720,resizable=yes,scrollbars=yes');
    });
});

const touchKeyboard = document.querySelector('[data-touch-keyboard]');
const touchKeyboardInput = document.querySelector('[data-touch-keyboard-input]');
const touchKeyboardModal = document.querySelector('[data-touch-keyboard-modal]');
const touchKeyboardOpen = document.querySelector('[data-keyboard-open]');
const touchKeyboardPreview = document.querySelector('[data-keyboard-preview]');

if (touchKeyboard && touchKeyboardInput && touchKeyboardModal && touchKeyboardOpen) {
    const focusSearch = () => {
        touchKeyboardInput.focus({preventScroll: true});
    };

    const updateKeyboardPreview = () => {
        if (touchKeyboardPreview) {
            touchKeyboardPreview.textContent = touchKeyboardInput.value;
        }
    };

    const openKeyboard = () => {
        updateKeyboardPreview();
        touchKeyboardModal.hidden = false;
        document.body.classList.add('keyboard-modal-open');
        focusSearch();
    };

    const closeKeyboard = () => {
        touchKeyboardModal.hidden = true;
        document.body.classList.remove('keyboard-modal-open');
        focusSearch();
    };

    const setCaret = (position) => {
        if (typeof touchKeyboardInput.setSelectionRange === 'function') {
            touchKeyboardInput.setSelectionRange(position, position);
        }
    };

    const insertText = (text) => {
        focusSearch();
        const start = touchKeyboardInput.selectionStart ?? touchKeyboardInput.value.length;
        const end = touchKeyboardInput.selectionEnd ?? start;
        const current = touchKeyboardInput.value;
        touchKeyboardInput.value = current.slice(0, start) + text + current.slice(end);
        const next = start + text.length;
        setCaret(next);
        touchKeyboardInput.dispatchEvent(new Event('input', {bubbles: true}));
    };

    const backspace = () => {
        focusSearch();
        const start = touchKeyboardInput.selectionStart ?? touchKeyboardInput.value.length;
        const end = touchKeyboardInput.selectionEnd ?? start;
        if (start === 0 && end === 0) {
            return;
        }

        const removeFrom = start === end ? Math.max(0, start - 1) : start;
        const current = touchKeyboardInput.value;
        touchKeyboardInput.value = current.slice(0, removeFrom) + current.slice(end);
        setCaret(removeFrom);
        touchKeyboardInput.dispatchEvent(new Event('input', {bubbles: true}));
    };

    const submitSearch = () => {
        const form = touchKeyboardInput.form;
        if (!form) {
            return;
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    };

    touchKeyboardOpen.addEventListener('click', openKeyboard);
    touchKeyboardInput.addEventListener('input', updateKeyboardPreview);
    updateKeyboardPreview();

    touchKeyboardModal.querySelectorAll('[data-keyboard-close]').forEach((button) => {
        button.addEventListener('click', closeKeyboard);
    });

    touchKeyboard.addEventListener('pointerdown', (event) => {
        if (event.target.closest('button')) {
            event.preventDefault();
        }
    });

    touchKeyboard.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) {
            return;
        }

        const key = button.dataset.key;
        const action = button.dataset.action;

        if (key) {
            insertText(key);
        } else if (action === 'space') {
            insertText(' ');
        } else if (action === 'backspace') {
            backspace();
        } else if (action === 'clear') {
            focusSearch();
            touchKeyboardInput.value = '';
            setCaret(0);
            touchKeyboardInput.dispatchEvent(new Event('input', {bubbles: true}));
        } else if (action === 'submit') {
            submitSearch();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !touchKeyboardModal.hidden) {
            closeKeyboard();
        }
    });
}
</script>
<script src="assets/cache-prompt.js"></script>
</body>
</html>
