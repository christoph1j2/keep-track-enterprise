<?php

namespace Src\Validators;

use Src\Validators\BaseValidator;

class RegisterValidator extends BaseValidator
{

    /**
     * @inheritDoc
     */
    public function validate(): static
    {
        $this->required('email', 'E-Mail is missing!')
            ->unique('email', 'users', 'email', null, 'An account with this E-Mail address already exists!')
            ->isEmail('email');

        $this->required('username', 'Username is missing!')
            ->unique('username', 'users', 'username', null, 'A user with this username already exists!')
            ->min('username', 5, 'Username must be at least 5 characters long!')
            ->max('username', 20, 'Username must be at most 20 characters long!');

        $this->required('password', 'Password is missing!')
            ->min('password', 8, 'Password must be at least 8 characters long!')
            ->match('password', 'password_confirm', 'Passwords do not match!');

        return $this;
    }

    public function isEmail(string $field, string $msg = "Invalid E-Mail"): static
    {
        if (!filter_var($this->getValue($field), FILTER_VALIDATE_EMAIL))
            $this->errors[$field] = $msg ?? "Invalid E-Mail";

        return $this;
    }
}