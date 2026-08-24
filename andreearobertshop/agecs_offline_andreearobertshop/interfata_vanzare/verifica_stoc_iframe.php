<?php
// verifica_stoc_iframe.php
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <title>VerificÄƒ Stoc</title>
  <!-- Include CSS pentru Select2 -->
  <link href="vendor/offline/select2/select2.min.css" rel="stylesheet" />
  <style>
    body {
      margin: 10px;
      font-family: Arial, sans-serif;
    }
    #stocRezultat {
      margin-top: 10px;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <select id="verificaStocSelect" style="width: 100%;" placeholder="Introdu numele produsului..."></select>
  <div id="stocRezultat"></div>

  <!-- Include jQuery È™i scriptul Select2 -->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/offline/select2/select2.min.js"></script>
  <script>
    $(document).ready(function(){
      $('#verificaStocSelect').select2({
        placeholder: 'Introdu numele produsului...',
        minimumInputLength: 2,  // Ã®ncepe cÄƒutarea dupÄƒ 2 caractere
        ajax: {
          url: 'verifica_stoc_cauta_produs.php',  // scriptul care cautÄƒ Ã®n tabela produselor
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term // textul introdus de utilizator
            };
          },
          processResults: function (data) {
  return {
    results: data.map(function(item) {
      return {
        id: item.cod_produs,
        text: item.nume + " (Cod produs: " + item.cod_produs + ")"
      };
    })
  };
},

          cache: true
        }
      })
      .on('select2:select', function(e){
          var selectedId = e.params.data.id;
          // Apel AJAX pentru calculul stocului
          $.ajax({
            url: 'verifica_stoc_calcul.php',
            dataType: 'json',
            data: { cod_produs: selectedId },
            success: function(response) {
              $('#stocRezultat').html("Stocul final: " + response.final_stock);
            },
            error: function() {
              $('#stocRezultat').html("Eroare la calculul stocului.");
            }
          });
      });
    });
  </script>
</body>
</html>

