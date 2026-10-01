<?php
/**
 * Período de trabajo activo — antes cada pantalla defaulteaba de forma
 * independiente a "el mes pasado" (o "el año actual" para Apertura/Cierre),
 * así que cambiar de pantalla perdía el período que el contador venía
 * revisando y había que reseleccionarlo en cada una. Se guarda en sesión
 * por empresa (un contador puede tener varias empresas abiertas en
 * paralelo, cada una en su propio período) para que persista sin importar
 * cómo se llegó a la pantalla — menú, selector de la barra de identidad,
 * o un link directo con ?periodo=.
 */
class Periodo
{
    /**
     * Primer período (YYYYMM) con el que trabaja el sistema: nada anterior se
     * ofrece en selectores ni se sincroniza. Es un filtro de visualización y de
     * sincronización — no borra datos. Se puede cambiar con PERIODO_MINIMO en .env.
     */
    public static function minimo(): string
    {
        $m = getenv('PERIODO_MINIMO');
        return ($m && preg_match('/^\d{6}$/', $m)) ? $m : '202601';
    }

    public static function permitido(string $periodo): bool
    {
        return (bool)preg_match('/^\d{6}$/', $periodo) && $periodo >= self::minimo();
    }

    /** Últimos $n meses (a partir de $desde meses atrás; 1 = el mes pasado), sin anteriores al mínimo. */
    public static function opciones(int $n = 12, int $desde = 1): array
    {
        $out = [];
        for ($i = $desde; $i < $desde + $n; $i++) {
            $p = date('Ym', strtotime("first day of -{$i} month"));
            if (self::permitido($p)) $out[] = $p;
        }
        return $out;
    }

    /** Años disponibles (más reciente primero), desde el año actual hasta el del período mínimo. */
    public static function anios(): array
    {
        return range((int)date('Y'), max((int)substr(self::minimo(), 0, 4), 2000));
    }

    public static function resolver(int $empresaId): string
    {
        if (!empty($_GET['periodo']) && self::permitido($_GET['periodo'])) {
            $_SESSION['periodo_activo'][$empresaId] = $_GET['periodo'];
        }
        $guardado = $_SESSION['periodo_activo'][$empresaId] ?? null;
        if ($guardado && self::permitido($guardado)) return $guardado;
        return max(self::minimo(), date('Ym', strtotime('first day of -1 month')));
    }

    /** Para pantallas a nivel de año (Apertura, Cierre) — respeta un
     *  ?anio= explícito si viene, si no deriva del período activo. */
    public static function anio(int $empresaId): int
    {
        if (!empty($_GET['anio'])) return max((int)$_GET['anio'], (int)substr(self::minimo(), 0, 4));
        return (int)substr(self::resolver($empresaId), 0, 4);
    }

    private const MESES_CORTO = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];
    private const MESES_LARGO = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /** "Ago 2025" o, con $largo, "Agosto 2025" — a partir de un período
     *  YYYYMM. No depende de setlocale() (nunca confiable entre servidores:
     *  es justo por qué salían los meses en inglés) — nombres fijos en
     *  español. */
    public static function etiqueta(string $periodoYYYYMM, bool $largo = false): string
    {
        $anio = (int)substr($periodoYYYYMM, 0, 4);
        $mes  = (int)substr($periodoYYYYMM, 4, 2);
        $nombre = ($largo ? self::MESES_LARGO : self::MESES_CORTO)[$mes] ?? $periodoYYYYMM;
        return "{$nombre} {$anio}";
    }
}
