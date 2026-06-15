document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 500);
        }, 4000); // 4000 = 4 seconds
    });
});

function confirmDelete(message) {
    message = message || 'Are you sure you want to delete this record?';
    return confirm(message);
}

function calculateRentalFee() {
    var petSelect  = document.getElementById('pet_id');
    var daysInput  = document.getElementById('rental_days');
    var feeDisplay = document.getElementById('calculated_fee');
    var feeInput   = document.getElementById('rental_fee');

    if (!petSelect || !daysInput || !feeDisplay) return;

    var selectedOption = petSelect.options[petSelect.selectedIndex];
    var pricePerDay    = parseFloat(selectedOption.getAttribute('data-price')) || 0;
    var days           = parseInt(daysInput.value) || 0;
    var totalFee       = pricePerDay * days;

    feeDisplay.textContent = 'PHP ' + totalFee.toFixed(2);

    if (feeInput) feeInput.value = totalFee.toFixed(2);
}

function calculateReturnDate() {
    var daysInput      = document.getElementById('rental_days');
    var returnDisplay  = document.getElementById('expected_return_display');

    if (!daysInput || !returnDisplay) return;

    var days = parseInt(daysInput.value) || 0;

    if (days > 0) {
        var today       = new Date();
        var returnDate  = new Date(today.getTime() + (days * 24 * 60 * 60 * 1000));

        // Format the date nicely: Month Day, Year
        var options = { year: 'numeric', month: 'long', day: 'numeric' };
        returnDisplay.textContent = returnDate.toLocaleDateString('en-PH', options);
    } else {
        returnDisplay.textContent = '—';
    }
}
function calculatePenalty() {
    var expectedInput  = document.getElementById('expected_return');
    var actualInput    = document.getElementById('actual_return');
    var penaltyDisplay = document.getElementById('penalty_display');
    var penaltyInput   = document.getElementById('penalty_amount');
    var pricePerDay    = parseFloat(document.getElementById('price_per_day').value) || 0;

    if (!expectedInput || !actualInput || !penaltyDisplay) return;

    var expected = new Date(expectedInput.value);
    var actual   = new Date(actualInput.value);

    if (actual > expected) {
        var diffTime = actual.getTime() - expected.getTime();
        var lateDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        var penalty  = lateDays * pricePerDay * 0.5;

        penaltyDisplay.textContent = 'Late by ' + lateDays + ' day(s). Penalty: PHP ' + penalty.toFixed(2);
        penaltyDisplay.style.color = '#C62828';

        if (penaltyInput) penaltyInput.value = penalty.toFixed(2);
    } else {
        penaltyDisplay.textContent = 'No penalty - returned on time!';
        penaltyDisplay.style.color = '#2E7D32';
        if (penaltyInput) penaltyInput.value = '0';
    }
}

function printPage() {
    window.print();
}