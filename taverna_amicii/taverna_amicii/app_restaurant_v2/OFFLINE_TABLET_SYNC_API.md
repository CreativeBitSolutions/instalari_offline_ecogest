# Contractul comenzilor online de pe tabletă

## Preluarea comenzilor

```http
GET https://agecs.agecs.in/api/offline-tablet-orders.php?cod_client=1008&cod_locatie=1&limit=200
X-Api-Key: CHEIA_CLIENTULUI
```

Cheia API identifică baza clientului. Parametrul `cod_client` este verificat față de clientul asociat cheii. Endpointul returnează numai comenzile cu `stare=TRIMISA` din locația cerută.

Fiecare element din `orders` conține antetul din `com_tableta`, `owner_operator_id`, numele ospătarului, `payload_hash` și lista `details` din `det_com_tableta`. Câmpurile de identificare sunt serializate numeric.

## Confirmarea importului

```http
POST https://agecs.agecs.in/api/offline-tablet-orders.php?cod_client=1008
Content-Type: application/json
X-Api-Key: CHEIA_CLIENTULUI
```

```json
{
  "action": "ack_imported",
  "cod_locatie": 1,
  "installation_uuid": "identificatorul-instalării",
  "orders": [
    {
      "online_nrbon": 123,
      "local_note_nrbon": 456,
      "payload_hash": "sha256"
    }
  ]
}
```

Confirmarea se trimite numai după commitul tranzacției SQLite care a creat sau a completat nota. Serverul schimbă `TRIMISA` în `IMPORTATA` și înregistrează asocierea în `offline_tablet_import_receipts`. Cheia unică pe `online_nrbon` face confirmarea repetabilă fără duplicate.

## Stările locale

- `TRIMISA`, comanda este copiată local și așteaptă alegerea mesei
- `IMPORTATA` cu `online_ack_status=pending`, nota locală este salvată și confirmarea nu a fost încă încercată
- `IMPORTATA` cu `online_ack_status=retry`, confirmarea va fi reluată după o eroare de rețea
- `IMPORTATA` cu `online_ack_status=sent`, serverul online a confirmat importul

Workerul rulează din interfața ospătarului, din panoul șefului de sală și din pagina de alegere a operatorului. Intervalul implicit pentru preluare este de 30 de secunde.
