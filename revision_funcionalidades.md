# Revisión funcional — Decena de Oro

Revisión enfocada en **funcionalidades** (bugs, estados inconsistentes,
concurrencia, mensajes), no en seguridad general ni en diseño. No se
repiten los puntos que ya figuran en `auditoria.md`.

**Estado: arreglado** salvo lo marcado como descartado o pendiente.
Cada arreglo se probó contra un MariaDB 10.11 local con el esquema
reconstruido desde las migraciones (con `jugada_numeros`, `sorteos` y
`sorteo_numeros` en MyISAM, igual que producción): 39 pruebas de
servicio (incluyendo fallas forzadas con triggers para comprobar cada
rollback), 17 pruebas de punta a punta por HTTP y un recorrido de 48
pantallas del panel y del portal sin errores de PHP.

---

## Arreglado

| # | Problema | Arreglo |
|---|---|---|
| 1 | `UsuarioService::crear()` no era atómico: si fallaba el código de referido, el usuario quedaba creado igual y el admin veía un error. | Alta y código en una transacción. |
| 1b | **Nuevo**: `UsuarioService::actualizar()` y `VendedorService::actualizar()` guardaban nombre, rol y estado y *recién después* validaban la contraseña. Con las contraseñas distintas, los cambios quedaban guardados aunque la pantalla dijera que hubo error. | La contraseña se valida antes de escribir y todo va en una transacción. |
| 1c | **Nuevo**: `VendedorService::crear()` creaba la cuenta de cliente antes que la de vendedor, sin transacción. Si fallaba el vendedor, el cliente quedaba creado. | Las dos cuentas en una sola transacción. |
| 2 | `SolicitudService::crear()` grababa el encabezado y las jugadas por separado; un corte entre los dos pasos dejaba una solicitud pendiente sin jugadas, imposible de resolver desde el panel. | Una sola transacción; `JugadaService::crearPendientes()` se suma a la transacción del llamador. |
| 3 | La configuración se grababa en 7 pasos sueltos; un campo inválido a mitad de camino dejaba los anteriores ya cambiados. Además `"2000abc"` se guardaba tal cual y un campo vacío pasaba como 0. | `ParametroService::actualizar()` valida todo primero (números estrictos, sin separador de miles ni exponentes, campos vacíos rechazados, claves no editables rechazadas) y graba todo junto en una transacción. `ConfiguracionService` expone un único `actualizar()`. |
| 4 | `RememberTokenService::validarYRotar()` borraba el token viejo y emitía el nuevo sin transacción: si fallaba el INSERT, el cliente perdía el "recordarme". | DELETE + INSERT en una transacción; la cookie se manda recién después del commit. |
| 5 | Confirmar un pago con un error inesperado terminaba en pantalla en blanco; una comisión ya acreditada para la jugada tiraba abajo la confirmación. | La comisión duplicada es un no-op (como ya decía su comentario). `confirmar.php` muestra un mensaje claro ante un error interno, y una falla de la auditoría ya no se informa como "no se guardó nada". |
| 8 | El aviso de anulación no mostraba la comisión revertida. | Se agrega (el pozo revertido ya se mostraba). |
| 9 | N+1 al armar el aviso de carga de jugadas. | `JugadaService::importeTotal()`: una consulta. |
| 10 | El aviso de configuración mostraba lo tipeado, no lo guardado. | Se arma con los valores ya guardados. |
| 12 | `ClienteService::eliminar()` chequeaba y borraba en sentencias separadas. | Un único `DELETE ... WHERE NOT EXISTS` (jugadas, solicitudes, vendedor) en transacción junto con las favoritas. |
| 14 | `portal/index.php` creaba dos `ParametroService` (cada uno con su caché: dos consultas). | Una sola instancia. |
| 15 | `JugadaService::crear()` sin uso. | Eliminado. |
| 16 | Mensajes sin tildes ("contrasena", "numero", "ningun"...). | 37 mensajes normalizados con un script basado en el tokenizador de PHP, que solo toca frases visibles (nunca claves de arrays ni SQL). |

## Descartado (la revisión original estaba equivocada)

- **#6 `ultimo_acceso` del vendedor**: la tabla `vendedores` no tiene esa
  columna y nada la lee. Agregar el UPDATE rompería el login de
  vendedores hasta aplicar una migración.
- **#7 formulario del vendedor al vencer la sesión**: el panel del
  vendedor no tiene ningún formulario; no hay nada que preservar.
- **#11 `HAVING` del cotejo**: la subconsulta que cuenta los números de
  cada jugada es la forma correcta. La cantidad de números del sábado es
  configurable (1 a 20), así que fijarla como constante haría que las
  jugadas cargadas antes de un cambio no puedan ganar nunca.
- **#13 zona horaria de PHP**: todos los puntos de entrada, incluidos los
  scripts CLI de `db/migrations/`, pasan por `config/bootstrap.php`, que
  la fija.

## Pendiente (requiere una decisión o acceso a producción)

1. **`jugada_numeros`, `sorteos` y `sorteo_numeros` siguen en MyISAM**,
   que ignora los rollbacks. Si el cotejo de un sorteo falla a mitad de
   camino, el sorteo queda cargado mientras el pozo y los ganadores se
   revierten. Hoy se recupera con "Editar" (que recoteja), pero lo
   correcto es convertirlas a InnoDB en una ventana de mantenimiento,
   después de correr `db/migrations/2026-09-24_preflight_integridad_jugada_numeros.sql`.
2. **Zona horaria de MySQL**: PHP usa `America/Argentina/Buenos_Aires`,
   pero `NOW()` y `CURRENT_TIMESTAMP` usan la del servidor de base. Vale
   la pena confirmar en producción que `SELECT @@global.time_zone, NOW();`
   da la hora de Argentina; si da UTC, los reportes por fecha corren 3 h.
