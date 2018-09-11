<?php

namespace AuthGateway\Auth\Transformers;

use AuthGateway\Auth\Transformers\Transformer as TransformerInterface;

class Auth0 implements TransformerInterface
{
    public static function transform($data)
    {
        return [
            'id' => $data['user_id'],
            'email' => $data['email'],
            'first_name' => $data['first_name'] ? $data['first_name'] : null,
            'last_name' => $data['last_name'] ? $data['last_name'] : null,
            'account_code' => $data['user_metadata']['recurly']['account_code'] ? $data['user_metadata']['recurly']['account_code'] : null,
            'created_at' => isset($data['created_at']) ? new \DateTime($data['created_at']) : null,
            'updated_at' => isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null, //Field doesn't exist but needs to consistent with other transformers
        ];
    }
}