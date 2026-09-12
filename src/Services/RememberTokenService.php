<?php

namespace Polla\Services;

use DateTimeImmutable;
use PDO;

/**
 * Sesion persistente ("recordarme") del portal del cliente [PWA].
 *
 * Token de un solo uso: cada vez que validarYRotar() acepta una
 * cookie, borra esa fila y emite una nueva -- asi una cookie vieja
 * (por ejemplo, copiada de un dispositivo perdido) deja de servir en
 * cuanto el dueño legitimo vuelve a abrir la app, sin que nadie tenga
 * que hacer nada a mano.
 *
 * Nunca guarda el token en texto plano: la tabla solo tiene el hash
 * (sha256), el token en si vive unicamente en la cookie del navegador.
 *
 * Exclusivo del portal del cliente. auth/login.php no la llama desde
 * la rama de usuarios ni la de vendedores.
 */
class RememberTokenService
{
    private const COOKIE_NAME    = 'remember_token';
    private const DIAS_VIGENCIA  = 30;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function crearDesde(PDO $db): self
    {
        return new self($db);
    }

    /** Emite un token nuevo para este cliente y lo deja en la cookie. */
    public function emitir(int $clienteId): void
    {
        $token  = bin2hex(random_bytes(32));
        $hash   = hash('sha256', $token);
        $expira = new DateTimeImmutable('+' . self::DIAS_VIGENCIA . ' days');

        $this->db->prepare(
            'INSERT INTO remember_tokens (cliente_id, token_hash, expira_en)
             VALUES (:cliente, :hash, :expira)'
        )->execute([
            ':cliente' => $clienteId,
            ':hash'    => $hash,
            ':expira'  => $expira->format('Y-m-d H:i:s'),
        ]);

        $this->setCookie($token, $expira->getTimestamp());
    }

    /**
     * Valida la cookie actual contra la tabla. Si es valida, rota el
     * token (borra esta fila, emite una nueva) y devuelve la fila del
     * cliente para que el llamador abra la sesion
     * (ClienteAuthService::abrirSesion()) -- esta clase no toca
     * $_SESSION, solo cookie + tabla.
     *
     * El hash se borra siempre que se presenta, haya sido valido o
     * no: un token usado una vez no vuelve a servir.
     *
     * @return array{id:int, nro_cliente:string, dni:string, nombre:string, activo:int}|null
     */
    public function validarYRotar(): ?array
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($token === '') {
            return null;
        }

        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'SELECT c.id, c.nro_cliente, c.dni, c.nombre, c.activo
               FROM remember_tokens rt
               JOIN clientes c ON c.id = rt.cliente_id
              WHERE rt.token_hash = :hash AND rt.expira_en > NOW()
              LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        $cliente = $stmt->fetch();

        $this->db->prepare('DELETE FROM remember_tokens WHERE token_hash = :hash')
                 ->execute([':hash' => $hash]);

        if (!$cliente || (int) $cliente['activo'] !== 1) {
            $this->borrarCookie();
            return null;
        }

        $this->emitir((int) $cliente['id']);

        return $cliente;
    }

    /** Logout, o cookie invalida: borra la fila (si existe) y la cookie. */
    public function olvidar(): void
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($token !== '') {
            $this->db->prepare('DELETE FROM remember_tokens WHERE token_hash = :hash')
                     ->execute([':hash' => hash('sha256', $token)]);
        }
        $this->borrarCookie();
    }

    private function setCookie(string $valor, int $expira): void
    {
        setcookie(self::COOKIE_NAME, $valor, [
            'expires'  => $expira,
            'path'     => '/',
            // 'Secure' exige HTTPS: en local (APP_ENV=development, http
            // llano) el navegador jamas mandaria la cookie si estuviera
            // fija en true. Mismo criterio que ya usa la cookie de
            // sesion en config/bootstrap.php.
            'secure'   => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function borrarCookie(): void
    {
        $this->setCookie('', time() - 3600);
    }
}
