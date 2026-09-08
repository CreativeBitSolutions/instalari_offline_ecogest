<?php
/**
 * Generator unic pentru XML SAGA facturi.
 *
 * - Accesat direct: descarcă XML pentru ?id_factura=ID
 * - Inclus în alte fișiere: oferă funcții reutilizabile pentru XML individual, XML combinat și ZIP.
 */

if (!function_exists('saga_xml_text')) {
    function saga_xml_text($value): string {
        return trim((string)($value ?? ''));
    }
}

if (!function_exists('saga_xml_date')) {
    function saga_xml_date($date): string {
        $date = saga_xml_text($date);
        if ($date === '' || $date === '0000-00-00') {
            return '';
        }

        $timestamp = strtotime($date);
        return $timestamp === false ? '' : date('d.m.Y', $timestamp);
    }
}

if (!function_exists('saga_xml_safe_filename_part')) {
    function saga_xml_safe_filename_part($value, string $fallback = 'NA'): string {
        $value = saga_xml_text($value);
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
        $value = trim($value, '-_.');
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('saga_xml_append')) {
    function saga_xml_append(DOMDocument $doc, DOMElement $parent, string $name, $value = ''): void {
        $parent->appendChild($doc->createElement($name, saga_xml_text($value)));
    }
}

if (!function_exists('saga_xml_ro_county_code')) {
    function saga_xml_ro_county_code($value): string {
        $value = strtoupper(saga_xml_text($value));
        $map = array(
            'ALBA' => 'AB',
            'ARAD' => 'AR',
            'ARGES' => 'AG',
            'ARGEȘ' => 'AG',
            'BACAU' => 'BC',
            'BACĂU' => 'BC',
            'BIHOR' => 'BH',
            'BISTRITA-NASAUD' => 'BN',
            'BISTRIȚA-NĂSĂUD' => 'BN',
            'BOTOSANI' => 'BT',
            'BOTOȘANI' => 'BT',
            'BRASOV' => 'BV',
            'BRAȘOV' => 'BV',
            'BRAILA' => 'BR',
            'BRĂILA' => 'BR',
            'BUCURESTI' => 'B',
            'BUCUREȘTI' => 'B',
            'MUNICIPIUL BUCURESTI' => 'B',
            'MUNICIPIUL BUCUREȘTI' => 'B',
            'BUZAU' => 'BZ',
            'BUZĂU' => 'BZ',
            'CARAS-SEVERIN' => 'CS',
            'CARAȘ-SEVERIN' => 'CS',
            'CALARASI' => 'CL',
            'CĂLĂRAȘI' => 'CL',
            'CLUJ' => 'CJ',
            'CONSTANTA' => 'CT',
            'CONSTANȚA' => 'CT',
            'COVASNA' => 'CV',
            'DAMBOVITA' => 'DB',
            'DÂMBOVIȚA' => 'DB',
            'DOLJ' => 'DJ',
            'GALATI' => 'GL',
            'GALAȚI' => 'GL',
            'GIURGIU' => 'GR',
            'GORJ' => 'GJ',
            'HARGHITA' => 'HR',
            'HUNEDOARA' => 'HD',
            'IALOMITA' => 'IL',
            'IALOMIȚA' => 'IL',
            'IASI' => 'IS',
            'IAȘI' => 'IS',
            'ILFOV' => 'IF',
            'MARAMURES' => 'MM',
            'MARAMUREȘ' => 'MM',
            'MEHEDINTI' => 'MH',
            'MEHEDINȚI' => 'MH',
            'MURES' => 'MS',
            'MUREȘ' => 'MS',
            'NEAMT' => 'NT',
            'NEAMȚ' => 'NT',
            'OLT' => 'OT',
            'PRAHOVA' => 'PH',
            'SATU MARE' => 'SM',
            'SALAJ' => 'SJ',
            'SĂLAJ' => 'SJ',
            'SIBIU' => 'SB',
            'SUCEAVA' => 'SV',
            'TELEORMAN' => 'TR',
            'TIMIS' => 'TM',
            'TIMIȘ' => 'TM',
            'TULCEA' => 'TL',
            'VASLUI' => 'VS',
            'VALCEA' => 'VL',
            'VÂLCEA' => 'VL',
            'VRANCEA' => 'VN',
        );

        if (isset($map[$value])) {
            return $map[$value];
        }

        $value = preg_replace('/^RO-/', '', $value);
        return strlen($value) <= 2 ? $value : '';
    }
}

if (!function_exists('saga_xml_country_code')) {
    function saga_xml_country_code($value): string {
        $value = strtoupper(saga_xml_text($value));
        if ($value === '' || $value === 'ROMANIA' || $value === 'ROMÂNIA' || $value === 'RO') {
            return 'RO';
        }
        return strlen($value) === 2 ? $value : $value;
    }
}

if (!function_exists('saga_xml_invoice_type')) {
    function saga_xml_invoice_type($tipFactura): string {
        $tipFactura = saga_xml_text($tipFactura);
        if ($tipFactura === '384') {
            return 'C';
        }
        if ($tipFactura === '751') {
            return 'B';
        }
        return '';
    }
}

if (!function_exists('saga_xml_number')) {
    function saga_xml_number($value, int $decimals = 2): string {
        return number_format((float)$value, $decimals, '.', '');
    }
}

if (!function_exists('saga_xml_trim_number')) {
    function saga_xml_trim_number($value, int $decimals = 3): string {
        $number = saga_xml_number($value, $decimals);
        return rtrim(rtrim($number, '0'), '.');
    }
}

if (!function_exists('saga_xml_get_date_firma')) {
    function saga_xml_get_date_firma(PDO $pdo): array {
        $stmt = $pdo->query("SELECT * FROM casa_online_company LIMIT 1");
        $firma = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if (!$firma) {
            throw new RuntimeException('Datele firmei nu au fost gasite.');
        }

        return $firma;
    }
}

if (!function_exists('saga_xml_get_factura')) {
    function saga_xml_get_factura(PDO $pdo, int $idFactura): array {
        if ($idFactura <= 0) {
            throw new InvalidArgumentException('ID factura invalid.');
        }

        $stmt = $pdo->prepare("SELECT * FROM facturi WHERE id_factura = :id LIMIT 1");
        $stmt->execute(array(':id' => $idFactura));
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            throw new RuntimeException('Factura nu a fost gasita.');
        }

        return $factura;
    }
}

