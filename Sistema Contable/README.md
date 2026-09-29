# Sistema Contable

Sistema web de contabilidad basica implementado en **PHP + PDO + PostgreSQL + Bootstrap 5**, disenado para funcionar en XAMPP local.

Cumple con los requisitos de la practica: Libro Diario con partida doble, mayorizacion automatica, catalogo de cuentas jerarquico precargado y los estados financieros basicos.

---

## 1. Requisitos

- XAMPP (Apache + PHP 8.0 o superior)
- PostgreSQL 18 instalado como servicio de Windows (`postgresql-x64-18`)
- Navegador web moderno (Chrome, Edge, Firefox)
- No se requieren librerias externas: Bootstrap y Bootstrap Icons se cargan por CDN

> **MySQL/MariaDB no se utiliza.** No es necesario iniciar el boton *MySQL* del panel de XAMPP.

## 2. Instalacion

### En una computadora nueva (un clic)

1. Instala **XAMPP** y **PostgreSQL 18**.
2. Copia esta carpeta completa en `C:\xampp\htdocs\SistemaContable`.
3. Doble clic en **`INSTALAR.bat`**.

Eso es todo. El instalador se encarga solo de:

- Copiar el proyecto a `C:\xampp\htdocs\SistemaContable` (si no esta ya).
- Habilitar `pdo_pgsql` y `pgsql` en `php.ini`, sin duplicar modulos.
- Detectar el puerto real de PostgreSQL (prueba 5432, 5433 y los que aparecen en el
  registro) y la contrasena que le hayas puesto.
- Guardar esos datos en `config/config.php`.
- Levantar Apache.
- Crear la base de datos y cargar las 404 cuentas del catalogo.
- Decirte que hacer si algo falta.

Despues, para iniciar el sistema en el dia a dia, doble clic en **`INICIAR.bat`**.

### Instalacion manual (si prefieres hacerlo paso a paso)

**Paso 1 — Instala XAMPP**

1. Descarga XAMPP de <https://www.apachefriends.org/es/index.html> e instalalo.
2. Abre **XAMPP Control Panel** y presiona **Start** en la fila **Apache**.
3. No presiones el boton *MySQL*: este proyecto no usa MySQL.

**Paso 2 — Habilita PostgreSQL en el PHP de XAMPP**

Abre `C:\xampp\php\php.ini` con el Bloc de notas y verifica que existan estas dos lineas
(sin el `;` al inicio):

```ini
extension=pdo_pgsql
extension=pgsql
```

Guarda y presiona **Stop** y luego **Start** en Apache para que las cargue.

> Si ya existe una linea activa `extension=pgsql`, **no** agregues otra: PHP mostraria
> un aviso de modulo duplicado. Lo mismo con `pdo_pgsql`.

**Paso 3 — Instala PostgreSQL**

