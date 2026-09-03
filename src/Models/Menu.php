<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class Menu extends Model
{
    protected string $table    = 'menus';
    protected bool $softDelete = false;
}
