<?php
/**
 * Lista semplice delle entry di un Content Type — solo per navigare al
 * form (Content Engine, motore di form V1), non elaborata: nessuna
 * ricerca/filtro/colonna dati custom, solo id/stato/data + link edit.
 *
 * @var array  $contentType  da ContentTypeService (id, slug, label, label_singular)
 * @var array  $entries      da ContentEntryService::getPaginated()['entries'] —
 *                            ogni riga include status_key ('draft'/'published'/'archived')
 *                            e entry_label (nome rappresentativo, può essere null)
 * @var string $typeSlug
 * @var array  $ui
 */
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');

// Mappa esplicita, non match(): stato non riconosciuto (caso limite, non
// dovrebbe verificarsi con i 3 status di sistema attuali) ricade sul
// fallback badge_neutral con la label grezza dal DB, mai un errore.
$statusStyles = [
    'draft'     => ['cls' => 'badge_neutral', 'label' => 'Draft'],
    'published' => ['cls' => 'badge_success', 'label' => 'Published'],
    'archived'  => ['cls' => 'badge_neutral', 'label' => 'Archived'],
];
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
                    <th class="<?= $ui['th'] ?>">Nome</th>
                    <th class="<?= $ui['th'] ?>">Stato</th>
                    <th class="<?= $ui['th'] ?>">Creata il</th>
                    <th class="<?= $ui['th_right'] ?>">Azioni</th>
                </tr>
            </thead>
            <tbody class="<?= $ui['tbody'] ?>">
                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="5" class="<?= $ui['td'] ?>">Nessuna entry ancora.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($entries as $entry): ?>
                    <?php $status = $statusStyles[$entry['status_key']] ?? ['cls' => 'badge_neutral', 'label' => $entry['status_label']]; ?>
                    <tr class="<?= $ui['tr'] ?>">
                        <td class="<?= $ui['td'] ?>">#<?= (int) $entry['id'] ?></td>
                        <td class="<?= $ui['td_strong'] ?>">
                            <?php $label = trim((string) ($entry['entry_label'] ?? '')); ?>
                            <?= $label !== '' ? htmlspecialchars($label, ENT_QUOTES, 'UTF-8') : '#' . (int) $entry['id'] ?>
                        </td>
                        <td class="<?= $ui['td'] ?>">
                            <?php if ($entry['status_key'] === 'archived'): ?>
                                <!-- is_terminal: nessuna azione di ripristino in questo blocco, fuori scope -->
                                <span class="<?= $ui[$status['cls']] ?>"><?= htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <form method="POST" action="<?= BASE_URL ?>/admin/content/<?= htmlspecialchars($typeSlug, ENT_QUOTES, 'UTF-8') ?>/<?= (int) $entry['id'] ?>/toggle-status">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="<?= $ui[$status['cls']] ?> cursor-pointer">
                                        <?= htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td class="<?= $ui['td'] ?>"><?= htmlspecialchars((string) ($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="<?= $ui['td'] ?> flex items-center justify-end gap-2">
                            <a href="<?= BASE_URL ?>/admin/content/<?= htmlspecialchars($typeSlug, ENT_QUOTES, 'UTF-8') ?>/<?= (int) $entry['id'] ?>/edit" class="<?= $ui['btn_ghost_sm'] ?>">
                                <i class="fas fa-pen text-xs"></i> Modifica
                            </a>
                            <?php if ($entry['status_key'] !== 'archived'): ?>
                                <form method="POST" action="<?= BASE_URL ?>/admin/content/<?= htmlspecialchars($typeSlug, ENT_QUOTES, 'UTF-8') ?>/<?= (int) $entry['id'] ?>/archive">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="<?= $ui['btn_warning'] ?>">
                                        <i class="fas fa-box-archive text-xs"></i> Archivia
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="<?= BASE_URL ?>/admin/content/<?= htmlspecialchars($typeSlug, ENT_QUOTES, 'UTF-8') ?>/<?= (int) $entry['id'] ?>/delete">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="<?= $ui['btn_danger'] ?>">
                                    <i class="fas fa-trash text-xs"></i> Elimina
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
