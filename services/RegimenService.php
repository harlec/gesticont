<?php
/**
 * Régimen tributario de la empresa con vigencia (tabla empresa_regimen, migración fase1k).
 *
 * Es INFORMATIVO: dice qué régimen regía en un período y qué libros son obligatorios, pero no
 * bloquea ni oculta nada. Los tramos siguen la RS 234-2006/SUNAT (ingresos brutos anuales en UIT):
 *   RER:           solo Registro de Compras y Registro de Ventas.
 *   MYPE / General hasta 300 UIT:     + Libro Diario de formato simplificado.
 *   MYPE / General 300 a 500 UIT:     + Libro Diario y Libro Mayor.
 *   MYPE / General 500 a 1,700 UIT:   + Libro de Inventarios y Balances.
 *   General más de 1,700 UIT:         contabilidad completa (+ Libro Caja y Bancos).
 *   Nuevo RUS: sin libros contables obligatorios.
 * Si la migración aún no se aplicó, se cae al dato suelto empresas.regimen sin romper nada.
 */
require_once ROOT . '/core/Periodo.php';

class RegimenService
{
    public const NOMBRES = [
        'rus'      => 'Nuevo RUS',
        'especial' => 'RER (Régimen Especial de Renta)',
        'mype'     => 'MYPE Tributario',
        'general'  => 'Régimen General',
    ];
    public const CORTOS = ['rus' => 'Nuevo RUS', 'especial' => 'RER', 'mype' => 'MYPE', 'general' => 'General'];

    /** @return array<string,string> tramos de ingresos válidos para el régimen */
    public static function tramos(string $regimen): array
    {
        if (!in_array($regimen, ['mype', 'general'], true)) return [];
        $t = ['hasta_300' => 'Hasta 300 UIT', '300_500' => 'De 300 a 500 UIT', '500_1700' => 'De 500 a 1,700 UIT'];
        if ($regimen === 'general') $t['mas_1700'] = 'Más de 1,700 UIT';   // MYPE tiene tope de 1,700 UIT
        return $t;
    }

    /**
     * Libros obligatorios y qué libros de GestiCont lo son.
     * 'requiere' usa true / false / null (= no se sabe: falta definir el tramo).
     *
     * @return array{nivel:string,etiqueta:string,libros:string[],requiere:array<string,?bool>}
     */
    public static function libros(string $regimen, ?string $tramo): array
    {
        $ninguno = ['diario' => false, 'mayor' => false, 'balances' => false, 'caja' => false];

        if ($regimen === 'rus') {
            return ['nivel' => 'sin_libros', 'etiqueta' => 'Sin libros contables obligatorios', 'libros' => [], 'requiere' => $ninguno];
        }
        if ($regimen === 'especial') {
            return ['nivel' => 'registros', 'etiqueta' => 'Solo registros de compras y ventas',
                    'libros' => ['Registro de Compras', 'Registro de Ventas'], 'requiere' => $ninguno];
        }

        $base = ['Registro de Ventas', 'Registro de Compras'];
        switch ($tramo) {
            case 'hasta_300':
                return ['nivel' => 'simplificado', 'etiqueta' => 'Contabilidad simplificada',
                        'libros' => array_merge($base, ['Libro Diario de formato simplificado']),
                        'requiere' => ['diario' => true, 'mayor' => false, 'balances' => false, 'caja' => false]];
            case '300_500':
                return ['nivel' => 'intermedio', 'etiqueta' => 'Diario y Mayor',
                        'libros' => array_merge($base, ['Libro Diario', 'Libro Mayor']),
                        'requiere' => ['diario' => true, 'mayor' => true, 'balances' => false, 'caja' => false]];
            case '500_1700':
                return ['nivel' => 'balance', 'etiqueta' => 'Con Inventarios y Balances',
                        'libros' => array_merge($base, ['Libro Diario', 'Libro Mayor', 'Libro de Inventarios y Balances']),
                        'requiere' => ['diario' => true, 'mayor' => true, 'balances' => true, 'caja' => false]];
            case 'mas_1700':
                return ['nivel' => 'completo', 'etiqueta' => 'Contabilidad completa',
                        'libros' => array_merge($base, ['Libro Caja y Bancos', 'Libro de Inventarios y Balances', 'Libro Diario', 'Libro Mayor']),
                        'requiere' => ['diario' => true, 'mayor' => true, 'balances' => true, 'caja' => true]];
        }
        return ['nivel' => 'por_definir', 'etiqueta' => 'Tramo de ingresos por definir', 'libros' => $base,
                'requiere' => ['diario' => null, 'mayor' => null, 'balances' => null, 'caja' => null]];
    }

