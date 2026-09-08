# Especificación funcional y técnica
## Polla Semanal Los Quevedo

**Fecha:** Septiembre 2026
**Versión:** 1.0

---

## 1. Objetivo

Plataforma web para gestionar una polla de quiniela sobre la Quiniela Nocturna de la provincia de Tucumán, con registro de clientes, carga de jugadas, carga de resultados y cotejo automático de ganadores. Uso principal desde celulares y tablets, tanto para la carga de datos (administrador/supervisor) como para la consulta de resultados (clientes).

## 2. Contexto del juego

Este juego es una variante de la modalidad oficial conocida como **Quiniela Poceada** (que usa 8 números de 2 cifras contra los 20 premios del extracto) y de la **Quiniela Plus**, que toma la decena y unidad de los 20 números del extracto de la Nocturna. La variante "Los Quevedo" usa **10 números** y exige que **los 10 estén contenidos en el extracto** para ganar, sin premios por aciertos parciales.

## 3. Reglas del juego

- Cada jugada cuesta **$2.000**.
- Reparto de cada jugada pagada: **60% al pozo de premios**, **40% a gastos/ganancias** de Los Quevedo.
- El cliente elige **10 números distintos entre 00 y 99**.
- Los 10 números deben salir **todos en un mismo sorteo** de la Quiniela Nocturna de Tucumán (que sortea 20 números de 2 cifras, lunes a viernes).
- Si los 10 números de una jugada están contenidos entre los 20 del sorteo, esa jugada **gana automáticamente**.
- Al producirse un ganador, la semana se **corta**: se liquida el pozo y se espera hasta el ciclo de la semana siguiente para volver a jugar.
- Si **más de un cliente** gana en el mismo sorteo, el pozo se **divide en partes iguales** entre todos los ganadores.
- Si nadie gana en toda la semana (lunes a viernes), la semana se cierra sin pago. *(Ver punto 3.1 sobre continuidad del pozo.)*
- No hay arrastre de saldo entre jugadas: para volver a jugar, el cliente **paga una jugada nueva**.

### 3.1 Pozo dinámico

El pozo **no es un monto fijo predefinido**: nace en $0 al abrir cada ciclo semanal y crece con el 60% de cada jugada pagada que se va cargando. Cuando aparece un ganador (o varios), el pozo acumulado hasta ese momento se reparte íntegro y el contador vuelve a $0 para el ciclo siguiente.

### 3.2 Corte a mitad de semana (resuelto)

Cuando el corte ocurre a mitad de semana (por ejemplo, gana alguien el martes):
- Los sorteos restantes de esa semana calendario (miércoles a viernes) **no participan** del juego.
- Cualquier jugada nueva que se cargue después del corte pertenece automáticamente al **ciclo de la semana siguiente**, y su 60% empieza a acumular el pozo nuevo desde $0.

### 3.3 Ciclo sin ganador (resuelto)

Si llega el sorteo del viernes sin ganador, el ciclo se cierra como `cerrado_sin_ganador`, pero el pozo **no se resetea**: el monto acumulado arrastra al ciclo siguiente, que abre ya con ese saldo en vez de en $0. En pantalla se muestra como un número único (el pozo vigente), aunque en la base queda registrado por ciclo, de modo que se puede desglosar por semana más adelante sin migrar el esquema.

## 4. Roles y permisos

| Acción | Administrador | Supervisor |
|---|---|---|
| Crear y borrar usuarios (admin/supervisor) | ✅ | ❌ |
| Dar de alta clientes | ✅ | ✅ |
| Cargar jugadas | ✅ | ✅ |
| Cargar los 20 números del sorteo | ✅ | ✅ |
| Borrar clientes / jugadas / sorteos | ✅ | ❌ |
| Configurar parámetros (costo de jugada, % pozo/gastos) | ✅ | ❌ |
| Ver reportes y recaudación | ✅ | ✅ (limitado, a definir) |

El cliente **no es un usuario administrativo**: tiene su propio login de solo lectura (ver punto 6).

## 5. Flujo funcional

