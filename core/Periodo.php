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
    public static function resolver(int $empresaId): string
    {
        if (!empty($_GET['periodo']) && preg_match('/^\d{6}$/', $_GET['periodo'])) {
            $_SESSION['periodo_activo'][$empresaId] = $_GET['periodo'];
        }
        return $_SESSION['periodo_activo'][$empresaId] ?? date('Ym', strtotime('-1 month'));
    }

    /** Para pantallas a nivel de año (Apertura, Cierre) — respeta un
     *  ?anio= explícito si viene, si no deriva del período activo. */
    public static function anio(int $empresaId): int
    {
        if (!empty($_GET['anio'])) return (int)$_GET['anio'];
        return (int)substr(self::resolver($empresaId), 0, 4);
    }
}
