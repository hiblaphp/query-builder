<nav aria-label="Page navigation">
    <ul class="pagination">
        <!-- Previous Page Link -->
        <?php if ($paginator->currentPage > 1): ?>
            <li class="page-item">
                <a class="page-link" href="<?= htmlspecialchars($paginator->previousPageUrl()); ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                    Previous
                </a>
            </li>
        <?php else: ?>
            <li class="page-item disabled">
                <span class="page-link">&laquo; Previous</span>
            </li>
        <?php endif; ?>

        <!-- Page Numbers -->
        <?php
        $currentPage = $paginator->currentPage;
        $lastPage = $paginator->lastPage;
        $start = max(1, $currentPage - 2);
        $end = min($lastPage, $currentPage + 2);
        ?>

        <?php if ($start > 1): ?>
            <li class="page-item">
                <a class="page-link" href="<?= htmlspecialchars($paginator->url(1)); ?>">1</a>
            </li>
            <?php if ($start > 2): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($page = $start; $page <= $end; $page++): ?>
            <?php if ($page === $currentPage): ?>
                <li class="page-item active" aria-current="page">
                    <span class="page-link">
                        <?= $page; ?>
                        <span class="sr-only">(current)</span>
                    </span>
                </li>
            <?php else: ?>
                <li class="page-item">
                    <a class="page-link" href="<?= htmlspecialchars($paginator->url($page)); ?>">
                        <?= $page; ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $lastPage): ?>
            <?php if ($end < $lastPage - 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
            <li class="page-item">
                <a class="page-link" href="<?= htmlspecialchars($paginator->url($lastPage)); ?>">
                    <?= $lastPage; ?>
                </a>
            </li>
        <?php endif; ?>

        <!-- Next Page Link -->
        <?php if ($paginator->hasMore): ?>
            <li class="page-item">
                <a class="page-link" href="<?= htmlspecialchars($paginator->nextPageUrl()); ?>" aria-label="Next">
                    Next
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        <?php else: ?>
            <li class="page-item disabled">
                <span class="page-link">Next &raquo;</span>
            </li>
        <?php endif; ?>
    </ul>
</nav>