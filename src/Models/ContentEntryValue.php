<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class ContentEntryValue extends Model
{
    protected string $table    = 'content_entry_values';
    protected bool $softDelete = false;
}
