<?php

namespace AuthGateway\Auth\Helper;

class DateTimeHelper
{
    public static function getDateFilterFormat($filter)
    {
        $date = new \DateTime();

        $interval = \DateInterval::createFromDateString($filter);
        
        $date->add($interval);

        return $date->format('Y-m-d H:i:s');
    }   
}
