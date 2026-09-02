# giga-cms

Content Engine / CMS layer per [giga-core](https://github.com/raffaele-giga/giga-core). Vedi `ARCHITETTURA-GIGA-CORE.md` (root del workspace) per le decisioni architetturali complete.

## Confine di responsabilità: permessi e audit

**I Service di questo pacchetto (`Giga\Cms\Services\*`) sono permission-agnostic.** Nessuno di essi chiama `PermissionService::can()` né `AuditService::log()` internamente, e questo è intenzionale, non un gap da colmare in futuro.

Motivo: `PermissionService` è codice di progetto (`App\Services\PermissionService`), non di `giga-core`/`giga-cms` — ogni progetto consumer ne ha una copia propria con le proprie costanti. `giga-cms` è consumato da progetti diversi (Decisione di rivendibilità: stesso package, zero fork), quindi non può dipendere in modo rigido da una classe che vive nel progetto che lo consuma. Verificato anche sul codice reale: in wos-pro e cms-neviobianchi, `PermissionService::can()`/`requirePermission()` e `AuditService::log()` sono chiamati **esclusivamente dai Controller** (`BaseController`, `RoleMiddleware`, singoli Controller Admin) — mai da un Service o un Repository.

**Enforcement dei permessi e audit logging sono responsabilità del Controller del progetto consumer**, esattamente come già avviene per `UserService`/`ProjectService` in giga-core/wos-pro:

```php
// Nel progetto consumer, non dentro giga-cms
class ContentTypesController extends BaseController
{
    public function store(): void
    {
        $this->requirePermission('content_types.create'); // Controller, non Service

        $service = new \Giga\Cms\Services\ContentTypeService();
        $id = $service->create($_POST);

        (new \Giga\Core\Services\AuditService())->log(
            \Giga\Core\Services\AuditService::class . '_created', // o costante di progetto
            $_SESSION['user_id'] ?? null,
            $_SESSION['user_email'] ?? null,
            'content_type',
            $id
        );
    }
}
```

**Eccezione**: la generazione/rinomina/cancellazione delle 5 righe di permesso in `sec_permissions` per un Content Type (`view`/`create`/`edit`/`delete`/`publish`, Decisione #4) *è* dentro `ContentTypeService` — ma è creazione di dati di autorizzazione, non un controllo di autorizzazione: non decide se l'utente corrente può fare qualcosa, scrive solo le righe che il `PermissionService` del progetto consumer troverà quando farà quel controllo.

## Requisiti sullo schema `sec_permissions`

`ContentTypeService` assume che `sec_permissions` abbia già la colonna `resource_type` (Decisione #4) — **non la crea, non la verifica a runtime**. Il progetto consumer deve applicare la migration `ALTER TABLE sec_permissions ADD COLUMN resource_type ...` prima di usare `ContentTypeService::create()`. Questa migration non è ancora stata scritta per wos-pro/cms-neviobianchi (nessuno dei due consuma ancora giga-cms) — verrà scritta quando uno dei due verrà davvero collegato al pacchetto.
