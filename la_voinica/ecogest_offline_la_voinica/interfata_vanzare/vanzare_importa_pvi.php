<?php
include('session.php');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

$adm_id = (int)($_SESSION['admin_id'] ?? 0);
$cod_locatie = (int)($_SESSION['cod_locatie'] ?? 0);

$nr_bon = $_SESSION['nr_bon'];
$cod_masa = $cod_locatie;



$procese = [];
$itemsByProces = [];
$importDataByProces = [];

$sqlProcese = "
    SELECT
        p.id_proces_verbal,
        p.unitate,
        p.gestiune,
        p.data_inventariere,
        p.ora_inventariere,
        COUNT(c.id_rand_proces_verbal) AS total_pozitii,
        SUM(CASE WHEN m.tip_miscare = 'O' THEN 1 ELSE 0 END) AS total_minus,
        SUM(CASE WHEN m.tip_miscare = 'I' THEN 1 ELSE 0 END) AS total_plus
    FROM procese_verbal_inventariere p
    LEFT JOIN continut_procese_verbal_inventariere c ON c.id_proces_verbal = p.id_proces_verbal
    LEFT JOIN miscari m ON m.id_rand_proces_verbal_inventar = c.id_rand_proces_verbal
    GROUP BY p.id_proces_verbal, p.unitate, p.gestiune, p.data_inventariere, p.ora_inventariere
    ORDER BY p.id_proces_verbal DESC
    LIMIT 120
