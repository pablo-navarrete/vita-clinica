<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;

class LoginMotivation
{
    public function next(Session $session, string $tenant): array
    {
        $phrases = config('login_motivation');
        $key = 'login_motivation.previous.' . hash('sha256', $tenant);
        $previous = $session->get($key);
        $choices = array_keys($phrases);
        if (count($choices) > 1) {
            $choices = array_values(array_filter($choices, fn ($index) => $index !== $previous));
        }
        $selected = $choices[random_int(0, count($choices) - 1)];
        $session->put($key, $selected);

        return $phrases[$selected];
    }
}
