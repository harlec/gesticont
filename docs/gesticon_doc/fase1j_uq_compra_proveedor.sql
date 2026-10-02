-- ============================================================
-- GestiCont — FASE 1j
-- La clave única de registro_compras en producción (uq_compra) es
-- (empresa_id, tipo_comp, serie, correlativo): NO incluye al proveedor.
-- Dos proveedores distintos pueden emitir el mismo F001-123 (o E001-11):
-- la segunda compra no se puede guardar y la sincronización la reportaba
-- como error de clave duplicada. Se agrega el RUC del proveedor a la clave.
--
-- Es una clave MÁS permisiva que la actual: ninguna fila existente puede
-- violarla, así que el cambio no falla por datos. Ejecutar primero en
-- una copia o con respaldo, como cualquier cambio de esquema.
--
-- Verificar antes cómo se llama y qué columnas tiene la clave actual:
--   SHOW INDEX FROM registro_compras WHERE Key_name = 'uq_compra';
-- Después de aplicarla, volver a sincronizar los períodos con aviso
-- "no se pudieron guardar": las compras faltantes entrarán como nuevas.
-- ============================================================

ALTER TABLE registro_compras
    DROP INDEX uq_compra,
    ADD UNIQUE KEY uq_compra (empresa_id, proveedor_ruc, tipo_comp, serie, correlativo);
