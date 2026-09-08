// CBS_functions.js

function getDenumireUM(cod_um) {
    // Mapează codurile populare la denumirile lor corespunzătoare.
    var popularUnits = {
        'H87': 'BUC',
        'KGM': 'KG',
        'LTR': 'LITRU',
        'MTK': 'METRU PĂTRAT',
        'MTQ': 'METRU CUB',
        'MTR': 'METRU'
    };

    // Transformă codul într-un string curat.
    var key = cod_um.toString().trim();

    // Returnează denumirea din obiectul popularUnits, dacă există.
    // Dacă codul nu se găsește, returnează codul original.
    return popularUnits[key] || key;
}