if (!function_exists('saga_xml_get_linii_factura')) {
    function saga_xml_get_linii_factura(PDO $pdo, int $idFactura): array {
        $factura = saga_xml_get_factura($pdo, $idFactura);

        // 1) Sursa corectă pentru produsele de pe factură: tabela vanzari, legată prin id_factura.
        $stmt = $pdo->prepare(""
            . "SELECT *\n"
            . "FROM vanzari\n"
            . "WHERE id_factura = :id\n"
            . "ORDER BY id_vanz ASC"
        );
        $stmt->execute(array(':id' => $idFactura));
        $linii = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($linii)) {
            return $linii;
        }

        // 2) Fallback tot în vanzari: pentru baze unde id_factura nu a fost completat/legat corect,
        // dar nr_factura + serie_factura corespund între facturi și vanzari.
        $nrFactura = saga_xml_text($factura['nr_factura'] ?? '');
        $serieFactura = saga_xml_text($factura['serie_factura'] ?? '');

        if ($nrFactura !== '') {
            $stmt2 = $pdo->prepare(""
                . "SELECT *\n"
                . "FROM vanzari\n"
                . "WHERE nr_factura = :nr_factura\n"
                . "  AND TRIM(CAST(serie_factura AS CHAR)) = :serie_factura\n"
                . "ORDER BY id_vanz ASC"
            );
            $stmt2->execute(array(
                ':nr_factura' => $nrFactura,
                ':serie_factura' => $serieFactura,
            ));
            $linii = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($linii)) {
                return $linii;
            }
        }

        throw new RuntimeException(
            'Factura ' . $idFactura . ' nu are linii gasite in tabela vanzari. '
            . 'Verifica vanzari.id_factura sau perechea vanzari.nr_factura + vanzari.serie_factura.'
        );
    }
}

