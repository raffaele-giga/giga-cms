<?php

namespace Giga\Cms\Services;

use Giga\Core\Database;
use Giga\Cms\Repositories\ContentTypeRepository;
use Giga\Cms\Repositories\FieldGroupRepository;
use Giga\Cms\Repositories\TaxonomyRepository;
use Giga\Cms\Repositories\ContentTypePermalinkPatternRepository;

/**
 * Permission-agnostic per design: nessun metodo qui chiama PermissionService
 * o AuditService. Enforcement dei permessi e audit logging sono
 * responsabilità del Controller del progetto consumer (stesso pattern già
 * in uso per UserService in giga-core/wos-pro — mai chiamati da un Service).
 * PermissionService in particolare è codice di progetto (App\Services\...,
 * diverso per ogni consumer): questo Service, condiviso da più consumer via
 * Composer, non può dipendervi. Vedi README.md di giga-cms.
 *
 * Eccezione volutamente diversa: create()/update()/delete() qui SCRIVONO
 * le righe di sec_permissions per il Content Type (Decisione #4). Non è un
 * controllo di autorizzazione (non decide se l'utente può fare qualcosa),
 * è generazione di dati che il PermissionService del consumer troverà.
 *
 * Assume che sec_permissions abbia già la colonna resource_type — non la
 * crea né la verifica a runtime. Il progetto consumer deve aver applicato
 * quella migration prima di usare create()/update()/delete() qui.
 */
class ContentTypeService
{
    /** Azioni sempre presenti per ogni Content Type, system o custom (Decisione #4). */
    private const PERMISSION_ACTIONS = ['view', 'create', 'edit', 'delete', 'publish'];

    private const PERMISSION_RESOURCE_TYPE = 'content';

    private Database $db;
    private ContentTypeRepository $typeRepository;
    private FieldGroupRepository $fieldGroupRepository;
    private TaxonomyRepository $taxonomyRepository;
    private ContentTypePermalinkPatternRepository $permalinkPatternRepository;

    public function __construct()
    {
        $this->db                        = Database::getInstance();
        $this->typeRepository            = new ContentTypeRepository();
        $this->fieldGroupRepository      = new FieldGroupRepository();
        $this->taxonomyRepository        = new TaxonomyRepository();
        $this->permalinkPatternRepository = new ContentTypePermalinkPatternRepository();
    }

    public function getById(int $id): array
    {
        $type = $this->typeRepository->findById($id);
        if (!$type) {
            throw new \RuntimeException('Content Type non trovato.');
        }
        return $this->decodeTemplateOptions($type);
    }

    public function getBySlug(string $slug): array
    {
        $type = $this->typeRepository->findBySlug($slug);
        if (!$type) {
            throw new \RuntimeException("Content Type '{$slug}' non trovato.");
        }
        return $this->decodeTemplateOptions($type);
    }

    /**
     * TUTTI i Content Type (abilitati e disabilitati), per la UI di
     * gestione /admin/content-types — a differenza di
     * getAllForAdminMenu(), che filtra admin_enabled=1 per popolare la
     * sidebar reale: qui serve poter vedere ed eventualmente riattivare
     * anche quelli spenti.
     */
    public function getAllForAdmin(): array
    {
        return array_map(
            $this->decodeTemplateOptions(...),
            $this->typeRepository->findAllForAdmin()
        );
    }

    public function getAllForAdminMenu(): array
    {
        return $this->typeRepository->findAllForAdminMenu();
    }

