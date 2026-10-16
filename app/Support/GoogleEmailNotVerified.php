<?php

namespace App\Support;

use RuntimeException;

class GoogleEmailNotVerified extends RuntimeException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct("Email Google {$email} belum diverifikasi oleh Google.");
    }
}
