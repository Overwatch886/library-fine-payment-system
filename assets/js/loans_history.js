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

async function loadHistory() {
    const placeholder = document.getElementById('history-placeholder');
    const table = document.getElementById('history-table');
    const tbody = document.getElementById('history-tbody');

    try {
        const res = await fetch(`${API}/loans/history.php`);
        const json = await res.json();

        if (!json.success) {
            placeholder.textContent = json.message || 'Failed to load history.';
            return;
        }

        const loans = json.data;
        if (!loans.length) {
            placeholder.textContent = 'No loans in your history yet.';
            return;
        }

        placeholder.hidden = true;
        table.hidden = false;

        tbody.innerHTML = loans.map((loan) => `
            <tr>
                <td>${escapeHtml(loan.book_title)}</td>
                <td>${formatDate(loan.borrow_date)}</td>
                <td>${formatDate(loan.due_date)}</td>
                <td>${formatDate(loan.return_date)}</td>
                <td>${loan.fine_amount ? '₦' + loan.fine_amount : '—'}</td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('History failed:', err);
        placeholder.textContent = 'Something went wrong. Please try again.';
    }
}

loadHistory();
