<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['admin_id'])) {
  http_response_code(401);
  exit('Sesiune invalida.');
}

include 'db.php';

if (!isset($_GET['categ'])) {
  exit;
}

$categ = $_GET['categ'];
$query = "SELECT nr_nir FROM $tabel_final_nir";
$params = [];

if ((string) $categ !== '999') {
  $query .= " WHERE cod_tert = :cod_tert";
  $params[':cod_tert'] = $categ;
}

$query .= ' ORDER BY CAST(nr_nir AS INTEGER) DESC, nr_nir DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$produse = '';
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $nrnir = $row['nr_nir'];
  $nrnirEscaped = htmlspecialchars($nrnir, ENT_QUOTES, 'UTF-8');
  $produse .= "<tr>
   <td width='700'><form method='POST'><button style='color:white;width:100%; font-size:1em; height:auto; white-space: normal;' class='btn btn-primary btn-block' type='submit' name='$nrnirEscaped'>Nir $nrnirEscaped</button></form></td>
  </tr>";

  if (isset($_POST[$nrnir])) {
    $_SESSION['nr_nir'] = $nrnir;
    printf("<script>location.href='fisa_furnizor.php'</script>");
  }
}

echo $produse;

?>
