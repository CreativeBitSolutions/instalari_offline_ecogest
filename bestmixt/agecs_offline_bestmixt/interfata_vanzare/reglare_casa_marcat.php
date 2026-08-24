<?php
// --- START PHP LOGIC ---
ini_set('display_errors', 1); // Recomandat 1 pentru dezvoltare, 0 pentru producÈ›ie
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

include('session.php'); // GestioneazÄƒ sesiunea È™i conexiunea $pdo

// Preluare È™i validare date sesiune
$client_id = $_SESSION['client_id'] ?? null;
$cod_locatie = $_SESSION['cod_locatie'] ?? null;
if (!$client_id || !$cod_locatie) {
    die("Eroare criticÄƒ: Sesiunea nu este validÄƒ. VÄƒ rugÄƒm sÄƒ vÄƒ autentificaÈ›i.");
}

// Preluare mapare TVA -> Departament Casa
$cote_tva_map = [];
try {
    $stmt_tva = $pdo->query("SELECT cota, dep_casa FROM cote_tva");
    foreach ($stmt_tva->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cote_tva_map[$row['cota']] = $row['dep_casa'];
    }
} catch (PDOException $e) {
    error_log("Eroare la preluarea cotelor TVA: " . $e->getMessage());
    die("Eroare la conectarea cu baza de date pentru a prelua cotele TVA.");
}

