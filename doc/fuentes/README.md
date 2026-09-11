# Fuentes de los manuales

`manual-cliente.html`, `manual-supervisor.html` y `manual-equipo.html` son
el origen de los PDF que están un nivel arriba, en `doc/`. Son HTML
autocontenido (mismos colores/tipografías que `assets/css/app.css`, pero
copiados a mano porque el PDF no puede depender de un archivo externo del
sitio).

- `manual-cliente.html` → `Manual del Cliente.pdf` — para el jugador.
- `manual-supervisor.html` → `Manual del Supervisor.pdf` — guía rápida del
  panel de carga (admin + supervisor), desactualizado desde la Fase 9 en
  adelante — se mantiene por compatibilidad, pero `manual-equipo.html` es
  el que está al día.
- `manual-equipo.html` → `Manual del Equipo.pdf` — el manual completo para
  administrador, supervisor y vendedor: cada función del sistema, permisos
  por rol, y una explicación del modelo de negocio (reparto pozo/gastos,
  premio base, juego de sábados, comisiones de referidos).

Para regenerar un PDF después de editar el HTML:

```bash
brave-browser --headless --disable-gpu --no-sandbox \
  --print-to-pdf="../Manual del Cliente.pdf" \
  --print-to-pdf-no-header --no-pdf-header-footer \
  "file://$(pwd)/manual-cliente.html"
```

(mismo comando para `manual-supervisor.html` → `Manual del Supervisor.pdf`,
o `manual-equipo.html` → `Manual del Equipo.pdf`). Cualquier navegador
Chromium/Chrome/Edge headless sirve igual — no hace falta que sea Brave
puntualmente, es lo que había instalado en este entorno.

En Windows, con Chrome ya instalado pero corriendo (perfil bloqueado), hace
falta un `--user-data-dir` propio y `--headless=new`:

```powershell
& "C:\Program Files\Google\Chrome\Application\chrome.exe" `
  --headless=new --disable-gpu --no-sandbox `
  --user-data-dir="$env:TEMP\chrome-pdf-profile" `
  --print-to-pdf="..\Manual del Equipo.pdf" `
  --print-to-pdf-no-header --no-pdf-header-footer `
  "file:///C:/wamp64/www/polla/doc/fuentes/manual-equipo.html"
```

No agregar `--virtual-time-budget`: en documentos grandes (varias páginas,
como `manual-equipo.html`) corta el renderizado antes de tiempo y Chrome
falla con "Multiple targets are not supported in headless mode". Sin esa
flag, Chrome espera a que termine de cargar solo y genera bien.
