// === Calcule plăți / rest ===
function updateDue(){
  var total = parseFloat(document.getElementById("total").value).toFixed(2);
  var val2  = parseFloat(document.getElementById("baniprim").value).toFixed(2);
  if(!total){ total = 0; }
  if(!val2){  val2  = 0; }
  var ansD = document.getElementById("rest");
  ansD.value = Math.round((val2 - total) * 100) / 100;
}

function updateNumerar(){
  var totalmixt = parseFloat(document.getElementById("totalmixt").value).toFixed(2);
  var val3      = parseFloat(document.getElementById("card").value).toFixed(2);
  if(!totalmixt){ totalmixt = 0; }
  if(!val3){      val3      = 0; }
  var ansD = document.getElementById("numerar");
  ansD.value = Math.round((totalmixt - val3) * 100) / 100;
}

function updateCard(){
  var totalmixt = parseFloat(document.getElementById("totalmixt").value).toFixed(2);
  var val       = parseFloat(document.getElementById("numerar").value).toFixed(2);
  if(!totalmixt){ totalmixt = 0; }
  if(!val){       val       = 0; }   // fix față de val3 inexistent
  var ansD = document.getElementById("card");
  ansD.value = Math.round((totalmixt - val) * 100) / 100;
}

// === Tichete de masă ===
function updateTichete(){
  var total_de_plata_tichete = parseFloat(document.getElementById("total_de_plata_tichete").value).toFixed(2);
  var nr_tichete             = parseFloat(document.getElementById("nr_tichete").value).toFixed(2);
  var val_tichet             = parseFloat(document.getElementById("val_tichet").value).toFixed(2);
  if(!total_de_plata_tichete){ total_de_plata_tichete = 0; }
  if(!nr_tichete){             nr_tichete             = 0; }
  if(!val_tichet){             val_tichet             = 0; }
  var total_tichete = document.getElementById("total_tichete");
  total_tichete.value = Math.round((nr_tichete * val_tichet) * 100) / 100;

  var rest_de_incasat = document.getElementById("rest_de_incasat");
  rest_de_incasat.value = Math.round((total_de_plata_tichete - total_tichete.value) * 100) / 100;

  if(rest_de_incasat.value < 0){
    $('#finalizare_tichet').attr('disabled','disabled');
    alert('ATENTIE! VALOAREA TICHETELOR DEPASESTE VALOAREA VANZARII! NU SE POATE DA REST LA TICHETUL DE MASA');
  } else {
    $('#finalizare_tichet').removeAttr('disabled');
  }
}

function updateNrTichete(){
  var total_de_plata_tichete = parseFloat(document.getElementById("total_de_plata_tichete").value).toFixed(2);
  var val_tichet             = parseFloat(document.getElementById("val_tichet").value).toFixed(2);
  if(!total_de_plata_tichete){ total_de_plata_tichete = 0; }
  if(!val_tichet){             val_tichet             = 0; }
  var nr_tich = document.getElementById("nr_tichete");
  nr_tich.value = Math.floor((total_de_plata_tichete / val_tichet));

  var rest_de_incasat = document.getElementById("rest_de_incasat");
  rest_de_incasat.value = Math.round((total_de_plata_tichete - nr_tich.value * val_tichet) * 100) / 100;

  var total_tich = document.getElementById("total_tichete");
  total_tich.value = Math.round((nr_tich.value * val_tichet) * 100) / 100;

  if(rest_de_incasat.value < 0){
    $('#finalizare_tichet').attr('disabled','disabled');
    alert('ATENTIE! VALOAREA TICHETELOR DEPASESTE VALOAREA VANZARII! NU SE POATE DA REST LA TICHETUL DE MASA');
  } else {
    $('#finalizare_tichet').removeAttr('disabled');
  }
  if(nr_tich.value <= 0){
    $('#finalizare_tichet').attr('disabled','disabled');
    alert('ATENTIE! NUMARUL TICHETELOR NU POATE FI ZERO!');
  } else {
    $('#finalizare_tichet').removeAttr('disabled');
  }
}

function updateRestTichete(){
  var rest_de_incasat        = parseFloat(document.getElementById("rest_de_incasat").value).toFixed(2);
  var total_de_plata_tichete = parseFloat(document.getElementById("total_de_plata_tichete").value).toFixed(2);
  var v_tichete              = parseFloat(document.getElementById("total_tichete").value).toFixed(2);
  if(!rest_de_incasat){        rest_de_incasat        = 0; }
  if(!total_de_plata_tichete){ total_de_plata_tichete = 0; }
  if(!v_tichete){              v_tichete              = 0; }
  var total_incasat = Number(rest_de_incasat) + Number(v_tichete);
  var ansD = document.getElementById("rest_de_returnat");
  ansD.value = Math.round((total_incasat - total_de_plata_tichete) * 100) / 100;

  if(ansD.value < 0){
    $('#finalizare_tichet').attr('disabled','disabled');
    alert('ATENTIE! RESTUL DE RETURNAT NU POATE FI NEGATIV!');
  } else {
    $('#finalizare_tichet').removeAttr('disabled');
  }
}

// === Discount TVA fix ===
function calc_tva_disc(){
  var valoare_fixa  = parseFloat(document.getElementById("val_fix").value).toFixed(2);
  var cota_calc_tva = parseFloat(document.getElementById("cota_calc_tva").value).toFixed(2);
  if(!valoare_fixa){  valoare_fixa  = 0; }
  if(!cota_calc_tva){ cota_calc_tva = 0; }
  var tva_disc = document.getElementById("tva_discount");
  var numitor  = Number(100) + Number(cota_calc_tva);
  tva_disc.value = Math.round(valoare_fixa * cota_calc_tva / numitor * 100) / 100;
}

// radio: arată/ascunde blocurile de discount
$('input[type="radio"]').click(function(){
  if($(this).attr("value")=="val_fixa"){
    $("#val_fixa").show('slow'); $("#procent").hide('slow');
  }
  if($(this).attr("value")=="procent"){
    $("#procent").show('slow');  $("#val_fixa").hide('slow');
  }
});

// === Inițializări specifice interfeței de vânzare ===
$(document).ready(function(){
  // deschidere modal discount pe click pe butonul de discount din listă
  $('.discount').on('click', function(){
    $('#Discount').modal('show');
    var prod_discount = $(this).val();
    var dataValue     = this.getAttribute("data-value");
    var idvanzare     = this.getAttribute("name");
    $('[name=prod_discount]').val(prod_discount);
    $('[name=cota_calc_tva]').val(dataValue);
    $('[name=idvanzare]').val(idvanzare);
  });

  // plăți mixte / tichete
  $('#plata_numerar_si_card').on('click', function(){ $('#Plata_numerar_si_card').modal('show'); });
  $('#plata_tichete').on('click',        function(){ $('#Plata_tichete').modal('show'); });

  // (opțional) activare/dezactivare metode după bani primiți
  var $baniprim = document.getElementById("baniprim");
  if ($baniprim) {
    var v = parseFloat($baniprim.value).toFixed(2);
    if (v <= 0){
      $('#plata_numerar,#plata_card,#plata_numerar_si_card,#plata_tichete').attr('disabled','disabled');
    } else {
      $('#plata_numerar,#plata_card,#plata_numerar_si_card,#plata_tichete').removeAttr('disabled');
    }
  }
});
