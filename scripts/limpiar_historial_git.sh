#!/usr/bin/env bash
# ============================================================
# P5: Limpieza de datos sensibles del historial de git
#
# El código ACTUAL ya está limpio (los commits P0 se encargaron).
# Pero en el HISTORIAL de commits viejos quedaron inserts con
# nombres, DNIs, teléfonos y hashes de clientes demo dentro de
# demo/datos_demo.sql.
#
# Este script usa git-filter-repo (pip install git-filter-repo)
# para reescribir esos commits y eliminar las líneas sensibles.
#
# IMPORTANTE:
# - Hacé un backup del repo antes de correr esto.
# - Después de correr, hay que hacer `git push --force --all`.
# - Todos los colaboradores (si hay) tienen que re-clonar.
# - Corré desde la raíz del repo clonado fresco (no un fork).
# ============================================================

set -euo pipefail

# 1. Verificar que git-filter-repo esté instalado
if ! command -v git-filter-repo &>/dev/null; then
    echo "❌ Necesitás git-filter-repo. Instalalo con:"
    echo "   pip install git-filter-repo"
    exit 1
fi

# 2. Verificar que estamos en la raíz del repo
if [ ! -f "especificacion-decena-de-oro.md" ]; then
    echo "❌ Corré este script desde la raíz del repo (donde está especificacion-decena-de-oro.md)"
    exit 1
fi

echo "🔍 Esto va a reescribir el historial de git para eliminar datos"
echo "   sensibles de demo/datos_demo.sql en commits viejos."
echo ""
read -rp "¿Continuar? (s/N) " resp
if [[ "$resp" != "s" && "$resp" != "S" ]]; then
    echo "Cancelado."
    exit 0
fi

# 3. Opción A: eliminar demo/datos_demo.sql del historial completo
# (el archivo actual se conserva si está limpio en HEAD)
echo ""
echo "⚙️  Reescribiendo historial..."
git filter-repo --invert-paths --path demo/datos_demo.sql --force

echo ""
echo "✅ Historial reescrito. Ahora:"
echo "   1. git push --force --all"
echo "   2. git push --force --tags"
echo "   3. Si alguien más tiene el repo clonado, que re-clone."
echo ""
echo "Si querés mantener demo/datos_demo.sql en HEAD (con datos"
echo "ficticios limpios), volvé a agregarlo y commitealo:"
echo "   git add demo/datos_demo.sql"
echo "   git commit -m 'Re-agrega datos demo limpios (sin datos reales)'"
