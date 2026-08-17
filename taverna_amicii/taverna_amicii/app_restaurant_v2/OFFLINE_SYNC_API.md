# Contractul sincronizării vânzărilor

## Endpoint

```http
POST https://agecs.agecs.in/sincronizare_online_app_restaurant/sincronizare_date_offline.php
Content-Type: application/json
```

Autentificarea folosește cheia configurată local în `offline_config.local.php`. Cheia nu se scrie în documentație, jurnale sau fișierele JSON exportate.

## Eligibilitatea datelor

O notă este transmisă numai dacă îndeplinește simultan următoarele condiții:

- `status = 'F'`
- `cod_inchidere` este diferit de zero
- `nr_raport_z` este diferit de zero
- nota nu a fost confirmată anterior în `offline_sync_exported`

Notele cu status `S` rămân exclusiv în SQLite. O notă finalizată, dar fără închidere sau raport Z, așteaptă completarea ciclului fiscal. Regula împiedică importarea online a unei stări intermediare care nu mai poate fi asociată corect ulterior cu închiderea și raportul Z.

## Structura pachetului

```json
{
  "schema_version": "offline-sync-v1",
  "sync_export_id": "SYNC-YYYYMMDD-HHMMSS-L01-abcdef",
  "cod_locatie": 1,
  "cod_locatie_suffix": "01",
  "data_sync": "YYYY-MM-DD HH:MM:SS",
  "utilizator_sync": {
    "id": 3,
    "nume": "SEF SALA1",
    "rank": "sefsala"
  },
  "note": [],
  "det_note": [],
  "inchideri_r_12": [],
  "rapoarte_z": [],
  "discounturi_acordate": []
}
```

Mișcările nu sunt copiate din SQLite. Aplicația online le generează din notele și liniile importate, folosind nomenclatorul și rețetele online.

## Identificare și prevenirea duplicatelor

Fiecare rând conține `_sync.source_table`, `_sync.source_pk`, `_sync.cod_locatie` și `_sync.sync_id`. Identificatorul stabil are la bază tabela sursă, locația și cheia locală. Online se generează `identificator_offline`, iar maparea către cheia online este păstrată în `offline_sync_imported`.

`offline_sync_exported` are unicitate pentru `source_table`, `source_pk` și `cod_locatie`. În modul strict, marcarea locală are loc numai după confirmarea importului online. Dacă transmisia eșuează, datele rămân eligibile pentru reluare.

## Jurnale și fișiere

Pachetele JSON se salvează în:

```text
C:\xampp\htdocs\instalari_offline_ecogest\api_offline_taverna_amicii\offline_sync_exports
```

Rezultatul fiecărei încercări este înregistrat în `offline_sync_logs`. Jurnalul include numărul de rânduri, momentul, operatorul, starea, hashul pachetului și mesajul de eroare.

## Drepturi

Transmiterea poate fi pornită numai de un operator cu rang `sefsala`, `administrator` sau `admin`. În instalarea Taverna Amicii sunt disponibile conturile SEF SALA1 și SEF SALA2.
