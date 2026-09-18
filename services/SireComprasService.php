<?php
/**
 * SireComprasService
 *
 * Lista la Propuesta del RCE (Compras) usando el endpoint que utiliza el UI de SOL:
 *   /v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/{periodo}/busqueda
 * con query: codTipoOpe, page, perPage.
 *
 * Notas:
 * - Host SIRE: https://api-sire.sunat.gob.pe
 * - Prefijo:   /v1/contribuyente/migeigv/libros/...
 * - Algunos despliegues aceptan header 'Num-Ruc: {RUC}' (lo incluimos como opcional).
 * - El endpoint puede devolver:
 *   a) Objeto { paginacion: {...}, registros: [...] }  ← (forma de SOL)
 *   b) Lista plana [...], o bien objeto con { items: [...] } / { data: [...] }  (fallbacks)
 */

class SireComprasService
{
    private string $base;

    public function __construct(string $baseUrl = 'https://api-sire.sunat.gob.pe')
    {
        $this->base = rtrim($baseUrl, '/');
    }

    /* ============================================================
     * Helpers HTTP
     * ========================================================== */

    private function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $ch = curl_init($url);
        $httpHeaders = array_merge(
            [
                'Accept: application/json, text/plain, */*'
            ],
            $headers
        );

        $opts = [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => $httpHeaders,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        return ['code' => $code, 'body' => $res, 'err' => $err];
    }

    private function authHeaders(string $accessToken, ?string $numRuc = null): array
    {
        $h = ['Authorization: Bearer ' . $accessToken];
        if (!empty($numRuc)) $h[] = 'Num-Ruc: ' . $numRuc; // opcional, algunos despliegues lo esperan
        // (opcional) mimetizar origen del UI:
        // $h[] = 'Origin: https://e-factura.sunat.gob.pe';
        // $h[] = 'Referer: https://e-factura.sunat.gob.pe/';
        return $h;
    }

    /* ============================================================
     * Normalización de filas
     * ========================================================== */

    private function normalizarFilaCompra(array $fila): array
    {
        // Campos de identificación
        $tipo   = $fila['codTipoCDP'] ?? $fila['tipoCdp'] ?? $fila['tipo_comp'] ?? '';
        $serie  = $fila['numSerieCDP'] ?? $fila['serieCdp'] ?? $fila['serie'] ?? '';
        $numero = $fila['numCDP'] ?? $fila['numeroCdp'] ?? $fila['correlativo'] ?? '';
        $fecEmi = $fila['fecEmision'] ?? $fila['fechaEmision'] ?? $fila['fecha_emision'] ?? '';
        $prov   = $fila['nomRazonSocialProveedor'] ?? $fila['razonSocialProveedor'] ?? $fila['proveedor'] ?? '';

        // Importes: vienen en 'montos'
        $m = is_array($fila['montos'] ?? null) ? $fila['montos'] : [];
        $base = (float)str_replace(',', '', (string)($m['mtoBIGravadaDG'] ?? $fila['baseImponible'] ?? 0));
        $igv  = (float)str_replace(',', '', (string)($m['mtoIgvIpmDG']   ?? $fila['igv']            ?? 0));
        $tot  = (float)str_replace(',', '', (string)($m['mtoTotalCp']    ?? $fila['importeTotal']   ?? ($base + $igv)));

        return [
            'tipo_comp'        => $tipo,
            'serie'            => $serie,
            'correlativo'      => $numero,
            'fecha_emision'    => $fecEmi,
            'proveedor_nombre' => $prov,
            'base_imponible'   => $base,
            'igv'              => $igv,
            'total'            => $tot,
        ];
    }

    /* ============================================================
     * PROCESOS PRINCIPALES (Propuesta RCE)
     * ========================================================== */

