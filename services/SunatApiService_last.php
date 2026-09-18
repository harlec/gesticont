<?php
/**
 * GestiCont — SunatApiService
 * Integración con API SIRE de SUNAT
 *
 * Endpoints confirmados funcionando:
 * - Token:    api-seguridad.sunat.gob.pe/v1/clientessol/{client_id}/oauth2/token/
 * - Ventas:   api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvie/propuesta/web/propuesta/{periodo}/comprobantes
 * - Compras:  api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/{periodo}/comprobantes (pendiente confirmar)
 */
class SunatApiService
{
    private const TOKEN_URL  = 'https://api-seguridad.sunat.gob.pe/v1/clientessol/{client_id}/oauth2/token/';
    private const SIRE_BASE  = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv';
    private const SCOPE      = 'https://api.sunat.gob.pe/v1/contribuyente/migeigv';

    private array $tokenCache = [];

    // ── Obtener token OAuth2 ─────────────────────────────
    public function getToken(string $clientId, string $clientSecret,
                             string $ruc, string $usuario, string $clave): string
    {
        $cacheKey = md5($clientId . $ruc . $usuario);
        if (isset($this->tokenCache[$cacheKey])
            && $this->tokenCache[$cacheKey]['expires_at'] > time() + 60) {
            return $this->tokenCache[$cacheKey]['token'];
        }

        $url  = str_replace('{client_id}', $clientId, self::TOKEN_URL);
        $resp = $this->post($url, [
            'grant_type'    => 'password',
            'scope'         => self::SCOPE,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'username'      => $ruc . $usuario,
            'password'      => $clave,
        ], 'form');

        if (empty($resp['access_token'])) {
            throw new RuntimeException('Token SUNAT fallido: ' . json_encode($resp));
        }

        $this->tokenCache[$cacheKey] = [
            'token'      => $resp['access_token'],
            'expires_at' => time() + ($resp['expires_in'] ?? 3600),
        ];
        return $resp['access_token'];
    }

    // ── Obtener ventas del período via SIRE ──────────────
    public function getVentasPeriodo(string $token, string $periodo,
                                     int $page = 1, int $perPage = 100): array
    {
        $url  = self::SIRE_BASE
              . "/libros/rvie/propuesta/web/propuesta/{$periodo}/comprobantes"
              . "?page={$page}&perPage={$perPage}";
        $resp = $this->get($url, $token);

        return [
            'total'      => $resp['paginacion']['totalRegistros'] ?? 0,
            'page'       => $resp['paginacion']['page']           ?? $page,
            'perPage'    => $resp['paginacion']['perPage']        ?? $perPage,
            'registros'  => array_map(
                [$this, 'normalizarVenta'],
                $resp['registros'] ?? []
            ),
        ];
    }

    // ── Obtener TODAS las ventas del período (paginación automática) ──
    public function getAllVentasPeriodo(string $token, string $periodo): array
    {
        $page    = 1;
        $perPage = 100;
        $todos   = [];

        do {
            $result = $this->getVentasPeriodo($token, $periodo, $page, $perPage);
            $todos  = array_merge($todos, $result['registros']);
            $page++;
        } while (count($todos) < $result['total'] && count($result['registros']) > 0);

        return $todos;
    }

    // ── Obtener compras del período via SIRE ──────────────
    public function getComprasPeriodo(string $token, string $periodo,
                                      int $page = 1, int $perPage = 100): array
    {
        // Path confirmado para compras
        $url  = self::SIRE_BASE
              . "/libros/rce/propuesta/web/propuesta/{$periodo}/comprobantes"
              . "?page={$page}&perPage={$perPage}";
        $resp = $this->get($url, $token);

        return [
            'total'     => $resp['paginacion']['totalRegistros'] ?? 0,
            'page'      => $resp['paginacion']['page']           ?? $page,
            'perPage'   => $resp['paginacion']['perPage']        ?? $perPage,
            'registros' => array_map(
                [$this, 'normalizarCompra'],
                $resp['registros'] ?? []
            ),
        ];
    }

    // ── Obtener TODAS las compras del período ──────────────
    public function getAllComprasPeriodo(string $token, string $periodo): array
    {
        $page  = 1; $perPage = 100; $todos = [];
        do {
            $result = $this->getComprasPeriodo($token, $periodo, $page, $perPage);
            $todos  = array_merge($todos, $result['registros']);
            $page++;
        } while (count($todos) < $result['total'] && count($result['registros']) > 0);
        return $todos;
    }