1. **Alta de cliente**: admin o supervisor carga DNI, nombre y teléfono. El sistema genera un **número de cliente** único. Usuario y clave inicial de acceso al portal = DNI; se exige cambio de clave en el primer ingreso.
2. **Carga de jugada**: admin o supervisor selecciona cliente, ingresa los 10 números (validación: 10 números distintos, entre 00 y 99), registra el pago y confirma. El sistema suma el 60% del importe al pozo del ciclo activo.
3. **Carga del sorteo**: al finalizar cada sorteo nocturno, admin o supervisor carga manualmente los 20 números del extracto oficial.
4. **Cotejo automático**: al guardar el sorteo, el sistema compara los 10 números de cada jugada activa del ciclo contra los 20 cargados. Si hay intersección de 10, marca la jugada como ganadora.
5. **Cierre de ciclo**: si hay uno o más ganadores, el sistema liquida el pozo (dividido en partes iguales si hay más de uno), marca el ciclo como cerrado y abre uno nuevo en $0.
6. **Consulta del cliente**: el cliente entra al portal y ve sus jugadas del ciclo actual e historial, con los números marcados según hayan salido o no en cada sorteo, y el estado del pozo.

## 6. Portal del cliente

- Login con usuario = DNI y clave = DNI, con cambio obligatorio de clave en el primer acceso.
- Vista de jugadas activas del ciclo en curso, con los 10 números y cuáles ya salieron en los sorteos corridos de la semana.
- Historial de jugadas y resultados de ciclos anteriores.
- Estado del pozo acumulado del ciclo actual (opcional, para generar expectativa).

## 7. Modelo de datos (propuesta inicial)

**usuarios**
`id, usuario, password_hash, rol (admin | supervisor), activo`

**clientes**
`id, nro_cliente, dni, nombre, telefono, password_hash, debe_cambiar_clave, fecha_alta`

**ciclos** *(semana de juego)*
`id, fecha_inicio, fecha_fin, estado (abierto | cerrado_con_ganador | cerrado_sin_ganador)`

**jugadas**
`id, cliente_id, ciclo_id, importe, pagada, fecha_carga, cargado_por (usuario_id)`

**jugada_numeros**
`id, jugada_id, numero (00-99)`

**sorteos**
`id, ciclo_id, fecha, cargado_por (usuario_id)`

**sorteo_numeros**
`id, sorteo_id, numero (00-99)`

**pozo_ciclo**
`ciclo_id, monto_acumulado, monto_pagado, fecha_liquidacion`

**ganadores**
`id, jugada_id, sorteo_id, monto_premio`

## 8. Stack tecnológico

- **Backend:** PHP, mismo criterio que `sas_imperio` y `crm_imperio`.
- **Base de datos:** MySQL, mismo servidor y flujo de despliegue (git pull) ya usado en el VPS.
- **Frontend:** Bootstrap 5 vía CDN (sin build tools), con diseño mobile-first: formularios grandes y táctiles para la carga de jugadas y sorteos, vistas simples y legibles en el portal del cliente.
- **Hosting:** VPS de prueba (Ubuntu 24.04) primero, réplica al VPS de producción una vez validado.

## 9. Fases de desarrollo

| Fase | Contenido | Estimación |
|---|---|---|
| 1. Núcleo | Usuarios y roles, ABM de clientes, carga de jugadas con validaciones | 2 semanas |
| 2. Sorteos y cotejo | Carga manual del extracto, motor de cotejo automático, cierre de ciclo y liquidación de pozo | 1 semana |
| 3. Portal del cliente | Login DNI/DNI, vista de jugadas y resultados, estado del pozo | 1 semana |
| 4. Administración y reportes | Recaudación, historial de ganadores, exportables | 1 semana |

## 10. Puntos abiertos antes de programar

1. Definir el nivel de detalle de reportes que verá el Supervisor (¿recaudación total, o solo sus propias cargas?). No bloquea la Fase 3 — se resuelve en la Fase 4.
2. Definir si el número de cliente sigue algún formato particular (correlativo, con prefijo, etc.) o alcanza con un correlativo simple.
