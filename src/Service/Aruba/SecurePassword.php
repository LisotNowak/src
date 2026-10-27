<?php
/**
 * Lecture d'un mot de passe chiffré par PowerShell :
 *     ConvertTo-SecureString ... | ConvertFrom-SecureString -Key $Key | Out-File Aruba.txt
 * avec la clé AES enregistrée par `$Key | Out-File Aruba.AES.key` (un octet par ligne).
 *
 * 1) Déchiffrement direct en PHP (extension openssl) ;
 * 2) à défaut, sous Windows, on demande à PowerShell de le faire, comme vos scripts.
 *
 * write() produit les mêmes fichiers (bin/console aruba:password) : ils restent lisibles par
 * ConvertTo-SecureString -Key dans les scripts PowerShell.
 */
declare(strict_types=1);

namespace App\Service\Aruba;

class SecurePassword
{
    private const HEADER = '76492d1116743f0423413b16050a5345';

    private static function readText(string $path): string
    {
        $raw = (string) file_get_contents($path);
        // Out-File de Windows PowerShell 5 écrit en UTF-16 avec BOM, PowerShell 7 en UTF-8
        if (str_starts_with($raw, "\xFF\xFE")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return substr($raw, 3);
        }
        if (substr_count($raw, "\0") > intdiv(strlen($raw), 4)) {
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        }
        return $raw;
    }

    private static function readKey(string $path): string
    {
        $nums = array_filter(preg_split('/\s+/', self::readText($path)), 'ctype_digit');
        if (!in_array(count($nums), [16, 24, 32], true)) {
            throw new \RuntimeException(sprintf('Clé AES invalide (%d octets) dans %s', count($nums), $path));
        }
        return implode('', array_map(fn($n) => chr((int) $n), $nums));
    }

    private static function decryptPhp(string $enc, string $key): string
    {
        $enc = trim($enc);
        if (!str_starts_with($enc, self::HEADER)) {
            throw new \RuntimeException('format SecureString inattendu');
        }
        $inner = mb_convert_encoding(base64_decode(substr($enc, strlen(self::HEADER))), 'UTF-8', 'UTF-16LE');
        [, $iv, $cipherHex] = explode('|', $inner);
        $plain = openssl_decrypt((string) hex2bin($cipherHex), 'aes-' . (strlen($key) * 8) . '-cbc', $key,
            OPENSSL_RAW_DATA, base64_decode($iv));
        if ($plain === false) {
            throw new \RuntimeException('déchiffrement AES impossible (clé incorrecte ?)');
        }
        return mb_convert_encoding($plain, 'UTF-8', 'UTF-16LE');
    }

    private static function decryptPowershell(string $passwordFile, string $keyFile): string
    {
        $ps = '$k = Get-Content -LiteralPath $env:ARUBA_KEYFILE; '
            . '$s = Get-Content -LiteralPath $env:ARUBA_PWDFILE | ConvertTo-SecureString -Key $k; '
            . '[Runtime.InteropServices.Marshal]::PtrToStringBSTR('
            . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))';
        $env = getenv() + ['ARUBA_KEYFILE' => $keyFile, 'ARUBA_PWDFILE' => $passwordFile];
        foreach (['pwsh', 'powershell'] as $exe) {
            $p = @proc_open([$exe, '-NoProfile', '-NonInteractive', '-Command', $ps],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
            if (!is_resource($p)) {
                continue;
            }
            $out = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($p) === 0 && trim((string) $out) !== '') {
                return rtrim((string) $out, "\r\n");
            }
        }
        throw new \RuntimeException("PowerShell n'a pas pu déchiffrer le mot de passe");
    }

    /** Chiffre $password comme ConvertFrom-SecureString -Key ; la clé AES-256 est créée si elle n'existe pas. */
    public static function write(string $password, string $passwordFile, string $keyFile): void
    {
        if (!is_file($keyFile)) {
            self::writeFile($keyFile, implode("\r\n", array_map('ord', str_split(random_bytes(32)))) . "\r\n");
        }
        $key = self::readKey($keyFile);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt(mb_convert_encoding($password, 'UTF-16LE', 'UTF-8'),
            'aes-' . (strlen($key) * 8) . '-cbc', $key, OPENSSL_RAW_DATA, $iv);
        $inner = '2|' . base64_encode($iv) . '|' . bin2hex((string) $cipher);
        self::writeFile($passwordFile, self::HEADER . base64_encode(mb_convert_encoding($inner, 'UTF-16LE', 'UTF-8')) . "\r\n");
    }

    private static function writeFile(string $path, string $content): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Écriture impossible : {$path}");
        }
        @chmod($path, 0600);
    }

    public static function read(string $passwordFile, string $keyFile): string
    {
        foreach ([$passwordFile, $keyFile] as $p) {
            if (!is_file($p)) {
                throw new \RuntimeException("Fichier introuvable : {$p}");
            }
        }
        $errors = [];
        try {
            $enc = preg_split('/\R/', trim(self::readText($passwordFile)))[0];
            return self::decryptPhp($enc, self::readKey($keyFile));
        } catch (\Throwable $e) {
            $errors[] = 'php: ' . $e->getMessage();
        }
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                return self::decryptPowershell($passwordFile, $keyFile);
            } catch (\Throwable $e) {
                $errors[] = 'powershell: ' . $e->getMessage();
            }
        }
        throw new \RuntimeException('Impossible de lire le mot de passe chiffré (' . implode(' ; ', $errors) . ')');
    }
}
