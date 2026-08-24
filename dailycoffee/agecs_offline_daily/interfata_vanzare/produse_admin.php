<?php
include 'session.php';

$messages = [];
$errors = [];

function redirect_products()
{
    $query = $_GET;
    unset($query['delete']);
    $url = 'produse_admin.php';
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    header('Location: ' . $url);
    exit;
}

function clean_text_value($value)
{
    return mb_strtoupper(trim((string)$value), 'UTF-8');
}

function upload_product_image($file, $current = '')
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $current;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Imaginea nu a putut fi incarcata.');
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Sunt permise doar imagini jpg, jpeg, png, gif sau webp.');
    }

    $dir = __DIR__ . '/images';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', basename($file['name']));
    $name = uniqid('prod_', true) . '_' . $safe;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Imaginea nu a putut fi salvata.');
    }

    if ($current && is_file(__DIR__ . '/' . $current)) {
        @unlink(__DIR__ . '/' . $current);
    }

    return 'images/' . $name;
}

function post_bool($key)
{
    return isset($_POST[$key]) ? 1 : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $cod_produs = (int)($_POST['cod_produs'] ?? 0);

        $nume = clean_text_value($_POST['nume'] ?? '');
        $descriere = clean_text_value($_POST['descriere'] ?? '');
        $cod_bare = trim((string)($_POST['cod_bare'] ?? ''));
        if ($cod_bare === '') {
            $cod_bare = $nume;
        }

        if ($nume === '') {
            throw new RuntimeException('Numele produsului este obligatoriu.');
        }

        $currentImage = '';
        if ($action === 'save' && $cod_produs > 0) {
            $stmt = $pdo->prepare("SELECT imagine FROM produse_servicii WHERE cod_produs = :cod_produs");
            $stmt->execute([':cod_produs' => $cod_produs]);
            $currentImage = (string)$stmt->fetchColumn();
        }

        $imagine = upload_product_image($_FILES['imagine'] ?? null, $currentImage);

        $data = [
            ':nume' => $nume,
            ':descriere' => $descriere,
            ':pret_achizitie' => (float)($_POST['pret_achizitie'] ?? 0),
            ':pret_cu_tva' => (float)($_POST['pret_cu_tva'] ?? 0),
            ':tip' => $_POST['tip'] ?? 'produs',
            ':cota_tva' => (int)($_POST['cota_tva'] ?? 0),
            ':um' => trim((string)($_POST['um'] ?? 'BUC')),
            ':id_categorie' => (int)($_POST['id_categorie'] ?? 0),
            ':imagine' => $imagine,
            ':cod_bare' => $cod_bare,
            ':id_gestiune' => (int)($_POST['id_gestiune'] ?? 0),
            ':departament' => trim((string)($_POST['departament'] ?? 'BAR')),
            ':stoc_critic' => (int)($_POST['stoc_critic'] ?? 0),
            ':sgr' => post_bool('sgr'),
            ':sgr_pet' => post_bool('sgr_pet'),
            ':sgr_alumin' => post_bool('sgr_alumin'),
            ':sgr_sticla' => post_bool('sgr_sticla'),
            ':nc8' => trim((string)($_POST['nc8'] ?? '')),
            ':activ' => post_bool('activ'),
            ':nume_en' => trim((string)($_POST['nume_en'] ?? '')),
            ':descriere_en' => trim((string)($_POST['descriere_en'] ?? '')),
            ':dep_casa_marcat' => (int)($_POST['dep_casa_marcat'] ?? 1),
        ];

        if ($action === 'add') {
            $nextId = (int)$pdo->query("SELECT COALESCE(MAX(cod_produs), 0) + 1 FROM produse_servicii")->fetchColumn();
            $sql = "INSERT INTO produse_servicii
                    (cod_produs, nume, descriere, pret_achizitie, pret_cu_tva, tip, cota_tva, um,
                     id_categorie, imagine, cod_bare, id_gestiune, departament, stoc_critic,
                     sgr, sgr_pet, sgr_alumin, sgr_sticla, nc8, activ, nume_en, descriere_en, dep_casa_marcat)
                    VALUES
                    (:cod_produs, :nume, :descriere, :pret_achizitie, :pret_cu_tva, :tip, :cota_tva, :um,
                     :id_categorie, :imagine, :cod_bare, :id_gestiune, :departament, :stoc_critic,
                     :sgr, :sgr_pet, :sgr_alumin, :sgr_sticla, :nc8, :activ, :nume_en, :descriere_en, :dep_casa_marcat)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([':cod_produs' => $nextId], $data));
            $_SESSION['success_message'] = 'Produs adaugat.';
        }

        if ($action === 'save' && $cod_produs > 0) {
            $sql = "UPDATE produse_servicii
                    SET nume = :nume,
                        descriere = :descriere,
                        pret_achizitie = :pret_achizitie,
                        pret_cu_tva = :pret_cu_tva,
                        tip = :tip,
                        cota_tva = :cota_tva,
                        um = :um,
                        id_categorie = :id_categorie,
                        imagine = :imagine,
                        cod_bare = :cod_bare,
                        id_gestiune = :id_gestiune,
                        departament = :departament,
                        stoc_critic = :stoc_critic,
                        sgr = :sgr,
                        sgr_pet = :sgr_pet,
                        sgr_alumin = :sgr_alumin,
                        sgr_sticla = :sgr_sticla,
                        nc8 = :nc8,
                        activ = :activ,
                        nume_en = :nume_en,
                        descriere_en = :descriere_en,
                        dep_casa_marcat = :dep_casa_marcat
                    WHERE cod_produs = :cod_produs";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($data, [':cod_produs' => $cod_produs]));
            $_SESSION['success_message'] = 'Produs salvat.';
        }

        redirect_products();
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $cod_produs = (int)$_GET['delete'];
    if ($cod_produs > 0) {
        $stmt = $pdo->prepare("SELECT imagine FROM produse_servicii WHERE cod_produs = :cod_produs");
        $stmt->execute([':cod_produs' => $cod_produs]);
        $image = (string)$stmt->fetchColumn();

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM retete WHERE cod_p = :cod OR cod_mat = :cod")->execute([':cod' => $cod_produs]);
        $pdo->prepare("DELETE FROM stoc_produse WHERE cod_p = :cod")->execute([':cod' => $cod_produs]);
        $pdo->prepare("DELETE FROM produse_servicii WHERE cod_produs = :cod")->execute([':cod' => $cod_produs]);
        $pdo->commit();

        if ($image && is_file(__DIR__ . '/' . $image)) {
            @unlink(__DIR__ . '/' . $image);
        }
        $_SESSION['success_message'] = 'Produs sters.';
    }
    redirect_products();
}

