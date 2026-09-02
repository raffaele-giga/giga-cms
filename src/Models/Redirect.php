<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class Redirect extends Model
{
    protected string $table    = 'redirects';
    protected bool $softDelete = false;
}
