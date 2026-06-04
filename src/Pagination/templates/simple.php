<div class="flex justify-between items-center gap-4 my-6">
    <div>
        <?php if ($paginator->currentPage > 1): ?>
            <a href="<?= htmlspecialchars($paginator->previousPageUrl()); ?>" class="text-blue-500 hover:text-blue-700 underline">
                ← Previous
            </a>
        <?php else: ?>
            <span class="text-gray-400 cursor-not-allowed">← Previous</span>
        <?php endif; ?>
    </div>

    <div class="text-sm text-gray-600">
        Page <strong><?= $paginator->currentPage; ?></strong> of <strong><?= $paginator->lastPage; ?></strong>
        (<?= $paginator->from; ?>–<?= $paginator->to; ?> of <?= $paginator->total; ?>)
    </div>

    <div>
        <?php if ($paginator->hasMore): ?>
            <a href="<?= htmlspecialchars($paginator->nextPageUrl()); ?>" class="text-blue-500 hover:text-blue-700 underline">
                Next →
            </a>
        <?php else: ?>
            <span class="text-gray-400 cursor-not-allowed">Next →</span>
        <?php endif; ?>
    </div>
</div>