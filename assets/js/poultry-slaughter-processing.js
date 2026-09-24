'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const batchPicker =
        document.querySelector('[data-slaughter-batch-picker]');

    if (
        batchPicker
        &&
        batchPicker.form
    ) {
        batchPicker.addEventListener('change', function () {
            batchPicker.form.submit();
        });
    }
});
