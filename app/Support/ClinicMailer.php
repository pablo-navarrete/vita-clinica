<?php

namespace App\Support;

use App\Models\ClinicMailSetting;
use Illuminate\Support\Facades\App;
use RuntimeException;

class ClinicMailer
{
    public function configured(int $customerId): bool
    {
        $settings = ClinicMailSetting::query()->where('customer_id', $customerId)->first();

        return (bool) ($settings
            && $settings->enabled
            && filled($settings->from_email)
            && filled($settings->host)
            && filled($settings->port)
            && filled($settings->encryption)
            && filled($settings->username)
            && filled($settings->password));
    }

    public function send(int $customerId, callable $callback): mixed
    {
        $settings = ClinicMailSetting::query()
            ->where('customer_id', $customerId)
            ->where('enabled', true)
            ->first();

        if (!$settings || !$this->configured($customerId)) {
            throw new RuntimeException('La clínica no tiene un correo saliente configurado.');
        }

        $mailerName = 'clinic_' . $customerId;
        $encryption = $settings->encryption === 'none' ? null : $settings->encryption;
        config([
            'mail.mailers.' . $mailerName => [
                'transport' => 'smtp',
                'host' => $settings->host,
                'port' => (int) $settings->port,
                'encryption' => $encryption,
                'scheme' => $settings->encryption === 'ssl' ? 'smtps' : 'smtp',
                'username' => $settings->username,
                'password' => $settings->password,
                'timeout' => 15,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
        ]);

        $manager = App::make('mail.manager');
        $manager->purge($mailerName);

        try {
            $mailer = $manager->mailer($mailerName);
            $mailer->alwaysFrom($settings->from_email, $settings->from_name ?: config('app.name'));

            return $callback($mailer, $settings);
        } finally {
            $manager->purge($mailerName);
        }
    }
}
