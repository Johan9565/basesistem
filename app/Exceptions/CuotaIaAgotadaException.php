<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CuotaIaAgotadaException extends RuntimeException
{
    public function __construct(
        public readonly ?string $reseteaEl,
        string $message = 'Se agotó la cuota de evaluaciones con IA.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'code' => 'CUOTA_IA_AGOTADA',
            'message' => $this->getMessage(),
            'limite_ia_resetea_el' => $this->reseteaEl,
        ], Response::HTTP_TOO_MANY_REQUESTS);
    }
}
