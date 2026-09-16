<?php
/**
 * Gestione Content Type a livello di installazione: abilitazione
 * (admin_enabled) e collocazione nel menu admin (admin_menu/admin_order),
 * più i gruppi di menu (admin_menu_groups) con il loro sort_order.
 * Nessun link a creazione/modifica struttura campi — fuori scope.
 *
 * @var array $contentTypes  da ContentTypeService::getAllForAdmin() — TUTTI,
 *                            inclusi i disabilitati
 * @var array $menuGroups    da AdminMenuGroupService::getAll()
 * @var array $ui
 */
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
?>

<div class="mb-6">
    <h1 class="<?= $ui['page_title'] ?>">Gestione contenuti</h1>
    <p class="<?= $ui['page_subtitle'] ?>">Content Type installati: abilitazione e collocazione nel menu admin.</p>
</div>

<div class="<?= $ui['card'] ?> mb-8">
    <div class="<?= $ui['table_scroll'] ?>">
        <table class="<?= $ui['table'] ?>">
            <thead>
                <tr class="<?= $ui['thead_row'] ?>">
                    <th class="<?= $ui['th'] ?>">Nome</th>
                    <th class="<?= $ui['th'] ?>">Slug</th>
                    <th class="<?= $ui['th'] ?>">Stato</th>
                    <th class="<?= $ui['th'] ?>">Gruppo menu / Ordine</th>
                    <th class="<?= $ui['th_right'] ?>">Azioni</th>
                </tr>
            </thead>
            <tbody class="<?= $ui['tbody'] ?>">
                <?php if (empty($contentTypes)): ?>
                    <tr><td colspan="5" class="<?= $ui['td'] ?>">Nessun Content Type installato.</td></tr>
                <?php endif; ?>
                <?php foreach ($contentTypes as $type): ?>
                    <tr class="<?= $ui['tr'] ?>">
                        <td class="<?= $ui['td_strong'] ?>"><?= htmlspecialchars($type['label'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="<?= $ui['td_mono'] ?>"><?= htmlspecialchars($type['slug'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="<?= $ui['td'] ?>">
                            <span class="<?= $type['admin_enabled'] ? $ui['badge_success'] : $ui['badge_neutral'] ?>">
                                <?= $type['admin_enabled'] ? 'Attivo' : 'Disattivo' ?>
                            </span>
                        </td>
                        <td class="<?= $ui['td'] ?>">
                            <form method="POST" action="<?= BASE_URL ?>/admin/content-types/<?= (int) $type['id'] ?>/menu" class="flex items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="text" name="admin_menu" value="<?= htmlspecialchars((string) ($type['admin_menu'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Gruppo (es. Contenuti)" class="<?= $ui['filter_input'] ?> w-40">
                                <input type="number" name="admin_order" value="<?= (int) $type['admin_order'] ?>"
                                    class="<?= $ui['filter_input'] ?> w-20" title="Ordine nel gruppo">
                                <button type="submit" class="<?= $ui['btn_ghost_sm'] ?>">Salva</button>
                            </form>
                        </td>
                        <td class="<?= $ui['td'] ?> text-right">
                            <form method="POST" action="<?= BASE_URL ?>/admin/content-types/<?= (int) $type['id'] ?>/toggle">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="<?= $type['admin_enabled'] ? $ui['btn_danger_soft'] : $ui['btn_primary_soft'] ?>">
                                    <?= $type['admin_enabled'] ? 'Disattiva' : 'Attiva' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mb-4">
    <h2 class="<?= $ui['card_title'] ?> text-base">Gruppi di menu</h2>
    <p class="<?= $ui['page_subtitle'] ?>">Ordine delle sezioni generate dai Content Type nella sidebar — numero più basso = più in alto.</p>
</div>

<div class="<?= $ui['card'] ?>">
    <div class="<?= $ui['table_scroll'] ?>">
        <table class="<?= $ui['table'] ?>">
            <thead>
                <tr class="<?= $ui['thead_row'] ?>">
                    <th class="<?= $ui['th'] ?>">Gruppo</th>
                    <th class="<?= $ui['th'] ?>">Ordine</th>
                </tr>
            </thead>
            <tbody class="<?= $ui['tbody'] ?>">
                <?php if (empty($menuGroups)): ?>
                    <tr><td colspan="2" class="<?= $ui['td'] ?>">Nessun gruppo ancora — creato automaticamente al primo salvataggio di un Content Type con un nome gruppo.</td></tr>
                <?php endif; ?>
                <?php foreach ($menuGroups as $group): ?>
                    <tr class="<?= $ui['tr'] ?>">
                        <td class="<?= $ui['td_strong'] ?>"><?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="<?= $ui['td'] ?>">
                            <form method="POST" action="<?= BASE_URL ?>/admin/menu-groups/<?= (int) $group['id'] ?>/order" class="flex items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="number" name="sort_order" value="<?= (int) $group['sort_order'] ?>" class="<?= $ui['filter_input'] ?> w-20">
                                <button type="submit" class="<?= $ui['btn_ghost_sm'] ?>">Salva</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
