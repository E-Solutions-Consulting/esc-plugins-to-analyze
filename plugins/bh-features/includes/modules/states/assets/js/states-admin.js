document.addEventListener('DOMContentLoaded', function () {

    // Toggle override fields on checkbox click
    document.querySelectorAll('.ah-override-toggle').forEach(function (checkbox) {

        checkbox.addEventListener('change', function () {

            const target = document.querySelector(checkbox.dataset.target);
            if (!target) return;

            if (checkbox.checked) {
                target.style.display = 'block';
            } else {
                target.style.display = 'none';
            }
        });
    });
});
