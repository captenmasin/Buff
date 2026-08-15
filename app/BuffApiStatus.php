<?php

namespace App;

enum BuffApiStatus
{
    case Success;
    case Unauthenticated;
    case EmailNotVerified;
    case Forbidden;
    case ValidationFailed;
    case RateLimited;
    case ConnectionFailed;
    case Failed;
}
