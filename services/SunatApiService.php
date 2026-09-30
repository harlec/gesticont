<?php
/**
 * GestiCont — SunatApiService v2
 * Lógica automática: intenta DECLARADO primero, cae en PROPUESTA si no hay datos.
 */
class SunatApiService
{
    private const TOKEN_URL = 'https://api-seguridad.sunat.gob.pe/v1/clientessol/{client_id}/oauth2/token/';
    private const SIRE_BASE = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv';
    private const SCOPE     = 'https://api.sunat.gob.pe/v1/contribuyente/migeigv';
    private array $tokenCache = [];

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

    // Tope de seguridad contra un ciclo infinito si SUNAT devolviera siempre lo mismo.
    private const MAX_PAGINAS = 2000;

    private function esSinDatos(RuntimeException $e): bool
    {
        return (bool)preg_match('/HTTP (404|422)\b/', $e->getMessage());
    }

    // ── VENTAS una página ────────────────────────────────────────────────────
    // $fuente null = intenta declarado y cae en propuesta (solo en la página 1).
    // Con $fuente fijo (páginas 2+) usa siempre la misma, para no mezclar
    // declarado con propuesta a mitad del período, y un fallo NO se oculta:
    // vuelve en 'error' para que quien pagina sepa que quedó incompleto.
    public function getVentasPeriodo(string $token, string $periodo,
                                     int $page = 1, int $perPage = 100, ?string $fuente = null): array
    {
        $base = self::SIRE_BASE . "/libros/rvie/propuesta/web/propuesta/{$periodo}/comprobantes";
        $urlDeclarado = "{$base}?page={$page}&perPage={$perPage}&codTipoResumen=5";
        $urlPropuesta = "{$base}?page={$page}&perPage={$perPage}";
        $vacio = fn(string $f, ?string $err = null) => ['total'=>0,'page'=>$page,'fuente'=>$f,'registros'=>[],'error'=>$err];
        $primeraVez = $fuente === null;

        try {
            if ($fuente === 'propuesta') {
                $resp = $this->get($urlPropuesta, $token);
            } elseif ($fuente === 'declarado') {
                $resp = $this->get($urlDeclarado, $token);
            } else {
                try {
                    $resp   = $this->get($urlDeclarado, $token);
                    $fuente = 'declarado';
                    if (($resp['paginacion']['totalRegistros'] ?? 0) === 0 && $page === 1) throw new RuntimeException('sin datos declarados');
                } catch (RuntimeException $e) {
                    $resp   = $this->get($urlPropuesta, $token);
                    $fuente = 'propuesta';
                }
            }
        } catch (RuntimeException $e) {
            // 404/422 = SUNAT no tiene datos para el período (no es un fallo).
            if ($this->esSinDatos($e)) return $vacio('sin_datos');
            return $vacio($primeraVez ? 'error' : $fuente, $e->getMessage());
        }

        return [
            'total'     => (int)($resp['paginacion']['totalRegistros'] ?? 0),
            'page'      => $page,
            'fuente'    => $fuente,
            'registros' => array_map([$this,'normalizarVenta'], $resp['registros'] ?? []),
            'error'     => null,
        ];
    }

    // ── VENTAS todas las páginas ─────────────────────────────────────────────
    public function getAllVentasPeriodo(string $token, string $periodo): array
    {
        return $this->paginar(fn(int $page, ?string $f) => $this->getVentasPeriodo($token, $periodo, $page, 100, $f), "ventas {$periodo}");
    }