";
$stmtProcese = $pdo->query($sqlProcese);
if ($stmtProcese) {
    $procese = $stmtProcese->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$procesIds = [];
foreach ($procese as $proc) {
    $procesIds[] = (int)$proc['id_proces_verbal'];
}

if (!empty($procesIds)) {
    $placeholders = implode(',', array_fill(0, count($procesIds), '?'));
    $sqlItems = "
        SELECT
            c.id_proces_verbal,
            c.id_rand_proces_verbal,
            c.cod_produs,
            c.nume_produs,
            c.um,
            c.diferenta_cantitate,
            c.valoare_vanzare_fara_tva,
            c.motiv,
            COALESCE(m.tip_miscare, '') AS tip_miscare,
            p.cod_produs AS cod_catalog,
            p.nume AS nume_catalog,
            p.pret_cu_tva,
            p.cota_tva,
            p.um AS um_catalog,
            p.sgr,
            p.sgr_pet,
            p.sgr_alumin,
            p.sgr_sticla,
            COALESCE(g.denumire_gestiune, '') AS gestiune_produs
        FROM continut_procese_verbal_inventariere c
        LEFT JOIN miscari m ON m.id_rand_proces_verbal_inventar = c.id_rand_proces_verbal
        LEFT JOIN produse_servicii p ON p.cod_produs = c.cod_produs
        LEFT JOIN gestiuni g ON g.id_gestiune = p.id_gestiune
        WHERE c.id_proces_verbal IN ($placeholders)
        ORDER BY c.id_proces_verbal DESC, c.id_rand_proces_verbal ASC
    ";

    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute($procesIds);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($items as $item) {
        $pid = (int)($item['id_proces_verbal'] ?? 0);
        if ($pid <= 0) {
            continue;
        }

        if (!isset($itemsByProces[$pid])) {
            $itemsByProces[$pid] = [];
            $importDataByProces[$pid] = [];
        }

        $itemsByProces[$pid][] = $item;

        $cantitate = (float)($item['diferenta_cantitate'] ?? 0);
        $codCatalog = isset($item['cod_catalog']) ? (int)$item['cod_catalog'] : 0;
        $pretCuTva = isset($item['pret_cu_tva']) ? (float)$item['pret_cu_tva'] : null;
        $cotaTva = isset($item['cota_tva']) ? (float)$item['cota_tva'] : 0;
        $numeProd = trim((string)($item['nume_catalog'] ?: $item['nume_produs']));
        $um = trim((string)($item['um_catalog'] ?: $item['um'] ?: 'buc'));
        $gestiuneProdus = trim((string)($item['gestiune_produs'] ?? ''));

        $importabil = ($cantitate > 0 && $codCatalog > 0 && $pretCuTva !== null && $numeProd !== '');

        $importDataByProces[$pid][] = [
            'id_rand' => (int)($item['id_rand_proces_verbal'] ?? 0),
            'cod_produs' => $codCatalog,
            'nume_produs' => $numeProd,
            'cantitate' => number_format($cantitate, 5, '.', ''),
            'pret_vanzare' => ($pretCuTva === null ? '0' : (string)$pretCuTva),
            'cota_tva' => (string)$cotaTva,
            'um' => $um,
            'gestiune' => $gestiuneProdus,
            'sgr' => (int)($item['sgr'] ?? 0),
            'sgr_pet' => (int)($item['sgr_pet'] ?? 0),
            'sgr_alumin' => (int)($item['sgr_alumin'] ?? 0),
            'sgr_sticla' => (int)($item['sgr_sticla'] ?? 0),
            'importabil' => $importabil,
            'tip_miscare' => (string)($item['tip_miscare'] ?? ''),
        ];
    }
}

$importDataJson = json_encode($importDataByProces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($importDataJson)) {
    $importDataJson = '{}';
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fqty($value): string
{
    return number_format((float)$value, 5, ',', '.');
}

function fmoney($value): string
{
    return number_format((float)$value, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Import produse din PVI pe bon curent</title>
    <link rel="stylesheet" href="vendor/offline/bootstrap4/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/offline/fontawesome5/css/all.min.css">
    <style>
        body {
            background: linear-gradient(180deg, #f7f8fb 0%, #eef2f7 100%);
        }
        .page-header {
            background: linear-gradient(110deg, #0f4c81 0%, #166aa8 60%, #2d86c6 100%);
            color: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 18px;
            box-shadow: 0 8px 24px rgba(15, 76, 129, 0.22);
        }
        .meta-pill {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            margin-right: 8px;
            margin-top: 8px;
            font-size: 13px;
        }
        .search-wrap {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #dfe5ee;
            padding: 12px;
            margin-bottom: 14px;
        }
        .process-card {
            border: 1px solid #d8e1ed;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 12px;
            background: #fff;
        }
        .process-card .card-header {
            background: #f6f9fe;
            padding: 0;
            border: 0;
        }
        .process-toggle {
            width: 100%;
            text-align: left;
            padding: 14px 16px;
            border: 0;
            background: transparent;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .process-title {
            font-weight: 600;
            color: #1a2b3b;
            margin-bottom: 4px;
        }
        .process-sub {
            color: #5f7286;
            font-size: 13px;
        }
        .tag-line {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
        }
        .tag-line .badge {
            font-size: 12px;
            padding: 6px 8px;
        }
        .table-sm td, .table-sm th {
            vertical-align: middle;
        }
        .status-box {
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            font-size: 14px;
            display: none;
        }
        .status-info {
            background: #eaf3ff;
            color: #114a7d;
            border: 1px solid #cde3ff;
        }
        .status-ok {
            background: #e9f9ef;
            color: #1d6e3e;
            border: 1px solid #c8efda;
        }
        .status-err {
            background: #fff1f1;
            color: #912a2a;
            border: 1px solid #f3cccc;
        }
        .muted-small {
            color: #6b7a8b;
            font-size: 12px;
        }
        .disabled-row {
            opacity: 0.6;
        }
        @media (max-width: 767.98px) {
            .process-toggle {
                align-items: flex-start;
                flex-direction: column;
            }
            .tag-line {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid py-3 px-3 px-lg-4">
    <div class="page-header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
            <div>
                <h4 class="mb-1">Import produse din proces verbal inventariere</h4>
                <div class="small">Importul merge direct pe bonul curent deschis in vanzare</div>
            </div>
            <div class="mt-2 mt-lg-0">
                <a href="vanzare_magazin.php" class="btn btn-light btn-sm">Inapoi la vanzare</a>
            </div>
        </div>
        <div>
            <span class="meta-pill">Operator ID <?php echo h($adm_id); ?></span>
            <span class="meta-pill">Locatie <?php echo h($cod_locatie); ?></span>
            <span class="meta-pill">Bon curent <?php echo $nr_bon > 0 ? h($nr_bon) : 'lipsa'; ?></span>
        </div>
    </div>

    <?php if ($nr_bon <= 0): ?>
        <div class="alert alert-warning">
            Nu exista bon curent deschis pentru operatorul curent. Deschide vanzarea si revino pe pagina.
        </div>
    <?php endif; ?>

    <div class="search-wrap">
        <div class="form-row align-items-center">
            <div class="col-md-8 col-lg-6 mb-2 mb-md-0">
                <label class="mb-1 font-weight-bold" for="search-proces">Cauta proces sau produs</label>
                <input type="text" id="search-proces" class="form-control" placeholder="ID proces, gestiune, cod produs, nume produs">
            </div>
            <div class="col-md-4 col-lg-3">
                <div class="muted-small mt-4 mt-md-0" id="search-count"></div>
            </div>
        </div>
    </div>

    <?php if (empty($procese)): ?>
        <div class="alert alert-info">Nu exista procese verbale disponibile.</div>
    <?php else: ?>
        <div id="proces-list">
            <?php foreach ($procese as $index => $proces): ?>
                <?php
                $pid = (int)$proces['id_proces_verbal'];
                $items = $itemsByProces[$pid] ?? [];
                $importItems = $importDataByProces[$pid] ?? [];

                $totalImportabile = 0;
                foreach ($importItems as $importItem) {
                    if (!empty($importItem['importabil'])) {
                        $totalImportabile++;
                    }
                }

                $searchBlob = 'proces ' . $pid . ' ' . ($proces['unitate'] ?? '') . ' ' . ($proces['gestiune'] ?? '');
                foreach ($items as $it) {
                    $searchBlob .= ' ' . ($it['cod_produs'] ?? '') . ' ' . ($it['nume_produs'] ?? '') . ' ' . ($it['nume_catalog'] ?? '');
                }

                $collapseId = 'collapse-proces-' . $pid;
                $isOpen = $index === 0 ? 'show' : '';
                ?>
                <div class="process-card" data-search="<?php echo h(strtolower($searchBlob)); ?>">
                    <div class="card-header" id="heading-<?php echo h($pid); ?>">
                        <button class="process-toggle" type="button" data-toggle="collapse" data-target="#<?php echo h($collapseId); ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="<?php echo h($collapseId); ?>">
                            <div>
<div class="process-title">
    Proces #<?php echo h($pid); ?>
    <span class="text-muted" style="font-weight: 500;">
        - <?php echo h($proces['unitate'] ?: '-'); ?>
    </span>
</div>                                <div class="process-sub">
                                    <?php echo h($proces['data_inventariere'] ?? '-'); ?> <?php echo h($proces['ora_inventariere'] ?? ''); ?>
                                    si gestiune <?php echo h($proces['gestiune'] ?: '-'); ?>
                                </div>
                            </div>
                            <div class="tag-line">
                                <span class="badge badge-primary">Pozitii <?php echo h($proces['total_pozitii'] ?? 0); ?></span>
                                <span class="badge badge-danger">Minus <?php echo h($proces['total_minus'] ?? 0); ?></span>
                                <span class="badge badge-success">Plus <?php echo h($proces['total_plus'] ?? 0); ?></span>
                                <span class="badge badge-info">Importabile <?php echo h($totalImportabile); ?></span>
                            </div>
                        </button>
                    </div>

                    <div id="<?php echo h($collapseId); ?>" class="collapse <?php echo $isOpen; ?>" aria-labelledby="heading-<?php echo h($pid); ?>">
                        <div class="card-body">
                            <?php if (empty($items)): ?>
                                <div class="alert alert-light mb-0">Procesul nu are produse in continut.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-2">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Cod</th>
                                                <th>Produs</th>
                                                <th>Sens</th>
                                                <th>Cantitate</th>
                                                <th>Valoare</th>
                                                <th>Motiv</th>
                                                <th>Status import</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $row): ?>
                                                <?php
                                                $tip = (string)($row['tip_miscare'] ?? '');
                                                $sens = 'N/A';
                                                $badge = 'secondary';
                                                if ($tip === 'O') {
                                                    $sens = 'Minus';
                                                    $badge = 'danger';
                                                } elseif ($tip === 'I') {
                                                    $sens = 'Plus';
                                                    $badge = 'success';
                                                }
                                                $canImport = ((float)($row['diferenta_cantitate'] ?? 0) > 0) && isset($row['cod_catalog']) && ((int)$row['cod_catalog'] > 0) && isset($row['pret_cu_tva']);
                                                ?>
                                                <tr class="<?php echo $canImport ? '' : 'disabled-row'; ?>">
                                                    <td><?php echo h($row['cod_produs']); ?></td>
                                                    <td>
                                                        <div><?php echo h($row['nume_produs']); ?></div>
                                                        <div class="muted-small">Catalog <?php echo h($row['nume_catalog'] ?: '-'); ?></div>
                                                    </td>
                                                    <td><span class="badge badge-<?php echo h($badge); ?>"><?php echo h($sens); ?></span></td>
                                                    <td><?php echo h(fqty($row['diferenta_cantitate'])); ?></td>
                                                    <td><?php echo h(fmoney($row['valoare_vanzare_fara_tva'])); ?></td>
                                                    <td><?php echo h($row['motiv'] ?: '-'); ?></td>
                                                    <td>
                                                        <?php if ($canImport): ?>
                                                            <span class="badge badge-success">Pregatit</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">Lipsa date catalog</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center pt-2 border-top">
                                    <label class="mb-2 mb-lg-0">
                                        <input type="checkbox" class="js-confirm-read" data-proces-id="<?php echo h($pid); ?>">
                                        Am verificat lista de produse si continui importul
                                    </label>
                                    <button type="button" class="btn btn-primary js-import-proces" data-proces-id="<?php echo h($pid); ?>" disabled>
                                        Importa acest proces pe bonul curent
                                    </button>
                                </div>
                                <div id="status-<?php echo h($pid); ?>" class="status-box"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/offline/popper/popper.min.js"></script>
<script src="vendor/offline/bootstrap4/bootstrap.min.js"></script>
<script>
(function() {
    const importDataByProces = <?php echo $importDataJson; ?>;
    const nrBon = <?php echo json_encode((string)$nr_bon); ?>;
    const codMasa = <?php echo json_encode((string)$cod_masa); ?>;
    const hasBonCurent = <?php echo $nr_bon > 0 ? 'true' : 'false'; ?>;
    const addEndpoint = 'vanzare_adaug_prod_pe_nota.php';

    const searchInput = document.getElementById('search-proces');
    const cards = Array.from(document.querySelectorAll('.process-card'));
    const searchCount = document.getElementById('search-count');

    function updateSearchCount(visibleCount) {
        if (!searchCount) {
            return;
        }
        searchCount.textContent = 'Afisate ' + visibleCount + ' procese';
    }

    function applyFilter() {
        const q = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase().trim();
        let visibleCount = 0;

        cards.forEach(function(card) {
            const bag = (card.getAttribute('data-search') || '').toLowerCase();
            const visible = q === '' || bag.indexOf(q) !== -1;
            card.style.display = visible ? '' : 'none';
            if (visible) {
                visibleCount++;
            }
        });

        updateSearchCount(visibleCount);
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilter);
    }
    applyFilter();

    const confirmBoxes = Array.from(document.querySelectorAll('.js-confirm-read'));
    confirmBoxes.forEach(function(checkbox) {
        const procesId = checkbox.getAttribute('data-proces-id');
        const button = document.querySelector('.js-import-proces[data-proces-id="' + procesId + '"]');
        if (!button) {
            return;
        }

        function syncButtonState() {
            button.disabled = !checkbox.checked || !hasBonCurent;
        }

        checkbox.addEventListener('change', syncButtonState);
        syncButtonState();
    });

   function setStatus(procesId, type, message, allowHtml) {
    const box = document.getElementById('status-' + procesId);
    if (!box) {
        return;
    }

    box.classList.remove('status-info', 'status-ok', 'status-err');
    if (type === 'ok') {
        box.classList.add('status-ok');
    } else if (type === 'err') {
        box.classList.add('status-err');
    } else {
        box.classList.add('status-info');
    }

    box.style.display = 'block';

    if (allowHtml) {
        box.innerHTML = message;
    } else {
        box.textContent = message;
    }
}

    function makePayload(item) {
        return {
            prod: String(item.cod_produs || ''),
            nume_produs: String(item.nume_produs || ''),
            pret_vanzare: String(item.pret_vanzare || '0'),
            cota_tva: String(item.cota_tva || '0'),
            um: String(item.um || 'buc'),
            gestiune: String(item.gestiune || ''),
            sgr: String(item.sgr || 0),
            sgr_pet: String(item.sgr_pet || 0),
            sgr_alumin: String(item.sgr_alumin || 0),
            sgr_sticla: String(item.sgr_sticla || 0),
            bonul: String(nrBon),
            cod_masa: String(codMasa),
            cantitate_de_adaugat_prod: String(item.cantitate || '0')
        };
    }

    async function addItemToBon(item) {
        const params = new URLSearchParams(makePayload(item));
        const response = await fetch(addEndpoint + '?' + params.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json'
            }
        });

        const text = await response.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (error) {
            data = null;
        }

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        if (data && data.error) {
            throw new Error(String(data.error));
        }

        return data;
    }

    async function importaProces(procesId, button) {
        if (!hasBonCurent) {
            setStatus(procesId, 'err', 'Nu exista bon curent deschis.');
            return;
        }

        const items = importDataByProces[procesId] || [];
        if (!items.length) {
            setStatus(procesId, 'err', 'Procesul nu are pozitii pentru import.');
            return;
        }

        const importabile = items.filter(function(item) {
            return !!item.importabil;
        });

        if (!importabile.length) {
            setStatus(procesId, 'err', 'Nicio pozitie nu are date complete in catalog.');
            return;
        }

        button.disabled = true;
        let imported = 0;
        let skipped = items.length - importabile.length;
        let errors = [];

        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            if (!item.importabil) {
                continue;
            }

            const progress = 'Import in curs. Pozitia ' + (imported + 1) + ' din ' + importabile.length;
            setStatus(procesId, 'info', progress);

            try {
                await addItemToBon(item);
                imported++;
            } catch (error) {
                const cod = item.cod_produs || '-';
                errors.push('Cod ' + cod + ' ' + error.message);
            }
        }

        if (errors.length) {
            setStatus(
                procesId,
                'err',
                'Import partial. Reusite ' + imported + '. Sarite ' + skipped + '. Erori ' + errors.length + '. ' + errors.join(' | ')
            );
        } else {
            setStatus(
    procesId,
    'ok',
    'Import finalizat. Reusite ' + imported + '. Sarite ' + skipped + '. Bon curent #' + nrBon +
    ' <br><a href="vanzare_magazin.php" class="btn btn-sm btn-success mt-2">Inapoi la vanzare</a>',
    true
);
        }

        button.disabled = false;
    }

    const importButtons = Array.from(document.querySelectorAll('.js-import-proces'));
    importButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const procesId = button.getAttribute('data-proces-id');
            if (!procesId) {
                return;
            }
            importaProces(procesId, button);
        });
    });
})();
</script>
</body>
</html>

