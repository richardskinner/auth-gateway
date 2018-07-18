<?php

namespace AuthGateway\Auth\Transformers;

class Auth0Transformer implements TransformerInterface
{
    public static function transform($data)
    {
        return [
            'id' => $data['user_id'],
            'company_id' => $data['company_id'],
            'email' => $data['email'],
            'first_name' => $data['user_metadata']['first_name'] ?? null,
            'last_name' => $data['user_metadata']['last_name'] ?? null,
            'account_code' => $data['user_metadata']['recurly']['account_code'] ?? null,
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }
}