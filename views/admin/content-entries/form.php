<?php
/**
 * Form generico Content-Type-aware — itera lo schema (field_groups/fields)
 * e per ciascun campo: costruisce FieldInputContext, chiama
 * FieldTypeInterface::renderInput() (già pronto per l'HTML dell'input),
 * passa il risultato + label/required/error al partial condiviso
 * layouts/partials/field.php di giga-admin-shell.
 *
 * Cross-package: questa view vive in giga-cms, field.php vive in
 * giga-admin-shell — risolto con ViewResolver::forPackage(), stesso
 * meccanismo già verificato (sempre risolve al pacchetto dove VIVE
 * ViewResolver stesso, cioè giga-admin-shell, indipendentemente da dove
 * vive il chiamante).
 *
 * @var array       $schema      da ContentEntryFormService::loadSchema()
 * @var array       $values      ['field_key' => valore] — da loadEntry()
 *                                (edit, GET) o dal postData appena
 *                                rifiutato da validate() (mai mescolati:
 *                                o l'uno o l'altro, mai entrambi)
 * @var array       $errors      ['field_key' => messaggio]
 * @var int|null    $entryId     null in creazione
 * @var string      $typeSlug
 * @var string      $formAction
 * @var array       $ui
 */

use Giga\AdminShell\Support\ViewRenderer;
use Giga\AdminShell\Support\ViewResolver;
use Giga\Cms\FieldTypes\FieldInputContext;

$fieldViewRenderer = new ViewRenderer(ViewResolver::forPackage(ROOT_PATH . '/views/admin-shell'));
?>

<form method="POST" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="<?= $ui['card_padded'] ?> space-y-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <?php if (!empty($errors['_general'])): ?>
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg px-4 py-3 text-sm">
            <?= htmlspecialchars($errors['_general'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php foreach ($schema['field_groups'] as $group): ?>
        <?php foreach ($group['fields'] as $entryField): ?>
            <?php
            $field    = $entryField['field'];
            $typeInst = $entryField['type_instance'];

            $fieldConfig = $field['config'] ?? [];
            if ($field['type'] === 'relation') {
                $fieldConfig['relation_options'] = $field['relation_options'] ?? [];
            }

            $inputId = 'field-' . $field['id'];
            $context = new FieldInputContext(
                name: "fields[{$field['id']}]",
                id: $inputId,
                value: $values[$field['key']] ?? null,
                error: $errors[$field['key']] ?? null,
            );

            $inputHtml = $typeInst->renderInput($context, $fieldConfig);

            $fieldViewRenderer->render('layouts/partials/field.php', [
                'label'     => $field['label'],
                'required'  => (bool) $field['required'],
                'help'      => null,
                'error'     => $errors[$field['key']] ?? null,
                'inputHtml' => $inputHtml,
                'inputId'   => $inputId,
                'ui'        => $ui,
            ]);
            ?>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="<?= $ui['form_actions'] ?>">
        <a href="<?= BASE_URL ?>/admin/content/<?= htmlspecialchars($typeSlug, ENT_QUOTES, 'UTF-8') ?>" class="<?= $ui['btn_ghost'] ?>">Annulla</a>
        <button type="submit" class="<?= $ui['btn_primary'] ?>"><?= $entryId === null ? 'Crea' : 'Salva' ?></button>
    </div>
</form>
