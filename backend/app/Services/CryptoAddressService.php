<?php

namespace App\Services;

use kornrunner\Keccak;

/**
 * Real blockchain address derivation using secp256k1 + GMP.
 * Generates deterministic per-user addresses from a master key stored in .env.
 *
 * MASTER_CRYPTO_KEY must be 64 hex chars (32 bytes) — never commit to git.
 * Each user gets a unique address derived as:
 *   HMAC-SHA512(master_key, "esahlan:{coin}:{network}:{user_id}")
 *   → first 32 bytes = child private key → secp256k1 → public key → address
 */
class CryptoAddressService
{
    // ── secp256k1 curve constants ─────────────────────────────────────────────
    private const P  = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F';
    private const GX = '79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798';
    private const GY = '483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8';

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Get or derive a deposit address for a user on a given coin/network.
     * Returns ['address' => '...', 'private_key' => '...'] — private_key is for internal hot-wallet use only.
     */
    public static function deriveAddress(int $userId, string $coinSymbol, string $networkChain): array
    {
        $privHex = self::derivePrivateKey($userId, $coinSymbol, $networkChain);
        $pubHex  = self::privateToUncompressedPublic($privHex);

        $chain = strtoupper($networkChain);
        $address = match(true) {
            str_starts_with($chain, 'TRC') || $chain === 'TRON' => self::toTronAddress($pubHex),
            default => self::toEthAddress($pubHex),
        };

        return ['address' => $address, 'private_key' => $privHex];
    }

    public static function getTronAddress(int $userId): string
    {
        return self::deriveAddress($userId, 'USDT', 'TRC20')['address'];
    }

    public static function getEthAddress(int $userId): string
    {
        return self::deriveAddress($userId, 'USDT', 'ERC20')['address'];
    }

    public static function getBscAddress(int $userId): string
    {
        // BNB Smart Chain uses same address format as ETH
        return self::deriveAddress($userId, 'USDT', 'BEP20')['address'];
    }

    // ── Private key derivation ────────────────────────────────────────────────

    private static function derivePrivateKey(int $userId, string $coin, string $network): string
    {
        $masterHex = config('crypto.master_key', env('MASTER_CRYPTO_KEY', ''));
        if (strlen($masterHex) !== 64) {
            // Fallback: derive from app key — NOT for production, only for dev
            $masterHex = substr(hash('sha256', config('app.key') . 'crypto_master'), 0, 64);
        }

        $masterBytes = hex2bin($masterHex);
        $data        = "esahlan:" . strtoupper($coin) . ":" . strtoupper($network) . ":" . $userId;
        $derived     = hash_hmac('sha512', $data, $masterBytes, true);

        // First 32 bytes = private key (ensure it's in valid range)
        $privHex = bin2hex(substr($derived, 0, 32));

        // Ensure private key is in [1, N-1]
        $N       = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
        $privGmp = gmp_init($privHex, 16);
        if (gmp_cmp($privGmp, 1) < 0 || gmp_cmp($privGmp, gmp_sub($N, gmp_init(1))) > 0) {
            // Re-derive with a tweak
            $privHex = bin2hex(substr(hash_hmac('sha512', $data . ':retry', $masterBytes, true), 0, 32));
        }

        return strtoupper($privHex);
    }

    // ── secp256k1 arithmetic (GMP) ────────────────────────────────────────────

    private static function modInverse(\GMP $a, \GMP $p): \GMP
    {
        // Fermat's little theorem: a^(-1) mod p = a^(p-2) mod p (p is prime)
        return gmp_powm($a, gmp_sub($p, gmp_init(2)), $p);
    }

    private static function pointAdd(\GMP $x1, \GMP $y1, \GMP $x2, \GMP $y2, \GMP $p): array
    {
        $dx  = gmp_mod(gmp_sub($x2, $x1), $p);
        $dy  = gmp_mod(gmp_sub($y2, $y1), $p);
        $lam = gmp_mod(gmp_mul($dy, self::modInverse($dx, $p)), $p);
        $x3  = gmp_mod(gmp_sub(gmp_sub(gmp_pow($lam, 2), $x1), $x2), $p);
        $y3  = gmp_mod(gmp_sub(gmp_mul($lam, gmp_sub($x1, $x3)), $y1), $p);
        return [gmp_mod($x3, $p), gmp_mod($y3, $p)];
    }

