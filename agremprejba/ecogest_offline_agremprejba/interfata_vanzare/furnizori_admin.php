<?php
include 'session.php';

function furn_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function furn_redirect($params = [])
{
    $base = 'furnizori_admin.php';
    if ($params) {
        $base .= '?' . http_build_query($params);
    }
    header('Location: ' . $base);
    exit;
}

function furn_columns(PDO $pdo)
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    $stmt = $pdo->query("PRAGMA table_info(furnizori)");
    foreach ($stmt as $row) {
        $columns[$row['name']] = $row;
    }
    return $columns;
}

function furn_pick_columns(array $columns, array $wanted)
{
    return array_values(array_filter($wanted, function ($name) use ($columns) {
        return isset($columns[$name]);
    }));
}

function furn_next_id(PDO $pdo, array $columns)
{
    if (!isset($columns['id_furnizor'])) {
        return null;
    }
    return (int)$pdo->query("SELECT COALESCE(MAX(CAST(id_furnizor AS INTEGER)), 0) + 1 FROM furnizori")->fetchColumn();
}

function furn_post_value($name)
{
    if (in_array($name, ['tva', 'status_inactiv', 'statusTvaIncasare', 'statusSplitTVA', 'statusRO_e_Factura'], true)) {
        return isset($_POST[$name]) ? 1 : 0;
    }

    $value = trim((string)($_POST[$name] ?? ''));
    return $value === '' ? null : $value;
}

$columns = furn_columns($pdo);
$editableFields = furn_pick_columns($columns, [
    'cod_fiscal',
    'nume',
    'adresa',
    'adresa_tara',
    'adresa_judet',
    'adresa_localitate',
    'cod_inmatriculare',
    'tel',
    'banca',
    'iban',
    'tva',
]);

$messages = [];
$errors = [];