    // ── Normalizar comprobante de venta ───────────────────
    private function normalizarVenta(array $item): array
    {
        return [
            // Identificación
            'id_sire'         => $item['id']               ?? null,
            'cod_car'         => $item['codCar']            ?? null,
            'tipo_comp'       => $item['codTipoCDP']        ?? '01',
            'serie'           => $item['numSerieCDP']       ?? '',
            'correlativo'     => $item['numCDP']            ?? '',
            'fecha_emision'   => $this->parseDate($item['fecEmision'] ?? ''),
            // Cliente
            'cliente_tipo_doc'=> $item['codTipoDocIdentidad']    ?? '6',
            'cliente_num_doc' => $item['numDocIdentidad']         ?? '',
            'cliente_nombre'  => $item['nomRazonSocialCliente']   ?? '',
            // Importes — campos exactos confirmados
            'base_imponible'  => (float)($item['mtoBIGravada']    ?? 0),
            'igv'             => (float)($item['mtoIGV']          ?? 0),
            'exonerado'       => (float)($item['mtoExonerado']    ?? 0),
            'inafecto'        => (float)($item['mtoInafecto']     ?? 0),
            'isc'             => (float)($item['mtoISC']          ?? 0),
            'otros_tributos'  => (float)($item['mtoOtrosTrib']    ?? 0),
            'total'           => (float)($item['mtoTotalCP']      ?? 0),
            // Moneda y tipo cambio
            'moneda'          => $item['codMoneda']               ?? 'PEN',
            'tipo_cambio'     => (float)($item['mtoTipoCambio']   ?? 1),
            // Estado
            'estado_sunat'    => $item['codEstadoComprobante']    ?? '1',
            'des_estado'      => $item['desEstadoComprobante']    ?? '',
            'tipo_operacion'  => $item['indTipoOperacion']        ?? '',
            // Periodo
            'periodo'         => $item['perPeriodoTributario']    ?? '',
        ];
    }

    // ── Normalizar comprobante de compra ──────────────────
    private function normalizarCompra(array $item): array
    {
        return [
            'id_sire'         => $item['id']                      ?? null,
            'cod_car'         => $item['codCar']                   ?? null,
            'tipo_comp'       => $item['codTipoCDP']               ?? '01',
            'serie'           => $item['numSerieCDP']              ?? '',
            'correlativo'     => $item['numCDP']                   ?? '',
            'fecha_emision'   => $this->parseDate($item['fecEmision'] ?? ''),
            // Proveedor
            'proveedor_tipo_doc' => $item['codTipoDocIdentidad']   ?? '6',
            'proveedor_ruc'   => $item['numDocIdentidad']          ?? '',
            'proveedor_nombre'=> $item['nomRazonSocialEmisor']     ?? $item['nomRazonSocial'] ?? '',
            // Importes
            'base_imponible'  => (float)($item['mtoBIGravada']     ?? $item['mtoBaseIgv'] ?? 0),
            'igv'             => (float)($item['mtoIGV']           ?? 0),
            'exonerado'       => (float)($item['mtoExonerado']     ?? 0),
            'inafecto'        => (float)($item['mtoInafecto']      ?? 0),
            'total'           => (float)($item['mtoTotalCP']       ?? $item['mtoImporteTotal'] ?? 0),
            'moneda'          => $item['codMoneda']                ?? 'PEN',
            'tipo_cambio'     => (float)($item['mtoTipoCambio']    ?? 1),
            // Estado
            'estado_sunat'    => $item['codEstadoComprobante']     ?? '1',
            'periodo'         => $item['perPeriodoTributario']     ?? '',
        ];
    }

    // ── Parsear fecha dd/mm/yyyy → Y-m-d ─────────────────
    private function parseDate(string $fecha): string
    {
        if (empty($fecha)) return date('Y-m-d');
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');
    }

    // ── HTTP GET ──────────────────────────────────────────
    private function get(string $url, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                "Accept: application/json",
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) throw new RuntimeException("cURL: {$err}");
        if ($code >= 400) throw new RuntimeException("SIRE HTTP {$code}: {$body}");

        return json_decode($body, true) ?? [];
    }

    // ── HTTP POST ─────────────────────────────────────────
    private function post(string $url, array $data, string $type = 'json'): array
    {
        $headers = ['Accept: application/json'];
        if ($type === 'form') {
            $headers[]   = 'Content-Type: application/x-www-form-urlencoded';
            $postData    = http_build_query($data);
        } else {
            $headers[]   = 'Content-Type: application/json';
            $postData    = json_encode($data);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return json_decode($body, true) ?? [];
    }
}