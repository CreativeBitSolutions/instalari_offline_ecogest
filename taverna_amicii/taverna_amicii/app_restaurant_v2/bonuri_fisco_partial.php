<?php
// bonuri_fisco_partial.php
session_start();
require_once __DIR__ . '/database_connection.php';
if (function_exists('restaurantIsOfflineSqlite') && restaurantIsOfflineSqlite()) {
  http_response_code(403);
  echo '<div class="alert alert-warning m-3">BonANSWER este dezactivat in modul SQLite offline.</div>';
  exit;
}

$clientId   = $_SESSION['client_id']   ?? null;
$locationId = $_SESSION['cod_locatie'] ?? null;
if (!$clientId || !$locationId) {
  http_response_code(403);
  echo '<div class="alert alert-danger m-3">Nu există client/locație în sesiune.</div>';
  exit;
}

$limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));

$base = RESTAURANT_OFFLINE_API_DIR . '/bonuri_procesate_fisco/' . basename((string)$clientId) . '/' . basename((string)$locationId);

$cats = ['BonOK','BonANSWER','BonERR'];
$found = [
  'BonOK'     => [],
  'BonANSWER' => [],
  'BonERR'    => [],
];

function safe_read_json($file, $maxBytes = 1048576) {
  $sz = @filesize($file);
  if ($sz === false || $sz > $maxBytes) return null;
  $raw = @file_get_contents($file);
  if ($raw === false) return null;
  $j = @json_decode($raw, true);
  return is_array($j) ? $j : null;
}

/* ===== Helpers BonANSWER → note (fără nZrep/DeviceSerial) ===== */

/**
 * Parsează conținut INI-like (key=value) din payload.continutul_fisierului.
 */
function parse_answer_kv($content) {
  $out = [];
  foreach (preg_split('/\R+/', (string)$content) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '[') continue;
    if (strpos($line, '=') !== false) {
      list($k, $v) = array_map('trim', explode('=', $line, 2));
      if ($k !== '') $out[$k] = $v;
    }
  }
  return $out;
}

/**
 * Convertește ISO (cu offset) la Europe/Bucharest (Y-m-d H:i:s).
 */
function to_local_ymdhis($iso) {
  if (!$iso) return null;
  try {
    $dt = new DateTime($iso);
    $dt->setTimezone(new DateTimeZone('Europe/Bucharest'));
    return $dt->format('Y-m-d H:i:s');
  } catch (Throwable $e) {
    return null;
  }
}

/**
 * Returnează candidații din `note` pentru o locație, într-o fereastră ±$windowMin minute
 * în jurul timestampului local $tsLocal. Sortează după diferența de timp absolută (ASC).
 */
