<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class ContentEntryTranslation extends Model
{
    protected string $table    = 'content_entry_translations';
    protected bool $softDelete = false;
}
