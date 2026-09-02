<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

/**
 * softDelete=true: deleted_at è il cestino tecnico, separato dal lifecycle
 * editoriale (status). delete()/restore() del Model base agiscono solo su
 * deleted_at — lo stato editoriale non viene mai toccato da queste operazioni.
 */
class ContentEntry extends Model
{
    protected string $table    = 'content_entries';
    protected bool $softDelete = true;
}
