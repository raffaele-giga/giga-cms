<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

class TaxonomyTerm extends Model
{
    protected string $table    = 'taxonomy_terms';
    protected bool $softDelete = false;
}