function note_candidates_by_time(PDO $pdo, int $locatie, ?string $tsLocal, int $windowMin = 10): array {
  if (!$tsLocal) return [];
  $windowMin = max(1, min(60, (int)$windowMin));
  // Căutăm pe data_bon+ora_bon (indexabile); fallback pe data_deschidere dacă nu găsim
  $sqlBase = "
    SELECT nrbon, data_bon, ora_bon, fiscalizat, status, valoare_vanzare_cu_tva
    FROM note
    WHERE locatie = :loc
      AND CONCAT(data_bon,' ',ora_bon) BETWEEN (TIMESTAMP(:ts) - INTERVAL {$windowMin} MINUTE)
                                          AND (TIMESTAMP(:ts) + INTERVAL {$windowMin} MINUTE)
  ";
  $stmt = $pdo->prepare($sqlBase . " ORDER BY ABS(TIMESTAMPDIFF(SECOND, CONCAT(data_bon,' ',ora_bon), :ts)) ASC");
  $stmt->execute([':loc'=>$locatie, ':ts'=>$tsLocal]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($rows) return $rows;

  // Fallback (mai rar): încercăm pe data_deschidere (neindexat, dar fereastră mică)
  $sqlOpen = "
    SELECT nrbon, DATE(data_deschidere) AS data_bon, TIME(data_deschidere) AS ora_bon,
           fiscalizat, status, valoare_vanzare_cu_tva
    FROM note
    WHERE locatie = :loc
      AND data_deschidere BETWEEN (TIMESTAMP(:ts) - INTERVAL {$windowMin} MINUTE)
                              AND (TIMESTAMP(:ts) + INTERVAL {$windowMin} MINUTE)
    ORDER BY ABS(TIMESTAMPDIFF(SECOND, data_deschidere, :ts)) ASC
  ";
  $stmt2 = $pdo->prepare($sqlOpen);
  $stmt2->execute([':loc'=>$locatie, ':ts'=>$tsLocal]);
  return $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Alege „cel mai bun” candidat după preferință de fiscalizare:
 *  - dacă $errorCode > 0 → preferă fiscalizat=0, altfel cel mai apropiat în timp
 *  - dacă $errorCode = 0 → preferă fiscalizat=1, altfel cel mai apropiat în timp
 * Returnează [ $bestSauNull, $rows ].
 */
function pick_best_candidate(array $rows, ?int $errorCode, ?string $tsLocal): array {
  if (!$rows) return [null, []];
  $preferFisc = null;
  if ($errorCode !== null) {
    $preferFisc = ($errorCode > 0) ? 0 : 1;
  }
  if ($preferFisc !== null) {
    $filtered = array_values(array_filter($rows, fn($r) => (int)$r['fiscalizat'] === $preferFisc));
    if (count($filtered) === 1) return [$filtered[0], $rows];
    if (count($filtered) > 1)  return [$filtered[0], $rows]; // deja sortate după timp
  }
  return [$rows[0], $rows];
}

/**
 * Extrage un hint de eroare din content (prima linie cu 'eroare/error/...') sau primele 140 chars.
 */
function extract_error_hint($content) {
  if (preg_match('/^.*?(eroare|error|fail|cod\s*err|E\d{2,})[^\r\n]*/im', (string)$content, $m)) {
    return trim($m[0]);
  }
  $s = trim(mb_substr((string)$content, 0, 140));
  return $s !== '' ? $s . (mb_strlen((string)$content) > 140 ? '…' : '') : '';
}
/* ===== end helpers ===== */


// colectare fișiere
foreach ($cats as $cat) {
  $catDir = $base . DIRECTORY_SEPARATOR . $cat;
  if (!is_dir($catDir)) continue;

  if ($cat === 'BonOK') {
    // subfoldere pe extensie (inp/nrb/txt/other/noext) + fallback direct în BonOK
    $subdirs = @scandir($catDir) ?: [];
    foreach ($subdirs as $sd) {
      if ($sd === '.' || $sd === '..') continue;
      $full = $catDir . DIRECTORY_SEPARATOR . $sd;
      if (is_dir($full)) {
        foreach (glob($full . DIRECTORY_SEPARATOR . '*.json') as $f) {
          $found['BonOK'][] = [$f, @filemtime($f) ?: 0];
        }
      }
    }
    foreach (glob($catDir . DIRECTORY_SEPARATOR . '*.json') as $f) {
      $found['BonOK'][] = [$f, @filemtime($f) ?: 0];
    }
  } else {
    foreach (glob($catDir . DIRECTORY_SEPARATOR . '*.json') as $f) {
      $found[$cat][] = [$f, @filemtime($f) ?: 0];
    }
  }
}

// sortare desc după timp
foreach ($found as $k => $arr) {
  usort($arr, function($a,$b){ return $b[1] <=> $a[1]; });
  if (count($arr) > $limit) $arr = array_slice($arr, 0, $limit);
  $found[$k] = $arr;
}

function render_tab($items, $label) {
  if (!$items) {
    echo '<div class="p-3 text-muted">Nu există fișiere recente în această categorie.</div>';
    return;
  }
  echo '<div class="bf-scroll">';
  foreach ($items as [$file, $mtime]) {
    $j = safe_read_json($file);
    if (!$j) continue;

    $meta     = $j['meta']    ?? [];
    $payload  = $j['payload'] ?? [];
    $nume     = (string)($payload['nume_fisier'] ?? basename($file));
    $content  = (string)($payload['continutul_fisierului'] ?? '');
    $utcTime  = (string)($meta['utc_time'] ?? '');
    $ext      = (string)($meta['file_ext'] ?? pathinfo($nume, PATHINFO_EXTENSION));
    $whenIso  = $utcTime ?: date('c', $mtime); // ISO cu offset
    $relName  = basename($file); // fisier json salvat

    // === Corelare BonANSWER ↔ note DOAR dupa timp+locatie ===
    $auditBadge = '';
    $auditLine  = '';
    $pdo = $GLOBALS['pdo'] ?? null;

    if ($label === 'BonANSWER' && $pdo instanceof PDO) {
      $kv         = parse_answer_kv($content);
      $errorCode  = isset($kv['ErrorCode']) ? (int)$kv['ErrorCode'] : null;
      $tsLocal    = to_local_ymdhis($whenIso);

      $rows = note_candidates_by_time($pdo, (int)($_SESSION['cod_locatie'] ?? 0), $tsLocal, 10);
      [$match, $all] = pick_best_candidate($rows, $errorCode, $tsLocal);

      // Badge principal în funcție de ErrorCode
      if ($errorCode === null) {
        $auditBadge = '<span class="badge badge-secondary ml-2">RĂSPUNS NECUNOSCUT</span>';
      } elseif ($errorCode > 0) {
        $auditBadge = '<span class="badge badge-danger ml-2">EROARE ECR</span>';
      } else {
        $auditBadge = '<span class="badge badge-success ml-2">ECR OK</span>';
      }

      $errHint = htmlspecialchars(extract_error_hint($content), ENT_QUOTES, 'UTF-8');

      if ($match) {
        $fisc = (int)$match['fiscalizat'];
        $st   = htmlspecialchars((string)$match['status'], ENT_QUOTES, 'UTF-8');
        $nr   = (int)$match['nrbon'];

        // calculează delta timp pentru transparență
        $rowTs = $match['data_bon'].' '.$match['ora_bon'];
        $deltaSec = 0;
        try {
          $a = new DateTime($rowTs, new DateTimeZone('Europe/Bucharest'));
          $b = new DateTime($tsLocal, new DateTimeZone('Europe/Bucharest'));
          $deltaSec = abs($a->getTimestamp() - $b->getTimestamp());
        } catch (Throwable $e) {}

        $badgeFisc = $fisc ? '<span class="badge badge-success">FISCALIZAT</span>' : '<span class="badge badge-danger">NEFISCALIZAT</span>';

        $auditLine = '<div class="small text-muted mt-1">'
          . 'Candidat: <strong>note.nrbon='.$nr.'</strong> (status: '.$st.') — '.$badgeFisc
          . ' &nbsp; <span class="text-monospace">Δt ~ '.$deltaSec.'s</span>'
          . ($errHint ? ' &nbsp; <em>Hint:</em> '.$errHint : '')
          . '</div>';

        if (($errorCode ?? 0) > 0 && !$fisc) {
          $auditBadge = '<span class="badge badge-danger ml-2">EROARE &amp; NEFISCALIZAT</span>';
        } elseif (($errorCode ?? 0) === 0 && !$fisc) {
          // ECR OK dar bonul apare nefiscalizat → atenționare
          $auditBadge = '<span class="badge badge-warning ml-2">ECR OK / BON NEFISCALIZAT?</span>';
        }
      } else {
        $auditBadge = ($errorCode ?? 0) > 0
          ? '<span class="badge badge-warning ml-2">EROARE — NECORELAT</span>'
          : '<span class="badge badge-secondary ml-2">NECORELAT</span>';
        $auditLine  = '<div class="small text-muted mt-1">Nu am găsit bon în `note` în fereastra de timp.'
                    . ($errHint ? ' &nbsp; <em>Hint:</em> '.$errHint : '')
                    . '</div>';
      }
    }
    // === end corelare ===

    // atribut pentru filtrare client-side
    $dataSearch = htmlspecialchars(strtolower($nume . ' ' . $content), ENT_QUOTES, 'UTF-8');
    $safeName   = htmlspecialchars($nume, ENT_QUOTES, 'UTF-8');
    $safeWhen   = htmlspecialchars($whenIso, ENT_QUOTES, 'UTF-8'); // afișăm ISO (UTC+offset); se poate adăuga și local, la nevoie
    $safeExt    = htmlspecialchars($ext, ENT_QUOTES, 'UTF-8');
    $safeJson   = htmlspecialchars($relName, ENT_QUOTES, 'UTF-8');
    $safeCont   = htmlspecialchars($content, ENT_NOQUOTES, 'UTF-8');

    echo '<div class="bf-item" data-search="'.$dataSearch.'">';
      echo '<div class="bf-head">';
        echo '<div>';
          echo '<strong>'.$safeName.'</strong>';
          if (!empty($auditBadge)) echo ' '.$auditBadge;
          echo ' <span class="text-muted">['.$safeExt.'] — '.$safeWhen.'</span>';
          echo ' <small class="text-muted d-none d-md-inline">('.$safeJson.')</small>';
        echo '</div>';
        echo '<div>';
          echo '<button type="button" class="btn btn-sm btn-outline-secondary bf-copy" title="Copiază conținutul"><i class="far fa-copy"></i></button>';
        echo '</div>';
      echo '</div>';

      if (!empty($auditLine)) {
        echo '<div class="px-3 pt-2">'.$auditLine.'</div>';
      }

      echo '<pre class="bf-pre">'.$safeCont.'</pre>';
    echo '</div>';
  }
  echo '</div>';
}

$cntOK  = count($found['BonOK']);
$cntANS = count($found['BonANSWER']);
$cntERR = count($found['BonERR']);
?>

<!-- Tabs -->
<ul class="nav nav-tabs px-3 pt-3" role="tablist">
  <li class="nav-item">
    <a class="nav-link active" data-toggle="tab" href="#bf-ok" role="tab">
      BonOK <span class="badge badge-pill badge-secondary"><?= $cntOK ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" data-toggle="tab" href="#bf-answer" role="tab">
      BonANSWER <span class="badge badge-pill badge-secondary"><?= $cntANS ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" data-toggle="tab" href="#bf-err" role="tab">
      BonERR <span class="badge badge-pill badge-secondary"><?= $cntERR ?></span>
    </a>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="bf-ok" role="tabpanel">
    <?php render_tab($found['BonOK'], 'BonOK'); ?>
  </div>
  <div class="tab-pane fade" id="bf-answer" role="tabpanel">
    <?php render_tab($found['BonANSWER'], 'BonANSWER'); ?>
  </div>
  <div class="tab-pane fade" id="bf-err" role="tabpanel">
    <?php render_tab($found['BonERR'], 'BonERR'); ?>
  </div>
</div>

<script>
(function(){
  // Copiere în clipboard
  $('#bonuri_fisco_content').off('click.bfcopy').on('click.bfcopy', '.bf-copy', function(){
    var pre = $(this).closest('.bf-item').find('.bf-pre').get(0);
    if (!pre) return;
    var txt = pre.innerText || pre.textContent || '';
    navigator.clipboard.writeText(txt).then(function(){
      $(pre).closest('.bf-item').find('.bf-head .bf-copy').addClass('btn-success').removeClass('btn-outline-secondary');
      setTimeout(function(){
        $(pre).closest('.bf-item').find('.bf-copy').removeClass('btn-success').addClass('btn-outline-secondary');
      }, 700);
    });
  });

  // Filtru live
  $('#bf_filter').off('input.bff').on('input.bff', function(){
    var q = (this.value || '').toLowerCase().trim();
    $('.bf-item').each(function(){
      var ok = !q || (($(this).data('search') || '').indexOf(q) !== -1);
      $(this).toggle(ok);
    });
  });
})();
</script>
