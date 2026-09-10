<?php

namespace Polla\Services;

use DateTimeImmutable;
use PDO;
use Polla\Support\ValidacionException;

/**
 * Gate de horario para cargar jugadas.
 *
 * El juego semanal (lunes a viernes) sortea a la noche, asi que la carga
 * se corta a la tarde (parametro horario_limite_semanal, default 18:00).
 * El juego de sabados sortea sus 5 turnos durante el dia, asi que la
 * carga se corta a la manana (horario_limite_sabado, default 11:00).
 * Sabados y domingos no tienen limite para el juego semanal, y de
 * domingo a viernes no hay limite para el juego de sabados: en los dos
 * casos el unico corte real es el del dia que le toca a cada juego.
 *
 * El parametro opcional $ahora en cada metodo existe para poder probar
 * el gate sin tocar el reloj del sistema.
 */
class HorarioCargaService
{
    private ParametroService $parametros;

    public function __construct(ParametroService $parametros)
    {
        $this->parametros = $parametros;
    }

    public static function crearDesde(PDO $db): self
    {
        return new self(new ParametroService($db));
    }

    /** true si en este momento se puede cargar jugadas de ese tipo de juego. */
    public function abierto(string $tipoJuego, ?DateTimeImmutable $ahora = null): bool
    {
        $ahora   = $ahora ?? new DateTimeImmutable('now');
        $diaIso  = (int) $ahora->format('N'); // 1 = lunes ... 7 = domingo
        $minutos = self::minutosDelDia($ahora);

        if ($tipoJuego === CicloService::TIPO_SABADO) {
            // Solo el sabado tiene corte; domingo a viernes, sin limite.
            return $diaIso !== 6 || $minutos <= self::minutosDeHora($this->parametros->horarioLimiteSabado());
        }

        // Semanal: corte de lunes a viernes; sabado y domingo, sin limite.
        return $diaIso > 5 || $minutos <= self::minutosDeHora($this->parametros->horarioLimiteSemanal());
    }

    /** Mensaje para mostrarle al usuario, o null si esta dentro de horario. */
    public function motivoCerrado(string $tipoJuego, ?DateTimeImmutable $ahora = null): ?string
    {
        if ($this->abierto($tipoJuego, $ahora)) {
            return null;
        }

        $limite = $tipoJuego === CicloService::TIPO_SABADO
            ? $this->parametros->horarioLimiteSabado()
            : $this->parametros->horarioLimiteSemanal();

        $juego = $tipoJuego === CicloService::TIPO_SABADO ? 'de sábados' : 'de esta semana';

        return 'El horario para cargar jugadas ' . $juego . ' cerró a las ' . $limite
             . '. Podés volver a cargar mañana a partir de las 00:00.';
    }

    /**
     * Gate autoritativo: lo llaman los Services de escritura antes de
     * tocar la base, nunca solo el controlador HTTP.
     *
     * @throws ValidacionException
     */
    public function exigirAbierto(string $tipoJuego, ?DateTimeImmutable $ahora = null): void
    {
        $motivo = $this->motivoCerrado($tipoJuego, $ahora);
        if ($motivo !== null) {
            throw ValidacionException::de($motivo);
        }
    }

    private static function minutosDelDia(DateTimeImmutable $momento): int
    {
        return ((int) $momento->format('H')) * 60 + (int) $momento->format('i');
    }

    /** 'HH:MM' -> minutos desde las 00:00. */
    private static function minutosDeHora(string $horaMinuto): int
    {
        [$horas, $minutos] = array_map('intval', explode(':', $horaMinuto));
        return $horas * 60 + $minutos;
    }
}
