<?php
// verifica_note.php
include('session.php'); // Asigură-te că stabilește conexiunea $pdo

// Preluăm eventualul interval selectat
$data_start = isset($_GET['data_start']) && $_GET['data_start'] !== '' ? $_GET['data_start'] : null;
$data_end   = isset($_GET['data_end'])   && $_GET['data_end']   !== '' ? $_GET['data_end']   : null;

// Construim SQL cu filtre dinamice
$sql = "
    SELECT n.nrbon, n.data_bon, n.valoare_vanzare_cu_tva 
    FROM note n
    LEFT JOIN miscari m ON n.nrbon = m.nr_doc AND m.fel_doc = 'BF'
    WHERE m.id IS NULL AND n.status = 'F'
";

$params = [];

if ($data_start !== null) {
    $sql .= " AND n.data_bon >= :data_start";
    $params['data_start'] = $data_start;
}

if ($data_end !== null) {
    $sql .= " AND n.data_bon <= :data_end";
    $params['data_end'] = $data_end;
}

$sql .= " ORDER BY n.data_bon, n.nrbon";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bonuri_lipsa = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Reparare Mișcări Bonuri Fiscale</title>
    <style>
        body { font-family: sans-serif; margin: 2em; background-color: #f4f4f9; color: #333; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; color: #4a4a4a; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        button { padding: 8px 12px; cursor: pointer; border: none; border-radius: 4px; color: #fff; }
        .btn-gen { background-color: #007bff; }
        .btn-gen-all { background-color: #28a745; display: block; width: 100%; text-align: center; padding: 15px; font-size: 1.2em; text-decoration: none; margin-bottom: 20px; }
        .success, .error { padding: 15px; border-radius: 5px; margin-bottom: 1em; text-align: center; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            margin: 20px 0;
        }
        .filters label {
            font-size: 0.9em;
            color: #555;
        }
        .filters input[type="date"] {
            padding: 6px 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Reparare Mișcări Bonuri Fiscale (BF)</h1>

    <?php if (isset($_GET['status'])): ?>
        <div class="<?php echo $_GET['status'] == 'succes' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($_GET['mesaj']); ?>
        </div>
    <?php endif; ?>

    <!-- Filtru după interval de date -->
    <form method="get" class="filters">
        <div>
            <label for="data_start">De la data:</label><br>
            <input type="date" name="data_start" id="data_start"
                   value="<?php echo htmlspecialchars($data_start ?? ''); ?>">
        </div>
        <div>
            <label for="data_end">Până la data:</label><br>
            <input type="date" name="data_end" id="data_end"
                   value="<?php echo htmlspecialchars($data_end ?? ''); ?>">
        </div>
        <div>
            <button type="submit" class="btn-gen">Filtrează</button>
        </div>
    </form>

    <?php if ($data_start || $data_end): ?>
        <p><strong>Interval curent:</strong>
            <?php echo $data_start ? 'de la ' . htmlspecialchars($data_start) : '…'; ?>
            –
            <?php echo $data_end ? 'până la ' . htmlspecialchars($data_end) : '…'; ?>
        </p>
    <?php endif; ?>

    <?php if (count($bonuri_lipsa) > 0): ?>
        <!-- Acum butonul "toate" va genera DOAR pentru intervalul filtrat -->
        <a href="genereaza_toate_miscarile_note.php?data_start=<?php echo urlencode($data_start ?? ''); ?>&data_end=<?php echo urlencode($data_end ?? ''); ?>"
           class="btn-gen-all"
           onclick="return confirm('ATENȚIE! Această acțiune va încerca să genereze mișcări pentru TOATE cele <?php echo count($bonuri_lipsa); ?> bonuri listate în intervalul selectat. Poate dura câteva minute. Ești sigur?');">
            🚀 Generează Mișcări pentru Toate Bonurile Lipsă din Interval
        </a>

        <h2>Sau generează individual:</h2>
        <table>
            <thead>
                <tr>
                    <th>Nr. Bon</th>
                    <th>Data</th>
                    <th>Valoare</th>
                    <th>Acțiune</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bonuri_lipsa as $bon): ?>
                <tr>
                    <td><?php echo $bon['nrbon']; ?></td>
                    <td><?php echo $bon['data_bon']; ?></td>
                    <td><?php echo number_format($bon['valoare_vanzare_cu_tva'], 2); ?> RON</td>
                    <td>
                        <form action="genereaza_miscari_nota.php" method="post" style="margin:0;">
                            <input type="hidden" name="nr_bon" value="<?php echo $bon['nrbon']; ?>">
                            <button type="submit" class="btn-gen">Generează</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="success">✅ Nicio neconcordanță găsită pentru criteriile alese. Toate bonurile fiscale au mișcări corespunzătoare.</div>
    <?php endif; ?>
</div>
</body>
</html>
