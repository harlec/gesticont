<?php
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/RegimenService.php';

/**
 * Obligaciones mensuales de una empresa con su fecha de vencimiento y su estado.
 *
 * Fechas: tabla cronograma_sunat (datos oficiales de SUNAT por último dígito del RUC; ver
 * migración fase1l). Si falta la fila del mes, la fecha queda en null ("por cargar"): nunca
 * se inventa una fecha.
 *
 * Qué obligaciones tiene una empresa:
 *   - Nuevo RUS: ninguna de estas (paga una cuota mensual aparte).
 *   - Resto: Registro de Ventas y de Compras (SIRE) + IGV-Renta mensual (PDT 621), y la
 *     planilla electrónica (PLAME) si la empresa lleva planillas.
 *
 * Estado de cada una:
 *   presentado: lo marcó el usuario, o (RVIE/RCE) SUNAT ya lo devuelve como DECLARADO en SIRE.
 *   bloqueado:  faltan comprobantes por clasificar.
 *   datos:      aún no hay datos del mes (sin sincronizar / sin planilla).
 *   espera:     el PDT 621 espera a que ventas y compras estén presentadas.
 *   listo:      puede presentarse.
 * GestiCont NO presenta nada ante SUNAT: "presentado" es un registro del avance del contador.
 */
class VencimientosService
{
    public const NOMBRES = [
        'sire_ventas'  => 'Registro de Ventas (SIRE)',
        'sire_compras' => 'Registro de Compras (SIRE)',
        'pdt621'       => 'IGV-Renta mensual (PDT 621)',
        'pdt601'       => 'Planilla electrónica (PLAME)',
    ];

    public static function grupoDeRuc(string $ruc): string
    {
        $d = (int)substr(trim($ruc), -1);
        return match (true) { $d === 0 => '0', $d === 1 => '1', $d <= 3 => '2-3', $d <= 5 => '4-5', $d <= 7 => '6-7', default => '8-9' };
    }

    public static function fechaVence(PDO $pdo, string $ruc, string $periodo): ?string
    {
        static $cache = [];
        $k = $ruc . $periodo;
        if (array_key_exists($k, $cache)) return $cache[$k];
        try {
            $s = $pdo->prepare("SELECT fecha FROM cronograma_sunat WHERE anio = ? AND mes = ? AND grupo = ?");
            $s->execute([(int)substr($periodo, 0, 4), (int)substr($periodo, 4, 2), self::grupoDeRuc($ruc)]);
            $f = $s->fetchColumn();
        } catch (Throwable $e) { $f = false; }   // la migración aún no se aplicó
        return $cache[$k] = ($f ?: null);
    }

    public static function dias(?string $fecha, ?string $hoy = null): ?int
    {
        if (!$fecha) return null;
        return (int)floor((strtotime($fecha) - strtotime($hoy ?? date('Y-m-d'))) / 86400);
    }

    /** Color del semáforo: presentado → ok; vencido → neg; ≤7 días → warn; más → info. */
    public static function tono(?int $dias, string $estado): string
    {
        if ($estado === 'presentado') return 'ok';
        if ($dias === null) return 'neutral';
        return $dias < 0 ? 'neg' : ($dias <= 7 ? 'warn' : 'info');
    }

    public static function textoDias(?int $dias, string $estado): string
    {
        if ($estado === 'presentado') return 'Presentado';
        if ($dias === null) return 'Fecha por cargar';
        if ($dias < 0)  return $dias === -1 ? 'Vencido ayer' : 'Vencido hace ' . (-$dias) . ' días';
        if ($dias === 0) return 'Vence hoy';
        if ($dias === 1) return 'Vence mañana';
        return 'Faltan ' . $dias . ' días';
    }

