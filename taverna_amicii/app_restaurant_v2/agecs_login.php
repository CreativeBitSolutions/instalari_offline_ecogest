<?php
// ------------------------------
// Modern responsive login page  
// (v2 – grid stânga + keypad dreapta)
// ------------------------------

// --- INITIAL PHP SETUP (nemodificat) ---

date_default_timezone_set('UTC+2');
date_default_timezone_set("Europe/Bucharest");

session_start();
include('database_connection.php');
require_once __DIR__ . '/offline_products_guard.php';

if ((!function_exists('restaurantIsOfflineSqlite') || !restaurantIsOfflineSqlite()) && !isset($_SESSION['client_id'])) {
    header("Location: ../conectare.php");
    exit();
}

// dacă există un bon activ îl resetăm
$productsSyncGuard = opg_check_products_sync($pdo, $restaurantConfig ?? []);
$productsSyncStatus = (string)($productsSyncGuard['status'] ?? '');
$productsNeedsAcknowledgement = $productsSyncStatus === 'products_changed';
$productsLoginBlocked = empty($productsSyncGuard['allow']) && !$productsNeedsAcknowledgement;

if (isset($_SESSION['nr_bon'])) {
    unset($_SESSION['nr_bon']);
}

$cust_id = 12; // rămâne neschimbat
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autentificare utilizator</title>

    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">

    <!-- Stiluri personalizate -->
    <style>
        body {
            background:#2f3640;
            overflow-x:hidden;
        }
        .logo {
            max-width:200px;
        }
        /* grid-ul de utilizatori*/
        .users-wrapper{
            max-height:75vh; /* scrollează utilizatorii dacă sunt mulți */
            overflow-y:auto;
        }
        .user-btn {
            background:#ffffff;
            border:2px solid #ced4da;
            border-radius:.75rem;
            transition:all .15s ease-in-out;
            width:100%;
        }
        .user-btn img {
            width:80px;
            height:80px;
            object-fit:contain;
        }
        .user-btn:hover {
            border-color:#0d6efd;
            transform:translateY(-3px);
        }
        .user-btn.active {
            border-color:#0d6efd;
            box-shadow:0 0 0 .25rem rgba(13,110,253,.25);
        }
        .user-name {
            font-size:1.2rem;
            font-weight:600;
            margin-top:.5rem;
            min-height:2.3rem;
            color:#ffffff;
        }
        .connected-msg {
            font-size:.75rem;
        }
        /* keypad */
        #keypadCard {
            display:none; /* ascuns până la selectare */
        }
        #keypad .btn {
            font-size:1.45rem;
            padding:1.2rem 0;
        }
        #keypad .btn-submit {
            font-size:1.1rem;
            font-weight:600;
        }
        .products-diff-head {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            margin-bottom:1rem;
        }
        .products-diff-head h4 {
            flex:1;
            margin:0;
            text-align:center;
        }
        .products-diff-head::before {
            content:"";
            width:158px;
        }
        .products-diff-head .btn {
            white-space:nowrap;
        }
        @media (max-width: 575.98px) {
            .products-diff-head {
                flex-direction:column;
            }
            .products-diff-head::before {
                display:none;
            }
        }
    </style>
