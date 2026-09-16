Ready for review
Select text to add comments on the plan
Auditoría Decena de Oro — corrección completa
Contexto
Pedido: "una auditoría de funcionamiento en diseño y estructura y revisar si existe algún Bug". Se auditó con tres agentes en paralelo (lógica de negocio, diseño y consistencia visual, seguridad y estructura) y verifiqué personalmente contra el código todos los hallazgos críticos antes de darlos por ciertos.

Aparecieron ~25 problemas. Dos son riesgo de pérdida de datos o de plata y no venían de un cambio reciente: estaban latentes. El resto se reparte entre bugs visibles, la grieta que dejó el rediseño Gentelella (aac0118) dentro del admin, y deuda técnica.

Decisiones tomadas con el usuario:

Alcance: todo, incluida la deuda técnica.
Bug del cotejo: replay completo — registrar() pasa a usar recotejarCiclo(), y la semana no cierra si falta un día sin cargar. Se conserva la posibilidad de cargar un extracto atrasado.
El orden de abajo es el orden de ejecución: cada bloque es independiente y commiteable por separado.

P0 — Seguridad y riesgo de destrucción (antes que nada)
0.1 db/schema.sql puede borrar la base de producción
db/schema.sql:7-11 hace CREATE DATABASE IF NOT EXISTS iifatgdb_decena + USE iifatgdb_decena; y después DROP TABLE IF EXISTS de las 16 tablas. El README.md:33 indica correr mysql -u root -p polla_quevedo < db/schema.sql — el USE interno pisa la base elegida en la línea de comandos. Seguir el README desde una máquina con acceso a producción borra producción.

Arreglo: sacar el CREATE DATABASE + USE de db/schema.sql, db/seed.sql, db/seed_produccion.sql, demo/datos_demo.sql y de la migración db/migrations/2026-09-11_vendedor_cliente_link.sql:14 (las otras dos ya se corrigieron en 4b528cf; a esta se le pasó). La base la elige siempre quien ejecuta. Dejar un comentario de una línea arriba de cada archivo diciéndolo, y actualizar el README.md para que el ejemplo use el nombre real de la base local.

0.2 Credenciales de producción en el historial de git
db/seed_produccion.sql (commit 1a74b11) tiene el usuario admin de producción y su contraseña en texto plano en un comentario (líneas 10-11 y 43-45), más el hash bcrypt.

Arreglo, en este orden:

Cambiar la contraseña en producción — el archivo ya está en el historial, así que borrarlo hoy no revierte la exposición.
Reemplazar el bloque de credenciales del archivo por un placeholder y una nota de cómo generar el hash (password_hash()), sin valores reales.
No reescribir el historial de git (el repo es compartido y el beneficio es nulo una vez rotada la clave).
0.3 Dump de producción dentro del webroot
sql/iifatgdb_decena (2).sql (110 KB, con hashes y DNIs de clientes reales) está en el directorio servido por Apache, sin .htaccess — a diferencia de config/, db/, includes/ y src/, que sí tienen Require all denied — y no está en .gitignore (que solo cubre /config/db.php, archivos de editor y logs).

Arreglo: mover el dump fuera del webroot; agregar /sql/ a .gitignore; y agregar sql/.htaccess con Require all denied como red por si vuelve a aparecer un archivo ahí.

0.4 La PWA nunca se deployó
.cpanel.yml copia únicamente admin assets auth config includes portal src vendedor index.php registro.php. manifest.json, service-worker.js e img/ no se copian nunca → en producción dan 404 y la PWA que se construyó en esta sesión no existe.

Arreglo: agregarlos a .cpanel.yml. Verificar después del deploy que https://decenadeoro.lol/manifest.json y /service-worker.js respondan 200.

0.5 Tres nombres de base conviviendo
config/db.php real usa danqueve_polla; todas las migraciones y el README dicen polla_quevedo; schema.sql/seed/producción dicen iifatgdb_decena. Ya causó un incidente real en esta sesión: dos migraciones con USE iifatgdb_decena nunca se aplicaron en local y dejaron dos filas de ciclos con estado = ''.

Arreglo: con el USE fuera de los .sql (0.1) el problema se disuelve solo. Queda unificar el README y config/db.example.php en un único nombre documentado para desarrollo (danqueve_polla, el que realmente se usa).

Archivos: db/schema.sql, db/seed.sql, db/seed_produccion.sql, demo/datos_demo.sql, db/migrations/2026-09-11_vendedor_cliente_link.sql, .gitignore, sql/.htaccess (nuevo), .cpanel.yml, README.md, config/db.example.php

