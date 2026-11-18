<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Http;

class FlowBillingClient
{
    public function account(): string
    {
        return app(FlowReadClient::class)->accountHash();
    }

    public function ready(): bool
    {
        if (! app(FlowReadClient::class)->configured()) {
            return false;
        }
        try {
            $this->assertCanContract();
            $this->callback('payment');

            return true;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return false;
        }
    }

    public function assertCanContract(): void
    {
        abort_unless(app(FlowReadClient::class)->configured(), 422, 'Flow no está configurado.');
        abort_if(config('flow.environment') === 'production' && ! config('flow.production_payments'), 422, 'La contratación en producción aún no está habilitada.');
        abort_if(app()->environment('production') && config('flow.environment') !== 'production', 422, 'No se pueden acreditar pagos sandbox en un despliegue de producción.');
    }

    public function callback(string $path): string
    {
        $base = rtrim((string) config('flow.public_url'), '/');
        $host = parse_url($base, PHP_URL_HOST);
        abort_unless(parse_url($base, PHP_URL_SCHEME) === 'https' && $host && ! filter_var($host, FILTER_VALIDATE_IP)
            && ! str_ends_with($host, '.test') && $host !== 'localhost' && ! parse_url($base, PHP_URL_QUERY) && ! parse_url($base, PHP_URL_USER), 422,
            'Configura FLOW_PUBLIC_URL con la URL HTTPS pública del servidor o túnel de pruebas.');

        return $base.'/api/billing/flow/'.$path;
    }

    public function request(string $method, string $endpoint, array $params = []): array
    {
        $allowed = ['GET' => ['payment/getStatus', 'payment/getStatusByCommerceId', 'customer/get', 'customer/getRegisterStatus', 'customer/getSubscriptions', 'plans/get', 'subscription/get', 'invoice/get'],
            'POST' => ['payment/create', 'customer/create', 'customer/register', 'customer/unRegister', 'plans/create', 'subscription/create', 'subscription/cancel']];
        abort_unless(in_array($endpoint, $allowed[$method] ?? [], true), 500);
        abort_unless(app(FlowReadClient::class)->configured(), 422, 'Flow no está configurado.');
        if ($method === 'POST' && ! in_array($endpoint, ['customer/unRegister', 'subscription/cancel'], true)) {
            $this->assertCanContract();
        }
        $params['apiKey'] = config('flow.api_key');
        ksort($params, SORT_STRING);
        $plain = '';
        foreach ($params as $key => $value) {
            $plain .= $key.$value;
        }
        $params['s'] = hash_hmac('sha256', $plain, config('flow.secret_key'));
        $base = config('flow.environment') === 'production' ? 'https://www.flow.cl/api' : 'https://sandbox.flow.cl/api';
        try {
            $http = Http::connectTimeout(5)->timeout(20)->withoutRedirecting()->acceptJson();
            $response = $method === 'GET' ? $http->get($base.'/'.$endpoint, $params) : $http->asForm()->post($base.'/'.$endpoint, $params);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo confirmar la operación con Flow. Actualiza el estado antes de reintentar; no se repetirá automáticamente.');
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new \RuntimeException('Flow no confirmó la operación. Actualiza el estado o solicita revisión; no se generará otro cobro automáticamente.');
        }

        return $response->json();
    }

    public function redirect(array $response): string
    {
        $url = $response['url'] ?? '';
        $token = $response['token'] ?? '';
        $host = config('flow.environment') === 'production' ? 'www.flow.cl' : 'sandbox.flow.cl';
        if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== $host
            || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_FRAGMENT) || ! is_string($token) || ! preg_match('/^[a-zA-Z0-9_-]{10,200}$/', $token)) {
            throw new \RuntimeException('Flow devolvió un enlace de pago no válido.');
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'token='.rawurlencode($token);
    }
}