if (!function_exists('saga_xml_append_factura_node')) {
    function saga_xml_append_factura_node(DOMDocument $doc, DOMElement $root, array $factura, array $linii, array $firma): void {
        $facturaNode = $doc->createElement('Factura');
        $root->appendChild($facturaNode);

        $antet = $doc->createElement('Antet');
        $facturaNode->appendChild($antet);

        saga_xml_append($doc, $antet, 'FurnizorNume', $firma['den_ent'] ?? '');
        saga_xml_append($doc, $antet, 'FurnizorCIF', $firma['cod_fiscal'] ?? '');
        saga_xml_append($doc, $antet, 'FurnizorNrRegCom', $firma['nr_reg_com'] ?? '');
        saga_xml_append($doc, $antet, 'FurnizorCapital', $firma['cap_soc'] ?? '');
        saga_xml_append($doc, $antet, 'FurnizorTara', 'RO');
        saga_xml_append($doc, $antet, 'FurnizorLocalitate', $firma['localitate'] ?? '');
        saga_xml_append($doc, $antet, 'FurnizorJudet', saga_xml_ro_county_code($firma['judet'] ?? ''));
        saga_xml_append($doc, $antet, 'FurnizorAdresa', $firma['sediu'] ?? '');
        saga_xml_append($doc, $antet, 'FurnizorTelefon', $firma['telefon'] ?? '');
        saga_xml_append($doc, $antet, 'FurnizorMail', $firma['email'] ?? '');
        saga_xml_append($doc, $antet, 'FurnizorBanca', $firma['banca'] ?? '');
        saga_xml_append($doc, $antet, 'FurnizorIBAN', $firma['cont_banca'] ?? '');
        saga_xml_append($doc, $antet, 'FurnizorInformatiiSuplimentare', '');

        $clientName = saga_xml_text($factura['denumire'] ?? '') !== ''
            ? saga_xml_text($factura['denumire'])
            : saga_xml_text($factura['nume'] ?? '');

        saga_xml_append($doc, $antet, 'ClientNume', $clientName);
        saga_xml_append($doc, $antet, 'ClientInformatiiSuplimentare', '');
        saga_xml_append($doc, $antet, 'ClientCIF', $factura['cod_fiscal'] ?? '');
        saga_xml_append($doc, $antet, 'ClientNrRegCom', $factura['cod_inmatriculare'] ?? ($factura['nr_reg_com'] ?? ''));
        saga_xml_append($doc, $antet, 'ClientJudet', saga_xml_ro_county_code($factura['adresa_judet'] ?? ($factura['judet'] ?? '')));
        saga_xml_append($doc, $antet, 'ClientTara', saga_xml_country_code($factura['adresa_tara'] ?? 'RO'));
        saga_xml_append($doc, $antet, 'ClientLocalitate', $factura['adresa_localitate'] ?? '');
        saga_xml_append($doc, $antet, 'ClientAdresa', $factura['adresa'] ?? '');
        saga_xml_append($doc, $antet, 'ClientBanca', $factura['banca'] ?? '');
        saga_xml_append($doc, $antet, 'ClientIBAN', $factura['iban'] ?? '');
        saga_xml_append($doc, $antet, 'ClientTelefon', $factura['tel'] ?? '');
        saga_xml_append($doc, $antet, 'ClientMail', $factura['email'] ?? ($factura['client_email'] ?? ''));

        $facturaNumar = trim((saga_xml_text($factura['serie_factura']) !== '' ? saga_xml_text($factura['serie_factura']) . '-' : '') . saga_xml_text($factura['nr_factura']), '-');
        saga_xml_append($doc, $antet, 'FacturaNumar', $facturaNumar);
        saga_xml_append($doc, $antet, 'FacturaData', saga_xml_date($factura['data_factura'] ?? ''));
        saga_xml_append($doc, $antet, 'FacturaScadenta', saga_xml_date($factura['data_scadenta'] ?? ''));
        saga_xml_append($doc, $antet, 'FacturaTaxareInversa', 'Nu');
        saga_xml_append($doc, $antet, 'FacturaTVAIncasare', 'Nu');
        saga_xml_append($doc, $antet, 'FacturaTip', saga_xml_invoice_type($factura['tip_factura'] ?? ''));
        saga_xml_append($doc, $antet, 'FacturaInformatiiSuplimentare', $factura['observatii'] ?? '');
        saga_xml_append($doc, $antet, 'FacturaMoneda', 'RON');
        saga_xml_append($doc, $antet, 'FacturaGreutate', '');
        saga_xml_append($doc, $antet, 'FacturaAccize', '');
        saga_xml_append($doc, $antet, 'FacturaIndexSPV', '');
        saga_xml_append($doc, $antet, 'FacturaIndexDescarcareSPV', '');
        saga_xml_append($doc, $antet, 'Cod', '');

        $detalii = $doc->createElement('Detalii');
        $continut = $doc->createElement('Continut');
        $detalii->appendChild($continut);
        $facturaNode->appendChild($detalii);

        $nrCrt = 1;
        foreach ($linii as $linie) {
            $cantitate = (float)($linie['cantitate'] ?? 0);
            $valoare = (float)($linie['valoare_vanzare'] ?? 0);
            $pret = $cantitate != 0.0 ? $valoare / $cantitate : (float)($linie['pret_vanzare'] ?? 0);

            $linieNode = $doc->createElement('Linie');
            $continut->appendChild($linieNode);

            saga_xml_append($doc, $linieNode, 'LinieNrCrt', $nrCrt++);
            saga_xml_append($doc, $linieNode, 'Gestiune', '');
            saga_xml_append($doc, $linieNode, 'Activitate', '');
            saga_xml_append($doc, $linieNode, 'Descriere', $linie['den_p'] ?? '');
            saga_xml_append($doc, $linieNode, 'CodArticolFurnizor', '');
            saga_xml_append($doc, $linieNode, 'CodArticolClient', $linie['cod_p'] ?? '');
            saga_xml_append($doc, $linieNode, 'GUID_cod_articol', '');
            saga_xml_append($doc, $linieNode, 'CodBare', '');
            saga_xml_append($doc, $linieNode, 'InformatiiSuplimentare', '');
            saga_xml_append($doc, $linieNode, 'UM', $linie['um'] ?? '');
            saga_xml_append($doc, $linieNode, 'Cantitate', saga_xml_trim_number($cantitate, 3));
            saga_xml_append($doc, $linieNode, 'Pret', saga_xml_number($pret, 5));
            saga_xml_append($doc, $linieNode, 'Valoare', saga_xml_number($valoare, 2));
            saga_xml_append($doc, $linieNode, 'ProcTVA', saga_xml_trim_number($linie['cota_tva'] ?? 0, 2));
            saga_xml_append($doc, $linieNode, 'TVA', saga_xml_number($linie['tva_col'] ?? 0, 2));
            saga_xml_append($doc, $linieNode, 'Cont', '');
        }

        saga_xml_append($doc, $facturaNode, 'FacturaID', $factura['id_factura'] ?? '');
    }
}

