$(document).ready(function() {
    // Xử lý tìm kiếm AJAX nếu cần
    $('#search-form').on('submit', function(e) {
        e.preventDefault();
        let query = $(this).find('input[name="q"]').val();
        window.location.href = '<?php echo BASE_URL; ?>search.php?q=' + encodeURIComponent(query);
    });
});