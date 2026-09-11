# Legătura cu sincronizarea AGECS

Repository-ul offline produce payloaduri locale. Repository-ul `agecsin` le validează și le importă în bazele MySQL ale clienților.

## Magazine

- Profiluri principale: `offline_magazin` și `offline_magazin_nir`.
- Importul normalizează identificatorii offline și verifică clientul și locația.
- Daily Coffee folosește profilul suplimentar pentru NIR și achiziții.

## Restaurante

- Contractul include `event_uuid`, `event_type`, `aggregate_type`, `aggregate_id` și `installation_uuid`.
- Importul folosește inbox de evenimente și loguri pentru idempotentă.
- Sunt urmărite notele, detaliile, discounturile, închiderile și rapoartele Z.

## Date descărcate local

Produsele, categoriile, gestiunile, TVA-ul și operatorii sunt exportate din AGECS prin endpointuri separate. Aceste date nu se documentează prin copierea configurațiilor cu chei sau parole.

