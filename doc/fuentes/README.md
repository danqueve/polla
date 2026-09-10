# Fuentes de los manuales

`manual-cliente.html` y `manual-supervisor.html` son el origen de los PDF
que están un nivel arriba, en `doc/`. Son HTML autocontenido (mismos
colores/tipografías que `assets/css/app.css`, pero copiados a mano porque
el PDF no puede depender de un archivo externo del sitio).

Para regenerar un PDF después de editar el HTML:

```bash
brave-browser --headless --disable-gpu --no-sandbox \
  --print-to-pdf="../Manual del Cliente.pdf" \
  --print-to-pdf-no-header --no-pdf-header-footer \
  "file://$(pwd)/manual-cliente.html"
```

(mismo comando para `manual-supervisor.html` → `Manual del Supervisor.pdf`).
Cualquier navegador Chromium/Chrome/Edge headless sirve igual — no hace
falta que sea Brave puntualmente, es lo que había instalado en este
entorno.
