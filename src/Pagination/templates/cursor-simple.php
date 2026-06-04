<?php if ($paginator->hasMore): ?>
    <div class="text-center my-6">
        <a href="<?= htmlspecialchars($paginator->nextPageUrl()); ?>" class="inline-block px-6 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">
            Load More
        </a>
    </div>
<?php endif; ?>