    private static function pointDouble(\GMP $x1, \GMP $y1, \GMP $p): array
    {
        $lam = gmp_mod(
            gmp_mul(
                gmp_mul(gmp_init(3), gmp_pow($x1, 2)),
                self::modInverse(gmp_mul(gmp_init(2), $y1), $p)
            ),
            $p
        );
        $x3 = gmp_mod(gmp_sub(gmp_pow($lam, 2), gmp_mul(gmp_init(2), $x1)), $p);
        $y3 = gmp_mod(gmp_sub(gmp_mul($lam, gmp_sub($x1, $x3)), $y1), $p);
        return [gmp_mod($x3, $p), gmp_mod($y3, $p)];
    }

    /**
     * Scalar multiplication: k * G on secp256k1 (double-and-add, LSB first)
     */
    private static function pointMultiply(string $kHex, \GMP $Gx, \GMP $Gy, \GMP $p): array
    {
        $k      = gmp_init($kHex, 16);
        $result = null;
        $addend = [$Gx, $Gy];

        while (gmp_cmp($k, 0) > 0) {
            if (gmp_testbit($k, 0)) {
                $result = ($result === null)
                    ? $addend
                    : self::pointAdd($result[0], $result[1], $addend[0], $addend[1], $p);
            }
            $addend = self::pointDouble($addend[0], $addend[1], $p);
            $k = gmp_div($k, 2);
        }
        return $result;
    }

    // ── Key/address derivation ────────────────────────────────────────────────

    private static function privateToUncompressedPublic(string $privHex): string
    {
        $p  = gmp_init(self::P,  16);
        $Gx = gmp_init(self::GX, 16);
        $Gy = gmp_init(self::GY, 16);

        [$Qx, $Qy] = self::pointMultiply($privHex, $Gx, $Gy, $p);

        $xHex = str_pad(gmp_strval($Qx, 16), 64, '0', STR_PAD_LEFT);
        $yHex = str_pad(gmp_strval($Qy, 16), 64, '0', STR_PAD_LEFT);

        return '04' . $xHex . $yHex;
    }

    /** ETH-style address: keccak256(pubKey[1:])[12:] */
    private static function pubHexToEthRaw(string $pubHex): string
    {
        // Strip 04 prefix → 64 bytes uncompressed key
        $pubBytes = hex2bin(substr($pubHex, 2));
        $hash     = Keccak::hash($pubBytes, 256); // 32 bytes hex
        return substr($hash, -40); // last 20 bytes = address
    }

    private static function toEthAddress(string $pubHex): string
    {
        return '0x' . strtolower(self::pubHexToEthRaw($pubHex));
    }

    /**
     * Tron address: same derivation as ETH but:
     * 1. Prepend 0x41 to the 20-byte address
     * 2. Double SHA256 → take first 4 bytes as checksum
     * 3. Base58 encode (41 + address + checksum)
     */
    private static function toTronAddress(string $pubHex): string
    {
        $rawHex  = self::pubHexToEthRaw($pubHex);
        $payload = hex2bin('41' . $rawHex); // 21 bytes

        $checksum = substr(hash('sha256', hex2bin(hash('sha256', $payload))), 0, 8);
        $fullHex  = bin2hex($payload) . $checksum;

        return self::base58Encode(hex2bin($fullHex));
    }

    private static function base58Encode(string $bytes): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $decimal  = gmp_init(bin2hex($bytes), 16);
        $result   = '';
        while (gmp_cmp($decimal, 0) > 0) {
            [$decimal, $rem] = gmp_div_qr($decimal, 58);
            $result = $alphabet[(int) gmp_strval($rem)] . $result;
        }
        // Add leading '1's for leading zero bytes
        foreach (str_split($bytes) as $byte) {
            if ($byte !== "\x00") break;
            $result = '1' . $result;
        }
        return $result;
    }

    // ── Validation helpers ────────────────────────────────────────────────────

    public static function isValidTronAddress(string $addr): bool
    {
        return preg_match('/^T[A-HJ-NP-Za-km-z1-9]{33}$/', $addr) === 1;
    }

    public static function isValidEthAddress(string $addr): bool
    {
        return preg_match('/^0x[0-9a-fA-F]{40}$/', $addr) === 1;
    }
}
