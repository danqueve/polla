# Integración de Gentelella v4 como tema del Admin de Decena de Oro

## Resumen

Se propone evaluar e integrar el template [Gentelella v4](https://github.com/ColorlibHQ/gentelella) como nueva capa visual del panel administrativo `/admin` de Decena de Oro. El objetivo es pasar del diseño actual (mobile-first sobre Bootstrap 5 con CSS custom) a un layout de dashboard profesional con sidebar, topbar, dark mode y charts — pero **solo para el admin**, sin tocar el portal del cliente ni el módulo vendedor.

---

## Análisis del Estado Actual

### Tu stack actual
| Aspecto | Detalle |
|---------|---------|
| **Backend** | PHP puro + Services (`src/Services/`) — 21 servicios |
| **Frontend** | Bootstrap 5.3.3 (CDN) + Bootstrap Icons + CSS custom ([app.css](file:///c:/wamp64/www/polla/assets/css/app.css) — 1471 líneas) |
| **Layout admin** | [head.php](file:///c:/wamp64/www/polla/includes/head.php) → [topbar.php](file:///c:/wamp64/www/polla/includes/topbar.php) → contenido → [bottom_nav.php](file:///c:/wamp64/www/polla/includes/bottom_nav.php) → [foot.php](file:///c:/wamp64/www/polla/includes/foot.php) |
| **Navegación** | Topbar compacta + barra inferior tipo mobile (5 items + offcanvas "Más") |
| **Diseño** | Mobile-first, tipográfico suizo, paleta verde/oro/papel |
| **Módulos admin** | 13 secciones: tablero, jugadas, sorteos, clientes, ciclos, reportes, solicitudes, liquidaciones, vendedores, referidos, usuarios, configuración, sábados, promociones |
| **JS propio** | 4 archivos en `assets/js/` (copiar, mostrar_clave, numeros, promociones) |
| **Auth** | Sesiones PHP, roles `admin` y `supervisor` |

### Gentelella v4
| Aspecto | Detalle |
|---------|---------|
| **Stack** | Vanilla JS + SCSS + Vite 8 — **NO usa Bootstrap ni jQuery** |
| **Layout** | Sidebar colapsable (rail mode) + topbar con búsqueda + área de contenido |
| **Features** | 58 páginas, dark mode, PWA, ECharts, DataTables, ⌘K palette, inbox, kanban |
| **Licencia** | MIT — libre para uso comercial |
| **Build** | Requiere Node.js + `npm install` + `npm run build` para generar assets estáticos |

---

## Análisis de Viabilidad

### ⚠️ Incompatibilidad Fundamental

> [!CAUTION]
> **Gentelella v4 eliminó Bootstrap completamente.** Tu proyecto depende 100% de Bootstrap 5 (grid, utilidades, dropdowns, offcanvas, botones). Integrar Gentelella v4 tal cual requeriría reescribir el HTML de **todas las ~40+ páginas PHP del admin** para eliminar clases Bootstrap.

### Opciones Evaluadas

| Opción | Esfuerzo | Riesgo | Recomendación |
|--------|----------|--------|---------------|
| **A) Gentelella v4 completo** | 🔴 Muy alto — reescribir ~40 vistas PHP, CSS y JS | 🔴 Rompe todo lo que funciona hoy | ❌ No recomendado |
| **B) Gentelella v2 (legacy)** — la versión con Bootstrap 3/jQuery | 🟡 Medio — migrar a Bootstrap 3 desde 5 | 🟡 Retroceso tecnológico (jQuery, Bootstrap 3) | ❌ No recomendado |
| **C) Extraer el diseño visual de Gentelella** — usar su paleta, layout sidebar y estética pero sobre tu Bootstrap 5 actual | 🟢 Moderado — cambiar layout includes + CSS | 🟢 No rompe lógica PHP ni dependencias | ✅ **Recomendado** |
| **D) Usar un admin template Bootstrap 5 alternativo** (ej: AdminLTE 4, SB Admin 2, Volt) | 🟢 Bajo-Moderado — compatibilidad directa con Bootstrap 5 | 🟢 Mínimo riesgo | ✅ Alternativa recomendada |

---

## User Review Required

> [!IMPORTANT]
> **Decisión clave:** ¿Querés ir con la **Opción C** (tomar la estética de Gentelella y reimplementarla sobre tu Bootstrap 5 actual) o con la **Opción D** (usar un template que ya sea Bootstrap 5 nativo, como AdminLTE 4)?
>
> La Opción C te da el look de Gentelella con menor riesgo. La Opción D es aún más rápida pero cambia el tema visual.

> [!WARNING]
> **Sobre mobile-first:** Tu admin actual está diseñado mobile-first (barra inferior, targets de 48px). Gentelella es un dashboard desktop-first con sidebar. El admin perdería la optimización mobile actual para los operadores que hoy cargan jugadas desde el celular. ¿Los operadores usan más desktop o mobile?

---

## Propuesta Detallada: Opción C — "Gentelella-izar" sobre Bootstrap 5

### Fase 1: Nuevo layout shell (sidebar + topbar)

Lo que se tocaría:

#### [NEW] `includes/admin_head.php`
- Nueva cabecera específica del admin con los CSS de Gentelella adaptados
- Carga Google Fonts (Inter, como usa Gentelella) + Bootstrap 5 + nuevo CSS admin

#### [NEW] `includes/admin_sidebar.php`  
- Sidebar lateral colapsable que reemplaza la bottom_nav en desktop
- Contiene las 13 secciones organizadas en grupos:
  - **Principal:** Tablero
  - **Jugadas:** Jugadas, Cargar, Clientes, Solicitudes
  - **Sorteos:** Sorteos, Ciclos, Sábados
  - **Equipo:** Usuarios, Vendedores, Referidos, Liquidaciones (solo admin)
  - **Negocio:** Reportes, Configuración (solo admin)
