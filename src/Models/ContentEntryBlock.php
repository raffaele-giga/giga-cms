<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class ContentEntryBlock extends Model
{
    protected string $table    = 'content_entry_blocks';
    protected bool $softDelete = false;
}
