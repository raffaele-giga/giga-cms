<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class MenuItem extends Model
{
    protected string $table    = 'menu_items';
    protected bool $softDelete = false;
}
