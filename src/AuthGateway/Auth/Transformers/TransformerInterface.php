<?php

namespace AuthGateway\Auth\Transformers;


interface TransformerInterface
{
    public static function transform($data);
}