// Definirea metodelor de platÄƒ
$metode_plata = [
    '0' => 'NUMERAR', '1' => 'CARD', '6' => 'PLATA MODERNA',
    '3' => 'TICHETE MASA', '4' => 'TICHETE VALORICE', '5' => 'VOUCHER', '2' => 'CREDIT'
];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generator Avansat Bon CasÄƒ de Marcat</title>
    <!-- Select2 CSS -->
    <link href="vendor/offline/select2/select2.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary-color: #007bff; --secondary-color: #6c757d; --background-color: #f4f7f9;
            --form-bg-color: #ffffff; --text-color: #333; --border-color: #dee2e6;
            --success-color: #28a745; --error-color: #dc3545; --info-bg-color: #e9ecef;
        }
        body { font-family: 'Roboto', sans-serif; background-color: var(--background-color); color: var(--text-color); margin: 0; padding: 20px; }
        .container { width: 100%; max-width: 960px; background-color: var(--form-bg-color); padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); margin: 20px auto; }
        h1, h2, h3 { text-align: center; }
        h1 { color: var(--primary-color); }
        .session-info { text-align: center; background-color: var(--info-bg-color); padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; color: var(--secondary-color); }
        .form-section { border: 1px solid var(--border-color); padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: 3fr 1fr 1fr 1fr; gap: 15px; align-items: flex-end; }
        .form-group { margin-bottom: 0; }
        label { display: block; font-weight: 500; margin-bottom: 8px; color: var(--secondary-color); font-size: 14px; }
        input, select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; }
        button { padding: 10px 20px; border: none; border-radius: 8px; font-size: 16px; font-weight: 500; cursor: pointer; transition: background-color 0.2s; }
        .btn-add { background-color: var(--success-color); color: white; width: 100%; }
        .btn-add:hover { background-color: #218838; }
        .btn-generate { background-color: var(--primary-color); color: white; display: block; width: 100%; margin-top: 20px; padding: 15px; }
        .btn-generate:hover { background-color: #0056b3; }
        .btn-remove { background-color: var(--error-color); color: white; font-size: 12px; padding: 5px 8px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid var(--border-color); }
        th { background-color: #f8f9fa; }
        td:last-child, th:last-child { text-align: center; }
        .total-row { font-weight: bold; font-size: 1.2em; }
        #summary-content pre { background-color: #2d2d2d; color: #f1f1f1; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; }
        #status-message { text-align: center; font-weight: bold; margin-top: 20px; padding: 15px; border-radius: 8px; display: none; }
        #status-message.success { background-color: #d4edda; color: #155724; display: block; }
        #status-message.error { background-color: #f8d7da; color: var(--error-color); display: block; }

        /* AjustÄƒri vizuale pentru Select2 ca sÄƒ se potriveascÄƒ cu input-urile */
        .select2-container .select2-selection--single {
            height: 42px; border: 1px solid var(--border-color); border-radius: 8px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px; padding-left: 10px; font-size: 16px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px; right: 8px;
        }
        .helper-text { margin-top: 6px; font-size: 13px; color: var(--secondary-color); }
    </style>
</head>
<body>

<div class="container" data-client-id="<?php echo htmlspecialchars($client_id); ?>">

    <h1>Generator Avansat de Bon</h1>
    <h1>ATENÈšIE! ACEST PROCES VA TRIMITE UN BON FISCAL LA CASA DE MARCAT! FOLOSIÈšI-L DOAR DACÄ‚ AVEÈšI DIFERENÈšE PE CASÄ‚! NU SE VOR SCÄ‚DEA DIN STOC PRODUSELE!</h1>
    <h2><a href="logout.php">ÃŽnapoi</a></h2>

    <div class="session-info" style="display:none;">
        Client ID: <strong><?php echo htmlspecialchars($client_id); ?></strong> |
        LocaÈ›ie ID: <strong><?php echo htmlspecialchars($cod_locatie); ?></strong>
    </div>

    <div class="form-section">
        <h2>AdaugÄƒ Produs pe Bon</h2>
        <div class="form-grid">
            <div class="form-group">
                <label for="produs-select">Produs / Serviciu</label>
                <select id="produs-select" style="width:100%"></select>
                <div class="helper-text">cauta produs scrie 3 litere in bara de mai jos</div>
            </div>
            <div class="form-group">
                <label for="produs-cantitate">Cantitate</label>
                <input type="number" id="produs-cantitate" value="1" step="any">
            </div>
            <div class="form-group">
                <label for="produs-pret">PreÈ› Unitar (LEI)</label>
                <input type="number" id="produs-pret" step="0.01">
            </div>
            <div class="form-group">
                <button id="add-to-bon" class="btn-add">AdaugÄƒ</button>
            </div>
        </div>
         <input type="hidden" id="produs-nume">
         <input type="hidden" id="produs-um">
         <input type="hidden" id="produs-cota-tva">
    </div>

    <div class="form-section">
        <h2>Bon Curent</h2>
        <table id="bon-items-table">
            <thead>
                <tr>
                    <th>Produs</th>
                    <th>Cant.</th>
                    <th>PreÈ› Unitar</th>
                    <th>Subtotal</th>
                    <th>AcÈ›iune</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3">TOTAL GENERAL</td>
                    <td id="grand-total">0.00 LEI</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="form-section">
        <h2>Finalizare È™i PlatÄƒ</h2>
        <div class="form-group">
            <label for="metoda_plata">Metoda de PlatÄƒ</label>
            <select id="metoda_plata">
                <?php foreach ($metode_plata as $cod => $nume): ?>
                    <option value="<?php echo $cod; ?>"><?php echo htmlspecialchars($nume); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="summary-section" style="display:none; margin-top:20px;">
            <h3>Sumar Detaliat È™i Previzualizare `continut_bon`</h3>
            <div id="summary-content"></div>
        </div>
        <button id="generate-btn" class="btn-generate">GenereazÄƒ È™i SalveazÄƒ Bonul</button>
    </div>
    
    <div id="status-message"></div>
</div>

<!-- jQuery + Select2 JS -->
<script src="js/jquery-3.6.0.min.js"></script>
<script src="vendor/offline/select2/select2.min.js"></script>

<script>
// HartÄƒ TVA -> departament din PHP
const tvaMap = <?php echo json_encode($cote_tva_map); ?>;

let bonItems = [];                 // Liniile bonului
let selectedProduct = null;        // Ultimul produs selectat din Select2 (obiectul cu cÃ¢mpuri extra)

document.addEventListener('DOMContentLoaded', () => {
    const produsSelect = $('#produs-select');
    const addButton = document.getElementById('add-to-bon');
    const generateButton = document.getElementById('generate-btn');

    // INIT Select2 cu AJAX, minim 3 caractere
    produsSelect.select2({
        width: '100%',
        placeholder: 'cauta produs scrie 3 litere in bara de mai jos',
        allowClear: true,
        minimumInputLength: 3,
        ajax: {
            url: 'reglare_casa_ajax_search_produse.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { term: params.term };
            },
            processResults: function (data) {
                // data.results trebuie sÄƒ fie [{id, text, pret_cu_tva, um, cota_tva}, ...]
                return { results: data.results || [] };
            },
            cache: true
        },
        language: {
            inputTooShort: () => 'Scrie cel puÈ›in 3 caractere',
            noResults: () => 'Niciun rezultat',
            searching: () => 'Se cautÄƒ...'
        }
    });

    // CÃ¢nd selectÄƒm un produs din listÄƒ, pÄƒstrÄƒm obiectul ales È™i completÄƒm automat preÈ›ul
    produsSelect.on('select2:select', function (e) {
        const d = e.params.data || {};
        selectedProduct = d; // {id, text, pret_cu_tva, um, cota_tva}
        document.getElementById('produs-pret').value = (parseFloat(d.pret_cu_tva || 0) || 0).toFixed(2);
        document.getElementById('produs-cantitate').value = 1;
        // setÄƒm cÃ¢mpurile ascunse pentru compatibilitate cu restul logicii
        document.getElementById('produs-nume').value = d.text || '';
        document.getElementById('produs-um').value = d.um || '';
        document.getElementById('produs-cota-tva').value = d.cota_tva || '';
    });

    // DacÄƒ se È™terge selecÈ›ia
    produsSelect.on('select2:clear', function () {
        selectedProduct = null;
        document.getElementById('produs-pret').value = '';
        document.getElementById('produs-nume').value = '';
        document.getElementById('produs-um').value = '';
        document.getElementById('produs-cota-tva').value = '';
    });

    addButton.addEventListener('click', addProductToBon);
    generateButton.addEventListener('click', generateAndSaveBon);
    document.getElementById('bon-items-table').addEventListener('click', handleTableActions);
});

function addProductToBon() {
    // ValideazÄƒ selecÈ›ia
    const selectEl = document.getElementById('produs-select');
    const cod_produs = selectEl.value;
    if (!cod_produs || !selectedProduct) {
        alert("VÄƒ rugÄƒm sÄƒ cÄƒutaÈ›i È™i sÄƒ selectaÈ›i un produs (minim 3 litere).");
        return;
    }

    // Cantitate + preÈ›
    const cantitate = parseFloat(document.getElementById('produs-cantitate').value);
    // DacÄƒ nu a fost modificat manual, folosim preÈ›ul din produsul selectat
    let pret_unitar = parseFloat(document.getElementById('produs-pret').value);
    if (isNaN(pret_unitar) || pret_unitar <= 0) {
        pret_unitar = parseFloat(selectedProduct.pret_cu_tva || 0);
    }

    if (isNaN(cantitate) || cantitate <= 0 || isNaN(pret_unitar) || pret_unitar <= 0) {
        alert("Cantitatea È™i preÈ›ul trebuie sÄƒ fie numere valide È™i pozitive.");
        return;
    }

    // Date suplimentare din selecÈ›ie (sau din hidden fields deja setate)
    const nume = selectedProduct.text || document.getElementById('produs-nume').value;
    const um = selectedProduct.um || document.getElementById('produs-um').value;
    const cota_tva = parseInt(selectedProduct.cota_tva || document.getElementById('produs-cota-tva').value) || 0;

    const item = {
        cod_produs: cod_produs,
        nume: nume,
        um: um,
        cota_tva: cota_tva,
        cantitate: cantitate,
        pret_unitar: pret_unitar,
        subtotal: cantitate * pret_unitar,
        line_id: 'line-' + Date.now()
    };

    bonItems.push(item);
    renderBonTable();
    updateTotals();

    // opÈ›ional: reset dupÄƒ adÄƒugare
    // $('#produs-select').val(null).trigger('change'); selectedProduct = null;
    // document.getElementById('produs-pret').value = '';
}

function renderBonTable() {
    const tbody = document.getElementById('bon-items-table').querySelector('tbody');
    tbody.innerHTML = '';

    bonItems.forEach(item => {
        const row = document.createElement('tr');
        row.id = item.line_id;
        row.innerHTML = `
            <td>${item.nume}</td>
            <td>${item.cantitate} ${item.um}</td>
            <td>${item.pret_unitar.toFixed(2)} LEI</td>
            <td>${item.subtotal.toFixed(2)} LEI</td>
            <td><button class="btn-remove" data-line-id="${item.line_id}">È˜terge</button></td>
        `;
        tbody.appendChild(row);
    });
}

function updateTotals() {
    const grandTotal = bonItems.reduce((total, item) => total + item.subtotal, 0);
    document.getElementById('grand-total').textContent = `${grandTotal.toFixed(2)} LEI`;
}

function handleTableActions(event) {
    if (event.target.classList.contains('btn-remove')) {
        const lineIdToRemove = event.target.dataset.lineId;
        bonItems = bonItems.filter(item => item.line_id !== lineIdToRemove);
        renderBonTable();
        updateTotals();
    }
}

function buildContinutBonPreview() {
    if (bonItems.length === 0) return "";

    const cr = "\\r\\n";
    let buffer = "H,1,______,_,__;" + cr;

    bonItems.forEach(item => {
        const depCasa = tvaMap[item.cota_tva] || 0;
        const numeProdusFormatat = item.nume.substring(0, 22);
        buffer += `S,1,______,_,__;${numeProdusFormatat};${item.pret_unitar.toFixed(2)};${item.cantitate};1;1;${depCasa};0;0;${item.um}` + cr;
    });

    const total = bonItems.reduce((sum, item) => sum + item.subtotal, 0);
    const metodaPlataCod = document.getElementById('metoda_plata').value;
    buffer += `T,1,______,_,__;${metodaPlataCod};${total.toFixed(2)};;;;` + cr;

    const clientId = document.querySelector('.container').dataset.clientId;
    if (clientId == 4) {
        buffer += "T,1,______,_,__;" + cr;
    }
    return buffer;
}

async function generateAndSaveBon() {
    if (bonItems.length === 0) {
        alert("Bonul este gol. VÄƒ rugÄƒm sÄƒ adÄƒugaÈ›i cel puÈ›in un produs.");
        return;
    }

    const metodaPlataCod = document.getElementById('metoda_plata').value;
    const payload = {
        items: bonItems,
        metoda_plata: metodaPlataCod,
        total_general: bonItems.reduce((total, item) => total + item.subtotal, 0)
    };
    
    // Previzualizare
    const summarySection = document.getElementById('summary-section');
    const summaryContent = document.getElementById('summary-content');
    
    let summaryHTML = '<h4>Produse pe bon:</h4><ul>';
    bonItems.forEach(item => {
        summaryHTML += `<li>${item.cantitate} x ${item.nume} @ ${item.pret_unitar.toFixed(2)} LEI = <strong>${item.subtotal.toFixed(2)} LEI</strong></li>`;
    });
    summaryHTML += '</ul><hr>';
    summaryHTML += `<p><strong>Total General:</strong> ${payload.total_general.toFixed(2)} LEI</p>`;
    summaryHTML += `<p><strong>Metoda de platÄƒ aleasÄƒ:</strong> ${document.querySelector('#metoda_plata option:checked').text}</p>`;
    summaryHTML += '<h4>Previzualizare conÈ›inut fiÈ™ier:</h4>';
    summaryHTML += `<pre>${buildContinutBonPreview()}</pre>`;
    
    summaryContent.innerHTML = summaryHTML;
    summarySection.style.display = 'block';

    if (!confirm("Sunt corecte datele din sumar?")) return;
    
    showStatus('Se genereazÄƒ fiÈ™ierul...', 'info');
    try {
        const response = await fetch('reglare_casa_marcat_procesare_bon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.status === 'success') {
            showStatus(result.message, 'success');
            bonItems = [];
            renderBonTable();
            updateTotals();
            summarySection.style.display = 'none';
        } else {
            showStatus('Eroare: ' + result.message, 'error');
        }
    } catch (error) {
        showStatus('Eroare de comunicare cu serverul: ' + error.message, 'error');
    }
}

function showStatus(message, type = 'success') {
    const statusMessage = document.getElementById('status-message');
    statusMessage.innerHTML = message;
    statusMessage.className = `status-message ${type}`;
    statusMessage.style.display = 'block';
}
</script>
</body>
</html>

