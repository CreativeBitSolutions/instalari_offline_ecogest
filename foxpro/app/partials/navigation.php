<?php
$activeNav = (string) ($activeNav ?? '');
?>
<nav class="main-navigation" aria-label="Navigare principala">
    <a class="<?= $activeNav === 'documents' ? 'active' : '' ?>" href="index.php">Documente si configurare</a>
    <a class="<?= $activeNav === 'reports' ? 'active' : '' ?>" href="report.php">Rapoarte operatori</a>
    <a class="<?= $activeNav === 'printer_report' ? 'active' : '' ?>" href="product_report_print.php">Trimite raport produse la imprimanta</a>
    <a class="<?= $activeNav === 'transactions' ? 'active' : '' ?>" href="transactions.php">Tranzactii note</a>
</nav>