if (isset($_SESSION['success_message'])) {
    $messages[] = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$categories = $pdo->query("SELECT id_categorie, den_categ FROM categorii ORDER BY den_categ")->fetchAll(PDO::FETCH_ASSOC);
$gestiuni = $pdo->query("SELECT id_gestiune, denumire_gestiune FROM gestiuni ORDER BY denumire_gestiune")->fetchAll(PDO::FETCH_ASSOC);
$coteTva = $pdo->query("SELECT id, cota FROM cote_tva ORDER BY cota")->fetchAll(PDO::FETCH_ASSOC);

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'id_categorie' => (int)($_GET['id_categorie'] ?? 0),
    'id_gestiune' => (int)($_GET['id_gestiune'] ?? 0),
    'departament' => trim((string)($_GET['departament'] ?? '')),
    'activ' => trim((string)($_GET['activ'] ?? '')),
];

$where = [];
$params = [];
if ($filters['q'] !== '') {
    $where[] = "(p.nume LIKE :q OR p.cod_bare LIKE :q OR CAST(p.cod_produs AS TEXT) = :cod)";
    $params[':q'] = '%' . $filters['q'] . '%';
    $params[':cod'] = $filters['q'];
}
if ($filters['id_categorie'] > 0) {
    $where[] = "p.id_categorie = :id_categorie";
    $params[':id_categorie'] = $filters['id_categorie'];
}
if ($filters['id_gestiune'] > 0) {
    $where[] = "p.id_gestiune = :id_gestiune";
    $params[':id_gestiune'] = $filters['id_gestiune'];
}
if ($filters['departament'] !== '') {
    $where[] = "p.departament = :departament";
    $params[':departament'] = $filters['departament'];
}
if ($filters['activ'] !== '') {
    $where[] = "p.activ = :activ";
    $params[':activ'] = (int)$filters['activ'];
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare(
    "SELECT p.*, c.den_categ, g.denumire_gestiune, COALESCE(sp.cantitate_stoc, 0) AS stoc,
            (SELECT COUNT(*) FROM retete r WHERE r.cod_p = p.cod_produs) AS nr_ingrediente
     FROM produse_servicii p
     LEFT JOIN categorii c ON c.id_categorie = p.id_categorie
     LEFT JOIN gestiuni g ON g.id_gestiune = p.id_gestiune
     LEFT JOIN stoc_produse sp ON sp.cod_p = p.cod_produs
     $whereSql
     ORDER BY p.cod_produs DESC
     LIMIT 500"
);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoryOptions = '';
foreach ($categories as $cat) {
    $categoryOptions .= '<option value="' . (int)$cat['id_categorie'] . '">' . htmlspecialchars($cat['den_categ']) . '</option>';
}
$gestiuneOptions = '';
foreach ($gestiuni as $gest) {
    $gestiuneOptions .= '<option value="' . (int)$gest['id_gestiune'] . '">' . htmlspecialchars($gest['denumire_gestiune']) . '</option>';
}
$tvaOptions = '';
foreach ($coteTva as $tva) {
    $tvaOptions .= '<option value="' . (int)$tva['id'] . '">' . htmlspecialchars($tva['cota']) . '%</option>';
}
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestionare produse</title>
    <link rel="stylesheet" href="vendor/offline/bootstrap5/bootstrap.min.css">
    <style>
        body { background:#f3f4f6; }
        .page { padding:18px; }
        .card { border-radius:8px; }
        .table-wrap { overflow:auto; }
        table { min-width:1700px; }
        th { white-space:nowrap; }
        .product-img { width:48px; height:48px; object-fit:cover; border-radius:6px; background:#eee; }
        .modal label { font-weight:600; }
        .badge-soft { background:#eef2ff; color:#3730a3; }
    </style>
</head>
<body>
<div class="page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">Gestionare produse si servicii</h3>
        <a class="btn btn-secondary" href="vanzare_magazin.php">Inapoi la vanzare</a>
    </div>

    <?php foreach ($messages as $message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" data-mode="add">Adauga produs</button>
                <a class="btn btn-outline-primary" href="produse_admin.php?activ=1">Produse active</a>
                <a class="btn btn-outline-secondary" href="produse_admin.php?activ=0">Produse inactive</a>
                <a class="btn btn-outline-dark" href="produse_admin.php">Toate</a>
            </div>
            <form method="get" class="row g-2">
                <div class="col-md-3"><input class="form-control" name="q" value="<?php echo htmlspecialchars($filters['q']); ?>" placeholder="Cauta nume, cod, cod bare"></div>
                <div class="col-md-3">
                    <select class="form-select" name="id_categorie">
                        <option value="0">Toate categoriile</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id_categorie']; ?>" <?php echo $filters['id_categorie'] === (int)$cat['id_categorie'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['den_categ']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="id_gestiune">
                        <option value="0">Toate gestiunile</option>
                        <?php foreach ($gestiuni as $gest): ?>
                            <option value="<?php echo (int)$gest['id_gestiune']; ?>" <?php echo $filters['id_gestiune'] === (int)$gest['id_gestiune'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($gest['denumire_gestiune']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="departament">
                        <option value="">Toate departamentele</option>
                        <?php foreach (['BAR', 'BUCATARIE', 'INDISPONIBIL'] as $dep): ?>
                            <option value="<?php echo $dep; ?>" <?php echo $filters['departament'] === $dep ? 'selected' : ''; ?>><?php echo $dep; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-dark w-100" type="submit">Filtreaza</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-wrap">
            <table class="table table-sm table-striped table-bordered align-middle">
                <thead class="table-dark">
                <tr>
                    <th>Actiuni</th>
                    <th>Cod</th>
                    <th>Imagine</th>
                    <th>Nume</th>
                    <th>Pret TVA</th>
                    <th>Pret achizitie</th>
                    <th>TVA</th>
                    <th>UM</th>
                    <th>Categorie</th>
                    <th>Gestiune</th>
                    <th>Departament</th>
                    <th>Cod bare</th>
                    <th>Stoc</th>
                    <th>Reteta</th>
                    <th>Activ</th>
                    <th>SGR</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $row): ?>
                    <tr>
                        <td>
                            <button class="btn btn-sm btn-warning edit-product"
                                    data-bs-toggle="modal"
                                    data-bs-target="#productModal"
                                    data-product='<?php echo htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>'>Editare</button>
                            <a class="btn btn-sm btn-success" href="retete_admin.php?cod_p=<?php echo (int)$row['cod_produs']; ?>">Reteta</a>
                            <a class="btn btn-sm btn-danger" href="produse_admin.php?delete=<?php echo (int)$row['cod_produs']; ?>" onclick="return confirm('Stergere produs?')">Sterge</a>
                        </td>
                        <td><?php echo (int)$row['cod_produs']; ?></td>
                        <td><?php if (!empty($row['imagine'])): ?><img class="product-img" src="<?php echo htmlspecialchars($row['imagine']); ?>"><?php endif; ?></td>
                        <td><?php echo htmlspecialchars($row['nume']); ?></td>
                        <td><?php echo htmlspecialchars($row['pret_cu_tva']); ?></td>
                        <td><?php echo htmlspecialchars($row['pret_achizitie']); ?></td>
                        <td><?php echo htmlspecialchars($row['cota_tva']); ?></td>
                        <td><?php echo htmlspecialchars($row['um']); ?></td>
                        <td><?php echo htmlspecialchars($row['den_categ'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['denumire_gestiune'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['departament']); ?></td>
                        <td><?php echo htmlspecialchars($row['cod_bare']); ?></td>
                        <td><?php echo htmlspecialchars($row['stoc']); ?></td>
                        <td><span class="badge badge-soft"><?php echo (int)$row['nr_ingrediente']; ?> ingrediente</span></td>
                        <td><?php echo (int)$row['activ'] === 1 ? 'Da' : 'Nu'; ?></td>
                        <td><?php echo ((int)$row['sgr'] === 1 || (int)$row['sgr_pet'] === 1 || (int)$row['sgr_alumin'] === 1 || (int)$row['sgr_sticla'] === 1) ? 'Da' : 'Nu'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Produs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="productAction" value="add">
                    <input type="hidden" name="cod_produs" id="cod_produs">
                    <div class="row g-3">
                        <div class="col-md-6"><label>Nume</label><input class="form-control" name="nume" id="nume" required></div>
                        <div class="col-md-6"><label>Descriere scurta</label><input class="form-control" name="descriere" id="descriere"></div>
                        <div class="col-md-3"><label>Pret cu TVA</label><input class="form-control" type="number" step="0.01" name="pret_cu_tva" id="pret_cu_tva" required></div>
                        <div class="col-md-3"><label>Pret achizitie fara TVA</label><input class="form-control" type="number" step="0.00001" name="pret_achizitie" id="pret_achizitie"></div>
                        <div class="col-md-3"><label>Tip</label><select class="form-select" name="tip" id="tip"><option value="produs">produs</option><option value="serviciu">serviciu</option></select></div>
                        <div class="col-md-3"><label>Cota TVA</label><select class="form-select" name="cota_tva" id="cota_tva"><?php echo $tvaOptions; ?></select></div>
                        <div class="col-md-3"><label>UM</label><input class="form-control" name="um" id="um" value="BUC"></div>
                        <div class="col-md-3"><label>Categorie</label><select class="form-select" name="id_categorie" id="id_categorie"><?php echo $categoryOptions; ?></select></div>
                        <div class="col-md-3"><label>Gestiune</label><select class="form-select" name="id_gestiune" id="id_gestiune"><?php echo $gestiuneOptions; ?></select></div>
                        <div class="col-md-3"><label>Departament</label><select class="form-select" name="departament" id="departament"><option>BAR</option><option>BUCATARIE</option><option>INDISPONIBIL</option></select></div>
                        <div class="col-md-4"><label>Cod bare</label><input class="form-control" name="cod_bare" id="cod_bare"></div>
                        <div class="col-md-2"><label>Stoc critic</label><input class="form-control" type="number" name="stoc_critic" id="stoc_critic" value="0"></div>
                        <div class="col-md-2"><label>NC8</label><input class="form-control" name="nc8" id="nc8"></div>
                        <div class="col-md-2"><label>Dep. casa</label><input class="form-control" type="number" name="dep_casa_marcat" id="dep_casa_marcat" value="1"></div>
                        <div class="col-md-2"><label>Imagine</label><input class="form-control" type="file" name="imagine" accept="image/*"></div>
                        <div class="col-md-6"><label>Nume EN</label><input class="form-control" name="nume_en" id="nume_en"></div>
                        <div class="col-md-6"><label>Descriere EN</label><input class="form-control" name="descriere_en" id="descriere_en"></div>
                        <div class="col-12 d-flex flex-wrap gap-3">
                            <label><input type="checkbox" name="activ" id="activ" checked> Activ</label>
                            <label><input type="checkbox" name="sgr" id="sgr"> SGR</label>
                            <label><input type="checkbox" name="sgr_pet" id="sgr_pet"> SGR PET</label>
                            <label><input type="checkbox" name="sgr_alumin" id="sgr_alumin"> SGR aluminiu</label>
                            <label><input type="checkbox" name="sgr_sticla" id="sgr_sticla"> SGR sticla</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">Salveaza</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Inchide</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="vendor/offline/bootstrap5/bootstrap.bundle.min.js"></script>
<script>
const emptyProduct = {
    cod_produs: '', nume: '', descriere: '', pret_achizitie: '0', pret_cu_tva: '0',
    tip: 'produs', cota_tva: '', um: 'BUC', id_categorie: '', id_gestiune: '',
    departament: 'BAR', cod_bare: '', stoc_critic: '0', nc8: '', dep_casa_marcat: '1',
    activ: '1', sgr: '0', sgr_pet: '0', sgr_alumin: '0', sgr_sticla: '0',
    nume_en: '', descriere_en: ''
};

function fillProductForm(product, mode) {
    document.getElementById('productModalTitle').textContent = mode === 'add' ? 'Adauga produs' : 'Editare produs';
    document.getElementById('productAction').value = mode === 'add' ? 'add' : 'save';
    for (const [key, value] of Object.entries(product)) {
        const el = document.getElementById(key);
        if (!el) continue;
        if (el.type === 'checkbox') {
            el.checked = String(value) === '1';
        } else {
            el.value = value ?? '';
        }
    }
}

document.querySelector('[data-mode="add"]').addEventListener('click', function () {
    fillProductForm(emptyProduct, 'add');
});

document.querySelectorAll('.edit-product').forEach(function (button) {
    button.addEventListener('click', function () {
        fillProductForm(JSON.parse(this.dataset.product), 'save');
    });
});
</script>
</body>
</html>
