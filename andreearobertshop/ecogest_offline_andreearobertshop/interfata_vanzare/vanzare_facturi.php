<?php
include('database_connection.php');

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
    <link href="vendor/offline/bootstrap4/bootstrap.min.css" rel="stylesheet">
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
  <a class="navbar-brand" href="#">ECOGEST</a>
  <div class="ml-auto">
    <a href="vanzare_magazin.php" class="btn btn-outline-light btn-sm">ÃŽnapoi la vÃ¢nzare</a>
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
                            <th>NumÄƒr</th>
                            <th>Data Emitere</th>
                            <th>Data ScadenÈ›Äƒ</th>
                            <th>Client</th>
                            <th>Nr. Bon</th>
                            <th>AdresÄƒ</th>
                            <th>Cod Fiscal</th>
                            <th>AcÈ›iuni</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($facturi)): ?>
                        <tr>
                            <td colspan="10" class="text-center">Nu existÄƒ facturi de afiÈ™at.</td>
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
                                    ListeazÄƒ
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

<!-- jQuery Ã®nainte de Bootstrap -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/offline/bootstrap4/bootstrap.bundle.min.js"></script>

</body>
</html>
