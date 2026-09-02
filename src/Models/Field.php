<?php

namespace Giga\Cms\Models;

use Giga\Core\Model;

/**
 * Solo per le letture generiche (find/findAll/count/exists): sono sicure
 * perché non interpolano nomi di colonna. insert()/update() del Model base
 * NON sono usati per questa tabella — `key` è parola riservata SQL e quei
 * metodi non la quotano; FieldRepository scrive query dirette per le
 * scritture (vedi FieldRepository::create()/update()).
 */
class Field extends Model
{
    protected string $table    = 'fields';
    protected bool $softDelete = false;
}
