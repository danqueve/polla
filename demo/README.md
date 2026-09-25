# Datos de muestra

Un juego de datos inventados para mostrar el sistema andando, sin usar
información real de clientes.

## Qué incluye

- **30 clientes**: 20 de alta manual, 5 autorregistrados ya aprobados,
  y **5 autorregistrados pendientes** de aprobar (para mostrar esa cola).
- **4 ciclos**: 3 semanas cerradas + la actual, abierta.
  - Semana 1: sin ganador, el pozo arrastra a la siguiente.
  - Semana 2: **con ganador** a mitad de semana — el pozo real no
    llegaba al piso garantizado, así que se ve el subsidio en acción.
  - Semana 3: sin ganador, incluye un paquete de 4 jugadas con una
    **promoción activa** aplicada.
  - Semana 4 (actual): abierta y sin sorteos cargados todavía, para
    poder cargar uno en vivo durante la muestra y ver el cotejo.
- Una promoción de paquete activa (4 jugadas por $7.000).

## Opción 1: cargar el resultado ya generado

Ninguno de los tres archivos trae `CREATE DATABASE` ni `USE` — la base
la elige siempre la línea de comandos, igual que en el README
principal. Usá el nombre real de tu entorno (`c2881399_polla` en VPS y
local hoy):

```sh
mysql -u root -p c2881399_polla < db/schema.sql
mysql -u root -p c2881399_polla < db/seed.sql
mysql -u root -p c2881399_polla < demo/datos_demo.sql
```

`demo/datos_demo.sql` no incluye la tabla `usuarios` (no se versiona
ningún hash de contraseña, ni siquiera de prueba). Después de cargarlo,
fijá tu propia clave de admin:

```sh
php -r "echo password_hash('tu-clave', PASSWORD_DEFAULT), PHP_EOL;"
```

```sql
UPDATE usuarios SET password_hash = '<hash generado>' WHERE usuario = 'admin';
```

## Opción 2: generar una tanda nueva

```sh
mysql -u root -p < db/schema.sql
mysql -u root -p < db/seed.sql
php demo/simulacion_demo.php
```

Arma nombres, DNIs y números de jugada distintos cada vez que corre
(por eso no es idéntico al dump versionado), y sí deja la clave del
admin en `demo2026` al final — conviene solo para uso local, no correr
contra una base con datos reales.

El script espera una base recién instalada (`schema.sql` + `seed.sql`,
sin clientes ni jugadas todavía) y, a diferencia de los scripts
`db/*.sql`, no resetea ni limpia nada al terminar: los datos quedan
para mostrar.
