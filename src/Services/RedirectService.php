<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\RedirectRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 */
class RedirectService
{
    private RedirectRepository $redirectRepository;

    public function __construct()
    {
        $this->redirectRepository = new RedirectRepository();
    }

    public function getById(int $id): array
    {
        $redirect = $this->redirectRepository->findById($id);
        if (!$redirect) {
            throw new \RuntimeException('Redirect non trovato.');
        }
        return $redirect;
    }

    public function getAll(): array
    {
        return $this->redirectRepository->findAll();
    }

    /** Destinazione attiva per un source, o null se nessun redirect esiste/è attivo. */
    public function resolve(string $source): ?string
    {
        $redirect = $this->redirectRepository->findBySource($source);
        if (!$redirect || !$redirect['active']) {
            return null;
        }
        return $redirect['destination'];
    }

    public function create(array $data): int
    {
        $source      = $this->validatePath($data['source'] ?? '', 'source');
        $destination = $this->validatePath($data['destination'] ?? '', 'destination');
        if ($source === $destination) {
            throw new \RuntimeException('source e destination non possono coincidere.');
        }

        return $this->redirectRepository->create([
            'source'      => $source,
            'destination' => $destination,
            'status_code' => (int) ($data['status_code'] ?? 301),
            'active'      => isset($data['active']) ? (int) (bool) $data['active'] : 1,
        ]);
    }

    /**
     * Crea o aggiorna (upsert per source) un redirect generato
     * automaticamente al cambio slug (URL, Slug, Redirect). Se $source
     * ha già un redirect (es. lo slug è cambiato più volte), ne aggiorna
     * solo la destination invece di fallire sull'UNIQUE su source.
     *
     * Limite noto, non gestito qui: se $destination coincide con il
     * source di un redirect preesistente (catena A->B, poi B->C), questo
     * metodo non collassa la catena né la rileva — resta A->B e B->C
     * come regole separate, non un'estensione richiesta in questo giro.
     */
    public function recordAutomaticRedirect(string $source, string $destination): void
    {
        if ($source === $destination) {
            return;
        }

        $existing = $this->redirectRepository->findBySource($source);
        if ($existing) {
            $this->redirectRepository->update($existing['id'], ['destination' => $destination]);
            return;
        }

        $this->redirectRepository->create([
            'source'      => $source,
            'destination' => $destination,
            'status_code' => 301,
            'active'      => 1,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $this->getById($id);
        $fields = array_intersect_key($data, array_flip(['destination', 'status_code', 'active']));

        if (array_key_exists('active', $fields)) {
            $fields['active'] = (int) (bool) $fields['active'];
        }
        if (array_key_exists('destination', $fields)) {
            $fields['destination'] = $this->validatePath($fields['destination'], 'destination');
        }

        if ($fields !== []) {
            $this->redirectRepository->update($id, $fields);
        }
    }

    public function delete(int $id): void
    {
        $this->getById($id);
        $this->redirectRepository->delete($id);
    }

    private function validatePath(string $path, string $label): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/') {
            throw new \RuntimeException("{$label} deve essere un path assoluto (che inizia con '/').");
        }
        return $path;
    }
}
