<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class Taxonomy extends Model
{
    protected string $table    = 'taxonomies';
    protected bool $softDelete = false;
}