    public function create(array $data): int
    {
        $slug            = $this->validateSlug($data['slug'] ?? '', 0);
        $templateOptions = $this->normalizeTemplateOptions($data['template_options'] ?? null);
        $template        = $data['template'] ?? null;
        $this->validateTemplateChoice($template, $templateOptions);

        return $this->db->transaction(function () use ($data, $slug, $template, $templateOptions) {
            $id = $this->typeRepository->create([
                'slug'               => $slug,
                'label'              => trim($data['label'] ?? ''),
                'label_singular'     => trim($data['label_singular'] ?? ''),
                'is_system'          => (int) (bool) ($data['is_system'] ?? false),
                'supports_archive'   => (int) (bool) ($data['supports_archive'] ?? false),
                'supports_seo'       => (int) (bool) ($data['supports_seo'] ?? false),
                'supports_media'     => (int) (bool) ($data['supports_media'] ?? false),
                'supports_featured'  => (int) (bool) ($data['supports_featured'] ?? false),
                'supports_stats'     => (int) (bool) ($data['supports_stats'] ?? false),
                'default_ordering'   => $data['default_ordering'] ?? 'created_at',
                'template'           => $template,
                'template_options'   => $templateOptions !== null ? json_encode($templateOptions) : null,
                'permalink_pattern'  => $data['permalink_pattern'] ?? null,
                'json_ld_type'       => $data['json_ld_type'] ?? null,
                'include_in_sitemap' => isset($data['include_in_sitemap']) ? (int) (bool) $data['include_in_sitemap'] : 1,
                'admin_icon'         => $data['admin_icon'] ?? null,
                'admin_menu'         => $data['admin_menu'] ?? null,
                'admin_order'        => (int) ($data['admin_order'] ?? 0),
                'admin_enabled'      => isset($data['admin_enabled']) ? (int) (bool) $data['admin_enabled'] : 1,
            ]);

            $this->createPermissions($slug);

            return $id;
        });
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->getById($id);

        $fields = array_intersect_key($data, array_flip([
            'label',
            'label_singular',
            'supports_archive',
            'supports_seo',
            'supports_media',
            'supports_featured',
            'supports_stats',
            'default_ordering',
            'template',
            'permalink_pattern',
            'json_ld_type',
            'include_in_sitemap',
            'admin_icon',
            'admin_menu',
            'admin_order',
            'admin_enabled',
        ]));

        foreach (['supports_archive', 'supports_seo', 'supports_media', 'supports_featured', 'supports_stats', 'include_in_sitemap', 'admin_enabled'] as $boolField) {
            if (array_key_exists($boolField, $fields)) {
                $fields[$boolField] = (int) (bool) $fields[$boolField];
            }
        }

        // template_options e template si validano insieme: qualunque delle
        // due venga toccata da questo update, il confronto usa il valore
        // finale effettivo (nuovo se passato, esistente altrimenti), mai i
        // due vecchi valori tra loro o i due nuovi isolatamente.
        $finalTemplateOptions = array_key_exists('template_options', $data)
            ? $this->normalizeTemplateOptions($data['template_options'])
            : $existing['template_options'];
        $finalTemplate = array_key_exists('template', $fields) ? $fields['template'] : $existing['template'];
        $this->validateTemplateChoice($finalTemplate, $finalTemplateOptions);

        if (array_key_exists('template_options', $data)) {
            $fields['template_options'] = $finalTemplateOptions !== null ? json_encode($finalTemplateOptions) : null;
        }

        $newSlug = null;
        if (array_key_exists('slug', $data) && $data['slug'] !== $existing['slug']) {
            $newSlug         = $this->validateSlug($data['slug'], $id);
            $fields['slug']  = $newSlug;
        }

        $this->db->transaction(function () use ($id, $fields, $existing, $newSlug) {
            if ($fields !== []) {
                $this->typeRepository->update($id, $fields);
            }
            if ($newSlug !== null) {
                $this->renamePermissions($existing['slug'], $newSlug);
            }
        });
    }

    public function getFieldGroups(int $id): array
    {
        return $this->typeRepository->getFieldGroups($id);
    }

    /**
     * Sostituisce l'intera assegnazione di Field Group del Content Type.
     * $fieldGroupIds nell'ordine di visualizzazione desiderato.
     */
    public function syncFieldGroups(int $id, array $fieldGroupIds): void
    {
        $this->getById($id);

        foreach ($fieldGroupIds as $fieldGroupId) {
            if (!$this->fieldGroupRepository->findById((int) $fieldGroupId)) {
                throw new \RuntimeException("Field Group id={$fieldGroupId} non trovato.");
            }
        }

        $this->typeRepository->syncFieldGroups($id, array_map('intval', $fieldGroupIds));
    }

    public function getTaxonomies(int $id): array
    {
        return $this->typeRepository->getTaxonomies($id);
    }

    /**
     * Sostituisce l'intera assegnazione di tassonomie del Content Type.
     * $taxonomyIds nell'ordine di visualizzazione desiderato.
     */
    public function syncTaxonomies(int $id, array $taxonomyIds): void
    {
        $this->getById($id);

        foreach ($taxonomyIds as $taxonomyId) {
            if (!$this->taxonomyRepository->findById((int) $taxonomyId)) {
                throw new \RuntimeException("Tassonomia id={$taxonomyId} non trovata.");
            }
        }

        $this->typeRepository->syncTaxonomies($id, array_map('intval', $taxonomyIds));
    }

    /**
     * Override del permalink pattern per lingua (Decisione #2: "il
     * permalink pattern è definito per Content Type e può essere
     * sovrascritto per lingua"). Senza override configurato,
     * ContentEntryTranslationService::buildPermalink() risolve sul
     * pattern di default di questo Content Type (getById()['permalink_pattern']),
     * poi sul fallback calcolato /{slug}/{slug}.
     */
    public function getPermalinkPatternOverrides(int $id): array
    {
        $this->getById($id);
        return $this->permalinkPatternRepository->findByContentType($id);
    }

    public function setPermalinkPatternOverride(int $id, int $languageId, string $pattern): void
    {
        $this->getById($id);

        $pattern = trim($pattern);
        if ($pattern === '') {
            throw new \RuntimeException('Il pattern non può essere vuoto.');
        }

        $this->permalinkPatternRepository->set($id, $languageId, $pattern);
    }

    public function removePermalinkPatternOverride(int $id, int $languageId): void
    {
        $this->getById($id);
        $this->permalinkPatternRepository->remove($id, $languageId);
    }

