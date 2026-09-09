<?php
declare(strict_types=1);

final class RelistareRepository
{
    private array $config;
    private DbfCache $cache;

    public function __construct(array $config, ?DbfCache $cache = null)
    {
        $this->config = $config;
        $this->cache = $cache ?? new DbfCache($config);
    }

    public function searchNotes(string $query, int $limit = 60): array
    {
        return $this->searchNotesPaginated($query, 1, $limit)['items'];
    }

    public function searchNotesPaginated(string $query, int $page = 1, int $perPage = 30): array
    {
        return $this->cache->searchNotesPaginated($query, $page, $perPage);
    }

    public function searchBonuri(string $query, int $limit = 60): array
    {
        return $this->searchBonuriPaginated($query, 1, $limit)['items'];
    }

    public function searchBonuriPaginated(string $query, int $page = 1, int $perPage = 30): array
    {
        return $this->cache->searchBonuriPaginated(
            $query,
            $page,
            $perPage,
            (bool) ($this->config['include_deleted_bonuri'] ?? true)
        );
    }

    public function getNotaDocument(string $id): ?array
    {
        return $this->cache->getNotaDocument($id);
    }

    public function getBonDocument(string $key): ?array
    {
        return $this->cache->getBonDocument($key, (bool) ($this->config['include_deleted_bonuri'] ?? true));
    }
}
