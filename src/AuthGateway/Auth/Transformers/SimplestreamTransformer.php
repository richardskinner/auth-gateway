<?php

namespace AuthGateway\Auth\Transformers;

class SimplestreamTransformer implements TransformerInterface
{
    public static function transform($data)
    {
        return [
            'id' => $data['account_code'],
            'email' => $data['account_email'],
            'first_name' => $data['account_first_name'] ?? null,
            'last_name' => $data['account_first_name'] ?? null,
            'account_code' => $data['account_code'] ?? null,
            'created_at' => $data['account_created'] ?? null,
            'updated_at' => $data['account_updated'] ?? null, //Field doesn't exist but needs to consistent with other transformers
        ];
    }
}