<?php
$offlineLoginClosures = offline_pending_closures($pdo, (int)($_SESSION['cod_locatie'] ?? 0));
if ($offlineLoginClosures):
    $offlineLoginReceipts = array_sum(array_map(static function (array $closure): int {
        return (int)$closure['bonuri'];
    }, $offlineLoginClosures));
    $offlineLoginOldest = $offlineLoginClosures[0];
?>
<div style="margin:12px 0 4px;padding:10px 12px;border:1px solid #f59e0b;border-left:5px solid #d97706;background:#fff7ed;color:#7c2d12;border-radius:5px;">
    <strong><?php echo count($offlineLoginClosures); ?> ture așteaptă raportul Z</strong>
    <div style="font-size:13px;margin-top:3px;">
        <?php echo $offlineLoginReceipts; ?> bonuri. Prima tură a fost închisă la
        <?php echo htmlspecialchars(trim((string)$offlineLoginOldest['data_inchiderii'] . ' ' . (string)$offlineLoginOldest['ora_inchiderii']), ENT_QUOTES, 'UTF-8'); ?>.
        După autentificare, folosiți butonul Raport Z.
    </div>
</div>
<?php endif; ?>
