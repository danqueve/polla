<?php
/**
 * Tarjeta de una jugada, tal como la ve el cliente.
 *
 * La usan igual "Mis jugadas" y el "Historial", asi que una semana
 * vieja y la actual se leen exactamente igual.
 *
 * Espera definidas:
 *   $jugada       fila de PortalService::jugadasDelCiclo()
 *   $evaluacion   salida de PortalService::evaluar()
 *   $cicloAbierto bool
 *   $totalSorteos cantidad de sorteos cargados en el ciclo
 *   $sorteos      salida de PortalService::sorteosDelCiclo() -- el
 *                 MISMO array que se le paso a evaluar() para armar
 *                 $evaluacion, en el mismo orden: $sorteos[$i] y
 *                 $evaluacion['porSorteo'][$i] son el mismo sorteo por
 *                 indice, asi que no hace falta cruzar por fecha para
 *                 mostrar el extracto completo de cada fila.
 *
 * Los numeros se marcan contra UN sorteo (el mejor), nunca contra la
 * union de la semana: ganar exige los 10 en un mismo sorteo.
 */
$gano   = $jugada['monto_premio'] !== null;
$mejor  = $evaluacion['mejor'];
$mejorN = $evaluacion['mejorAciertos'];
$total  = count($jugada['numeros']);

// Contra que sorteo se pintan las bolillas. Si gano, contra el sorteo
// que la hizo ganar; si no, contra el que mejor le fue.
$marcados = $mejor['acertados'] ?? [];
?>
<article class="tarjeta tarjeta--realce mb-3 overflow-hidden">

    <?php if ($gano): ?>

        <div class="estado-jugada estado-jugada--gano">
            <span class="estado-jugada__icono"><i class="bi bi-trophy-fill"></i></span>
            <div class="min-w-0">
                <p class="estado-jugada__titulo">¡Ganaste!</p>
                <p class="estado-jugada__detalle">
                    Con el sorteo del <?= e(formatFechaDia($jugada['fecha_premio'])) ?>
                </p>
            </div>
        </div>
        <div class="px-3 pt-3 pb-1 text-center" style="background:var(--verde-oscuro)">
            <div class="pozo__rotulo mb-1">Te tocó</div>
            <div class="premio-monto"><?= e(formatPesos($jugada['monto_premio'])) ?></div>
            <p class="mt-2 mb-3" style="color:rgba(255,255,255,.72);font-size:.8125rem">
                <a href="<?= e(whatsappUrl()) ?>" target="_blank" rel="noopener"
                   style="color:inherit;text-decoration:underline">
                    Hablá con Decena de Oro
                </a>
                para cobrarlo.
            </p>
        </div>

    <?php elseif ($totalSorteos === 0): ?>

        <div class="estado-jugada estado-jugada--jugando">
            <span class="estado-jugada__icono"><i class="bi bi-hourglass-split"></i></span>
            <div class="min-w-0">
                <p class="estado-jugada__titulo">Jugada cargada</p>
                <p class="estado-jugada__detalle">
                    Todavía no salió ningún sorteo de este ciclo
                </p>
            </div>
        </div>

    <?php elseif ($cicloAbierto): ?>

        <div class="estado-jugada estado-jugada--jugando">
            <span class="estado-jugada__icono"><i class="bi bi-hourglass-split"></i></span>
            <div class="min-w-0">
                <p class="estado-jugada__titulo">Todavía jugando</p>
                <p class="estado-jugada__detalle">
                    <?php $faltan = $total - $mejorN; ?>
                    En tu mejor sorteo te
                    <?= $faltan === 1 ? 'faltó 1 número' : 'faltaron ' . $faltan . ' números' ?>
                </p>
            </div>
        </div>

    <?php else: ?>

        <div class="estado-jugada estado-jugada--cerrada">
            <span class="estado-jugada__icono"><i class="bi bi-dash-lg"></i></span>
            <div class="min-w-0">
                <p class="estado-jugada__titulo">Sin premio</p>
                <p class="estado-jugada__detalle">
                    Tu mejor sorteo fue de <?= $mejorN ?> de <?= $total ?>
                </p>
            </div>
        </div>

    <?php endif; ?>

    <div class="p-3">

        <?php if ($totalSorteos > 0 && $mejor): ?>
            <p class="rotulo mb-2">
                <?= $gano ? 'Tus números en ese sorteo' : 'Tus números en el sorteo del ' . e(nombreDia($mejor['fecha'])) ?>
            </p>
        <?php else: ?>
            <p class="rotulo mb-2">Tus <?= $total ?> números</p>
        <?php endif; ?>

        <div class="bolillas-cliente">
            <?php foreach ($jugada['numeros'] as $numero): ?>
                <?php $salio = isset($marcados[$numero]); ?>
                <div class="bolilla-cli <?= $salio ? 'bolilla-cli--salio' : '' ?>">
                    <?= e(num2($numero)) ?>
                    <span class="visually-hidden"><?= $salio ? '(salió)' : '(no salió)' ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalSorteos > 0): ?>
            <hr class="my-3">
            <p class="rotulo mb-2">Cómo te fue en cada sorteo</p>
            <p class="form-text mt-0 mb-2">Tocá un sorteo para ver el extracto completo.</p>

            <?php foreach ($evaluacion['porSorteo'] as $idx => $entrada): ?>
                <?php
                $esMejor        = $mejor && $entrada['fecha'] === $mejor['fecha'] && $mejorN > 0;
                $numerosSorteo  = $sorteos[$idx]['numeros'] ?? [];
                $hayRepetidos   = count($numerosSorteo) !== count(array_unique($numerosSorteo));
                $idCollapse     = 'sorteo-' . (int) $jugada['id'] . '-' . $idx;
                ?>
                <button type="button"
                        class="reng-sorteo reng-sorteo--clic <?= $esMejor ? 'reng-sorteo--mejor' : '' ?> collapsed"
                        data-bs-toggle="collapse" data-bs-target="#<?= e($idCollapse) ?>"
                        aria-expanded="false" aria-controls="<?= e($idCollapse) ?>">
                    <span><?= e(formatFechaDia($entrada['fecha'])) ?></span>
                    <span class="reng-sorteo__conteo">
                        <?= $entrada['aciertos'] ?> de <?= $total ?>
                        <?php if ($esMejor): ?>
                            <i class="bi bi-star-fill ms-1" aria-label="tu mejor sorteo"></i>
                        <?php endif; ?>
                        <i class="bi bi-chevron-down ms-1 reng-sorteo__flecha" aria-hidden="true"></i>
                    </span>
                </button>
                <div class="collapse" id="<?= e($idCollapse) ?>">
                    <div class="pt-2 pb-1 px-1">
                        <p class="fila__meta mb-2">Números que salieron ese día:</p>
                        <div class="bolillas mb-2">
                            <?php foreach ($numerosSorteo as $numero): ?>
                                <span class="bolilla <?= isset($entrada['acertados'][$numero]) ? 'bolilla--acertada' : '' ?>">
                                    <?= e(num2($numero)) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($hayRepetidos): ?>
                            <p class="fila__meta mb-0">
                                <i class="bi bi-info-circle"></i>
                                Un número repetido en el extracto cuenta una sola vez.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$gano): ?>
                <p class="fila__meta mt-3 mb-0">
                    Para ganar, tus <?= $total ?> números tienen que salir todos
                    en un mismo sorteo.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</article>
