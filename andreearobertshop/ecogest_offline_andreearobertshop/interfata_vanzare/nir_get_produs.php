<?php
// nir_get_produs.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
  http_response_code(401);
  exit('Sesiune invalidă.');
}

include 'db.php';

$q = intval($_GET['q']);

// înlocuiește SELECT-ul simplu cu:
$sql = "
  SELECT ps.*, g.denumire_gestiune
  FROM produse_servicii ps
  INNER JOIN gestiuni g ON ps.id_gestiune = g.id_gestiune
  WHERE ps.cod_produs = :cod_produs
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['cod_produs' => $q]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if ($product) {
    $id_produs     = $product['cod_produs'];
    $pret_achiz    = $product['pret_achizitie'];
    $pret_vanzare  = $product['pret_cu_tva'];
    $um            = $product['um'];

    // Separăm clar cotele:
    $cota_tva_vanzare = (float)($product['cota_tva'] ?? 0);   // TVA de VÂNZARE (din catalog)
    $cota_tva_achiz   = $cota_tva_vanzare;                    // Implicit pentru select (userul poate schimba)

    // Presupunem că valoarea implicită a cantității primite este 1
    $cantitatePrimita = 1;
    $val_fara_tva     = $pret_achiz * $cantitatePrimita;

    // Preț vânzare fără TVA doar informativ (bazat pe COTA DE VÂNZARE)
    $pret_vanzare_fara_tva = ($cota_tva_vanzare > 0)
        ? $pret_vanzare / (1 + ($cota_tva_vanzare / 100))
        : $pret_vanzare;

    $adaos_comercial = 0;
    if ($pret_achiz > 0) {
        $adaos_comercial = (($pret_vanzare_fara_tva - $pret_achiz) / $pret_achiz) * 100;
    }

    $den_gestiune = $product['denumire_gestiune'];

echo "
<div class='form-group'>
  <label class='form-label'>UM: {$um}</label>
  <div class='small text-muted'>Preț cu TVA curent (catalog): {$pret_vanzare}</div>
  <div class='small text-muted'>Cota TVA de vânzare (catalog): <b>{$cota_tva_vanzare}%</b></div>
  <input type='hidden' id='den_gestiune' value='".htmlspecialchars($den_gestiune, ENT_QUOTES)."' />
  <input type='hidden' id='cota_tva_vanzare' value='".htmlspecialchars($cota_tva_vanzare, ENT_QUOTES)."' />
</div>
<div class='form-group camp-principal'>
  <label for='pret_achiz' class='form-label'>Preț de achiziție (fără TVA)</label>
  <input type='number' class='form-control' name='pret_achiz' id='pret_achiz' value='{$pret_achiz}' step='0.00001' required>
</div>
<input type='hidden' name='initial_pret_achiz' id='initial_pret_achiz' value='{$pret_achiz}'>";

    // construim dinamic lista de cote TVA; pentru client_id = 999 adaugam 9% si 19%
    $tva_rates = [11, 0, 21]; // default
    if (isset($_SESSION['client_id']) && intval($_SESSION['client_id']) === 999) {
        $tva_rates = [11, 9, 0, 19, 21];
    }

echo "
<div class='form-group camp-principal'>
  <label for='cota_tva_achiz' class='form-label'>Cota TVA de achiziție</label>
  <select class='form-select' name='cota_tva_achiz' id='cota_tva_achiz' required>";
    foreach ($tva_rates as $rate) {
        $selected = ((int)$cota_tva_achiz === (int)$rate) ? "selected" : "";
        echo "<option value='{$rate}' {$selected}>{$rate}%</option>";
    }
echo "</select>
</div>

<div class='form-group camp-principal'>
  <label for='cant_doc' class='form-label'>Cantitate de primit (document)</label>
  <input type='number' class='form-control' name='cant_doc' id='cant_doc' value='1' step='0.00001' required>
</div>

<div class='form-group camp-principal'>
  <label for='cant_prim' class='form-label'>Cantitate primită</label>
  <input type='number' class='form-control' name='cant_prim' id='cant_prim' value='1' step='0.00001' required>
</div>

<div class='form-group camp-principal'>
  <label for='val_fara_tva' class='form-label'>Valoare achiziție (fără TVA)</label>
  <input type='number' class='form-control' name='val_fara_tva' id='val_fara_tva' step='0.00001' required>
</div>

<div class='form-group camp-principal'>
  <label for='tva_unitar_achizitie' class='form-label'>TVA unitar achiziție</label>
  <input type='number' class='form-control' name='tva_unitar_achizitie' id='tva_unitar_achizitie' step='0.00001'>
</div>

<div class='form-group camp-principal'>
  <label for='valoare_tva_achizitie' class='form-label'>TVA total achiziție</label>
  <input type='number' class='form-control' name='valoare_tva_achizitie' id='valoare_tva_achizitie' step='0.00001'>
</div>

<div class='form-group camp-principal'>
  <label for='valoare_achizitie_cu_tva' class='form-label'>Valoare achiziție cu TVA</label>
  <input type='number' class='form-control' name='valoare_achizitie_cu_tva' id='valoare_achizitie_cu_tva' step='0.00001'>
</div>

<div class='form-group camp-principal'>
  <label for='pret_cu_tva' class='form-label'>Preț vânzare (cu TVA) nou</label>
  <input type='number' class='form-control' name='pret_cu_tva' id='pret_cu_tva' value='{$pret_vanzare}' step='0.01' required>
</div>

<div class='form-group camp-secundar'>
  <label for='procent_adaos' class='form-label'>Adaos comercial (%)</label>
  <input type='number' class='form-control' name='procent_adaos' id='procent_adaos' step='0.00001'>
</div>

<div class='form-group camp-secundar'>
  <label for='adaos_unitar' class='form-label'>Adaos unitar</label>
  <input type='number' class='form-control' name='adaos_unitar' id='adaos_unitar' step='0.00001'>
</div>

<div class='form-group camp-secundar'>
  <label for='valoare_adaos' class='form-label'>Valoare adaos</label>
  <input type='number' class='form-control' name='valoare_adaos' id='valoare_adaos' step='0.00001'>
</div>

<div class='form-group camp-secundar' style='display:none;'>
  <label for='pret_cu_adaos_unitar_fara_tva' class='form-label'>Preț unitar cu amănuntul (fără TVA)</label>
  <input type='number' class='form-control' name='pret_cu_adaos_unitar_fara_tva' id='pret_cu_adaos_unitar_fara_tva' step='0.00001'>
</div>

<div class='form-group camp-secundar'>
  <label for='tva_adaos_comercial' class='form-label'>TVA adaos comercial (unitar)</label>
  <input type='number' class='form-control' name='tva_adaos_comercial' id='tva_adaos_comercial' step='0.00001'>
</div>

<div class='form-group camp-secundar'>
  <label for='tva_total_unitar' class='form-label'>TVA total unitar (achiziție + adaos)</label>
  <input type='number' class='form-control' name='tva_total_unitar' id='tva_total_unitar' step='0.00001'>
</div>

<div class='form-group camp-secundar'>
  <label for='valoare_tva_totala' class='form-label'>Valoare TVA totală</label>
  <input type='number' class='form-control' name='valoare_tva_totala' id='valoare_tva_totala' step='0.00001'>
</div>

<div class='form-group camp-secundar'>
  <label for='pret_unitar_cu_amanuntul_cu_tva' class='form-label'>Preț unitar cu amănuntul (cu TVA)</label>
  <input type='number' class='form-control' name='pret_unitar_cu_amanuntul_cu_tva' id='pret_unitar_cu_amanuntul_cu_tva' step='0.00001'>
</div>

<div class='form-group camp-secundar'>
  <label for='valoare_pret_amanunt' class='form-label'>Valoare la preț cu amănuntul</label>
  <input type='number' class='form-control' name='valoare_pret_amanunt' id='valoare_pret_amanunt' step='0.00001'>
</div>

<script>
(function(){
  const get = (id)=>document.getElementById(id);
  const num = (v)=>{ v=parseFloat(v); return isNaN(v)?0:v; };
  const set = (id,val,fix=5)=>{ const el=get(id); if(!el) return; el.value = (typeof val==='number') ? val.toFixed(fix) : val; };

  function recalc(){
    const denGestiune  = (get('den_gestiune')?.value||'').toUpperCase();
    const cant         = num(get('cant_prim')?.value||'1');
    const pretAchiz    = num(get('pret_achiz')?.value||'0');        // (4)
    const cotaAchiz    = num(get('cota_tva_achiz')?.value||'0');    // TVA ACHIZIȚIE
    const cotaVanz     = num(get('cota_tva_vanzare')?.value||'0');  // TVA VÂNZARE (catalog)
    const pretVanzCTVA = num(get('pret_cu_tva')?.value||'0');       // țintă pentru (14), dacă e dată
    const pInput       = num(get('procent_adaos')?.value||'0');     // input manual, dacă vrei

    const rA = cotaAchiz/100;
    const rV = cotaVanz/100;

    // === ACHIZIȚIE (Col 4–8) ===
    const valFTVA      = cant * pretAchiz;              // (5) = 3*4
    const tvaUnitarAch = pretAchiz * rA;                // (6)
    const valTVAAch    = cant * tvaUnitarAch;           // (7) = 3*6
    const valAchCTVA   = valFTVA + valTVAAch;           // (8) = 5+7

    set('val_fara_tva', valFTVA);
    set('tva_unitar_achizitie', tvaUnitarAch);
    set('valoare_tva_achizitie', valTVAAch);
    set('valoare_achizitie_cu_tva', valAchCTVA);

    // === VÂNZARE (Col 9–16) — folosim EXCLUSIV cotaVanz pentru TVA pe adaos ===
    if (denGestiune.includes('MARFURI') || denGestiune.includes('SGR')) {
      // determinăm procent_adaos în două moduri:
      // a) dacă există P(14) introdus (pret_cu_tva), îl respectăm și aflăm procentul
      //    Formula: 14 = 4 + 6 + 10 + 13, iar 10 = 4 * p/100, 13 = 10 * rV
      //    => 14 = 4 + (4*rA) + (4*p/100) + (4*p/100*rV)
      //    => 14 = 4*(1 + rA) + 4*(p/100)*(1 + rV)
      //    => p = ( (14 - 4*(1+rA)) / (4*(1+rV)) ) * 100
      let procentAdaos = 0;
      if (pretVanzCTVA > 0 && pretAchiz > 0) {
        const baseCTVA = pretAchiz * (1 + rA);
        const denom    = pretAchiz * (1 + rV);
        if (denom > 0) {
          procentAdaos = ((pretVanzCTVA - baseCTVA) / denom) * 100;
          if (!isFinite(procentAdaos)) procentAdaos = 0;
        }
        if (procentAdaos < 0) procentAdaos = 0;
        set('procent_adaos', procentAdaos); // sincronizăm câmpul vizual
      } else if (pInput > 0) {
        // b) fără P(14), dacă utilizatorul a dat procentul manual
        procentAdaos = pInput;
      }

      // (10) Adaos unitar = 4 * 9 / 100
      const adaosUnitar = pretAchiz * (procentAdaos/100);

      // (11) Valoare adaos = 3 * 10
      const valAdaos    = cant * adaosUnitar;

      // (12) Preț unitar cu amănuntul FĂRĂ TVA = 4 + 10
      const pretAmanuntFTVA = pretAchiz + adaosUnitar;

      // (13) TVA unitar aferent adaos = 10 * rV
      const tvaAdaos        = adaosUnitar * rV;

      // (16) TVA total unitar = 6 + 13
      const tvaTotalUnitar  = tvaUnitarAch + tvaAdaos;

      // (14) Preț unitar cu amănuntul CU TVA = 4 + 6 + 10 + 13
      const pretAmanuntCTVA = pretAchiz + tvaUnitarAch + adaosUnitar + tvaAdaos;

      // (15) Valoare la preț cu amănuntul = 3 * 14
      const valAmanunt      = cant * pretAmanuntCTVA;

      // Valoare TVA totală = 3 * 16
      const valTVATotal     = cant * tvaTotalUnitar;

      set('adaos_unitar', adaosUnitar);
      set('valoare_adaos', valAdaos);
      set('pret_cu_adaos_unitar_fara_tva', pretAmanuntFTVA);
      set('tva_adaos_comercial', tvaAdaos);
      set('tva_total_unitar', tvaTotalUnitar);
      set('pret_unitar_cu_amanuntul_cu_tva', pretAmanuntCTVA);
      set('valoare_pret_amanunt', valAmanunt);
      set('valoare_tva_totala', valTVATotal);
    } else {
      // gestiuni fără adaos
      set('procent_adaos', 0);
      set('adaos_unitar', 0);
      set('valoare_adaos', 0);
      set('pret_cu_adaos_unitar_fara_tva', 0);
      set('tva_adaos_comercial', 0);
      set('tva_total_unitar', 0);
      set('pret_unitar_cu_amanuntul_cu_tva', 0);
      set('valoare_pret_amanunt', 0);
      set('valoare_tva_totala', 0);
    }
  }

  // recalculăm când se schimbă intrările driver:
  ['cant_prim','pret_achiz','cota_tva_achiz','pret_cu_tva','procent_adaos'].forEach(id=>{
    const el = get(id); if(el){ el.addEventListener('input', recalc); el.addEventListener('change', recalc); }
  });

  recalc(); // precompletare la încărcare
})();
</script>
";

} else {
    echo "Produsul nu a fost găsit.";
}
?>