    /**
     * Recorre todas las páginas y devuelve además lo necesario para detectar
     * un corte: 'total_sunat' (lo que SUNAT dice que hay), 'paginas', y
     * 'error' si quedó incompleto (fallo a mitad o menos registros de los
     * informados). Antes esos casos terminaban en silencio con lo que hubiera
     * alcanzado a bajar, y la pantalla lo mostraba como si fuera el total.
     */
    private function paginar(callable $pedirPagina, string $etiqueta): array
    {
        $page = 1; $todos = []; $fuente = null; $totalSunat = 0; $error = null; $paginas = 0;
        do {
            $r = $pedirPagina($page, $fuente);
            if ($page === 1) { $fuente = $r['fuente']; $totalSunat = $r['total']; }
            if (!empty($r['error'])) { $error = "Página {$page}: " . $r['error']; break; }
            $todos = array_merge($todos, $r['registros']);
            $paginas++; $page++;
        } while (count($todos) < $totalSunat && count($r['registros']) > 0 && $page <= self::MAX_PAGINAS);

        if (!$error && count($todos) < $totalSunat) {
            $error = "SUNAT informa {$totalSunat} comprobantes pero solo se recibieron " . count($todos) . " (páginas leídas: {$paginas}).";
        }
        if ($error) error_log("[SIRE {$etiqueta}] INCOMPLETO — {$error}");

        return [
            'registros'   => $todos,
            'fuente'      => $fuente ?? 'sin_datos',
            'total'       => count($todos),
            'total_sunat' => $totalSunat,
            'paginas'     => $paginas,
            'error'       => $error,
        ];
    }

    // ── COMPRAS una página (misma lógica que ventas) ─────────────────────────
    public function getComprasPeriodo(string $token, string $ruc, string $periodo,
                                      int $page = 1, int $perPage = 100, ?string $fuente = null): array
    {
        $base = self::SIRE_BASE . "/libros/rce/propuesta/web/propuesta/{$periodo}/busqueda";
        $urlDeclarado = "{$base}?codTipoOpe=3&page={$page}&perPage={$perPage}&codTipoResumen=5";
        $urlPropuesta = "{$base}?codTipoOpe=3&page={$page}&perPage={$perPage}";
        $vacio = fn(string $f, ?string $err = null) => ['total'=>0,'page'=>$page,'fuente'=>$f,'registros'=>[],'error'=>$err];
        $primeraVez = $fuente === null;

        try {
            if ($fuente === 'propuesta') {
                $resp = $this->get($urlPropuesta, $token, $ruc);
            } elseif ($fuente === 'declarado') {
                $resp = $this->get($urlDeclarado, $token, $ruc);
            } else {
                try {
                    $resp   = $this->get($urlDeclarado, $token, $ruc);
                    $fuente = 'declarado';
                    if (($resp['paginacion']['totalRegistros'] ?? 0) === 0 && $page === 1) throw new RuntimeException('sin datos declarados');
                } catch (RuntimeException $e) {
                    if ($this->esSinDatos($e)) return $vacio('sin_datos');
                    $resp   = $this->get($urlPropuesta, $token, $ruc);
                    $fuente = 'propuesta';
                }
            }
        } catch (RuntimeException $e) {
            if ($this->esSinDatos($e)) return $vacio('sin_datos');
            return $vacio($primeraVez ? 'error' : $fuente, $e->getMessage());
        }

        return [
            'total'     => (int)($resp['paginacion']['totalRegistros'] ?? 0),
            'page'      => $page,
            'fuente'    => $fuente,
            'registros' => array_map([$this,'normalizarCompra'], $resp['registros'] ?? []),
            'error'     => null,
        ];
    }

    // ── COMPRAS todas las páginas ────────────────────────────────────────────
    public function getAllComprasPeriodo(string $token, string $ruc, string $periodo): array
    {
        return $this->paginar(fn(int $page, ?string $f) => $this->getComprasPeriodo($token, $ruc, $periodo, $page, 100, $f), "compras {$periodo}");
    }

