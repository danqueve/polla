<?php
/**
 * Compatibilidad con enlaces/formularios anteriores.
 *
 * Las jugadas ya no se eliminan físicamente: se anulan para conservar la
 * trazabilidad y aplicar las mismas reglas de negocio que el nuevo handler.
 */
require __DIR__ . '/anular.php';