P1 — Bugs que pueden costar plata
1.1 Cargar un extracto fuera de orden rompe el cotejo acumulado
El peor de todos. registrar() (SorteoService.php:82-90) inserta el sorteo y corre un solo cotejarYCerrar() sobre ese sorteo. Y jugadasGanadoras() (:471) acota el acumulado con:

AND (s.fecha < ref.fecha OR (s.fecha = ref.fecha AND s.turno <= ref.turno))
El comentario de a0608e2 justifica ese recorte asumiendo que los extractos entran siempre en orden. Esa premisa es falsa: validarFechaContraCiclo() (:622-624) documenta explícitamente el caso "sorteo atrasado" como soportado, y en producción se opera así — en el ciclo 1 los extractos entraron jue → lun → mié → mar → vie.

El caso grave, y es el que el propio formulario empuja a hacer: falta el jueves; el viernes el supervisor entra a "Cargar Extracto" y admin/sorteos/nuevo.php:37 (end($pendientes)) le preselecciona el viernes. Guarda. El cotejo ve {lun, mar, mié, vie} sin los 20 números del jueves, no encuentra ganador, $esUltimoDeLaSecuencia = ($fecha === $ciclo['fecha_fin']) da true, y cierra la semana sin_ganador arrastrando el pozo entero. El jueves ya no se puede cargar nunca (validarFechaContraCiclo() lo rechaza contra el ciclo nuevo) y corregir() tampoco lo salva, porque solo reemplaza números de un sorteo existente. Una jugada que se completaba con los números del jueves pierde el premio de forma irrecuperable.

Arreglo (replay completo):

En registrar(), reemplazar el cotejarYCerrar() puntual por $this->recotejarCiclo($ciclo, CicloService::TIPO_SEMANAL) — ya existe (:394-416), hace exactamente el replay cronológico correcto y corregir() ya lo usa (:372). Idem en registrarTurnoSabado() por simetría.

El replay es seguro sobre una carga normal en orden: si un sorteo anterior no tuvo ganador, cotejarYCerrar() cae en if (!$esUltimoDeLaSecuencia) return $base; (:545-547) y no toca nada. Y si hubiera tenido ganador, el ciclo estaría cerrado y no podríamos estar cargando. O sea: sin ganador el replay es idempotente, y con ganador acierta el sorteo correcto.

Agregar un helper privado secuenciaCompleta(array $ciclo, string $tipo): bool y usarlo dentro de recotejarCiclo() para calcular $esUltimo:

sábado: COUNT(*) === 5 (ya equivale al turno === 5 actual)
semanal: además de fecha === fecha_fin, que estén cargadas todas las fechas de fecha_inicio..fecha_fin (mismo recorrido día a día que ya arma admin/sorteos/nuevo.php:28-35).
Consecuencia buscada: con un día faltante la semana no cierra; queda abierta hasta que se cargue el día que falta, y al cargarlo el replay liquida al ganador correcto. Consecuencia a vigilar: si un día realmente nunca se carga, el ciclo queda abierto. No se pierden jugadas nuevas (desde 8913716 el corte del lunes 22hs ya las manda al ciclo programado), pero hay que hacerlo visible → punto 3.

admin/ciclos/ver.php y admin/index.php: mostrar "faltan N días para cerrar la semana" cuando la secuencia esté incompleta, para que nunca sea silencioso.

admin/sorteos/nuevo.php:37: preseleccionar la fecha pendiente más vieja ($pendientes[0]) en vez de la más nueva (end($pendientes)), para que el flujo natural sea el correcto aunque el motor ya aguante el desorden.

1.2 El portal muestra "10 de 10" y el sistema dice que no ganó
PortalService::evaluar() (:150-182) acumula sobre todo el array de sorteos que recibe, sin acotar a "hasta el que se está cotejando". jugadasGanadoras() sí acota. Con los extractos en orden las dos reglas coinciden; fuera de orden divergen, y el cliente lee en includes/portal_jugada.php:91:

Sin premio — Acertaste 10 de 10 en todo el ciclo

Arreglo: es la cara visible de 1.1 y se resuelve en gran parte con él. Además, alinear explícitamente el contrato: evaluar() documenta que recibe los sorteos "en orden de fecha/turno" — hacer que sorteosDelCiclo() lo garantice (ver 2.4) y dejar el docblock diciendo que el acumulado es el del ciclo completo, que es lo que la pantalla realmente muestra.

