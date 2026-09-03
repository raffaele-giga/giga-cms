<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class BlockType extends Model
{
    protected string $table    = 'block_types';
    protected bool $softDelete = false;
}
