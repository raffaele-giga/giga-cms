<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class ContentStatus extends Model
{
    protected string $table    = 'content_statuses';
    protected bool $softDelete = false;
}
