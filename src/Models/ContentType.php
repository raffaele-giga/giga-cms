<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class ContentType extends Model
{
    protected string $table    = 'content_types';
    protected bool $softDelete = false;
}
