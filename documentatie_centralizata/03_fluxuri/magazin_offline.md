# Fluxul magazinului offline

1. Interfața locală citește configurația externă.
2. Configurația stabilește clientul, locația, baza SQLite și endpointurile AGECS.
3. Baza locală aplică schema versionată și identificatorii offline.
4. Vânzarea finalizată este introdusă în coada persistentă.
5. Workerul revendică evenimentul, calculează hashul și trimite payloadul către AGECS.
6. AGECS validează clientul, locația, profilul și identificatorii.
7. Importul online inserează operațiunile sau răspunde că evenimentul a fost deja procesat.
8. Coada locală marchează evenimentul ca transmis, retry sau blocat.

Pentru Daily Coffee, fluxul include NIR, achiziții, transferuri și profilul `offline_magazin_nir`. Pentru magazinele sales-only, operațiunile locale de gestiune sunt restricționate conform fișei clientului.

