<?php

namespace App\Domain\EndpointProfiles;

enum AuthMode: string
{
    case None = 'none';
    case StaticHeader = 'static_header';
    case Bearer = 'bearer';
    case Basic = 'basic';
    case QueryToken = 'query_token';
}
