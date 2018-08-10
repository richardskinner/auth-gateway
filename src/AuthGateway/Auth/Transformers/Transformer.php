<?php

namespace AuthGateway\Auth\Transformers;

interface Transformer
{
    public static function transform($data);
}