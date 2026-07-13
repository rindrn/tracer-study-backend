<?php
namespace App\Exceptions;
 
use Exception;
 
class BusinessException extends Exception
{
    /**
     * Data tambahan yang perlu ikut masuk ke body JSON response (mis.
     * { error: 'role_already_mapped', conflicting_mapping: {...} }),
     * di luar { success, message } standar. Default kosong -- backward
     * compatible dengan seluruh pemakaian BusinessException yang sudah
     * ada (bootstrap/app.php merge payload ini ke response, array kosong
     * berarti tidak ada perubahan body dari sebelumnya).
     */
    protected array $payload;

    public function __construct(string $message, int $code = 400, array $payload = [])
    {
        parent::__construct($message, $code);
        $this->payload = $payload;
    }

    public static function withPayload(string $message, int $code, array $payload): self
    {
        return new self($message, $code, $payload);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