1. Descarga e instala PostgreSQL 18 (<https://www.postgresql.org/download-windows/>).
   Al instalarlo anota la **contrasena** del usuario `postgres`.
2. Deja marcada la opcion de registrar el servicio, para que arranque con Windows.

**Paso 4 — Copia el proyecto**

Copia la carpeta completa del proyecto en `C:\xampp\htdocs\`:

```
C:\xampp\htdocs\SistemaContable\
```

> Importante: deben quedar los archivos `config\`, `database\`, `includes\`, `assets\`,
> `diario\`, `mayor\`, `catalogo\`, `reportes\`, `api\`, `ajustes\`, `instalar.php`
> e `index.php`. Si copias solo algunas carpetas, no funciona.

**Paso 5 — Ajusta la conexion si hace falta**

Abre `config\config.php`. Lo unico que suele cambiar es el **puerto** y la **contrasena**:

| Constante | Valor por defecto | Cuando cambiarlo |
|-----------|-------------------|------------------|
| `DB_PORT` | `5433` | Si PostgreSQL se instalo en el puerto estandar, pon `5432` |
| `DB_PASS` | *(vacio)* | Si le pusiste contrasena al usuario `postgres` |

Para saber el puerto real, abre `pgAdmin 4` o el *SQL Shell* de PostgreSQL y conectate;
el puerto aparece en la ventana de conexion. Este valor se puede leer del registro de
Windows en `HKLM\SOFTWARE\PostgreSQL`.

**Paso 6 — Crea la base de datos**

Abre en el navegador:

```
http://localhost/SistemaContable/instalar.php
```

El instalador crea la base `contabilidad`, las tablas y las 404 cuentas del catalogo.
Si todo sale **OK**, ya puedes entrar al sistema.

**Paso 7 — Listo**

```
http://localhost/SistemaContable/
```

### En esta computadora (ya instalado)

1. Inicia **Apache** desde el panel de control de XAMPP, o doble clic en el acceso
   directo **Sistema Contable** del Escritorio, que tambien abre el navegador.
2. Abre en el navegador:
   `http://localhost/SistemaContable/`

El servicio **PostgreSQL** se inicia automaticamente con Windows, por lo que no hay nada mas
que ejecutar.

### Reinstalar la base de datos (opcional)

Si necesitas volver a crear tablas y catalogo desde cero, entra a:

`http://localhost/SistemaContable/instalar.php`

El instalador es idempotente: se puede ejecutar varias veces sin duplicar el catalogo.
**Ojo:** no borra los asientos que ya hayas registrado.

### Los archivos .bat

| Archivo | Para que sirve |
|---------|----------------|
| `INSTALAR.bat` | Solo la primera vez, en una computadora nueva. Instala todo. |
| `INICIAR.bat` | Cada dia. Prende PostgreSQL, Apache y abre el navegador. |
| `iniciar_apache.vbs` | Ayuda interna de `INICIAR.bat`: levanta Apache desacoplado. |

## 3. Configuracion

Archivo: `config/config.php`

| Constante    | Valor por defecto | Descripcion                    |
|--------------|-------------------|--------------------------------|
| `DB_DRIVER`  | `pgsql`           | Driver PDO                     |
| `DB_HOST`    | `127.0.0.1`       | Servidor de base de datos     |
| `DB_PORT`    | `5433`            | Puerto de PostgreSQL 18        |
| `DB_NAME`    | `contabilidad`    | Nombre de la base de datos     |
| `DB_USER`    | `postgres`        | Usuario de la base de datos    |
| `DB_PASS`    | *(vacio)*         | Clave del usuario              |
| `MAX_LINEAS` | `50`              | Maximo de partidas por asiento |

La extension `pdo_pgsql` debe estar habilitada en `C:\xampp\php\php.ini`:

```ini
extension=pdo_pgsql
extension=pgsql
```

Los datos de la empresa (nombre, carrera, asignatura y profesor) se editan desde
**Ajustes** dentro del sistema y se guardan en la tabla `empresa`.

## 4. Estructura del proyecto

```
config/config.php          Datos de conexion y ajustes generales
database/01_esquema.sql    Creacion de tablas
database/02_catalogo.sql   Catalogo de cuentas (404 cuentas)
includes/functions.php     Funciones auxiliares, saldos y validaciones
includes/header.php        Encabezado, menu y mensajes
includes/footer.php        Pie de pagina
assets/css/estilos.css     Estilos propios y formato de reportes
assets/js/asiento.js       Selector jerarquico de cuentas y validacion
api/cuentas.php            Busqueda de cuentas en JSON (para el formulario)
instalar.php               Instalador de la base de datos (desde el navegador)
instalar_pc.php            Instalador automatico para una computadora nueva (solo consola)
INSTALAR.bat               Lanzador del instalador automatico
INICIAR.bat                Inicia PostgreSQL + Apache y abre el navegador
index.php                  Pagina de inicio (resumen)
diario/                    Libro Diario: listar, nuevo, editar, ver, guardar, eliminar
mayor/index.php            Libro Mayor
catalogo/index.php         Catalogo de cuentas
ajustes/index.php          Datos de la empresa
reportes/                  Balance General, Estado de Resultados,
                           Balance de Comprobacion, Indice de Cuentas
```

## 5. Base de datos

Motor: **PostgreSQL 18**, base `contabilidad` en el puerto `5433`.

| Tabla              | Contenido                                              |
|--------------------|---------------------------------------------------------|
| `catalogo_cuentas` | Codigo, nombre, nivel, tipo, naturaleza, hoja          |
| `asientos`         | Cabecera: numero, fecha, concepto, documento, totales   |
| `asiento_partidas` | Detalle: cuenta, debe, haber por linea                  |
| `saldos_cuentas`   | Saldos acumulados por cuenta (mayorizacion)             |
| `empresa`          | Datos institutional                                     |

La mayorizacion es **automatico**: cada alta, edicion o eliminacion recalcula los saldos
de las cuentas afectadas. Los reportes se obtienen en tiempo real sumando las partidas,
por lo que el Balance de Comprobacion siempre debe mostrar
*PARTIDA DOBLE CORRECTA*.

El catalogo incluye **404 cuentas**, de las cuales **281 son cuentas de detalle** aptas para
registrar movimientos. El ultimo asiento de cada grupo se actualiza mediante el trigger
`trg_asientos_updated`.

## 6. Uso

1. **Libro Diario -> Registrar asiento**: elige el grupo y luego la cuenta de detalle,
   indica los importes en Debito o Credito. El sistema valida que el total de Debito sea
   igual al total de Credito antes de guardar.
2. Los asientos se pueden consultar, editar y eliminar desde el listado del Libro Diario.
3. **Libro Mayor**: muestra el detalle de movimientos de cada cuenta con saldo acumulado,
   y un resumen de saldos de todas las cuentas con movimiento.
4. **Reportes**: Balance General, Estado de Resultados, Balance de Comprobacion e
   Indice de Cuentas. Todos aceptan un rango de fechas, se pueden imprimir y exportar a CSV.

## 7. Criterios contables implementados

- **Partida doble**: suma de debitos = suma de creditos, validada en el navegador y en el servidor.
- **Cuentas de detalle**: no se permite registrar movimientos en grupos o cuentas de nivel superior.
- **Clasificacion**: `1 Activo = 2 Pasivo + 3 Capital` y `5 Ingresos - 4 Costos y gastos`.
- **Naturaleza de las cuentas**: deudora o acreedora; el saldo se presenta en la columna
  correspondiente.

## 8. Seguridad

- Consultas preparadas (PDO) en todo el acceso a datos.
- Token **CSRF** en todos los formularios que modifican informacion.
- Escape de salida con `htmlspecialchars` para evitar XSS.
- Sesiones de PHP para los mensajes flash.

## 9. Solucion de problemas

| Sintoma | Causa probable | Solucion |
|---------|----------------|----------|
| `could not find driver` | `pdo_pgsql` no habilitada | Activar la extension en `C:\xampp\php\php.ini` y reiniciar Apache |
| `Connection refused` en puerto `5433` | Servicio PostgreSQL detenido | Iniciar `postgresql-x64-18` desde *Servicios* de Windows |
| `password authentication failed` | `pg_hba.conf` exige clave | Configurar `trust` para `127.0.0.1` en `C:\Program Files\PostgreSQL\18\data\pg_hba.conf` |
| Pagina en blanco | PHP con errores ocultos | Revisar `C:\xampp\php\logs\php_error.log` |