    private function normalizarVenta(array $i): array
    {
        return [
            'id_sire'          => $i['id']                   ?? null,
            'cod_car'          => $i['codCar']                ?? null,
            'tipo_comp'        => $i['codTipoCDP']            ?? '01',
            'serie'            => $i['numSerieCDP']           ?? '',
            'correlativo'      => $i['numCDP']                ?? '',
            'fecha_emision'    => $this->parseDate($i['fecEmision'] ?? ''),
            'cliente_tipo_doc' => $i['codTipoDocIdentidad']   ?? '6',
            'cliente_num_doc'  => $i['numDocIdentidad']       ?? '',
            'cliente_nombre'   => $i['nomRazonSocialCliente'] ?? '',
            'moneda'           => $i['codMoneda']             ?? 'PEN',
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

    private function normalizarCompra(array $i): array
    {
        $m = $i['montos'] ?? [];
        return [
            'id_sire'            => $i['id']                      ?? null,
            'cod_car'            => $i['codCar']                   ?? null,
            'tipo_comp'          => $i['codTipoCDP']               ?? '01',
            'serie'              => $i['numSerieCDP']              ?? '',
            'correlativo'        => $i['numCDP']                   ?? '',
            'fecha_emision'      => $this->parseDate($i['fecEmision'] ?? ''),
            'proveedor_tipo_doc' => $i['codTipoDocIdentidadProveedor'] ?? $i['codTipoDocIdentidad'] ?? '6',
            'proveedor_ruc'      => $this->docProveedor($i),
            'proveedor_nombre'   => $i['nomRazonSocialProveedor']  ?? $i['nomRazonSocial'] ?? '',
            'moneda'             => $i['codMoneda']                ?? 'PEN',
            'tipo_cambio'        => (float)($i['mtoTipoCambio']   ?? 1),
            'base_imponible'     => (float)($m['mtoBIGravadaDG']  ?? $i['mtoBIGravada']  ?? 0),
            'igv'                => (float)($m['mtoIgvIpmDG']     ?? $i['mtoIGV']        ?? 0),
            'exonerado'          => (float)($m['mtoExonerado']    ?? $i['mtoExonerado']  ?? 0),
            'inafecto'           => (float)($m['mtoInafecto']     ?? $i['mtoInafecto']   ?? 0),
            'total'              => (float)($m['mtoTotalCp']      ?? $i['mtoTotalCP']    ?? 0),
            'estado_sunat'       => $i['codEstadoComprobante']     ?? '1',
            'periodo'            => $i['perPeriodoTributario']     ?? '',
        ];
    }

    /**
     * En el RCE de SIRE el documento del proveedor no llega con el mismo
     * nombre que en el RVIE (ventas: numDocIdentidad) — el nombre lleva el
     * sufijo "Proveedor", igual que nomRazonSocialProveedor. Se prueban los
     * nombres conocidos y, como último recurso, cualquier clave que parezca
     * un documento de identidad y no sea del adquirente (la propia empresa).
     */
    private function docProveedor(array $i): string
    {
        foreach (['numDocIdentidadProveedor', 'numRucProveedor', 'numDocProveedor', 'numDocIdentidad'] as $k) {
            if (!empty($i[$k])) return trim((string)$i[$k]);
        }
        foreach ($i as $k => $v) {
            if (is_scalar($v) && preg_match('/(ruc|docidentidad|numdoc)/i', (string)$k)
                && !preg_match('/(adquirent|adquirient|cliente|contribuyente)/i', (string)$k)
                && preg_match('/^\d{8,11}$/', trim((string)$v))) {
                return trim((string)$v);
            }
        }
        return '';
    }

    private function parseDate(string $f): string
    {
        if (empty($f)) return date('Y-m-d');
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $f, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : date('Y-m-d');
    }

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
        $err  = curl_error($ch);
        curl_close($ch);
        // Sin respuesta (timeout, corte de red) NO es "sin datos": antes volvía [] y
        // el ciclo de páginas terminaba en silencio con el período cortado.
        if ($body === false) throw new RuntimeException("SIRE sin respuesta: {$err}");
        if ($code >= 400) throw new RuntimeException("SIRE HTTP {$code}: " . substr($body, 0, 300));
        $json = json_decode($body, true);
        if (!is_array($json)) throw new RuntimeException('SIRE respuesta no válida: ' . substr($body, 0, 200));
        return $json;
    }

    private function post(string $url, array $data, string $type = 'json'): array
    {
        $headers   = ['Accept: application/json'];
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
