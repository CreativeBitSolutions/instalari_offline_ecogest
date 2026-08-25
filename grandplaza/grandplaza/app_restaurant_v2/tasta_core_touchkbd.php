<?php /* tasta_core_touchkbd.php */ ?>
<style>
  /* Buton mic de trigger lângă input */
  .tk-trigger {
    margin-left:.4rem; padding:.2rem .45rem; line-height:1;
    border-radius:6px; border:1px solid #ced4da; background:#fff; color:#333;
  }
  .tk-trigger:focus { outline:none; box-shadow:0 0 0 .2rem rgba(0,123,255,.25); }

  /* Stil comun pentru taste */
  .tk-grid { display:grid; gap:.5rem; user-select:none; }
  .tk-key, .tk-act {
    display:flex; justify-content:center; align-items:center;
    padding:.6rem .2rem; border:1px solid #dee2e6; border-radius:.5rem; background:#f8f9fa; font-weight:600; cursor:pointer;
  }
  .tk-key:active, .tk-act:active { background:#e9ecef; }
  .tk-row { display:grid; grid-auto-flow:column; gap:.5rem; }
  .tk-fullwidth { grid-column: 1 / -1; }

  /* Numeric */
  #TouchKeyboardNumeric .tk-grid { grid-template-columns:repeat(3, 1fr); }
  #tk-num-display { font-size:1.4rem; text-align:right; font-weight:700; letter-spacing:.5px; }

  /* Alfabetic */
  #TouchKeyboardAlpha .tk-grid { grid-template-columns:repeat(10, 1fr); }
  #tk-alpha-display { font-size:1.2rem; font-weight:600; }

  /* Utilitare */
  .d-none { display:none!important; }
</style>

<div id="tk-core-anchor"></div>

<!-- ============== Tastatură Numerică ============== -->
<div class="modal fade" id="TouchKeyboardNumeric" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog modal-sm" role="document" style="max-width: 320px;">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Tastatură numerică</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body pt-2">
        <input type="text" id="tk-num-display" class="form-control mb-2" readonly>
        <div class="tk-grid">
          <div class="tk-key" data-k="7">7</div>
          <div class="tk-key" data-k="8">8</div>
          <div class="tk-key" data-k="9">9</div>

          <div class="tk-key" data-k="4">4</div>
          <div class="tk-key" data-k="5">5</div>
          <div class="tk-key" data-k="6">6</div>

          <div class="tk-key" data-k="1">1</div>
          <div class="tk-key" data-k="2">2</div>
          <div class="tk-key" data-k="3">3</div>

          <div class="tk-key" data-k="0">0</div>
          <div class="tk-key" data-k="00">00</div>
          <div class="tk-key" data-k=".">.</div>

          <div class="tk-act tk-fullwidth" id="tk-num-row-act" style="grid-template-columns:repeat(3,1fr);display:grid">
            <div class="tk-act" data-k="bksp" title="Backspace">&larr;</div>
            <div class="tk-act" data-k="clear" title="Șterge">Șterge</div>
            <div class="tk-act btn-primary text-success" data-k="ok" title="OK">OK</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============== Tastatură Alfabetică (QWERTY + diacritice) ============== -->
<div class="modal fade" id="TouchKeyboardAlpha" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog" role="document" style="max-width:540px;">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Tastatură text</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Închide"><span>&times;</span></button>
      </div>
      <div class="modal-body pt-2">
        <input type="text" id="tk-alpha-display" class="form-control mb-2" readonly>
        <div class="tk-grid" id="tk-alpha-grid" style="grid-template-columns:repeat(10,1fr)">
          <!-- rând cifre -->
          <div class="tk-key" data-k="1">1</div><div class="tk-key" data-k="2">2</div><div class="tk-key" data-k="3">3</div>
          <div class="tk-key" data-k="4">4</div><div class="tk-key" data-k="5">5</div><div class="tk-key" data-k="6">6</div>
          <div class="tk-key" data-k="7">7</div><div class="tk-key" data-k="8">8</div><div class="tk-key" data-k="9">9</div>
          <div class="tk-key" data-k="0">0</div>

          <!-- QWERTY -->
          <div class="tk-key" data-k="q">q</div><div class="tk-key" data-k="w">w</div><div class="tk-key" data-k="e">e</div>
          <div class="tk-key" data-k="r">r</div><div class="tk-key" data-k="t">t</div><div class="tk-key" data-k="y">y</div>
          <div class="tk-key" data-k="u">u</div><div class="tk-key" data-k="i">i</div><div class="tk-key" data-k="o">o</div>
          <div class="tk-key" data-k="p">p</div>

          <div class="tk-key" data-k="a">a</div><div class="tk-key" data-k="s">s</div><div class="tk-key" data-k="d">d</div>
          <div class="tk-key" data-k="f">f</div><div class="tk-key" data-k="g">g</div><div class="tk-key" data-k="h">h</div>
          <div class="tk-key" data-k="j">j</div><div class="tk-key" data-k="k">k</div><div class="tk-key" data-k="l">l</div>
          <div class="tk-key" data-k="-">-</div>

          <div class="tk-key" data-k="z">z</div><div class="tk-key" data-k="x">x</div><div class="tk-key" data-k="c">c</div>
          <div class="tk-key" data-k="v">v</div><div class="tk-key" data-k="b">b</div><div class="tk-key" data-k="n">n</div>
          <div class="tk-key" data-k="m">m</div><div class="tk-key" data-k=",">,</div><div class="tk-key" data-k=".">.</div>
          <div class="tk-key" data-k="'">'</div>

          <!-- diacritice RO -->
          <div class="tk-key" data-k="ă">ă</div><div class="tk-key" data-k="â">â</div><div class="tk-key" data-k="î">î</div>
          <div class="tk-key" data-k="ș">ș</div><div class="tk-key" data-k="ț">ț</div>
          <div class="tk-act" data-k="space" style="grid-column: span 3">Spațiu</div>
          <div class="tk-act" data-k="bksp">&larr;</div>
          <div class="tk-act" data-k="clear">Șterge</div>
          <div class="tk-act" id="tk-shift">SHIFT</div>
          <div class="tk-act btn-primary text-success" data-k="ok">OK</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  if (window.__TouchKbdLoaded) return; // protecție include dublu
  window.__TouchKbdLoaded = true;

  var $ = window.jQuery || null;
  if (!$) { console.error('[TouchKbd] Necesită jQuery.'); return; }

  var activeInput = null;
  var mode = null; // 'numeric' | 'alpha'
  var alphaUpper = false;

  function getDecimalsFor(inputEl){
    var step = (inputEl.getAttribute('step')||'').toLowerCase();
    if (!step || step === 'any') return 3;
    if (step.indexOf('.') >= 0) return (step.split('.')[1]||'').length;
    return 0;
  }
  function clamp(n, min, max){
    if (min != null && n < min) n = min;
    if (max != null && n > max) n = max;
    return n;
  }
  function toNumberSafe(str){
    if (str == null) return NaN;
    str = String(str).replace(',', '.').replace(/[^\d.-]/g,'');
    var n = parseFloat(str);
    return isNaN(n) ? NaN : n;
  }

  // ===== Numeric =====
function openNumericFor(input){
  activeInput = input; 
  mode = 'numeric';

  // Deschide tastatura numerică cu afișajul gol
  $('#tk-num-display').val('');

  $('#TouchKeyboardNumeric').modal('show');
}
  $(document).on('click', '#TouchKeyboardNumeric .tk-key, #TouchKeyboardNumeric .tk-act', function(){
    if (!activeInput || mode!=='numeric') return;
var k = String($(this).attr('data-k') || '');
    var $d = $('#tk-num-display');
    var txt = $d.val() || '';
    if (k === 'bksp') { $d.val(txt.slice(0,-1)); return; }
    if (k === 'clear'){ $d.val(''); return; }
if (k === 'ok') {
  var raw = ($d.val() || '').trim();

  if (raw === '') {
    activeInput.value = '';
    $(activeInput).trigger('input').trigger('change');
    $('#TouchKeyboardNumeric').modal('hide');
    setTimeout(function(){ activeInput.focus(); }, 100);
    return;
  }

  var min = activeInput.hasAttribute('min') ? parseFloat(activeInput.getAttribute('min')) : null;
  var max = activeInput.hasAttribute('max') ? parseFloat(activeInput.getAttribute('max')) : null;
  var dec = getDecimalsFor(activeInput);
  var n = toNumberSafe(raw);

  if (isNaN(n)) n = 0;

  n = clamp(n, min, max);
  var factor = Math.pow(10, dec);
  n = Math.round(n * factor) / factor;

  activeInput.value = n.toFixed(dec);

  $(activeInput).trigger('input').trigger('change');
  $('#TouchKeyboardNumeric').modal('hide');
  setTimeout(function(){ activeInput.focus(); }, 100);
  return;
}
    // acceptă și virgulă = punct
    if (k === ',') k='.';
    // previne dublu punct
    if ((k === '.' || k === ',') && txt.indexOf('.') !== -1) return;
    $d.val(txt + k);
  });

  // ===== Alfabetic =====
  function openAlphaFor(input){
    activeInput = input; mode = 'alpha'; alphaUpper = false;
    $('#tk-alpha-display').val(input.value||'');
    updateAlphaLabels();
    $('#TouchKeyboardAlpha').modal('show');
  }

  function toUpperWithDiacritics(ch){
    var map = { 'ă':'Ă','â':'Â','î':'Î','ș':'Ș','ț':'Ț' };
    if (map[ch]) return map[ch];
    return ch.toUpperCase();
  }
  function updateAlphaLabels(){
    $('#tk-alpha-grid .tk-key').each(function(){
      var k = String($(this).data('k')||'');
      if (!k) return;
      if (/[a-zăâîșț]/.test(k)) {
        $(this).text(alphaUpper ? toUpperWithDiacritics(k) : k);
      } else {
        $(this).text(k);
      }
    });
    $('#tk-shift').toggleClass('btn-dark', alphaUpper);
  }

  $(document).on('click', '#TouchKeyboardAlpha .tk-key, #TouchKeyboardAlpha .tk-act', function(){
    if (!activeInput || mode!=='alpha') return;
   var k = String($(this).attr('data-k') || '');
    var $d = $('#tk-alpha-display');
    var txt = $d.val() || '';

    if (k === 'bksp') { $d.val(txt.slice(0,-1)); return; }
    if (k === 'clear'){ $d.val(''); return; }
    if (k === 'space'){ $d.val(txt + ' '); return; }
    if (k === 'ok') {
      activeInput.value = $d.val();
      $(activeInput).trigger('input').trigger('change');
      $('#TouchKeyboardAlpha').modal('hide');
      setTimeout(function(){ activeInput.focus(); }, 100);
      return;
    }
    if (this.id === 'tk-shift') {
      alphaUpper = !alphaUpper; updateAlphaLabels(); return;
    }
    // literă/simbol
    var out = k;
    if (alphaUpper && /^[a-zăâîșț]$/.test(k)) out = toUpperWithDiacritics(k);
    $d.val(txt + out);
  });

  // ===== API public: atașare buton lângă input =====
  function attachTrigger(selector, type){
    // rulează acum și la fiecare deschidere de modal bootstrap (pentru cazuri dinamice)
    function attach(){
      var $els = $(selector);
      $els.each(function(){
        var $inp = $(this);
        if ($inp.data('tk-attached')) return;

        // inserează buton mic după input
        var $btn = $('<button type="button" class="tk-trigger" title="Tastatură">⌨︎</button>');
        $btn.on('click', function(e){
          e.preventDefault(); e.stopPropagation();
          var el = $inp.get(0);
          if (type === 'numeric') openNumericFor(el); else openAlphaFor(el);
        });
        // dacă inputul e într-un .input-group, îl punem ca .input-group-append
        var $grp = $inp.closest('.input-group');
        if ($grp.length) {
          var $wrap = $('<div class="input-group-append"></div>').append($btn);
          $inp.after($wrap);
        } else {
          $inp.after($btn);
        }
        $inp.data('tk-attached', true);
      });
    }
    $(attach); // DOM ready
    $(document).on('shown.bs.modal', attach);
  }

  // expune global
  window.TouchKbd = {
    register: function(selector, type){
      attachTrigger(selector, (type === 'numeric') ? 'numeric' : 'alpha');
    }
  };
})();
</script>
