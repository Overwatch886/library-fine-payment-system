const API = 'api';

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function getReferenceFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('reference');
}

async function loadReceipt() {
    const container = document.getElementById('receipt-container');
    const reference = getReferenceFromUrl();

    if (!reference) {
        container.innerHTML = '<p class="results-placeholder">No receipt reference provided.</p>';
        return;
    }

    try {
        const res = await fetch(`${API}/payments/receipt.php?reference=${encodeURIComponent(reference)}`);
        const json = await res.json();

        if (!json.success) {
            container.innerHTML = `<p class="results-placeholder">${escapeHtml(json.message || 'Receipt not found.')}</p>`;
            return;
        }

        const r = json.data;
        container.innerHTML = `
            <dl class="receipt-details">
                <dt>Reference</dt>
                <dd>${escapeHtml(r.reference)}</dd>
                <dt>Amount</dt>
                <dd>₦${r.amount}</dd>
                <dt>Method</dt>
                <dd>${escapeHtml(r.payment_method)}</dd>
                <dt>Date</dt>
                <dd>${formatDate(r.paid_at)}</dd>
                <dt>Status</dt>
                <dd>${escapeHtml(r.status)}</dd>
            </dl>
        `;
    } catch (err) {
        console.error('Receipt failed:', err);
        container.innerHTML = '<p class="results-placeholder">Something went wrong. Please try again.</p>';
    }
}

loadReceipt();