1.3 corregir() deja sorteos huérfanos dentro del ciclo
recotejarCiclo() (:410) corta el recorrido en cuanto el ciclo cierra, pero no hace nada con los sorteos posteriores, que quedan con ciclo_id apuntando al ciclo ya cerrado y liquidado.

Repro: semana con los 5 extractos, cerrada sin_ganador. El admin corrige el lunes; con los números corregidos alguien completa el martes. El replay cierra en el martes y miércoles, jueves y viernes siguen colgando del ciclo → sorteosDelCiclo() los devuelve, evaluar() los suma, y un cliente que perdió ve "10 de 10 / Sin premio" (mismo síntoma que 1.2, ahora sobre un ciclo ya pagado). Además numerosSalidos() los pinta en el ranking, y esas fechas quedan ocupadas en uk_sorteos_fecha_turno.

El motor en sí queda coherente (el cotejo está acotado por ref), así que no hay pérdida de plata directa — es inconsistencia de datos y una contradicción visible.

Arreglo: al cerrar dentro de recotejarCiclo(), reasignar los sorteos posteriores al ciclo siguiente (el que devuelve promoverOAbrirSiguiente()), que es donde cronológicamente corresponden. Si el siguiente no los admite por fecha, dejarlos fuera de todo ciclo (ciclo_id = NULL) antes que dentro de uno liquidado, y registrarlo en auditoría.

1.4 Borrar un turno del medio de un sábado traba el ciclo para siempre
proximoTurno() (:163-169) saca el turno de contar filas, no de MAX(turno) + 1:

'SELECT COUNT(*) FROM sorteos WHERE ciclo_id = :ciclo'
return (int) $stmt->fetchColumn() + 1;
Con turnos {1, 2, 3} cargados, borrar el 2 deja {1, 3} → COUNT = 2 → proximoTurno() = 3 → choca contra uk_sorteos_fecha_turno y devuelve "Ese turno ya estaba cargado". Para siempre: nunca puede llegar a 4. El sábado no alcanza el turno 5, no cierra, no liquida y no arrastra. Solo se sale con cirugía en la base.

Atenuante: admin/sabados/sorteo_eliminar.php no tiene ningún enlace en la UI (cero referencias en todo el repo), así que es un bug latente — pero el endpoint está publicado y el arreglo es de una línea.

Arreglo: SELECT COALESCE(MAX(turno), 0) + 1 FROM sorteos WHERE ciclo_id = :ciclo.

Archivos: src/Services/SorteoService.php, src/Services/PortalService.php, includes/portal_jugada.php, admin/sorteos/nuevo.php, admin/ciclos/ver.php, admin/index.php

P2 — Bugs visibles, sin pérdida de plata
2.1 Los reportes cuentan como recaudado jugadas que nadie pagó
ReporteService::condicionesJugadas() (:620) filtra solo j.estado <> 'anulada', sin pagada = 1. Una jugada armada desde el portal y nunca pagada queda estado = 'activa', estado_pago = 'pendiente_pago', ciclo_id = NULL, con su importe ya cargado → entra en las tarjetas "Recaudado", "Al pozo" y "A gastos" de admin/reportes/index.php.

Contraste: CicloService::resumen() (:580-588) sí filtra pagada = 1, y ReporteService::porCiclo() (:222) las excluye de rebote por el JOIN ciclos sobre ciclo_id NULL. Síntoma visible: las barras "por ciclo" no suman lo que dice la tarjeta "Recaudado".

Arreglo: agregar j.pagada = 1 a condicionesJugadas() y al recaudadoGlobal() (:138), que replica el mismo WHERE.

2.2 El corte del lunes bloquea correcciones legítimas con un mensaje que no explica nada
corregir() (:304-315) tiene una guarda defensiva marcada "no debería poder pasar": si el ciclo programado ya tiene jugadas, rechaza la corrección. Era cierto cuando se escribió (41e5259), porque el único desvío al programado era "el ciclo abierto ya tiene un sorteo". 8913716 lo invalidó: desde el lunes 21:00 toda jugada nueva va al programado aunque no haya corrido ningún sorteo.

Repro: lunes 21:30 un cliente compra → cae en el ciclo N+2. Martes a la mañana el admin quiere corregir un dígito del ciclo N y, si todavía no cargó el sorteo del lunes (habitual: cargan con 1-2 días de atraso), la corrección se rechaza con "hay actividad inesperada en un ciclo posterior". El dump lo confirma: el ciclo 6 (programado) ya tiene monto_acumulado = 3.00.

