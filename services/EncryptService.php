<?php
class EncryptService {
    private string $key;
    private string $cipher = 'AES-256-CBC';
    public function __construct() {
        $key = getenv('ENCRYPT_KEY');
        if (empty($key) || strlen($key) < 32) throw new RuntimeException('ENCRYPT_KEY no configurada. Minimo 32 caracteres.');
        $this->key = substr(hash('sha256', $key, true), 0, 32);
    }
    public function encrypt(string $value): string {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv);
        $hmac = hash_hmac('sha256', $encrypted, $this->key, true);
        return base64_encode($iv . $hmac . $encrypted);
    }
    public function decrypt(string $payload): string {
        $data = base64_decode($payload);
        $ivLen = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLen);
        $hmac = substr($data, $ivLen, 32);
        $encrypted = substr($data, $ivLen + 32);
        $expected = hash_hmac('sha256', $encrypted, $this->key, true);
        if (!hash_equals($hmac, $expected)) throw new RuntimeException('Error de integridad al desencriptar.');
        return openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
    }
    public function encryptFile(string $sourcePath, string $destPath): void {
        $content = file_get_contents($sourcePath);
        if ($content === false) throw new RuntimeException("No se pudo leer: $sourcePath");
        file_put_contents($destPath, $this->encrypt($content));
    }
    public function decryptFile(string $encryptedPath): string {
        $content = file_get_contents($encryptedPath);
        if ($content === false) throw new RuntimeException("No se pudo leer: $encryptedPath");
        return $this->decrypt($content);
    }
}
