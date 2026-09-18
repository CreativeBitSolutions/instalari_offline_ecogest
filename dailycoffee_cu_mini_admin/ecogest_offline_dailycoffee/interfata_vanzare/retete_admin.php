<?php
include 'session.php';

$codProdus = (int)($_GET['cod_p'] ?? $_POST['cod_p'] ?? 0);
if ($codProdus <= 0) {
    header('Location: produse_admin.php');
    exit;
}

$messages = [];
$errors = [];

$stmt = $pdo->prepare("SELECT ps.*, g.denumire_gestiune FROM produse_servicii ps LEFT JOIN gestiuni g ON g.id_gestiune = ps.id_gestiune WHERE ps.cod_produs = :cod");
$stmt->execute([':cod' => $codProdus]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    header('Location: produse_admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $codMat = (int)($_POST['cod_mat'] ?? 0);
            $cantitate = (float)($_POST['cant_folos'] ?? 0);
            if ($codMat <= 0 || $codMat === $codProdus) {
                throw new RuntimeException('Ingredient invalid.');
            }
            if ($cantitate <= 0) {
                throw new RuntimeException('Cantitatea trebuie sa fie mai mare ca zero.');
            }

            $stmt = $pdo->prepare("SELECT id FROM retete WHERE cod_p = :cod_p AND cod_mat = :cod_mat");
            $stmt->execute([':cod_p' => $codProdus, ':cod_mat' => $codMat]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $stmt = $pdo->prepare("UPDATE retete SET cant_folos = cant_folos + :cant WHERE id = :id");
                $stmt->execute([':cant' => $cantitate, ':id' => $existing]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO retete (cod_p, cod_mat, cant_folos) VALUES (:cod_p, :cod_mat, :cant)");
                $stmt->execute([':cod_p' => $codProdus, ':cod_mat' => $codMat, ':cant' => $cantitate]);
            }
            $messages[] = 'Ingredient adaugat.';
        }

        if ($action === 'save') {
            $recipeId = (int)($_POST['recipe_id'] ?? 0);
            $cantitate = (float)($_POST['cant_folos'] ?? 0);
            if ($cantitate <= 0) {
                throw new RuntimeException('Cantitatea trebuie sa fie mai mare ca zero.');
            }
            $stmt = $pdo->prepare("UPDATE retete SET cant_folos = :cant WHERE id = :id AND cod_p = :cod_p");
            $stmt->execute([':cant' => $cantitate, ':id' => $recipeId, ':cod_p' => $codProdus]);
            $messages[] = 'Cantitate salvata.';
        }

        if ($action === 'delete') {
            $recipeId = (int)($_POST['recipe_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM retete WHERE id = :id AND cod_p = :cod_p");
            $stmt->execute([':id' => $recipeId, ':cod_p' => $codProdus]);
            $messages[] = 'Ingredient sters.';
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$materialsStmt = $pdo->prepare(
    "SELECT ps.cod_produs, ps.nume, ps.um, ps.pret_achizitie, g.denumire_gestiune
     FROM produse_servicii ps
     LEFT JOIN gestiuni g ON g.id_gestiune = ps.id_gestiune
     WHERE ps.cod_produs <> :cod
     ORDER BY g.denumire_gestiune, ps.nume"
);
$materialsStmt->execute([':cod' => $codProdus]);
$materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

$recipeStmt = $pdo->prepare(
    "SELECT r.id, r.cod_mat, r.cant_folos, ps.nume, ps.um, ps.pret_achizitie, g.denumire_gestiune
     FROM retete r
     INNER JOIN produse_servicii ps ON ps.cod_produs = r.cod_mat
     LEFT JOIN gestiuni g ON g.id_gestiune = ps.id_gestiune
     WHERE r.cod_p = :cod
     ORDER BY ps.nume"
);
$recipeStmt->execute([':cod' => $codProdus]);
$recipe = $recipeStmt->fetchAll(PDO::FETCH_ASSOC);

$totalCost = 0;
foreach ($recipe as $row) {
    $totalCost += (float)$row['cant_folos'] * (float)$row['pret_achizitie'];
}
$profit = (float)$product['pret_cu_tva'] - $totalCost;
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reteta produs</title>
    <link rel="stylesheet" href="vendor/offline/bootstrap5/bootstrap.min.css">
    <style>
        body { background:#f3f4f6; }
        .page { padding:18px; }
        .summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .summary .box { background:#fff; border:1px solid #ddd; border-radius:8px; padding:14px; }
    </style>
</head>
<body>
<div class="page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="m-0">Reteta pentru <?php echo htmlspecialchars($product['nume']); ?></h3>
            <small>Cod produs <?php echo (int)$product['cod_produs']; ?>. Gestiune <?php echo htmlspecialchars($product['denumire_gestiune'] ?? ''); ?></small>
        </div>
        <div>
            <a class="btn btn-secondary" href="produse_admin.php">Inapoi la produse</a>
            <a class="btn btn-dark" href="vanzare_magazin.php">Inapoi la vanzare</a>
        </div>
    </div>

    <?php foreach ($messages as $message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endforeach; ?>

    <div class="summary mb-3">
        <div class="box"><strong>Pret vanzare cu TVA</strong><br><?php echo number_format((float)$product['pret_cu_tva'], 2); ?> lei</div>
        <div class="box"><strong>Cost reteta</strong><br><?php echo number_format($totalCost, 4); ?> lei</div>
        <div class="box"><strong>Diferenta</strong><br><span class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($profit, 4); ?> lei</span></div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Adauga ingredient</div>
        <div class="card-body">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="cod_p" value="<?php echo $codProdus; ?>">
                <input type="hidden" name="action" value="add">
                <div class="col-md-8">
                    <label>Ingredient</label>
                    <select class="form-select" name="cod_mat" required>
                        <option value="">Alege ingredient</option>
                        <?php foreach ($materials as $mat): ?>
                            <option value="<?php echo (int)$mat['cod_produs']; ?>">
                                <?php echo htmlspecialchars($mat['nume'] . ' | ' . $mat['um'] . ' | ' . ($mat['denumire_gestiune'] ?? '') . ' | ' . $mat['pret_achizitie'] . ' lei'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Cantitate</label>
                    <input class="form-control" type="number" step="0.0001" min="0.0001" name="cant_folos" value="1" required>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit">Adauga</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Ingrediente</div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-dark">
                <tr>
                    <th>Ingredient</th>
                    <th>UM</th>
                    <th>Gestiune</th>
                    <th>Pret achizitie</th>
                    <th>Cantitate</th>
                    <th>Cost</th>
                    <th>Actiuni</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recipe as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nume']); ?></td>
                        <td><?php echo htmlspecialchars($row['um']); ?></td>
                        <td><?php echo htmlspecialchars($row['denumire_gestiune'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['pret_achizitie']); ?></td>
                        <td>
                            <form method="post" class="d-flex gap-2">
                                <input type="hidden" name="cod_p" value="<?php echo $codProdus; ?>">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="recipe_id" value="<?php echo (int)$row['id']; ?>">
                                <input class="form-control form-control-sm" type="number" step="0.0001" min="0.0001" name="cant_folos" value="<?php echo htmlspecialchars($row['cant_folos']); ?>">
                                <button class="btn btn-success btn-sm" type="submit">Salveaza</button>
                            </form>
                        </td>
                        <td><?php echo number_format((float)$row['cant_folos'] * (float)$row['pret_achizitie'], 4); ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Stergere ingredient?')">
                                <input type="hidden" name="cod_p" value="<?php echo $codProdus; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="recipe_id" value="<?php echo (int)$row['id']; ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Sterge</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
