document.addEventListener('DOMContentLoaded', function (e) {
    document.querySelectorAll('input[name*="setono_sylius_terms_terms[translations]"][name*="[name]"]').forEach(function (input) {
        input.addEventListener('input', function (event) {
            const element = event.currentTarget;
            element.closest('.content').querySelector('[name*="[slug]"]').value = slugger(element.value);
        });
    });
});