if (!function_exists('saga_xml_build_document_for_ids')) {
    function saga_xml_build_document_for_ids(PDO $pdo, array $ids): string {
        if (!class_exists('DOMDocument')) {
            throw new RuntimeException('Extensia DOM nu este activa pe server (php-xml).');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));

        if (empty($ids)) {
            throw new InvalidArgumentException('Nu au fost primite facturi valide.');
        }

        $firma = saga_xml_get_date_firma($pdo);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('Facturi');
        $doc->appendChild($root);

        foreach ($ids as $idFactura) {
            $factura = saga_xml_get_factura($pdo, $idFactura);
            $linii = saga_xml_get_linii_factura($pdo, $idFactura);
            saga_xml_append_factura_node($doc, $root, $factura, $linii, $firma);
        }

        return $doc->saveXML();
    }
}

if (!function_exists('saga_xml_build_filename_for_factura')) {
    function saga_xml_build_filename_for_factura(PDO $pdo, int $idFactura): string {
        $firma = saga_xml_get_date_firma($pdo);
        $factura = saga_xml_get_factura($pdo, $idFactura);

        $facturaNumar = trim((saga_xml_text($factura['serie_factura']) !== '' ? saga_xml_text($factura['serie_factura']) . '-' : '') . saga_xml_text($factura['nr_factura']), '-');

        return sprintf(
            'F_%s_%s_%s.xml',
            saga_xml_safe_filename_part($firma['cod_fiscal'] ?? ''),
            saga_xml_safe_filename_part($facturaNumar, (string)$idFactura),
            saga_xml_safe_filename_part(saga_xml_date($factura['data_factura'] ?? ''), date('d.m.Y'))
        );
    }
}

if (!function_exists('saga_xml_generate_factura_file')) {
    function saga_xml_generate_factura_file(PDO $pdo, int $idFactura): array {
        return array(
            'filename' => saga_xml_build_filename_for_factura($pdo, $idFactura),
            'xml' => saga_xml_build_document_for_ids($pdo, array($idFactura)),
        );
    }
}

if (!function_exists('saga_xml_download_factura')) {
    function saga_xml_download_factura(PDO $pdo, int $idFactura): void {
        $data = saga_xml_generate_factura_file($pdo, $idFactura);
        $xml = $data['xml'];
        $filename = $data['filename'];

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($xml));
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $xml;
        exit;
    }
}

// Acces direct: descarcă o singură factură.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    require_once 'database_connection.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        http_response_code(500);
        exit('Conexiunea la baza de date nu este disponibila.');
    }

    $idFactura = isset($_GET['id_factura']) ? (int)$_GET['id_factura'] : 0;

    try {
        saga_xml_download_factura($pdo, $idFactura);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        exit($e->getMessage());
    } catch (RuntimeException $e) {
        http_response_code(404);
        exit($e->getMessage());
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Eroare la generarea XML SAGA.');
    }
}