    private static function marcada(PDO $pdo, int $empresaId, string $tipo, string $periodo): bool
    {
        try {
            $s = $pdo->prepare("SELECT 1 FROM declaraciones WHERE empresa_id = ? AND tipo = ? AND periodo = ? AND estado IN ('declarado','rectificado') LIMIT 1");
            $s->execute([$empresaId, $tipo, substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2)]);
            return (bool)$s->fetchColumn();
        } catch (Throwable $e) { return false; }
    }

    /** @return array{total:int,pend:int,decl:int} */
    private static function stats(PDO $pdo, int $empresaId, string $tabla, string $periodo): array
    {
        $s = $pdo->prepare("
            SELECT COUNT(*) AS total,
                   COALESCE(SUM(estado_imputacion = 'pendiente' AND estado_sunat = '1'), 0) AS pend,
                   COALESCE(SUM(fuente = 'declarado'), 0) AS decl
            FROM {$tabla} WHERE empresa_id = ? AND periodo = ?
        ");
        $s->execute([$empresaId, $periodo]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return ['total' => (int)$r['total'], 'pend' => (int)$r['pend'], 'decl' => (int)$r['decl']];
    }

    /**
     * Obligaciones del período de trabajo, más el PDT/PLAME del mes anterior si ya venció y sigue sin presentar.
     * @return array<int,array<string,mixed>>
     */
    public static function obligaciones(PDO $pdo, array $empresa, string $periodo, ?string $hoy = null): array
    {
        $id = (int)$empresa['id'];
        if (RegimenService::vigente($pdo, $empresa, $periodo)['regimen'] === 'rus') return [];

        $s = $pdo->prepare("SELECT COUNT(*) FROM planillas WHERE empresa_id = ?");
        $s->execute([$id]);
        $llevaPlanilla = (int)$s->fetchColumn() > 0;

        $lista = [];
        $armar = function (string $per, array $tipos) use ($pdo, $empresa, $id, $llevaPlanilla, $hoy, &$lista) {
            $v = self::stats($pdo, $id, 'registro_ventas', $per);
            $c = self::stats($pdo, $id, 'registro_compras', $per);
            $mes = Periodo::etiqueta($per, true);
            $fecha = self::fechaVence($pdo, $empresa['ruc'], $per);
            $dias = self::dias($fecha, $hoy);
            $ventasOk  = self::marcada($pdo, $id, 'sire_ventas', $per)  || $v['decl'] > 0;
            $comprasOk = self::marcada($pdo, $id, 'sire_compras', $per) || $c['decl'] > 0;

            foreach ($tipos as $tipo) {
                $o = ['tipo' => $tipo, 'nombre' => self::NOMBRES[$tipo], 'periodo' => $per, 'periodo_txt' => $mes,
                      'fecha' => $fecha, 'dias' => $dias, 'estado' => 'listo', 'texto' => 'Listo para presentar', 'accion' => null, 'pendientes' => 0];
                $irClasificar = fn(string $t) => "/empresas/{$id}/imputacion?tipo={$t}&periodo={$per}";
                $marcar = ['etiqueta' => 'Marcar como presentado', 'tipo' => 'marcar'];

                if ($tipo === 'sire_ventas' || $tipo === 'sire_compras') {
                    $esV = $tipo === 'sire_ventas'; $st = $esV ? $v : $c; $ok = $esV ? $ventasOk : $comprasOk; $nom = $esV ? 'ventas' : 'compras';
                    if ($ok)                  { $o['estado'] = 'presentado'; $o['texto'] = 'Presentado'; }
                    elseif ($st['total'] === 0) { $o['estado'] = 'datos'; $o['texto'] = "Aún no descargas las {$nom} de {$mes}"; $o['accion'] = ['etiqueta' => 'Sincronizar', 'tipo' => 'ir', 'url' => "/empresas/{$id}/sync"]; }
                    elseif ($st['pend'] > 0)  { $o['estado'] = 'bloqueado'; $o['pendientes'] = $st['pend']; $o['texto'] = "Faltan clasificar {$st['pend']} {$nom}"; $o['accion'] = ['etiqueta' => 'Clasificar', 'tipo' => 'ir', 'url' => $irClasificar($nom)]; }
                    else                      { $o['accion'] = $marcar; }
                } elseif ($tipo === 'pdt621') {
                    if (self::marcada($pdo, $id, 'pdt621', $per)) { $o['estado'] = 'presentado'; $o['texto'] = 'Presentado'; }
                    elseif (!$ventasOk || !$comprasOk)            { $o['estado'] = 'espera'; $o['texto'] = 'Primero presenta ventas y compras'; }
                    else                                          { $o['accion'] = $marcar; }
                } else { // pdt601 PLAME
                    $q = $pdo->prepare("SELECT COUNT(*) FROM planillas WHERE empresa_id = ? AND periodo = ?");
                    $q->execute([$id, $per]);
                    if (self::marcada($pdo, $id, 'pdt601', $per)) { $o['estado'] = 'presentado'; $o['texto'] = 'Presentado'; }
                    elseif ((int)$q->fetchColumn() === 0)         { $o['estado'] = 'datos'; $o['texto'] = "Aún no cargas la planilla de {$mes}"; $o['accion'] = ['etiqueta' => 'Ir a Planillas', 'tipo' => 'ir', 'url' => "/empresas/{$id}/planillas"]; }
                    else                                          { $o['accion'] = $marcar; }
                }
                $lista[] = $o;
            }
        };

        $tiposMes = ['sire_ventas', 'sire_compras', 'pdt621'];
        if ($llevaPlanilla) $tiposMes[] = 'pdt601';
        $armar($periodo, $tiposMes);

        // PDT/PLAME del mes anterior: solo si ya venció y no está presentado.
        $ant = Periodo::anterior($periodo);
        if ($ant >= Periodo::minimo()) {
            $antes = count($lista);
            $armar($ant, $llevaPlanilla ? ['pdt621', 'pdt601'] : ['pdt621']);
            $extra = array_slice($lista, $antes);
            $lista = array_merge(array_slice($lista, 0, $antes), array_values(array_filter($extra, fn($o) => $o['estado'] !== 'presentado' && $o['dias'] !== null && $o['dias'] < 0)));
        }
        return $lista;
    }

    /** La obligación sin presentar más urgente (la de fecha más cercana, o más vencida). */
    public static function proxima(array $obligaciones): ?array
    {
        $pend = array_values(array_filter($obligaciones, fn($o) => $o['estado'] !== 'presentado' && $o['fecha'] !== null));
        if (!$pend) return null;
        usort($pend, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
        return $pend[0];
    }

    /** Marca / desmarca una obligación como presentada (registro del contador; no presenta nada ante SUNAT). */
    public static function marcar(PDO $pdo, int $empresaId, string $tipo, string $periodo, bool $presentado, ?int $usuarioId): array
    {
        if (!isset(self::NOMBRES[$tipo]) || !preg_match('/^\d{6}$/', $periodo)) return ['ok' => false, 'error' => 'Obligación inválida.'];
        $per = substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2);
        $s = $pdo->prepare("SELECT id FROM declaraciones WHERE empresa_id = ? AND tipo = ? AND periodo = ? LIMIT 1");
        $s->execute([$empresaId, $tipo, $per]);
        $id = $s->fetchColumn();
        $fecha = self::fechaVence($pdo, (string)$pdo->query("SELECT ruc FROM empresas WHERE id = " . (int)$empresaId)->fetchColumn(), $periodo);
        if ($id) {
            $pdo->prepare("UPDATE declaraciones SET estado = ?, fecha_declaracion = ?, registrado_por = ? WHERE id = ?")
                ->execute([$presentado ? 'declarado' : 'pendiente', $presentado ? date('Y-m-d') : null, $usuarioId, $id]);
        } else {
            $pdo->prepare("INSERT INTO declaraciones (empresa_id, tipo, periodo, fecha_declaracion, fecha_vencimiento, estado, registrado_por) VALUES (?,?,?,?,?,?,?)")
                ->execute([$empresaId, $tipo, $per, $presentado ? date('Y-m-d') : null, $fecha, $presentado ? 'declarado' : 'pendiente', $usuarioId]);
        }
        return ['ok' => true];
    }
}
