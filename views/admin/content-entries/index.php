<?php
/**
 * Lista semplice delle entry di un Content Type — solo per navigare al
 * form (Content Engine, motore di form V1), non elaborata: nessuna
 * ricerca/filtro/colonna dati custom, solo id/stato/data + link edit.
 *
 * @var array  $contentType  da ContentTypeService (id, slug, label, label_singular)
 * @var array  $entries      da ContentEntryService::getPaginated()['entries']
 * @var string $typeSlug
 * @var array  $ui
 */
?>

<div class="flex items-center justify-between mb-4">
    <h1 class="<?= $ui['page_title'] ?>"><?= htmlspecialchars($contentType['label'], ENT_QUOTES, 'UTF-8') ?></h1>
    <a href="<?= BASE_URL ?>/admin/content/<?= htmlspecialchars($typeSlug, ENT_QUOTES, 'UTF-8') ?>/create" class="<?= $ui['btn_primary'] ?>">
        <i class="fas fa-plus"></i> Nuovo <?= htmlspecialchars($contentType['label_singular'], ENT_QUOTES, 'UTF-8') ?>
    </a>
</div>

<div class="<?= $ui['card'] ?>">
    <div class="<?= $ui['table_scroll'] ?>">
        <table class="<?= $ui['table'] ?>">
            <thead>
                <tr class="<?= $ui['thead_row'] ?>">
                    <th class="<?= $ui['th'] ?>">ID</th>
                    <th class="<?= $ui['th'] ?>">Stato</th>
                    <th class="<?= $ui['th'] ?>">Creata il</th>
                    <th class="<?= $ui['th_right'] ?>">Azioni</th>
                </tr>
            </thead>
            <tbody class="<?= $ui['tbody'] ?>">
                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="4" class="<?= $ui['td'] ?>">Nessuna entry ancora.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($entries as $entry): ?>
                    <tr class="<?= $ui['tr'] ?>">
                        <td class="<?= $ui['td_strong'] ?>">#<?= (int) $entry['id'] ?></td>
                        <td class="<?= $ui['td'] ?>"><?= htmlspecialchars((string) ($entry['status_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="<?= $ui['td'] ?>"><?= htmlspecialchars((string) ($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="<?= $ui['td'] ?> text-right">
                            <a href="<?= BASE_URL ?>/admin/content/<?= htmlspecialchars($typeSlug, ENT_QUOTES, 'UTF-8') ?>/<?= (int) $entry['id'] ?>/edit" class="<?= $ui['btn_ghost_sm'] ?>">
                                Modifica
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