    /**
     * Descarga UNA PÁGINA de Propuesta RCE (endpoint de SOL).
     *
     * @param string $accessToken   Token OAuth
     * @param string $perTributario YYYYMM (p.ej. 202511)
     * @param string $numRuc        RUC del contribuyente (11 dígitos) – opcional en header
     * @param int    $page          Página (1..N)
     * @param int    $perPage       Tamaño de página (UI usa 20/100)
     * @param int    $codTipoOpe    Según tu captura: 3 (puedes cambiar a 1 si tu UI lo usa)
     * @return array ['registros'=>[], 'paginacion'=>[], 'raw'=>[]]
     */
    public function descargarPropuestaPagina(
        string $accessToken,
        string $perTributario,
        string $numRuc,
        int $page = 1,
        int $perPage = 100,
        int $codTipoOpe = 3
    ): array {
        $headers = $this->authHeaders($accessToken, $numRuc);

        $url = $this->base
             . "/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/{$perTributario}/busqueda"
             . "?codTipoOpe={$codTipoOpe}&page={$page}&perPage={$perPage}";

        $resp = $this->request("GET", $url, $headers);

        if ($resp['code'] === 401 || $resp['code'] === 403) {
            throw new Exception("No autorizado en Propuesta RCE (HTTP {$resp['code']}).");
        }
        if ($resp['code'] === 422) {
            // Validación/ausencia de info (SIRE suele retornar 422 con 'errors')
            $json = json_decode($resp['body'] ?? '[]', true) ?: [];
            return ['registros' => [], 'paginacion' => null, 'raw' => $json];
        }
        if ($resp['code'] >= 500) {
            throw new Exception("HTTP {$resp['code']} en Propuesta RCE: " . substr((string)$resp['body'], 0, 600));
        }
        if ($resp['code'] >= 400) {
            throw new Exception("HTTP {$resp['code']} en Propuesta RCE: " . substr((string)$resp['body'], 0, 600));
        }

        $json = json_decode($resp['body'] ?? '[]', true);
        if (!is_array($json)) {
            throw new Exception("Propuesta RCE: respuesta no-JSON");
        }

        // Forma A (UI SOL): { paginacion: {...}, registros: [...] }
        $rows       = [];
        $paginacion = null;

        if (isset($json['registros']) && is_array($json['registros'])) {
            $rows       = $json['registros'];
            $paginacion = $json['paginacion'] ?? null;
        }
        // Forma B: lista plana
        elseif (isset($json[0]) && is_array($json[0])) {
            $rows = $json;
        }
        // Forma C: { items: [...] } o { data: [...] }
        elseif (isset($json['items']) && is_array($json['items'])) {
            $rows = $json['items'];
        } elseif (isset($json['data']) && is_array($json['data'])) {
            $rows = $json['data'];
        }

        $registros = [];
        foreach ($rows as $fila) {
            if (is_array($fila)) {
                $registros[] = $this->normalizarFilaCompra($fila);
            }
        }

        return [
            'registros'  => $registros,
            'paginacion' => $paginacion,
            'raw'        => $json
        ];
    }

    /**
     * Recorre todas las páginas usando la paginación oficial (paginacion.totalRegistros / perPage).
     *
     * @return array ['total'=>N, 'registros'=>[], 'sumas'=>['base','igv','total'], 'raw'=>[...por página...]]
     */
    public function obtenerComprasPeriodo(
        string $accessToken,
        string $perTributario,
        string $numRuc,
        int $perPage = 100,
        int $codTipoOpe = 3
    ): array {
        $raws = [];
        $all  = [];

        // 1) Primera página para conocer paginación
        $p1   = $this->descargarPropuestaPagina($accessToken, $perTributario, $numRuc, 1, $perPage, $codTipoOpe);
        $raws[] = $p1['raw'] ?? [];
        $all    = array_merge($all, $p1['registros'] ?? []);

        $totalRegistros = (int)($p1['paginacion']['totalRegistros'] ?? 0);
        if ($totalRegistros === 0) {
            // Si la primera página vino vacía, terminar
            $total = count($all);
            $sumBase  = array_sum(array_column($all, 'base_imponible'));
            $sumIgv   = array_sum(array_column($all, 'igv'));
            $sumTotal = array_sum(array_column($all, 'total'));
            return [
                'total'     => $total,
                'registros' => $all,
                'sumas'     => ['base' => $sumBase, 'igv' => $sumIgv, 'total' => $sumTotal],
                'raw'       => $raws
            ];
        }

        $totalPages = (int)ceil($totalRegistros / max(1, $perPage));

        // 2) Siguientes páginas (si las hay)
        for ($page = 2; $page <= $totalPages; $page++) {
            $parte  = $this->descargarPropuestaPagina($accessToken, $perTributario, $numRuc, $page, $perPage, $codTipoOpe);
            $raws[] = $parte['raw'] ?? [];
            $all    = array_merge($all, $parte['registros'] ?? []);
        }

        $total    = count($all);
        $sumBase  = array_sum(array_column($all, 'base_imponible'));
        $sumIgv   = array_sum(array_column($all, 'igv'));
        $sumTotal = array_sum(array_column($all, 'total'));

        return [
            'total'     => $total,
            'registros' => $all,
            'sumas'     => ['base' => $sumBase, 'igv' => $sumIgv, 'total' => $sumTotal],
            'raw'       => $raws
        ];
    }
}