</head>
<body>
<div class="container py-4">
    <!-- logo + info + buton back -->
    <div class="d-flex flex-column align-items-center text-center text-white mb-4">
        
        <h5 class="mb-2">Locație <?php echo $_SESSION['cod_locatie']; ?></h5>
        <a class="btn btn-outline-light btn-sm" href="offline_products_sync.php?force=1&rewrite_existing=1">Sincronizare Produse</a>
    </div>

    <!---------------------- ZONA PRINCIPALĂ: GRID + KEYPAD ---------------------->
    <?php if (!$productsLoginBlocked && !$productsNeedsAcknowledgement && !empty($productsSyncGuard['message'])): ?>
    <?php
        $productsSyncAlert = $productsSyncStatus === 'check_error' ? 'warning' : 'success';
    ?>
    <div class="row justify-content-center mb-3">
        <div class="col-12 col-lg-8">
            <div class="alert alert-<?php echo $productsSyncAlert; ?> text-center mb-0" role="alert">
                <?php echo htmlspecialchars((string)$productsSyncGuard['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($productsNeedsAcknowledgement): ?>
    <?php
        $productsDiffStats = isset($productsSyncGuard['diff_stats']) && is_array($productsSyncGuard['diff_stats'])
            ? $productsSyncGuard['diff_stats']
            : [];
        $productsDiffProducts = isset($productsDiffStats['products']) && is_array($productsDiffStats['products'])
            ? $productsDiffStats['products']
            : [];
        $productsOnlineFound = (int)($productsDiffProducts['received'] ?? ($productsSyncGuard['products_count'] ?? 0));
        $productsMissing = (int)($productsDiffProducts['missing'] ?? 0);
        $productsDifferent = (int)($productsDiffProducts['different'] ?? 0);
        $productsUnchanged = (int)($productsDiffProducts['unchanged'] ?? 0);
        $lookupChanged = (int)($productsDiffStats['lookup_changed'] ?? 0);
    ?>
    <div class="row justify-content-center mb-3" id="productsDiffNotice">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-warning">
                <div class="card-body text-center">
                    <div class="products-diff-head">
                        <h4 class="text-warning">Diferențe produse</h4>
                        <a class="btn btn-success" href="offline_products_sync.php?force=1&rewrite_existing=1">Sincronizare Produse</a>
                    </div>
                    <p class="mb-3">
                        Există diferențe între produsele offline și produsele online.
                    </p>
                    <div class="row g-2 justify-content-center mb-3">
                        <div class="col-6 col-md-3">
                            <div class="border rounded bg-light p-2 h-100">
                                <div class="small text-muted">Produse diferite</div>
                                <div class="fw-bold fs-5"><?php echo $productsDifferent; ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded bg-light p-2 h-100">
                                <div class="small text-muted">Produse lipsă offline</div>
                                <div class="fw-bold fs-5"><?php echo $productsMissing; ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded bg-light p-2 h-100">
                                <div class="small text-muted">Neschimbate</div>
                                <div class="fw-bold fs-5"><?php echo $productsUnchanged; ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded bg-light p-2 h-100">
                                <div class="small text-muted">Produse online găsite</div>
                                <div class="fw-bold fs-5"><?php echo $productsOnlineFound; ?></div>
                            </div>
                        </div>
                        <?php if ($lookupChanged > 0): ?>
                        <div class="col-6 col-md-3">
                            <div class="border rounded bg-light p-2 h-100">
                                <div class="small text-muted">Date auxiliare diferite</div>
                                <div class="fw-bold fs-5"><?php echo $lookupChanged; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        <button type="button" class="btn btn-warning" id="productsDiffAcknowledge">Am înțeles</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($productsLoginBlocked): ?>
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-danger">
                <div class="card-body text-center">
                    <h4 class="text-danger mb-3">Acces blocat</h4>
                    <p class="mb-3">
                        <?php echo htmlspecialchars((string)($productsSyncGuard['message'] ?? 'Nomenclatorul local nu este sincronizat.'), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <?php if (!empty($productsSyncGuard['products_count'])): ?>
                        <p class="text-muted mb-3">Produse online: <?php echo (int)$productsSyncGuard['products_count']; ?></p>
                    <?php endif; ?>
                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        <a class="btn btn-outline-secondary" href="agecs_login.php">Reverifică</a>
                        <a class="btn btn-success" href="offline_products_sync.php?force=1&rewrite_existing=1">Sincronizare Produse</a>
                    </div>
                    <p class="text-muted mt-3 mb-0">După sincronizare, revino la login sau apasă Reverifică.</p>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-4 justify-content-center <?php echo $productsNeedsAcknowledgement ? 'd-none' : ''; ?>" id="loginUsersArea">
        <!-- COL STÂNGA: utilizatorii -->
        <div class="col-12 col-lg-7 users-wrapper">
            <div class="row g-3">
                <?php
                    $ul = $cust_id;
                    $tabel_admins = 'admins' . '_' . $ul;
                    $cod_locatie = $_SESSION['cod_locatie'];

                    if ($_SESSION['d'] == 1) {
                        $dsql = "SELECT * FROM $tabel_admins WHERE rank NOT IN ('administrator', 'client', 'tableta') AND locatie='$cod_locatie' AND lucreaza_la='restaurant'";
                    } else {
                        $dsql = "SELECT * FROM $tabel_admins WHERE locatie='$cod_locatie' AND lucreaza_la='restaurant' AND rank NOT IN ('client', 'tableta')";
                    }
                    $dstmt = $pdo->prepare($dsql);
                    $dstmt->execute();

                    while ($row = $dstmt->fetch(PDO::FETCH_ASSOC)) {
                        $id              = $row['admin_id'];
                        $admin_firstname = $row['admin_firstname'];
                        $admin_lastname  = $row['admin_lastname'];
                        $rank            = $row['rank'];
                        $conectat        = $row['conectat'];

                        $disabled       = ($conectat == 1) ? 'disabled' : '';
                        $mesajConectat  = ($conectat == 1) ? "<div class='text-danger fw-bold connected-msg'>UTILIZATOR DEJA CONECTAT</div>" : '';

                        // avatar-ul în funcție de rang
                        switch ($rank) {
                            case 'bucatar':        $avatar = 'images/chef.jpg';      break;
                            case 'ospatar':        $avatar = 'images/waiter.png';    break;
                            case 'sefsala':        $avatar = 'images/waiter.png';    break;
                            case 'barman':         $avatar = 'images/barman.png';    break;
                            case 'administrator':  $avatar = 'images/hombre.jpg';    break;
                            default:               $avatar = 'images/operator1.jpg'; break; // operator
                        }

                         $ln = trim($admin_lastname);
                        $fullName = ($ln !== '' && $ln !== '-') ? "$admin_firstname $ln" : $admin_firstname;
                        $label = $fullName;
                        
                ?>
                    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                        <button type="button" value="<?php echo $id; ?>" class="my_button user-btn <?php echo $disabled; ?>">
                            <img src="<?php echo $avatar; ?>" alt="avatar" class="img-fluid">
                        </button>
                        <div class="user-name text-white">
                            <?php echo $label; ?>
                        </div>
                        <?php echo $mesajConectat; ?>
                    </div>
                <?php } ?>
            </div>
        </div><!-- /col utilizatori -->

        <!-- COL DREAPTA: keypad -->
        <div class="col-12 col-lg-5" id="keypadCol">
            <form method="POST" action="admin_logincheck.php" id="keypadCard" class="card shadow-sm">
                <input type="hidden" name="oper" value="" />
                <div class="card-body">
                    <div class="mb-3">
                        <input type="password" maxlength="10" name="calc_result" id="calc_result" class="form-control form-control-lg text-center" placeholder="PIN" autocomplete="off" />
                    </div>
                    <div id="keypad" class="d-grid gap-2">
                        <div class="row g-2">
                            <?php
                                // taste cifre 7-9 4-6 1-3
                                $digits = [7,8,9,4,5,6,1,2,3];
                                foreach ($digits as $index => $d) {
                                    if ($index % 3 == 0) echo '</div><div class="row g-2">';
                                    echo "<div class='col-4'><button type='button' class='btn btn-secondary w-100' onclick='add_calc(\"calc\",$d);'>$d</button></div>";
                                }
                            ?>
                        </div>
                        <!-- rândul 0 + backspace + submit -->
                        <div class="row g-2 mt-2">
                            <div class="col-4">
                                <button type="button" class="btn btn-secondary w-100" onclick="add_calc('calc',0);">0</button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-warning w-100" onclick="f_calc('calc','nbs');">&larr;</button>
                            </div>
                            <div class="col-4">
                                <button type="submit" name="continua" class="btn btn-success w-100 btn-submit">Continuă &#10004;</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div><!-- /col keypad -->
    </div><!-- /row principal -->
    <?php endif; ?>

    <!-- mesaj eroare backend -->
    <h4 class="text-danger text-center mt-3">
        <?php echo isset($_SESSION['error']) ? $_SESSION['error'] : ''; ?>
    </h4>
</div><!-- /container -->

<script src="js/jquery-3.6.0.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- logica de interacțiune -->
<script>
    $(function(){
        //  Resetăm memoria pentru auto-login la încărcarea paginii de login ===
        if (window.sessionStorage) {
            sessionStorage.removeItem('login_auto_failed');
        }

        $('#productsDiffAcknowledge').on('click', function(){
            $('#productsDiffNotice').fadeOut(150, function(){
                $('#loginUsersArea').removeClass('d-none').hide().fadeIn(150);
            });
        });

        // selectare utilizator
        $('.my_button').on('click', function(){
            if($(this).prop('disabled')) return; // nu face nimic dacă e disabled
            $('.my_button').removeClass('active');
            $(this).addClass('active');
            // setăm id utilizator
            $('[name=oper]').val($(this).val());
            // afișăm keypad
            $('#keypadCard').fadeIn();
            // focus PIN
            $('#calc_result').val('').focus();
            // scroll to top of keypad on mobile
            if(window.innerWidth < 992){
                $('html,body').animate({scrollTop: $('#keypadCol').offset().top - 20}, 400);
            }
        });
    });

    // funcții simple pt tastatură numerică
    function $id(id){ return document.getElementById(id); }
    function add_calc(id,n){
        var inp = $id('calc_result');
        inp.value += n;
        inp.focus();
    }
    function f_calc(id,n){
        if(n=='nbs'){
            var inp = $id('calc_result');
            inp.value = inp.value.slice(0,-1);
            inp.focus();
        }
    }
</script>
<script>
    
  (function(){
    // La fiecare 5 minute trimit un POST către keep_alive.php
    const KEEP_ALIVE_INTERVAL = 5 * 60 * 1000;

    function keepSessionAlive() {
      fetch('keep_alive.php', {
        method: 'POST',
        credentials: 'same-origin'  // pentru a trimite și cookie‑ul de sesiune
      })
      .then(res => {
        if (!res.ok) console.error('Keep‑alive failed:', res.statusText);
      })
      .catch(err => console.error('Eroare keep‑alive:', err));
    }

    // Prima invocare după încărcare
    keepSessionAlive();
    // Recurring
    setInterval(keepSessionAlive, KEEP_ALIVE_INTERVAL);
  })();
</script>

<script src="offline_sync_heartbeat.js"></script>

</body>
</html>
