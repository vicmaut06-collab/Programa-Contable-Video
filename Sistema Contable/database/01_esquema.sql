-- ============================================================
-- Sistema Contable - Estructura de la base de datos
-- Motor: PostgreSQL 13 o superior
-- ============================================================

CREATE TABLE IF NOT EXISTS catalogo_cuentas (
  id           SERIAL PRIMARY KEY,
  codigo       VARCHAR(20) NOT NULL UNIQUE,
  nombre       VARCHAR(200) NOT NULL,
  nivel        SMALLINT NOT NULL,
  tipo         VARCHAR(30) NOT NULL CHECK (tipo IN ('ACTIVO','PASIVO','PATRIMONIO','RESULTADO_DEUDOR','RESULTADO_ACREEDOR','ORDEN')),
  naturaleza   VARCHAR(1) NOT NULL CHECK (naturaleza IN ('D','C')),
  padre_codigo VARCHAR(20) DEFAULT NULL,
  es_hoja      SMALLINT NOT NULL DEFAULT 1,
  activo       SMALLINT NOT NULL DEFAULT 1
);

CREATE INDEX IF NOT EXISTS idx_catalogo_tipo   ON catalogo_cuentas (tipo);
CREATE INDEX IF NOT EXISTS idx_catalogo_padre  ON catalogo_cuentas (padre_codigo);
CREATE INDEX IF NOT EXISTS idx_catalogo_nivel  ON catalogo_cuentas (nivel);

CREATE TABLE IF NOT EXISTS asientos (
  id          SERIAL PRIMARY KEY,
  numero      INTEGER NOT NULL UNIQUE,
  fecha       DATE NOT NULL,
  concepto    VARCHAR(500) NOT NULL,
  documento   VARCHAR(50) DEFAULT NULL,
  tipo        VARCHAR(20) NOT NULL DEFAULT 'ORDINARIO' CHECK (tipo IN ('ORDINARIO','APERTURA','AJUSTE','CIERRE')),
  estado      VARCHAR(20) NOT NULL DEFAULT 'REGISTRADO' CHECK (estado IN ('REGISTRADO','ANULADO')),
  total_debe  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_haber DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_asientos_fecha  ON asientos (fecha);
CREATE INDEX IF NOT EXISTS idx_asientos_estado ON asientos (estado);

CREATE TABLE IF NOT EXISTS asiento_partidas (
  id            SERIAL PRIMARY KEY,
  asiento_id    INTEGER NOT NULL REFERENCES asientos (id) ON DELETE CASCADE,
  linea         SMALLINT NOT NULL,
  cuenta_id     INTEGER NOT NULL REFERENCES catalogo_cuentas (id),
  cuenta_codigo VARCHAR(20) NOT NULL,
  cuenta_nombre VARCHAR(200) NOT NULL,
  debe          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  haber         DECIMAL(15,2) NOT NULL DEFAULT 0.00
);

CREATE INDEX IF NOT EXISTS idx_partidas_asiento ON asiento_partidas (asiento_id);
CREATE INDEX IF NOT EXISTS idx_partidas_cuenta  ON asiento_partidas (cuenta_id);

CREATE TABLE IF NOT EXISTS saldos_cuentas (
  cuenta_id         INTEGER PRIMARY KEY REFERENCES catalogo_cuentas (id) ON DELETE CASCADE,
  total_debe        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_haber       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  saldo_deudor      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  saldo_acreedor    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  ultimo_movimiento DATE DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS empresa (
  id         SMALLINT PRIMARY KEY,
  nombre     VARCHAR(200) NOT NULL DEFAULT 'UNIVERSIDAD CATOLICA DE EL SALVADOR',
  carrera    VARCHAR(200) NOT NULL DEFAULT 'CONTABILIDAD 1',
  asignatura VARCHAR(200) NOT NULL DEFAULT 'TECNICAS CONTABLES',
  responsable VARCHAR(200) DEFAULT NULL
);

-- Mantiene updated_at al actualizar un asiento
CREATE OR REPLACE FUNCTION tocar_asiento() RETURNS trigger AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_asientos_updated ON asientos;
CREATE TRIGGER trg_asientos_updated
    BEFORE UPDATE ON asientos
    FOR EACH ROW EXECUTE FUNCTION tocar_asiento();
