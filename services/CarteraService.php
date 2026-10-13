<?php
require_once ROOT . '/services/VencimientosService.php';

/**
 * Resumen de todas las empresas del usuario: lo que el contador necesita ver primero
 * (vencimientos, comprobantes por clasificar, certificado). Alimenta "Mis empresas",
 * el selector de empresa (Ctrl+K) y la alerta del menú lateral.
 */
class CarteraService
{
    /** @return array<int,array<string,mixed>> ordenadas por vencimiento más cercano */
    public static function resumen(PDO $pdo, bool $esSuper, ?int $usuarioId, ?string $periodo = null): array
    {
        static $cache = [];
        $clave = ($esSuper ? 'S' : 'U' . $usuarioId) . '|' . ($periodo ?? '');
        if (isset($cache[$clave])) return $cache[$clave];

        if ($esSuper) {
            $empresas = $pdo->query("SELECT id, razon_social, ruc, regimen FROM empresas WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $s = $pdo->prepare("
                SELECT e.id, e.razon_social, e.ruc, e.regimen FROM empresas e
                INNER JOIN empresa_usuarios eu ON eu.empresa_id = e.id
                WHERE e.activo = 1 AND eu.usuario_id = ? AND eu.activo = 1
            ");
            $s->execute([$usuarioId]);
            $empresas = $s->fetchAll(PDO::FETCH_ASSOC);
        }
        $periodo ??= Periodo::ultimoCerrado();

        $out = [];
        foreach ($empresas as $e) {
            $id = (int)$e['id'];
            $q = $pdo->prepare("
                SELECT (SELECT COUNT(*) FROM registro_ventas  WHERE empresa_id = ? AND estado_imputacion = 'pendiente' AND estado_sunat = '1' AND periodo >= ?) AS v,
                       (SELECT COUNT(*) FROM registro_compras WHERE empresa_id = ? AND estado_imputacion = 'pendiente' AND estado_sunat = '1' AND periodo >= ?) AS c
            ");
            $q->execute([$id, Periodo::minimo(), $id, Periodo::minimo()]);
            $pend = $q->fetch(PDO::FETCH_ASSOC);

            $cert = $pdo->prepare("SELECT estado FROM empresa_certificados WHERE empresa_id = ? ORDER BY id DESC LIMIT 1");
            $cert->execute([$id]);
            $certEstado = $cert->fetchColumn();

            $obl = VencimientosService::obligaciones($pdo, $e, $periodo);
            $sinPresentar = array_values(array_filter($obl, fn($o) => $o['estado'] !== 'presentado'));
            $prox = VencimientosService::proxima($obl);

            $out[] = [
                'id' => $id, 'nombre' => $e['razon_social'], 'ruc' => $e['ruc'], 'regimen' => $e['regimen'],
                'por_clasificar' => (int)$pend['v'] + (int)$pend['c'], 'por_clasificar_v' => (int)$pend['v'], 'por_clasificar_c' => (int)$pend['c'],
                'certificado' => ($certEstado && $certEstado !== 'revocado') ? ($certEstado === 'vencido' ? 'vencido' : 'activo') : 'falta',
                'proximo' => $prox, 'obligaciones' => count($obl), 'por_presentar' => count($sinPresentar),
                'vencidos' => count(array_filter($sinPresentar, fn($o) => $o['dias'] !== null && $o['dias'] < 0)),
                'proximos10' => count(array_filter($sinPresentar, fn($o) => $o['dias'] !== null && $o['dias'] >= 0 && $o['dias'] <= 10)),
                'sin_obligaciones' => count($obl) === 0,
            ];
        }
        // Más urgente primero: por días al próximo vencimiento; sin vencimiento pendiente, al final.
        usort($out, function ($a, $b) {
            $da = $a['proximo']['dias'] ?? PHP_INT_MAX; $db = $b['proximo']['dias'] ?? PHP_INT_MAX;
            return $da <=> $db ?: strcasecmp($a['nombre'], $b['nombre']);
        });
        return $cache[$clave] = $out;
    }

    /** Totales para las tarjetas de arriba de "Mis empresas" y la alerta del menú. */
    public static function totales(array $cartera): array
    {
        return [
            'con_vencidos'  => count(array_filter($cartera, fn($e) => $e['vencidos'] > 0)),
            'por_vencer'    => count(array_filter($cartera, fn($e) => $e['proximos10'] > 0)),
            'por_clasificar'=> array_sum(array_column($cartera, 'por_clasificar')),
            'sin_cert'      => count(array_filter($cartera, fn($e) => $e['certificado'] === 'falta')),
            'empresas'      => count($cartera),
        ];
    }
}