if (isset($_SESSION['furnizori_message'])) {
    $messages[] = $_SESSION['furnizori_message'];
    unset($_SESSION['furnizori_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id_furnizor'] ?? 0);
        $nume = trim((string)($_POST['nume'] ?? ''));
        $codFiscal = trim((string)($_POST['cod_fiscal'] ?? ''));

        if ($nume === '') {
            throw new RuntimeException('Numele furnizorului este obligatoriu.');
        }

        $data = [];
        foreach ($editableFields as $field) {
            $data[$field] = furn_post_value($field);
        }
        if (isset($columns['adresa']) && empty($data['adresa'])) {
            $data['adresa'] = '';
        }
        if (isset($columns['cod_fiscal']) && $codFiscal === '') {
            $data['cod_fiscal'] = '';
        }
        if (isset($columns['adresa_tara']) && empty($data['adresa_tara'])) {
            $data['adresa_tara'] = 'Romania';
        }

        if ($action === 'add') {
            if (isset($columns['cod_fiscal']) && $codFiscal !== '') {
                $check = $pdo->prepare("SELECT COUNT(*) FROM furnizori WHERE cod_fiscal = :cod_fiscal");
                $check->execute([':cod_fiscal' => $codFiscal]);
                if ((int)$check->fetchColumn() > 0) {
                    throw new RuntimeException('Exista deja un furnizor cu acest cod fiscal.');
                }
            }

            if (isset($columns['id_furnizor'])) {
                $data = array_merge(['id_furnizor' => furn_next_id($pdo, $columns)], $data);
            }

            $fieldList = array_keys($data);
            $sql = "INSERT INTO furnizori (" . implode(', ', $fieldList) . ")
                    VALUES (:" . implode(', :', $fieldList) . ")";
            $stmt = $pdo->prepare($sql);
            foreach ($data as $field => $value) {
                $stmt->bindValue(':' . $field, $value);
            }
            $stmt->execute();

            $_SESSION['furnizori_message'] = 'Furnizor adaugat.';
            $return = trim((string)($_POST['return_to'] ?? ''));
            if ($return === 'creare_nir.php') {
                header('Location: creare_nir.php');
                exit;
            }
            furn_redirect();
        }

        if ($action === 'save' && $id > 0) {
            if (isset($columns['cod_fiscal']) && $codFiscal !== '') {
                $check = $pdo->prepare("SELECT COUNT(*) FROM furnizori WHERE cod_fiscal = :cod_fiscal AND id_furnizor <> :id");
                $check->execute([':cod_fiscal' => $codFiscal, ':id' => $id]);
                if ((int)$check->fetchColumn() > 0) {
                    throw new RuntimeException('Exista deja un alt furnizor cu acest cod fiscal.');
                }
            }

            $set = [];
            foreach (array_keys($data) as $field) {
                $set[] = "$field = :$field";
            }

            $stmt = $pdo->prepare("UPDATE furnizori SET " . implode(', ', $set) . " WHERE id_furnizor = :id");
            foreach ($data as $field => $value) {
                $stmt->bindValue(':' . $field, $value);
            }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['furnizori_message'] = 'Furnizor actualizat.';
            furn_redirect();
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $check = $pdo->prepare("SELECT COUNT(*) FROM nir WHERE cod_tert = :id");
        $check->execute([':id' => $id]);
        if ((int)$check->fetchColumn() > 0) {
            throw new RuntimeException('Furnizorul are NIR-uri asociate si nu poate fi sters.');
        }

        $stmt = $pdo->prepare("DELETE FROM furnizori WHERE id_furnizor = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['furnizori_message'] = 'Furnizor sters.';
        furn_redirect();
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'action' => trim((string)($_GET['action'] ?? '')),
    'return' => trim((string)($_GET['return'] ?? '')),
];

$where = [];
$params = [];
if ($filters['q'] !== '') {
    $parts = [];
    foreach (['nume', 'cod_fiscal', 'adresa', 'tel'] as $field) {
        if (isset($columns[$field])) {
            $parts[] = "$field LIKE :q";
        }
    }
    if ($parts) {
        $where[] = '(' . implode(' OR ', $parts) . ')';
        $params[':q'] = '%' . $filters['q'] . '%';
    }
}

$selectFields = furn_pick_columns($columns, [
    'id_furnizor',
    'cod_fiscal',
    'nume',
    'adresa',
    'adresa_tara',
    'adresa_judet',
    'adresa_localitate',
    'cod_inmatriculare',
    'tel',
    'banca',
    'iban',
    'tva',
]);
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderField = isset($columns['nume']) ? 'nume' : (isset($columns['id_furnizor']) ? 'id_furnizor' : $selectFields[0]);

$stmt = $pdo->prepare("SELECT " . implode(', ', $selectFields) . " FROM furnizori $whereSql ORDER BY $orderField LIMIT 500");
$stmt->execute($params);
$furnizori = $stmt->fetchAll(PDO::FETCH_ASSOC);

$empty = [];
foreach ($selectFields as $field) {
    $empty[$field] = '';
}
$empty['adresa_tara'] = 'Romania';
$empty['tva'] = 0;
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Furnizori</title>
    <link rel="stylesheet" href="vendor/offline/bootstrap5/bootstrap.min.css">
    <style>
        body { background:#f3f4f6; }
        .page { padding:18px; }
        .card { border-radius:8px; }
        .table-wrap { overflow:auto; }
        table { min-width:1200px; }
        th { white-space:nowrap; }
        .modal label { font-weight:600; }
    </style>
</head>
<body>
<div class="page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">Furnizori</h3>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="creare_nir.php">Creare NIR manual</a>
            <a class="btn btn-secondary" href="vanzare_magazin.php">Inapoi la vanzare</a>
        </div>
    </div>

    <?php foreach ($messages as $message): ?>
        <div class="alert alert-success"><?php echo furn_h($message); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo furn_h($error); ?></div>
    <?php endforeach; ?>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal" data-mode="add">Adauga furnizor</button>
                <a class="btn btn-outline-dark" href="furnizori_admin.php">Resetare</a>
            </div>
            <form method="get" class="row g-2">
                <div class="col-md-10">
                    <input class="form-control" name="q" value="<?php echo furn_h($filters['q']); ?>" placeholder="Cauta nume, CUI, adresa sau telefon">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-dark w-100" type="submit">Filtreaza</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-wrap">
            <table class="table table-sm table-striped table-bordered align-middle">
                <thead class="table-dark">
                <tr>
                    <th>Actiuni</th>
                    <th>ID</th>
                    <th>Nume</th>
                    <th>CUI</th>
                    <th>Nr. reg.</th>
                    <th>Adresa</th>
                    <th>Judet</th>
                    <th>Localitate</th>
                    <th>Telefon</th>
                    <th>Banca</th>
                    <th>IBAN</th>
                    <th>TVA</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($furnizori as $row): ?>
                    <tr>
                        <td>
                            <button class="btn btn-sm btn-warning edit-supplier"
                                    data-bs-toggle="modal"
                                    data-bs-target="#supplierModal"
                                    data-supplier='<?php echo furn_h(json_encode($row, JSON_UNESCAPED_UNICODE)); ?>'>Editare</button>
                            <a class="btn btn-sm btn-danger" href="furnizori_admin.php?delete=<?php echo (int)($row['id_furnizor'] ?? 0); ?>" onclick="return confirm('Stergere furnizor?')">Sterge</a>
                        </td>
                        <td><?php echo furn_h($row['id_furnizor'] ?? ''); ?></td>
                        <td><?php echo furn_h($row['nume'] ?? ''); ?></td>
                        <td><?php echo furn_h($row['cod_fiscal'] ?? ''); ?></td>
                        <td><?php echo furn_h($row['cod_inmatriculare'] ?? ''); ?></td>
                        <td><?php echo furn_h($row['adresa'] ?? ''); ?></td>
                        <td><?php echo furn_h($row['adresa_judet'] ?? ''); ?></td>
                        <td><?php echo furn_h($row['adresa_localitate'] ?? ''); ?></td>
                        <td><?php echo furn_h($row['tel'] ?? ''); ?></td>
                        <td><?php echo furn_h($row['banca'] ?? ''); ?></td>
                        <td><?php echo furn_h($row['iban'] ?? ''); ?></td>
                        <td><?php echo ((int)($row['tva'] ?? 0) === 1) ? 'Da' : 'Nu'; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$furnizori): ?>
                    <tr><td colspan="12" class="text-center text-muted">Nu exista furnizori pentru filtrul curent.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierModalTitle">Furnizor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="supplierAction" value="add">
                    <input type="hidden" name="id_furnizor" id="id_furnizor">
                    <input type="hidden" name="return_to" value="<?php echo $filters['return'] === 'creare_nir.php' ? 'creare_nir.php' : ''; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Nume</label>
                            <input class="form-control" name="nume" id="nume" required>
                        </div>
                        <div class="col-md-3">
                            <label>CUI</label>
                            <input class="form-control" name="cod_fiscal" id="cod_fiscal">
                        </div>
                        <div class="col-md-3">
                            <label>Nr. reg.</label>
                            <input class="form-control" name="cod_inmatriculare" id="cod_inmatriculare">
                        </div>
                        <div class="col-md-12">
                            <label>Adresa</label>
                            <input class="form-control" name="adresa" id="adresa">
                        </div>
                        <div class="col-md-4">
                            <label>Tara</label>
                            <input class="form-control" name="adresa_tara" id="adresa_tara" value="Romania">
                        </div>
                        <div class="col-md-4">
                            <label>Judet</label>
                            <input class="form-control" name="adresa_judet" id="adresa_judet">
                        </div>
                        <div class="col-md-4">
                            <label>Localitate</label>
                            <input class="form-control" name="adresa_localitate" id="adresa_localitate">
                        </div>
                        <div class="col-md-4">
                            <label>Telefon</label>
                            <input class="form-control" name="tel" id="tel">
                        </div>
                        <div class="col-md-4">
                            <label>Banca</label>
                            <input class="form-control" name="banca" id="banca">
                        </div>
                        <div class="col-md-4">
                            <label>IBAN</label>
                            <input class="form-control" name="iban" id="iban">
                        </div>
                        <div class="col-md-12">
                            <label><input type="checkbox" name="tva" id="tva" value="1"> Platitor TVA</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-outline-secondary me-auto" href="vanzare_magazin.php">Inapoi la vanzare</a>
                    <button class="btn btn-primary" type="submit">Salveaza</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Inchide</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="vendor/offline/bootstrap5/bootstrap.bundle.min.js"></script>
<script>
const emptySupplier = <?php echo json_encode($empty, JSON_UNESCAPED_UNICODE); ?>;

function fillSupplierForm(supplier, mode) {
    document.getElementById('supplierModalTitle').textContent = mode === 'add' ? 'Adauga furnizor' : 'Editare furnizor';
    document.getElementById('supplierAction').value = mode === 'add' ? 'add' : 'save';
    for (const [key, value] of Object.entries(emptySupplier)) {
        const el = document.getElementById(key);
        if (!el) continue;
        const nextValue = supplier[key] ?? value ?? '';
        if (el.type === 'checkbox') {
            el.checked = String(nextValue) === '1';
        } else {
            el.value = nextValue;
        }
    }
}

document.querySelector('[data-mode="add"]').addEventListener('click', function () {
    fillSupplierForm(emptySupplier, 'add');
});

document.querySelectorAll('.edit-supplier').forEach(function (button) {
    button.addEventListener('click', function () {
        fillSupplierForm(JSON.parse(this.dataset.supplier), 'save');
    });
});

<?php if ($filters['action'] === 'add'): ?>
document.addEventListener('DOMContentLoaded', function () {
    const modal = new bootstrap.Modal(document.getElementById('supplierModal'));
    fillSupplierForm(emptySupplier, 'add');
    modal.show();
});
<?php endif; ?>
</script>
</body>
</html>