    /**
     * Cancella il Content Type e le sue righe di permesso (FK CASCADE su
     * sec_role_permissions). Se esistono content_entries collegate, la FK
     * RESTRICT su content_entries.content_type_id blocca la delete prima
     * che qualunque permesso venga toccato (transazione unica).
     */
    public function delete(int $id): void
    {
        $existing = $this->getById($id);

        $this->db->transaction(function () use ($id, $existing) {
            $this->typeRepository->delete($id);
            $this->deletePermissions($existing['slug']);
        });
    }

    private function validateSlug(string $slug, int $excludeId): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            throw new \RuntimeException('Lo slug è obbligatorio.');
        }
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            throw new \RuntimeException('Lo slug può contenere solo lettere minuscole, numeri e trattini.');
        }
        if ($this->typeRepository->slugExists($slug, $excludeId)) {
            throw new \RuntimeException("Esiste già un Content Type con slug '{$slug}'.");
        }
        if ($this->slugCollidesWithSystemModule($slug)) {
            throw new \RuntimeException(
                "Lo slug '{$slug}' coincide con un modulo di sistema esistente: la chiave "
                . '(module, action) diventerebbe ambigua tra permesso di sistema e permesso di contenuto.'
            );
        }

        return $slug;
    }

    /**
     * NULL/[] = nessun vincolo dichiarato (template resta libero, come
     * prima di questa colonna). Altrimenti: array di stringhe non vuote.
     */
    private function normalizeTemplateOptions(mixed $raw): ?array
    {
        if ($raw === null || $raw === []) {
            return null;
        }
        if (!is_array($raw)) {
            throw new \RuntimeException('template_options deve essere un array di stringhe.');
        }
        foreach ($raw as $option) {
            if (!is_string($option) || trim($option) === '') {
                throw new \RuntimeException('Ogni voce di template_options deve essere una stringa non vuota.');
            }
        }

        return array_values($raw);
    }

    /** Nessun vincolo se template_options non è dichiarato, o se non si sta impostando un template. */
    private function validateTemplateChoice(?string $template, ?array $templateOptions): void
    {
        if ($templateOptions === null || $template === null) {
            return;
        }
        if (!in_array($template, $templateOptions, true)) {
            throw new \RuntimeException(
                "template '{$template}' non è tra le varianti dichiarate: " . implode(', ', $templateOptions)
            );
        }
    }

    private function decodeTemplateOptions(array $type): array
    {
        $type['template_options'] = $type['template_options'] !== null
            ? json_decode($type['template_options'], true)
            : null;

        return $type;
    }

    /** Decisione #4: uno slug non può coincidere con un module di sistema (resource_type IS NULL). */
    private function slugCollidesWithSystemModule(string $slug): bool
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM sec_permissions WHERE module = ? AND resource_type IS NULL',
            [$slug]
        );
        return (int) ($row['total'] ?? 0) > 0;
    }

    /**
     * Crea le 5 righe di permesso per il nuovo Content Type e le assegna
     * di default solo al ruolo 'admin' (nessuna UI di assegnazione richiesta
     * per funzionare, Decisione #4). Se il ruolo 'admin' non esiste, le
     * righe di permesso vengono comunque create ma non assegnate a nessuno
     * — non blocca la creazione del Content Type.
     */
    private function createPermissions(string $slug): void
    {
        $permissionIds = [];
        foreach (self::PERMISSION_ACTIONS as $action) {
            $permissionIds[] = (int) $this->db->insert(
                'INSERT INTO sec_permissions (module, action, resource_type) VALUES (?, ?, ?)',
                [$slug, $action, self::PERMISSION_RESOURCE_TYPE]
            );
        }

        $adminRole = $this->db->fetchOne("SELECT id FROM sec_roles WHERE slug = 'admin' LIMIT 1");
        if ($adminRole) {
            foreach ($permissionIds as $permissionId) {
                $this->db->execute(
                    'INSERT INTO sec_role_permissions (role_id, permission_id) VALUES (?, ?)',
                    [(int) $adminRole['id'], $permissionId]
                );
            }
        }

        $this->bumpAllRolesPermissionsVersion();
    }

    /** Le assegnazioni in sec_role_permissions sopravvivono: referenziano permission_id, non la stringa. */
    private function renamePermissions(string $oldSlug, string $newSlug): void
    {
        $this->db->execute(
            "UPDATE sec_permissions SET module = ? WHERE module = ? AND resource_type = ?",
            [$newSlug, $oldSlug, self::PERMISSION_RESOURCE_TYPE]
        );
        $this->bumpAllRolesPermissionsVersion();
    }

    /** FK ON DELETE CASCADE su sec_role_permissions ripulisce le assegnazioni. */
    private function deletePermissions(string $slug): void
    {
        $this->db->execute(
            'DELETE FROM sec_permissions WHERE module = ? AND resource_type = ?',
            [$slug, self::PERMISSION_RESOURCE_TYPE]
        );
        $this->bumpAllRolesPermissionsVersion();
    }

    private function bumpAllRolesPermissionsVersion(): void
    {
        $this->db->execute('UPDATE sec_roles SET permissions_version = permissions_version + 1');
    }
}
