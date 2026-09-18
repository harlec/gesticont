<?php
/**
 * GestiCont — SunatApiService
 * Integración SIRE: Ventas (RVIE) + Compras (RCE)
 *
 * Endpoints confirmados:
 * - Token:   api-seguridad.sunat.gob.pe/v1/clientessol/{id}/oauth2/token/
 * - Ventas:  api-sire.sunat.gob.pe/.../rvie/propuesta/web/propuesta/{p}/comprobantes
 * - Compras: api-sire.sunat.gob.pe/.../rce/propuesta/web/propuesta/{p}/busqueda
 */
class SunatApiService
{
    private const TOKEN_URL = 'https://api-seguridad.sunat.gob.pe/v1/clientessol/{client_id}/oauth2/token/';
    private const SIRE_BASE = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv';
    private const SCOPE     = 'https://api.sunat.gob.pe/v1/contribuyente/migeigv';
    private array $tokenCache = [];

    // ── Token OAuth2 ────────────────────────────────────────────────────────
    public function getToken(string $clientId, string $clientSecret,
                             string $ruc, string $usuario, string $clave): string
    {
        $key = md5($clientId . $ruc . $usuario);
        if (isset($this->tokenCache[$key]) && $this->tokenCache[$key]['exp'] > time() + 60) {
            return $this->tokenCache[$key]['token'];
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
        $this->tokenCache[$key] = [
            'token' => $resp['access_token'],
            'exp'   => time() + ($resp['expires_in'] ?? 3600),
        ];
        return $resp['access_token'];
    }

    // ── VENTAS — una página ─────────────────────────────────────────────────
    public function getVentasPeriodo(string $token, string $periodo,
                                     int $page = 1, int $perPage = 100): array
    {
        $url  = self::SIRE_BASE
              . "/libros/rvie/propuesta/web/propuesta/{$periodo}/comprobantes"
              . "?page={$page}&perPage={$perPage}";
        $resp = $this->get($url, $token);
        return [
            'total'     => $resp['paginacion']['totalRegistros'] ?? 0,
            'page'      => $page,
            'registros' => array_map([$this, 'normalizarVenta'], $resp['registros'] ?? []),
        ];
    }

    // ── VENTAS — todas las páginas ──────────────────────────────────────────
    public function getAllVentasPeriodo(string $token, string $periodo): array
    {
        $page = 1; $todos = [];
        do {
            $r     = $this->getVentasPeriodo($token, $periodo, $page);
            $todos = array_merge($todos, $r['registros']);
            $page++;
        } while (count($todos) < $r['total'] && count($r['registros']) > 0);
        return $todos;
    }

    // ── COMPRAS — una página ────────────────────────────────────────────────
    public function getComprasPeriodo(string $token, string $ruc, string $periodo,
                                      int $page = 1, int $perPage = 100,
                                      int $codTipoOpe = 3): array
    {
        $url  = self::SIRE_BASE
              . "/libros/rce/propuesta/web/propuesta/{$periodo}/busqueda"
              . "?codTipoOpe={$codTipoOpe}&page={$page}&perPage={$perPage}";
        try {
            $resp = $this->get($url, $token, $ruc);
        } catch (RuntimeException $e) {
            // 422 = sin datos para ese período
            if (str_contains($e->getMessage(), '422')) {
                return ['total' => 0, 'page' => $page, 'registros' => []];
            }
            throw $e;
        }
        return [
            'total'     => $resp['paginacion']['totalRegistros'] ?? 0,
            'page'      => $page,
            'registros' => array_map([$this, 'normalizarCompra'], $resp['registros'] ?? []),
        ];
    }

    // ── COMPRAS — todas las páginas ─────────────────────────────────────────
    public function getAllComprasPeriodo(string $token, string $ruc, string $periodo): array
    {
        $page = 1; $todos = [];
        do {
            $r     = $this->getComprasPeriodo($token, $ruc, $periodo, $page);
            $todos = array_merge($todos, $r['registros']);
            $page++;
        } while (count($todos) < $r['total'] && count($r['registros']) > 0);
        return $todos;
    }

    // ── Normalizar venta ────────────────────────────────────────────────────
    private function normalizarVenta(array $i): array
    {
        return [
            'id_sire'          => $i['id']                    ?? null,
            'cod_car'          => $i['codCar']                 ?? null,
            'tipo_comp'        => $i['codTipoCDP']             ?? '01',
            'serie'            => $i['numSerieCDP']            ?? '',
            'correlativo'      => $i['numCDP']                 ?? '',
            'fecha_emision'    => $this->parseDate($i['fecEmision'] ?? ''),
            'cliente_tipo_doc' => $i['codTipoDocIdentidad']    ?? '6',
            'cliente_num_doc'  => $i['numDocIdentidad']        ?? '',
            'cliente_nombre'   => $i['nomRazonSocialCliente']  ?? '',
            'moneda'           => $i['codMoneda']              ?? 'PEN',
            'tipo_cambio'      => (float)($i['mtoTipoCambio'] ?? 1),
            'base_imponible'   => (float)($i['mtoBIGravada']  ?? 0),
            'igv'              => (float)($i['mtoIGV']        ?? 0),
            'exonerado'        => (float)($i['mtoExonerado']  ?? 0),
            'inafecto'         => (float)($i['mtoInafecto']   ?? 0),
            'total'            => (float)($i['mtoTotalCP']    ?? 0),
            'estado_sunat'     => $i['codEstadoComprobante']  ?? '1',
            'periodo'          => $i['perPeriodoTributario']  ?? '',
        ];
    }

    // ── Normalizar compra ───────────────────────────────────────────────────
    private function normalizarCompra(array $i): array
    {
        $m = $i['montos'] ?? [];
        return [
            'id_sire'            => $i['id']                         ?? null,
            'cod_car'            => $i['codCar']                      ?? null,
            'tipo_comp'          => $i['codTipoCDP']                  ?? '01',
            'serie'              => $i['numSerieCDP']                 ?? '',
            'correlativo'        => $i['numCDP']                      ?? '',
            'fecha_emision'      => $this->parseDate($i['fecEmision'] ?? ''),
            'proveedor_tipo_doc' => $i['codTipoDocIdentidad']         ?? '6',
            'proveedor_ruc'      => $i['numDocIdentidad']             ?? '',
            'proveedor_nombre'   => $i['nomRazonSocialProveedor']     ?? $i['nomRazonSocial'] ?? '',
            'moneda'             => $i['codMoneda']                   ?? 'PEN',
            'tipo_cambio'        => (float)($i['mtoTipoCambio']      ?? 1),
            'base_imponible'     => (float)($m['mtoBIGravadaDG']     ?? $i['mtoBIGravada']   ?? 0),
            'igv'                => (float)($m['mtoIgvIpmDG']        ?? $i['mtoIGV']         ?? 0),
            'exonerado'          => (float)($m['mtoExonerado']       ?? $i['mtoExonerado']   ?? 0),
            'inafecto'           => (float)($m['mtoInafecto']        ?? $i['mtoInafecto']    ?? 0),
            'total'              => (float)($m['mtoTotalCp']         ?? $i['mtoTotalCP']     ?? 0),
            'estado_sunat'       => $i['codEstadoComprobante']        ?? '1',
            'periodo'            => $i['perPeriodoTributario']        ?? '',
        ];
    }

    // ── Parsear fecha dd/mm/yyyy → Y-m-d ───────────────────────────────────
    private function parseDate(string $f): string
    {
        if (empty($f)) return date('Y-m-d');
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $f, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : date('Y-m-d');
    }

    // ── HTTP GET ────────────────────────────────────────────────────────────
    public function get(string $url, string $token, ?string $numRuc = null): array
    {
        $headers = ["Authorization: Bearer {$token}", "Accept: application/json"];
        if ($numRuc) $headers[] = "Num-Ruc: {$numRuc}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) throw new RuntimeException("SIRE HTTP {$code}: " . substr($body, 0, 300));
        return json_decode($body, true) ?? [];
    }

    // ── HTTP POST form ──────────────────────────────────────────────────────
    private function post(string $url, array $data, string $type = 'json'): array
    {
        $headers  = ['Accept: application/json'];
        $headers[] = $type === 'form'
            ? 'Content-Type: application/x-www-form-urlencoded'
            : 'Content-Type: application/json';
        $postData = $type === 'form' ? http_build_query($data) : json_encode($data);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch); curl_close($ch);
        return json_decode($body, true) ?? [];
    }
}
