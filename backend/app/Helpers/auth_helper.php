<?php

if (! function_exists('current_user')) {
    function current_user(?string $key = null, mixed $default = null): mixed
    {
        $user = session('auth_user') ?? [];

        if ($key === null) {
            return $user;
        }

        return $user[$key] ?? $default;
    }
}

if (! function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return (bool) current_user('loggedIn', false);
    }
}

if (! function_exists('has_role')) {
    function has_role(string ...$roles): bool
    {
        return in_array(current_user('role'), $roles, true);
    }
}
