<?php

namespace AuthGateway\Auth\Transformers;

use AuthGateway\Auth\Transformers\Transformer as TransformerInterface;

class Simplestream implements TransformerInterface
{
    public static function transform($data)
    {
        return [
            'id' => $data['account_code'],
            'company_id' => $data['company_id'],
            'email' => $data['account_email'],
            'first_name' => $data['account_first_name'] ?? null,
            'last_name' => $data['account_last_name'] ?? null,
            'account_code' => $data['account_code'] ?? null,
            'created_at' => $data['account_created'] ?? null,
            'updated_at' => $data['account_updated'] ?? null, //Field doesn't exist but needs to consistent with other transformers
        ];
    }
}