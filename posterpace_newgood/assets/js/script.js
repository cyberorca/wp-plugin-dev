// File: assets/js/script.js
document.addEventListener('DOMContentLoaded', function() {
    const submitButton = document.querySelector('.online-course-form input[type="submit"]');
    
    submitButton.addEventListener('click', function() {
        const scoreInput = document.querySelector('input[name="user_score"]');
        if (scoreInput.value < 70) {
            alert('Skor Anda harus minimal 70 untuk melanjutkan.');
        }
    });
});