Arreglo: permitir que el programado tenga jugadas (es el caso normal ahora) y frenar solo si tiene sorteos propios, que sigue siendo lo que de verdad impide reacomodar. Actualizar el comentario, que quedó describiendo un diseño que ya no rige.

2.3 El saldo del vendedor puede quedar negativo
ComisionService (:145-156) calcula saldoPendiente = SUM(comisiones) - SUM(liquidaciones), y fk_comisiones_jugada es ON DELETE CASCADE. Si se acredita una comisión, se liquida (plata entregada), y después el admin borra la jugada con JugadaService::eliminar() (permitido si no es ganadora), la comisión desaparece por cascade y el saldo queda negativo. LiquidacionService::liquidar() corta con if ($saldo <= 0), así que el vendedor no cobra hasta volver a superar ese monto, y en admin/referidos/index.php figura en negativo sin explicación.

Arreglo: en JugadaService::eliminar(), bloquear el borrado si la jugada tiene una comisión ya liquidada (mismo criterio de "desactivar en vez de borrar" que ya usa ClienteService::eliminar()). Comisión acreditada pero no liquidada se puede seguir borrando, que es el caso real de corregir una carga del día.

2.4 Los 5 turnos del sábado salen en orden indefinido
PortalService::sorteosDelCiclo() (:115) ordena solo ORDER BY s.fecha ASC, y ni siquiera selecciona turno. En sábados los 5 turnos comparten fecha, así que el desempate queda a criterio de MySQL — rompiendo el contrato que el docblock de evaluar() (:143) declara. En pantalla el cliente ve cinco renglones con la misma fecha en "Cómo te fue en cada sorteo", sin poder distinguir el turno, y con el acumulado potencialmente atribuido al turno equivocado.

Arreglo: ORDER BY s.fecha ASC, s.turno ASC, sumar s.turno al SELECT, y en includes/portal_jugada.php:127 mostrar "Turno N" cuando el ciclo es de sábado.

2.5 admin/ciclos/ver.php muestra el premio base del semanal en un ciclo de sábado
admin/ciclos/ver.php:42 llama a premioBase() sin validar el tipo del ciclo, a diferencia de admin/sabados/ver.php:23, que sí lo hace. Con ?id=<un sábado> muestra el piso de $30.000 en vez de $10.000 y un "subsidio" inventado. Cosmético, pero es un número de plata en pantalla.

Arreglo: replicar la guarda de admin/sabados/ver.php — si el ciclo es de sábado, redirigir a admin/sabados/ver.php.

Archivos: src/Services/ReporteService.php, src/Services/SorteoService.php, src/Services/JugadaService.php, src/Services/PortalService.php, includes/portal_jugada.php, admin/ciclos/ver.php