    private static function tablaExiste(PDO $pdo): bool
    {
        static $existe = null;
        if ($existe === null) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'empresa_regimen'");
            $s->execute();
            $existe = (bool)$s->fetchColumn();
        }
        return $existe;
    }

    /**
     * Régimen que regía la empresa en el período YYYYMM.
     * @return array{regimen:string,tramo:?string,desde:?string,hasta:?string,nota:?string,historial:bool}
     */
    public static function vigente(PDO $pdo, array $empresa, string $periodo): array
    {
        $respaldo = ['regimen' => $empresa['regimen'] ?? 'mype', 'tramo' => null, 'desde' => null, 'hasta' => null, 'nota' => null, 'historial' => false];
        if (!preg_match('/^\d{6}$/', $periodo) || !self::tablaExiste($pdo)) return $respaldo;

        $inicio = substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01';
        $fin    = date('Y-m-t', strtotime($inicio));
        $s = $pdo->prepare("
            SELECT regimen, tramo, vigente_desde, vigente_hasta, nota FROM empresa_regimen
            WHERE empresa_id = ? AND vigente_desde <= ? AND (vigente_hasta IS NULL OR vigente_hasta >= ?)
            ORDER BY vigente_desde DESC LIMIT 1
        ");
        $s->execute([(int)$empresa['id'], $fin, $inicio]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r) return $respaldo;
        return ['regimen' => $r['regimen'], 'tramo' => $r['tramo'], 'desde' => $r['vigente_desde'], 'hasta' => $r['vigente_hasta'], 'nota' => $r['nota'], 'historial' => true];
    }

    /** @return array<int,array<string,mixed>> del más reciente al más antiguo */
    public static function historial(PDO $pdo, int $empresaId): array
    {
        if (!self::tablaExiste($pdo)) return [];
        $s = $pdo->prepare("
            SELECT r.*, u.nombre AS usuario FROM empresa_regimen r
            LEFT JOIN usuarios u ON u.id = r.usuario_id
            WHERE r.empresa_id = ? ORDER BY r.vigente_desde DESC, r.id DESC
        ");
        $s->execute([$empresaId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Primera fila de historial de una empresa nueva. */
    public static function inicializar(PDO $pdo, int $empresaId, string $regimen, ?int $usuarioId): void
    {
        if (!self::tablaExiste($pdo) || !isset(self::NOMBRES[$regimen])) return;
        $pdo->prepare("INSERT INTO empresa_regimen (empresa_id, regimen, vigente_desde, usuario_id) VALUES (?, ?, ?, ?)")
            ->execute([$empresaId, $regimen, date('Y-01-01'), $usuarioId]);
    }

    /**
     * Registra un cambio de régimen (o de tramo) desde el mes indicado (YYYY-MM).
     * Cierra la vigencia anterior el día anterior y deja empresas.regimen con el nuevo.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function cambiar(PDO $pdo, int $empresaId, string $regimen, string $mes, ?string $tramo, string $nota, ?int $usuarioId): array
    {
        if (!self::tablaExiste($pdo)) return ['ok' => false, 'error' => 'Falta aplicar la migración fase1k_regimen_vigencia.sql.'];
        if (!isset(self::NOMBRES[$regimen])) return ['ok' => false, 'error' => 'Régimen inválido.'];
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) return ['ok' => false, 'error' => 'Indica desde qué mes rige el cambio.'];
        $desde = $mes . '-01';
        if (!checkdate((int)substr($mes, 5, 2), 1, (int)substr($mes, 0, 4))) return ['ok' => false, 'error' => 'Mes inválido.'];

        $minimo = substr(Periodo::minimo(), 0, 4) . '-' . substr(Periodo::minimo(), 4, 2) . '-01';
        if ($desde < $minimo) return ['ok' => false, 'error' => 'El sistema trabaja desde ' . Periodo::etiqueta(Periodo::minimo(), true) . '; el cambio no puede ser anterior.'];

        $validos = self::tramos($regimen);
        $tramo = $tramo !== null && $tramo !== '' ? $tramo : null;
        if (!$validos) $tramo = null;   // RUS y RER no tienen tramo: se ignora lo que llegue
        elseif ($tramo !== null && !isset($validos[$tramo])) return ['ok' => false, 'error' => 'Ese tramo de ingresos no corresponde al régimen elegido.'];

        $s = $pdo->prepare("SELECT * FROM empresa_regimen WHERE empresa_id = ? AND vigente_hasta IS NULL ORDER BY vigente_desde DESC, id DESC LIMIT 1");
        $s->execute([$empresaId]);
        $actual = $s->fetch(PDO::FETCH_ASSOC);

        if ($actual) {
            if ($actual['regimen'] === $regimen && ($actual['tramo'] ?? null) === $tramo) {
                return ['ok' => false, 'error' => 'Ese régimen y tramo ya están vigentes.'];
            }
            if ($desde <= $actual['vigente_desde']) {
                return ['ok' => false, 'error' => 'El cambio debe regir después del inicio de la vigencia actual (' . Periodo::etiqueta(date('Ym', strtotime($actual['vigente_desde']))) . ').'];
            }
        }

        $pdo->beginTransaction();
        try {
            if ($actual) {
                $pdo->prepare("UPDATE empresa_regimen SET vigente_hasta = ? WHERE id = ?")
                    ->execute([date('Y-m-d', strtotime($desde . ' -1 day')), $actual['id']]);
            }
            $pdo->prepare("INSERT INTO empresa_regimen (empresa_id, regimen, tramo, vigente_desde, nota, usuario_id) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$empresaId, $regimen, $tramo, $desde, trim($nota) !== '' ? mb_substr(trim($nota), 0, 300) : null, $usuarioId]);
            $pdo->prepare("UPDATE empresas SET regimen = ? WHERE id = ?")->execute([$regimen, $empresaId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'No se pudo guardar el cambio: ' . $e->getMessage()];
        }
        return ['ok' => true];
    }
}
