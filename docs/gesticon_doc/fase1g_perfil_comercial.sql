-- ============================================================
-- GestiCont — Motor Contable, FASE 1g
-- Perfil comercial de la empresa (qué vende / qué compra) + activación
-- del motor de reglas de clasificación por proveedor/cliente
-- (reglas_imputacion, creada en fase0_plan_contable.sql pero dejada
-- inactiva "sin lógica de negocio ni datos en Fase 1").
--
-- El perfil comercial es el fallback genérico cuando NO hay una regla
-- específica por RUC: si la empresa declara que solo vende "servicios",
-- toda venta pendiente sin una regla propia se sugiere con la cuenta de
-- servicio configurada aquí. Si declara "ambos", no hay forma de
-- adivinar cuál de las dos aplica a un comprobante puntual — en ese caso
-- no se sugiere nada por perfil (sigue habiendo el criterio de la regla
-- por RUC, o la elección manual de siempre).
--
-- reglas_imputacion no necesita ALTER: su columna `activa` ya soporta
-- 0/1 desde el inicio, simplemente ahora la aplicación empieza a crear
-- filas con activa = 1 por defecto.
--
-- Ejecutar DESPUÉS de fase1f_cobro_pago.sql.
-- ============================================================

ALTER TABLE empresas
    ADD COLUMN vende_tipo                   ENUM('productos','servicios','ambos') DEFAULT NULL AFTER tipo_contribuyente,
    ADD COLUMN venta_tipo_gasto_producto_id INT UNSIGNED DEFAULT NULL AFTER vende_tipo,
    ADD COLUMN venta_tipo_gasto_servicio_id INT UNSIGNED DEFAULT NULL AFTER venta_tipo_gasto_producto_id,
    ADD COLUMN compra_tipo                  ENUM('productos','servicios','ambos') DEFAULT NULL AFTER venta_tipo_gasto_servicio_id,
    ADD COLUMN compra_tipo_gasto_producto_id INT UNSIGNED DEFAULT NULL AFTER compra_tipo,
    ADD COLUMN compra_tipo_gasto_servicio_id INT UNSIGNED DEFAULT NULL AFTER compra_tipo_gasto_producto_id,
    ADD CONSTRAINT fk_empresas_venta_tg_producto  FOREIGN KEY (venta_tipo_gasto_producto_id)  REFERENCES tipos_gasto(id),
    ADD CONSTRAINT fk_empresas_venta_tg_servicio  FOREIGN KEY (venta_tipo_gasto_servicio_id)  REFERENCES tipos_gasto(id),
    ADD CONSTRAINT fk_empresas_compra_tg_producto FOREIGN KEY (compra_tipo_gasto_producto_id) REFERENCES tipos_gasto(id),
    ADD CONSTRAINT fk_empresas_compra_tg_servicio FOREIGN KEY (compra_tipo_gasto_servicio_id) REFERENCES tipos_gasto(id);