P3 — Diseño: lo que rompe pantallas
aac0118 creó assets/css/admin.css (tokens --g-*, paleta azul #3b82f6, tipografía Inter) y 4 partials nuevos, y migró 33 pantallas de admin/**. No tocó app.css, y includes/admin_head.php:44-45 carga los dos: app.css primero, admin.css después. admin.css pisa selectivamente; todo lo que no pisó sigue mandando desde app.css. Ahí están estos problemas. Nota positiva: el prefijo g- y el scope body.admin-gentelella evitaron cualquier colisión de nombres — el híbrido es por omisión, no por choque.

3.1 La barra inferior tapa el botón de acción en 10 formularios
includes/admin_foot.php:8 hace require bottom_nav.php incondicionalmente. Antes del commit, bottom_nav.php se incluía a mano en 23 pantallas de listado y deliberadamente no en las 10 que usan .accion-fija.

.accion-fija (app.css:420-430) es fixed; bottom:0; z-index:1035, ~72px. .navbar-abajo (app.css:448-457) es fixed; bottom:0; z-index:1030, 76px. La de acción tapa a la de navegación pero es 4px más baja → queda una franja oscura asomando arriba del botón "Confirmar y cobrar", y la nav queda inaccesible.

Afecta: admin/jugadas/nueva.php, clientes/form.php, usuarios/form.php, vendedores/form.php, promociones/form.php, configuracion/index.php, sorteos/nuevo.php, sorteos/editar.php, sabados/sorteo_nuevo.php, sabados/sorteo_editar.php.

Arreglo: incluir bottom_nav.php solo si $bodyClass no contiene con-accion-fija.

3.2 El último campo del formulario queda tapado en iPhone
admin.css:79-87 declara body.admin-gentelella { margin: 0; padding: 0 } — especificidad (0,1,1), la misma que body.con-accion-fija { padding-bottom: 7.5rem } (app.css:438) — y gana por orden de carga. Las 10 pantallas de arriba pierden los 120px de reserva y quedan con los 76px del media query (admin.css:1064), insuficientes para la barra de acción de ~72px + safe-area.

Arreglo: sacar el padding: 0 del bloque (el margin: 0 puede quedarse) y reponer la reserva bajo body.admin-gentelella.con-accion-fija.

Archivos: includes/admin_foot.php, assets/css/admin.css

P4 — Diseño: fuera de paleta
4.1 Los títulos de tarjeta salen en la fuente equivocada (67 lugares)
app.css:65-70 declara h1, h2, h3, h4 { font-family: var(--ff-display) } (Bricolage Grotesque). admin.css pone Inter en body.admin-gentelella por herencia y explícitamente en .g-page-title, .g-stat__value, .g-hero__amount — pero .g-card__title y .g-list-item__title no declaran font-family y viven sobre <h2>/<h3>. Una declaración directa sobre el elemento le gana siempre a un valor heredado. Resultado: en la misma pantalla, H1 en Inter y H2/H3 en Bricolage.

Arreglo: body.admin-gentelella h1, h2, h3, h4 { font-family: var(--g-ff) } — una línea, 67 títulos.

4.2 Dos azules conviviendo (22 usos)
Ninguno de los dos CSS redefine --bs-primary, así que los 22 usos de text-primary / bg-primary / border-primary en 14 archivos del admin renderizan el #0d6efd de Bootstrap al lado del #3b82f6 de Gentelella — incluido el "Total a Cobrar" de admin/jugadas/nueva.php:297, el número más importante de la pantalla.

Arreglo: body.admin-gentelella { --bs-primary: #3b82f6; --bs-primary-rgb: 59,130,246 } — una línea, 22 usos.

4.3 Islas verdes dentro del admin azul
admin.css:945-955 sí repintó .bolilla, pero se olvidó de lo que está en la misma pantalla:

Componente	app.css	Dónde se ve
.casilla__input:focus / .cargada	783-794	5 pantallas de carga
.tablero__celda.marcada	826-830	5 pantallas
.contador--completo	877	5 pantallas
.nav-pills .nav-link.active	391-394	admin/jugadas/nueva.php:139-152
.accion-fija (fondo beige)	426-428	10 pantallas
.filtros (borde beige, radio 10px)	1263-1268	4 pantallas de reportes
.alert-info	694	usuarios/index.php, promociones/index.php + 4 flashes
Arreglo: repintar las siete bajo body.admin-gentelella en admin.css. El .alert-info es el único de los cuatro alerts que no se migró (admin.css:1014-1030 ya pisa warning, success y danger).

4.4 Cuatro verdes en simultáneo
--verde #14663f (sistema propio), --bs-success #198754 (Bootstrap), --g-success #10b981 (Gentelella) y el #25D366 de WhatsApp. admin/jugadas/nueva.php:259-265 usa los verdes Bootstrap para la tarjeta de promo y admin/reportes/jugadas.php:132 pinta la columna Premio con text-success.

Detalle semántico: en el sistema viejo el premio era oro (la regla "el oro es solo para plata"). Ahora el premio en reportes es verde Bootstrap.

Arreglo: redefinir --bs-success bajo body.admin-gentelella para que coincida con --g-success, y devolver el premio a oro en las vistas de reportes.

4.5 fw-extrabold no existe
5 usos, todos en cifras destacadas, todos renderizando en peso normal: el "Total a Cobrar" (jugadas/nueva.php:297), el monto de la solicitud (solicitudes/index.php:120), y los premios de ciclos/ver.php:196, sabados/ver.php:201, reportes/ganadores.php:129. Bootstrap 5.3 no tiene esa clase y ningún CSS del proyecto la define (verificado con grep).

Arreglo: cambiar a fw-bold, o definir .fw-extrabold { font-weight: 800 } en admin.css. Preferible lo segundo: la intención era destacar.

4.6 Bolillas deformadas (4 lugares)
.bolilla (app.css:252-253) es min-width: 2.125rem; height: 2.125rem. El markup le pone style="width:24px", pero min-width le gana a width → sale de 34×24px. En admin/ciclos/ver.php:218 una bolilla-leyenda vacía de 16px sale como una barrita de 34×16.

Arreglo: un modificador .bolilla--chica que baje min-width junto con width, y sacar los 4 style inline. Afecta admin/ciclos/ver.php:218,345, admin/sabados/ver.php:357, admin/reportes/jugadas.php:116.

4.7 La tabla de reportes perdió el trabajo que ya estaba hecho
El comentario de cabecera de app.css:11 dice literal "ninguna tabla obliga a hacer scroll horizontal", y para eso existían .tabla-scroll + .tabla-reporte (header sticky, tabular-nums, .tabla-pista avisando del desplazamiento). aac0118 las reemplazó por table-responsive + table table-hover de Bootstrap pelado en admin/reportes/jugadas.php:88-139: 10 columnas en 360px con scroll lateral sin aviso, sin header sticky, sin tabular-nums en las 4 columnas de plata, thead .table-light fuera de las dos paletas, y las esquinas de la tabla comiéndose el border-radius de la .g-card.

Arreglo: volver a .tabla-scroll + .tabla-reporte (que siguen en app.css, ver 5.1) dentro de la g-card, agregando overflow: hidden al contenedor.

4.8 La barra de reportes aplastada por .rounded
admin/reportes/index.php:173-174 pone .rounded de Bootstrap (border-radius: .375rem !important) sobre .barra-ciclo__dato, aplastando el border-radius: 0 999px 999px 0 deliberado (app.css:1154). Además el style inline re-declara background/height/overflow que la clase ya define, con un gris distinto (#e5e7eb vs #eceae3), y usa __pista/__dato sin el contenedor .barra-ciclo.

Arreglo: sacar los .rounded y los style inline, reponer el contenedor .barra-ciclo.

4.9 El notch se come la topbar del admin en la PWA
app.css:102 tiene .topbar { padding-top: calc(.75rem + env(safe-area-inset-top)) }. admin.css:324 declara .g-topbar { height: 64px; padding: 0 1.5rem } sin safe-area, y .g-sidebar (admin.css:92) tampoco. Con viewport-fit=cover + black-translucent (admin_head.php:14,18), en la PWA instalada en iPhone la hamburguesa y el avatar quedan debajo de la barra de estado. Es una regresión respecto del admin viejo.

Arreglo: env(safe-area-inset-top) en .g-topbar y .g-sidebar.

4.10 auth/cambiar_clave.php es la única pantalla mitad y mitad
Es la única del panel que sigue con includes/head.php + includes/topbar.php + bottom_nav.php, y es alcanzable desde el dropdown Gentelella (includes/admin_topbar.php:73): el usuario hace clic en un panel azul con sidebar y aterriza en una pantalla verde oscuro, sin sidebar, con la topbar vieja.

Arreglo: migrarla a admin_head / admin_sidebar / admin_topbar / admin_foot y a .g-content + .g-card. Con eso includes/topbar.php queda sin usuarios (ver 5.3).

4.11 Detalles menores de navegación y layout
app.css:60 reserva padding-bottom: 76px en todo <body>, así que portal/solicitud.php, vendedor/index.php y vendedor/historial.php — que no renderizan ninguna barra — muestran una franja vacía de 76px al pie. Se arregla con $bodyClass = 'sin-barra', que ya existe (app.css:63) y usan bien portal/pendiente.php:12, registro.php:83 y auth/login.php:140.
admin/jugadas/nueva.php:98 setea $navSeccion = 'jugadas', así que en la barra inferior se ilumina "Jugadas" y nunca "Cargar" (bottom_nav.php:24).
portal/ranking_semanal.php:35 y ranking_sabado.php:36 setean $navSeccion = 'mis-jugadas' → estando en el ranking la nav dice "Mis jugadas".
includes/vendedor_cabecera.php:27,32 hace navActivo('x') ? ' active' : '', pero navActivo() ya devuelve ' active' o ''. Funciona por accidente.
theme-color inconsistente: admin_head.php:15 usa #1a2332 y head.php:15 + manifest.json usan #0d4a2d, así que la barra del navegador cambia de color al entrar y salir del admin.
Archivos: assets/css/admin.css (el grueso), assets/css/app.css, auth/cambiar_clave.php, admin/reportes/jugadas.php, admin/reportes/index.php, admin/ciclos/ver.php, admin/sabados/ver.php, admin/jugadas/nueva.php, portal/solicitud.php, vendedor/index.php, vendedor/historial.php, portal/ranking_semanal.php, portal/ranking_sabado.php, includes/vendedor_cabecera.php

P5 — Deuda técnica
5.1 ~180 líneas de CSS muerto en app.css
Verificado con grep de atributos class= sobre todo el repo: se usaban antes de aac0118 y ahora tienen cero usos.

.tabla-scroll + .tabla-reporte + .tabla-pista (1204-1259), .tarjeta-dato (1171-1202), .evento (1284-1316), .pozo__desglose + .desglose-pozo__fila (1433-1470), .chip-alcance (1272-1282), .leyenda-calor (851-866), .input-codigo (1379-1385), .barra-ciclo + __cifra + __dato--tenue (1125-1137, 1149-1169), .display-titulo (65, ya estaba muerta).

Este paso va último a propósito: .tabla-scroll/.tabla-reporte y .barra-ciclo se reponen en 4.7 y 4.8, así que no hay que borrarlas. Hacer el barrido recién cuando P4 esté cerrado y se sepa qué quedó realmente en uso.

5.2 ~40 líneas de CSS nacido muerto en admin.css
.g-quick-actions (837-848 + media query — el markup usa d-grid gap-2 de Bootstrap), .g-page-header (478-480), .g-card__footer (541-546), .g-btn--block (832-834), .g-animate-delay-4 (1111).

5.3 Navegación duplicada en 4 archivos
includes/topbar.php queda zombi tras 4.10, y su dropdown (líneas 29-90) duplica todo el menú que ahora vive en admin_sidebar.php + admin_topbar.php + bottom_nav.php. Agregar una sección hoy obliga a tocar 3 archivos.

Arreglo: borrar includes/topbar.php, y extraer la lista de secciones a un array único que consuman sidebar, topbar y bottom nav.

5.4 58 estilos inline repetidos en admin/
style="background:#e5e7eb;color:#4b5563" × 16 (chip gris "Perdió"/"Inactivo" — #e5e7eb es exactamente --g-card-border, pero no usa el token); style="background:#f9fafb;border:1px solid #f3f4f6" × 6; style="background:#fffbeb;border:1px solid #fef3c7" × 2 (es --g-warning-light hardcodeado); style="font-size:.625rem" × 6; style="width:2.5rem;height:1.25rem" × 4; style="color:inherit" × 4.

Arreglo: .g-badge--gris, .g-panel--sutil, .g-panel--aviso, .g-switch.

5.5 Cuatro familias tipográficas por pantalla
app.css:14 importa Bricolage + Sora + Space Grotesk (solo Bricolage se usa en el admin, y por el bug 4.1); admin.css:13 importa Inter; admin_head.php:30 importa Inter de nuevo. Un @import dentro de un CSS es serialmente bloqueante.

Arreglo: sacar el @import de Inter de admin.css (queda el <link>, que es paralelo) y evaluar cargar los @import de app.css como <link> en head.php.

5.6 !important encadenados
admin.css:786-816 usa 12 !important en .g-btn--primary/.g-btn--outline contra nada en particular (son clases propias sin conflicto). Y admin.css:987-988 los necesita solo porque app.css:346-347 ya los tenía. Con --bs-primary redefinido (4.2), varios de esos overrides dejan de hacer falta.

5.7 border-radius duplicado
admin.css:196-198: border-radius: 0 en la línea 196 y border-radius: var(--g-radius-sm) en la 198, en la misma regla. La primera es muerta.

5.8 Textos con el reparto hardcodeado
porcentaje_pozo es configurable (ParametroService.php:23,75) y se edita desde admin/configuracion. admin/jugadas/nueva.php:304 y admin/index.php:134 lo leen bien con $parametros->porcentajePozo(), pero admin/reportes/index.php:118,126 dice "Al Pozo Acumulado (60%)" y "Gastos / Ganancias (40%)" literal, y admin/promociones/form.php:51 dice "el pozo sigue sumando el 60%". Si alguien cambia el reparto, Reportes miente. Idem admin/index.php:160, donde el / 5 de los extractos es literal.

5.9 Higiene del repo
implementation_plan.md y task.md quedaron commiteados en la raíz por aac0118, y los dos HTML de prototipo (961 KB + 64 KB) siguen en staging sin commitear. Ninguno se deploya, pero ensucian. Además service-worker.js:39-48 no incluye admin.css en ARCHIVOS_PRECACHE y CACHE_NAME sigue en decena-static-v1 pese a que el comentario de la línea 24 pide bumpearlo (los ?v=filemtime de admin_head.php:41-42 lo salvan en la práctica).

Lo que se revisó y no es bug
Vale registrarlo para no volver a auditarlo:

PozoService::repartirEnPartesIguales() trabaja en centavos enteros y reparte el sobrante de a uno: la suma de las partes es exacta.
Doble acreditación de comisión: cubierta por uk_comisiones_jugada + bloquearPendiente() con FOR UPDATE. (El docblock de ComisionService:50 dice que la segunda inserción "choca y no duplica", pero la excepción no está capturada: el efecto real es abortar la confirmación entera. Es fail-closed — está mal el comentario, no el código.)
Doble liquidación al recotejar un ciclo cerrado con ganador: corregir() (:331-337) borra ganadores y resetea el pozo antes de recotejar.
Reversión del arrastre al reabrir un ciclo cerrado_sin_ganador: correcta.
Orden de los UPDATE de estado en corregir() (:348-352): respeta uk_ciclo_tipo_abierto y uk_ciclo_tipo_programado, y es deliberado.
COUNT(DISTINCT jn.numero) en jugadasGanadoras(): correcto — con COUNT(*) un número repetido en el extracto descartaría al ganador legítimo.
Doble acumulación del pozo: monto_acumulado arranca igual a monto_arrastrado y acumular() solo suma sobre el primero. Confirmado contra el dump.
Carrera de dos supervisores cargando el mismo sorteo: bloquearAbierto() con FOR UPDATE + uk_sorteos_fecha_turno + traducción del error.
SorteoService::eliminar() sobre un sorteo con premios pagados: bloqueado por el chequeo de ciclo_estado !== 'abierto' (:864).
fecha_inicio de un ciclo semanal siempre cae lunes. Confirmado contra el dump.
Los 15 href de bottom_nav.php y los 13 del sidebar apuntan a archivos que existen — ningún 404. navActivo() funciona y las 33 pantallas definen $navSeccion.
Ni un error de sintaxis PHP en los 125 archivos, y el balance <div>/</div> es exacto en los 125 pese al tamaño del refactor.
Portal y vendedor quedaron enteros en el sistema verde/oro: son mundos separados y el cliente nunca ve el admin. Está bien resuelto.
Verificación
P0 — grep -rn "^USE " db/ demo/ debe dar cero. Levantar schema.sql contra una base descartable y confirmar que crea las 16 tablas ahí y no en otra. curl -I https://decenadeoro.lol/sql/ debe dar 403. Tras el deploy, manifest.json y service-worker.js deben dar 200 y la PWA instalarse.

P1 — Script de humo (mismo patrón que test_fase11.php), sobre base local:

Jugada que se completa con lun ∪ mar ∪ mié. Cargar en orden lun → mié → mar. Debe ganar, y el ganador debe quedar atribuido al miércoles.
Falta el jueves, se carga el viernes. La semana no debe cerrar y el pozo no debe arrastrar. Cargar el jueves después: debe cerrar liquidando al ganador correcto.
Carga normal en orden lun → mar → mié → jue → vie sin ganador: debe cerrar el viernes con el mismo arrastre que antes del cambio (regresión del replay).
Sábado: cargar turnos 1, 2, 3; borrar el 2; el próximo debe ser el 4.
corregir() que adelanta al ganador: los sorteos posteriores no deben quedar apuntando al ciclo cerrado.
P2 — Reportes: una jugada pendiente_pago con importe cargado no debe aparecer en "Recaudado", y la tarjeta debe coincidir con la suma de las barras por ciclo. corregir() con jugadas en el ciclo programado debe funcionar. Portal de un cliente con jugada de sábado: los 5 turnos en orden, etiquetados.

P3/P4 — Capturas headless (Chrome con --user-data-dir dedicado y Start-Process -Wait, nunca &) a 390px de ancho de: admin/jugadas/nueva.php (una sola barra al pie, último campo visible, pastilla activa azul, Total a Cobrar en #3b82f6), admin/reportes/jugadas.php (header sticky, sin scroll sorpresa), admin/reportes/index.php (barras con el extremo redondeado correcto), auth/cambiar_clave.php (sidebar + topbar Gentelella), vendedor/index.php (sin franja vacía al pie). Más una pasada de grep confirmando cero fw-extrabold huérfano y cero text-primary renderizando #0d6efd.

P5 — php -l sobre los 125 archivos, y un grep de cada clase antes de borrarla para confirmar que sigue en cero usos después de P4.

Orden de commits sugerido
P0 seguridad — schema/seed sin USE, credenciales fuera, .gitignore, .cpanel.yml
P1 cotejo — replay en registrar(), secuencia completa, MAX(turno)+1, huérfanos
P2 bugs visibles — reportes, corregir(), saldo, orden de turnos, premio base
P3 diseño roto — admin_foot.php + reserva de scroll
P4 paleta — fuentes, azules, islas verdes, bolillas, tablas, safe-area, cambiar_clave
P5 limpieza — CSS muerto, inline styles, nav unificada, textos hardcodeados