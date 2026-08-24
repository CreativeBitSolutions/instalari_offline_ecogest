<?php
// verifica_facturi.php
include('session.php');

$sql = "
    SELECT DISTINCT f.id_factura, f.nr_factura, f.serie_factura, f.data_factura
    FROM facturi f
    JOIN vanzari v ON f.id_factura = v.id_factura
    WHERE f.tip_factura NOT IN (751, 384)
      AND f.nr_factura <> 0
      AND NOT EXISTS (
          SELECT 1 
          FROM miscari m 
          WHERE m.id_vanz_fact = v.id_vanz 
            AND m.fel_doc = 'FAC'
      )
    ORDER BY f.data_factura, f.nr_factura;
";


$stmt = $pdo->query($sql);
$facturi_lipsa = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Reparare Mișcări Facturi</title>
    <link rel="stylesheet" href="style.css"> </head>
<body>
<div class="container">
    <h1>Reparare Mișcări Facturi (FAC)</h1>

    <?php if (isset($_GET['status'])): ?>
        <div class="<?php echo $_GET['status'] == 'succes' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($_GET['mesaj']); ?>
        </div>
    <?php endif; ?>

    <?php if (count($facturi_lipsa) > 0): ?>
        <a href="genereaza_toate_miscarile_facturi.php" class="btn-gen-all" onclick="return confirm('ATENȚIE! Vei genera mișcări pentru TOATE cele <?php echo count($facturi_lipsa); ?> facturi. Ești sigur?');">
            🚀 Generează Mișcări pentru Toate Facturile Lipsă
        </a>
        <h2>Sau generează individual:</h2>
        <table>
            <thead>
                <tr>
                    <th>Serie / Nr. Factură</th>
                    <th>Data</th>
                    <th>Acțiune</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($facturi_lipsa as $factura): ?>
                <tr>
                    <td><?php echo htmlspecialchars($factura['serie_factura']) . ' / ' . $factura['nr_factura']; ?></td>
                    <td><?php echo $factura['data_factura']; ?></td>
                    <td>
                        <form action="genereaza_miscari_factura.php" method="post" style="margin:0;">
                            <input type="hidden" name="id_factura" value="<?php echo $factura['id_factura']; ?>">
                            <button type="submit" class="btn-gen">Generează</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="success">✅ Nicio neconcordanță găsită. Toate facturile par să aibă mișcări corespunzătoare.</div>
    <?php endif; ?>
</div>
</body>
</html>