- En mobile: drawer lateral que se abre con hamburguesa (en vez de bottom nav)

#### [NEW] `includes/admin_topbar.php`
- Topbar estilo Gentelella: logo a la izquierda, toggle sidebar, breadcrumbs, perfil a la derecha
- Dropdown de usuario con las mismas opciones actuales
- Opcional: badge de notificaciones (solicitudes pendientes)

#### [NEW] `includes/admin_foot.php`
- Cierre con scripts de Bootstrap 5 + JS del sidebar collapse

#### [NEW] `assets/css/admin.css`
- CSS del layout admin inspirado en Gentelella:
  - Variables CSS con la paleta de Gentelella (o tu paleta actual adaptada)
  - Layout de 2 columnas (sidebar + content)
  - Sidebar colapsable con transición
  - Tipografía Inter
  - Cards, métricas, tablas re-estilizadas
  - Dark mode toggle
  - Responsive: sidebar drawer en mobile

### Fase 2: Adaptar las páginas admin existentes

#### [MODIFY] Todas las páginas en `admin/` (~40 archivos PHP)
Cambio mínimo en cada archivo: reemplazar los includes actuales por los nuevos:

```diff
-require __DIR__ . '/../includes/head.php';
-require __DIR__ . '/../includes/topbar.php';
+require __DIR__ . '/../includes/admin_head.php';
+require __DIR__ . '/../includes/admin_topbar.php';
+require __DIR__ . '/../includes/admin_sidebar.php';
```

```diff
-require __DIR__ . '/../includes/bottom_nav.php';
-require __DIR__ . '/../includes/foot.php';
+require __DIR__ . '/../includes/admin_foot.php';
```

El contenido (`<main>`) no se toca — los formularios, tablas y lógica PHP quedan iguales.

### Fase 3: Componentes visuales mejorados

#### Dashboard con charts (ECharts)
- Agregar gráficos al [tablero](file:///c:/wamp64/www/polla/admin/index.php):
  - Línea de jugadas por día del ciclo
  - Barras de recaudación por ciclo (últimos 4)
  - Donut de distribución por vendedor

#### Tablas mejoradas
- Aplicar estilo Gentelella a las tablas de jugadas, sorteos, clientes, etc.
- Opcional: DataTables para búsqueda/ordenamiento (ya funciona con Bootstrap 5)

#### Cards de métricas
- Re-estilizar las métricas del tablero (jugadas, sorteos, recaudado) con el estilo tile de Gentelella

### Fase 4: Dark mode (opcional)

- Toggle en el topbar
- CSS variables para ambos temas
- Persistencia en `localStorage`

---

## Lo que NO se toca

- ❌ **Portal del cliente** (`/portal/`) — mantiene su diseño actual
- ❌ **Módulo vendedor** (`/vendedor/`) — mantiene su diseño actual
- ❌ **Auth pages** (`/auth/`) — login, registro, etc.
- ❌ **Lógica PHP** — ningún Service, controller ni query cambia
- ❌ **Base de datos** — cero migraciones
- ❌ **`app.css`** original — se preserva intacto para portal/vendedor

---

## Open Questions

> [!IMPORTANT]
> 1. **¿Los operadores (supervisores) usan el admin desde el celular o desde desktop?** Si mayormente celular, la bottom_nav actual es mejor que un sidebar. Podríamos hacer un híbrido: sidebar en desktop, bottom_nav en mobile.

> [!IMPORTANT]  
> 2. **¿Querés mantener tu paleta actual (verde/oro/papel) o adoptar la paleta de Gentelella (azul/gris)?** Yo sugeriría mantener la paleta de Decena de Oro pero con el layout de Gentelella.

> [!IMPORTANT]
> 3. **¿Preferís la Opción C (reimplementar estética Gentelella sobre Bootstrap 5) o la Opción D (usar un template Bootstrap 5 como AdminLTE 4 que ya viene listo)?**

> [!IMPORTANT]
> 4. **¿Querés hacer esto primero como prueba de concepto en 1-2 páginas (ej: tablero + jugadas) para ver cómo queda antes de migrar todo?**

---

## Estimación de Esfuerzo (Opción C)

| Fase | Alcance | Estimación |
|------|---------|------------|
| Fase 1 — Layout shell | 4 nuevos includes + 1 CSS nuevo | ~4-6 horas |
| Fase 2 — Migrar páginas | ~40 archivos, cambio mecánico de includes | ~2-3 horas |
| Fase 3 — Charts y componentes | ECharts + re-estilo visual | ~3-4 horas |
| Fase 4 — Dark mode | CSS variables + toggle | ~2 horas |
| **Total** | | **~11-15 horas** |

---

## Verification Plan

### Prueba de Concepto (recomendado primero)
1. Crear los 4 nuevos includes + CSS admin
2. Migrar solo `admin/index.php` (tablero) como prueba
3. Verificar visualmente en desktop y mobile
4. Si se aprueba, migrar el resto

### Verificación Funcional
- Login/logout funciona igual
- Todas las secciones admin son accesibles desde el sidebar
- Formularios de jugadas, sorteos, clientes siguen funcionando
- Permisos admin vs supervisor se respetan en el sidebar
- Portal y vendedor no se ven afectados
- Responsive: sidebar colapsa correctamente en mobile

### Verificación Visual
- Screenshot comparativo antes/después del tablero
- Probar en Chrome, Firefox, Safari mobile
- Verificar dark mode si se implementa
