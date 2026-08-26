<?php
/**
 * FACTA — Footer Template
 */
?>

</main>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Mobile Bottom Tab Bar -->
<nav class="mobile-tab-bar" id="mobileTabBar" aria-label="Mobile navigation">
  <a href="index.php" class="tab-item" data-page="home">
    <span class="tab-icon">🏠</span>
    <span class="tab-label">Browse</span>
  </a>
  <a href="index.php?page=search" class="tab-item" data-page="search">
    <span class="tab-icon">🔎</span>
    <span class="tab-label">Search</span>
  </a>
  <a href="index.php?page=ai_chat" class="tab-item" data-page="ai_chat">
    <span class="tab-icon">🤖</span>
    <span class="tab-label">AI</span>
  </a>
  <a href="index.php?page=bookmarks" class="tab-item" data-page="bookmarks">
    <span class="tab-icon">🔖</span>
    <span class="tab-label">Marks</span>
  </a>
  <a href="#" class="tab-item" data-page="menu" id="tabMenu">
    <span class="tab-icon">☰</span>
    <span class="tab-label">Menu</span>
  </a>
</nav>

</div><!-- /app -->

<script src="assets/js/app.js?v=<?=filemtime(__DIR__ . '/../assets/js/app.js')?>"></script>
</body>
</html>
