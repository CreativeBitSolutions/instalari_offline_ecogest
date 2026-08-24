<?php
include 'session.php';

$messages = [];
$errors = [];

function redirect_categories()
{
    header('Location: categorii_admin.php');
    exit;
}

function upload_category_image($file, $current = '')
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
    $name = uniqid('cat_', true) . '_' . $safe;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Imaginea nu a putut fi salvata.');
    }

    if ($current && is_file(__DIR__ . '/' . $current)) {
        @unlink(__DIR__ . '/' . $current);
    }

    return 'images/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id_categorie'] ?? 0);
        $den = mb_strtoupper(trim((string)($_POST['den_categ'] ?? '')), 'UTF-8');
        $desc = trim((string)($_POST['desc_categ'] ?? ''));
        $seVinde = isset($_POST['se_vinde']) ? 1 : 0;

        if ($den === '') {
            throw new RuntimeException('Denumirea este obligatorie.');
        }

        $currentImage = '';
        if ($action === 'save' && $id > 0) {
            $stmt = $pdo->prepare("SELECT imagine FROM categorii WHERE id_categorie = :id");
            $stmt->execute([':id' => $id]);
            $currentImage = (string)$stmt->fetchColumn();
        }
        $image = upload_category_image($_FILES['imagine'] ?? null, $currentImage);

        if ($action === 'add') {
            $nextId = (int)$pdo->query("SELECT COALESCE(MAX(id_categorie), 0) + 1 FROM categorii")->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO categorii (id_categorie, den_categ, desc_categ, imagine, se_vinde) VALUES (:id, :den, :descr, :img, :se_vinde)");
            $stmt->execute([
                ':id' => $nextId,
                ':den' => $den,
                ':descr' => $desc,
                ':img' => $image,
                ':se_vinde' => $seVinde,
            ]);
            $_SESSION['success_message'] = 'Categorie adaugata.';
        }

        if ($action === 'save' && $id > 0) {
            $stmt = $pdo->prepare("UPDATE categorii SET den_categ = :den, desc_categ = :descr, imagine = :img, se_vinde = :se_vinde WHERE id_categorie = :id");
            $stmt->execute([
                ':den' => $den,
                ':descr' => $desc,
                ':img' => $image,
                ':se_vinde' => $seVinde,
                ':id' => $id,
            ]);
            $_SESSION['success_message'] = 'Categorie salvata.';
        }

        redirect_categories();
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM produse_servicii WHERE id_categorie = :id");
        $stmt->execute([':id' => $id]);
        $used = (int)$stmt->fetchColumn();

        if ($used > 0) {
            $_SESSION['error_message'] = 'Categoria are produse asociate si nu poate fi stearsa.';
        } else {
            $stmt = $pdo->prepare("SELECT imagine FROM categorii WHERE id_categorie = :id");
            $stmt->execute([':id' => $id]);
            $image = (string)$stmt->fetchColumn();

            $pdo->prepare("DELETE FROM categorii_locatii WHERE id_categorie = :id")->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM categorii WHERE id_categorie = :id")->execute([':id' => $id]);

            if ($image && is_file(__DIR__ . '/' . $image)) {
                @unlink(__DIR__ . '/' . $image);
            }
            $_SESSION['success_message'] = 'Categorie stearsa.';
        }
    }
    redirect_categories();
}

if (isset($_SESSION['success_message'])) {
    $messages[] = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $errors[] = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$categories = $pdo->query(
    "SELECT c.*, COUNT(p.cod_produs) AS produse_count
     FROM categorii c
     LEFT JOIN produse_servicii p ON p.id_categorie = c.id_categorie
     GROUP BY c.id_categorie
     ORDER BY c.id_categorie DESC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestionare categorii</title>
    <link rel="stylesheet" href="vendor/offline/bootstrap5/bootstrap.min.css">
    <style>
        body { background:#f3f4f6; }
        .page { padding:18px; }
        .cat-img { width:56px; height:56px; object-fit:cover; border-radius:6px; background:#eee; }
    </style>
</head>
<body>
<div class="page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">Gestionare categorii</h3>
        <a class="btn btn-secondary" href="vanzare_magazin.php">Inapoi la vanzare</a>
    </div>

    <?php foreach ($messages as $message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endforeach; ?>

    <div class="mb-3">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" data-mode="add">Adauga categorie</button>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped table-bordered align-middle">
                <thead class="table-dark">
                <tr>
                    <th>Actiuni</th>
                    <th>ID</th>
                    <th>Imagine</th>
                    <th>Denumire</th>
                    <th>Descriere</th>
                    <th>Se vinde</th>
                    <th>Produse</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td>
                            <button class="btn btn-sm btn-warning edit-category"
                                    data-bs-toggle="modal"
                                    data-bs-target="#categoryModal"
                                    data-category='<?php echo htmlspecialchars(json_encode($cat, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>'>Editare</button>
                            <a class="btn btn-sm btn-info" href="produse_admin.php?id_categorie=<?php echo (int)$cat['id_categorie']; ?>">Produse</a>
                            <a class="btn btn-sm btn-danger" href="categorii_admin.php?delete=<?php echo (int)$cat['id_categorie']; ?>" onclick="return confirm('Stergere categorie?')">Sterge</a>
                        </td>
                        <td><?php echo (int)$cat['id_categorie']; ?></td>
                        <td><?php if (!empty($cat['imagine'])): ?><img class="cat-img" src="<?php echo htmlspecialchars($cat['imagine']); ?>"><?php endif; ?></td>
                        <td><?php echo htmlspecialchars($cat['den_categ']); ?></td>
                        <td><?php echo htmlspecialchars($cat['desc_categ']); ?></td>
                        <td><?php echo (int)$cat['se_vinde'] === 1 ? 'Da' : 'Nu'; ?></td>
                        <td><?php echo (int)$cat['produse_count']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Categorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="categoryAction" value="add">
                    <input type="hidden" name="id_categorie" id="id_categorie">
                    <div class="mb-3">
                        <label>Denumire</label>
                        <input class="form-control" name="den_categ" id="den_categ" required>
                    </div>
                    <div class="mb-3">
                        <label>Descriere</label>
                        <textarea class="form-control" name="desc_categ" id="desc_categ" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Imagine</label>
                        <input class="form-control" type="file" name="imagine" accept="image/*">
                    </div>
                    <label><input type="checkbox" name="se_vinde" id="se_vinde" value="1" checked> Se vinde</label>
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
const emptyCategory = { id_categorie: '', den_categ: '', desc_categ: '', se_vinde: '1' };

function fillCategoryForm(category, mode) {
    document.getElementById('categoryModalTitle').textContent = mode === 'add' ? 'Adauga categorie' : 'Editare categorie';
    document.getElementById('categoryAction').value = mode === 'add' ? 'add' : 'save';
    document.getElementById('id_categorie').value = category.id_categorie || '';
    document.getElementById('den_categ').value = category.den_categ || '';
    document.getElementById('desc_categ').value = category.desc_categ || '';
    document.getElementById('se_vinde').checked = String(category.se_vinde) === '1';
}

document.querySelector('[data-mode="add"]').addEventListener('click', function () {
    fillCategoryForm(emptyCategory, 'add');
});

document.querySelectorAll('.edit-category').forEach(function (button) {
    button.addEventListener('click', function () {
        fillCategoryForm(JSON.parse(this.dataset.category), 'save');
    });
});
</script>
</body>
</html>
