<?php
return [
    'ambiente' => getenv('SUNAT_AMBIENTE') ?: 'beta',
    'endpoints' => [
        'beta' => [
            'factura' => 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
            'guia'    => 'https://e-beta.sunat.gob.pe/ol-ti-itemision-guia-pide-gem/billService',
            'token'   => 'https://api-seguridad.sunat.gob.pe/v1/clientessol/{ruc}/oauth2/token/',
            'sire'    => 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes/{ruc}',
        ],
        'produccion' => [
            'factura' => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
            'guia'    => 'https://e-guiaremision.sunat.gob.pe/ol-ti-itemision-guia-pide-gem/billService',
            'token'   => 'https://api.sunat.gob.pe/v1/clientessol/{ruc}/oauth2/token/',
            'sire'    => 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes/{ruc}',
        ],
    ],
];
