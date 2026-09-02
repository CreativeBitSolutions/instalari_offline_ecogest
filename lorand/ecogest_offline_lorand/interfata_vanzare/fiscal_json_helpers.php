<?php

function normalize_bon_casa_marcat_json_row(array $row): array
{
    foreach (['id', 'de_trimis_la_casa_marcat', 'locatie', 'nrbon', 'id_factura'] as $key) {
        if (array_key_exists($key, $row)) {
            $row[$key] = (int)$row[$key];
        }
    }

    return $row;
}

function normalize_bon_casa_marcat_json_rows(array $rows): array
{
    return array_map('normalize_bon_casa_marcat_json_row', $rows);
}
