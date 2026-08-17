<?php
include('database_connection.php');
if (function_exists('restaurantIsOfflineSqlite') && restaurantIsOfflineSqlite()) {
    http_response_code(403);
    die('<h1>Facturarea este dezactivata in modul SQLite offline.</h1>');
}

// Preluarea tuturor facturilor
$sql = "SELECT * FROM facturi ORDER BY id_factura DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$facturi = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lista Facturi</title>
    <!-- Bootstrap CSS (jsDelivr) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .navbar { box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .card { border: none; }
        .table thead th { background-color: #343a40; color: #fff; }
        .table-hover tbody tr:hover { background-color: #e9ecef; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <a class="navbar-brand" href="#">AGECS</a>
  <div class="ml-auto">
    <a href="vanzare_restaurant.php" class="btn btn-outline-light btn-sm">Înapoi la vânzare</a>
  </div>
</nav>

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="card-title mb-4">Lista Facturi</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Serie</th>
                            <th>Număr</th>
                            <th>Data Emitere</th>
                            <th>Data Scadență</th>
                            <th>Client</th>
                            <th>Nr. Bon</th>
                            <th>Adresă</th>
                            <th>Cod Fiscal</th>
                            <th>Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($facturi)): ?>
                        <tr>
                            <td colspan="10" class="text-center">Nu există facturi de afișat.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($facturi as $fact): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fact['id_factura']); ?></td>
                            <td><?php echo htmlspecialchars($fact['serie_factura']); ?></td>
                            <td><?php echo htmlspecialchars($fact['nr_factura']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($fact['data_factura'])); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($fact['data_scadenta'])); ?></td>
                            <td><?php echo htmlspecialchars($fact['nume']); ?></td>
                            <td><?php echo htmlspecialchars($fact['nrbon']); ?></td>
                            <td><?php echo htmlspecialchars($fact['adresa']); ?></td>
                            <td><?php echo htmlspecialchars($fact['cod_fiscal']); ?></td>
                            <td>
                                <a target="_blank"
                                   href="../listeaza_fact.php?id_factura=<?php echo urlencode($fact['id_factura']); ?>"
                                   class="btn btn-sm btn-primary">
                                    Listează
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- jQuery înainte de Bootstrap -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
