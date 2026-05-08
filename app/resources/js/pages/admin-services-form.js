(function () {
    const colorPicker = document.getElementById('color_picker');
    const colorHidden = document.getElementById('color');
    const colorSwatch = document.getElementById('color_swatch_btn');
    const colorChip = document.getElementById('color_preview_chip');
    const colorLabel = document.getElementById('color_preview_label');
    const colorClear = document.getElementById('color_clear');

    function applyColor(hex) {
        colorHidden.value = hex;
        colorPicker.value = hex;
        colorSwatch.style.backgroundColor = hex;
        colorChip.style.backgroundColor = hex;
        colorChip.style.borderColor = 'transparent';
        colorChip.classList.remove('border-border', 'text-foreground/40', 'bg-muted');
        colorChip.classList.add('text-white');
        colorLabel.textContent = hex;
        colorClear.classList.remove('hidden');
    }

    function clearColor() {
        colorHidden.value = '';
        colorPicker.value = '#3B82F6';
        colorSwatch.style.backgroundColor = '';
        colorSwatch.classList.add('bg-muted');
        colorChip.style.backgroundColor = '';
        colorChip.style.borderColor = '';
        colorChip.classList.add('border-border', 'text-foreground/40', 'bg-muted');
        colorChip.classList.remove('text-white');
        colorLabel.textContent = 'No color';
        colorClear.classList.add('hidden');
    }

    colorPicker.addEventListener('input', function () {
        applyColor(colorPicker.value);
    });

    colorClear.addEventListener('click', clearColor);

    const directCheckbox = document.getElementById('is_direct_service');
    const sendEmailSection = document.getElementById('send-email-section');

    function toggleSendEmail() {
        sendEmailSection.classList.toggle('hidden', directCheckbox.checked);
    }

    directCheckbox.addEventListener('change', toggleSendEmail);
})();
