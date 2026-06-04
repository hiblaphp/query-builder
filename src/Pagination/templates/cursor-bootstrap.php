<?php if ($paginator->hasMore): ?>
    <nav aria-label="Cursor pagination" class="d-flex justify-content-center">
        <ul class="pagination">
            <li class="page-item">
                <a class="page-link" href="<?= htmlspecialchars($paginator->nextPageUrl()); ?>">
                    Next →
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>