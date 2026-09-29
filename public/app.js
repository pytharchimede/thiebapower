(() => {
    const get = id => document.getElementById(id);
    const screens = [...document.querySelectorAll('.kiosk-screen')];
    const formatMoney = value => new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
    function showStep(step) {
        screens.forEach(screen => screen.hidden = Number(screen.dataset.step) !== step);
        get('step-counter').textContent = `Étape ${step} sur 2`;
        get('progress-bar').style.width = `${step * 50}%`;
        document.querySelector('.kiosk-workflow').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    document.querySelectorAll('[data-back]').forEach(button => {
        button.addEventListener('click', () => showStep(Number(button.dataset.back)));
    });
    document.querySelectorAll('.battery-option').forEach(button => {
        button.addEventListener('click', () => {
            const deposit = Number(button.dataset.deposit);
            if (get('checkout-battery')) get('checkout-battery').value = button.dataset.id;
            get('summary-station').textContent = window.TB_STATION;
            get('summary-battery').textContent = `${button.dataset.serial} · slot ${button.dataset.slot}`;
            get('summary-deposit').textContent = formatMoney(deposit);
            get('summary-total').textContent = formatMoney(Number(window.TB_PRICE) + deposit);
            showStep(2);
        });
    });
})();
