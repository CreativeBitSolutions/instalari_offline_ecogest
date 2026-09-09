<?php
$activeNav = (string) ($activeNav ?? '');
?>
<nav class="main-navigation" aria-label="Navigare principala">
    <a class="<?= $activeNav === 'documents' ? 'active' : '' ?>" href="index.php">Documente si configurare</a>
    <a class="<?= $activeNav === 'reports' ? 'active' : '' ?>" href="report.php">Rapoarte operatori</a>
</nav>
