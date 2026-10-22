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
        $this->required('email', 'E-Mail je povinný!')
            ->unique('email', 'users', 'email', null, 'Účet s touto e-mailovou adresou již existuje!')
            ->isEmail('email');

        $this->required('username', 'Uživatelské jméno je povinné!')
            ->unique('username', 'users', 'username', null, 'Uživatel s tímto uživatelským jménem již existuje!')
            ->min('username', 5, 'Uživatelské jméno musí mít alespoň 5 znaků!')
            ->max('username', 20, 'Uživatelské jméno může mít nejvýše 20 znaků!');

        $this->required('password', 'Heslo je povinné!')
            ->min('password', 8, 'Heslo musí mít alespoň 8 znaků!')
            ->match('password', 'password_confirm', 'Hesla se neshodují!');

        $this->required('password_confirm', 'Heslo je povinné!')
            ->min('password_confirm', 8, 'Heslo musí mít alespoň 8 znaků!')
            ->match('password_confirm', 'password', 'Hesla se neshodují!');

        return $this;
    }

    public function isEmail(string $field, string $msg = "Neplatný e-mail"): static
    {
        if (!filter_var($this->getValue($field), FILTER_VALIDATE_EMAIL))
            $this->errors[$field] = $msg ?? "Neplatný e-mail";

        return $this;
    }
}