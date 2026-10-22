/* Borrowing history uses the same loans and fines saved by the existing API. */
const API = '../api';

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function addCell(row, text) {
    const cell = document.createElement('td');
    cell.textContent = text;
    row.appendChild(cell);
    return cell;
}

async function loadHistory() {
    const placeholder = document.getElementById('history-placeholder');
    const table = document.getElementById('history-table');
    const tbody = document.getElementById('history-tbody');

    try {
        const res = await fetch(`${API}/loans/history.php`);
        if (res.status === 401) {
            window.location.assign('../login.php');
            return;
        }
        const json = await res.json();
        if (!json.success) {
            placeholder.textContent = json.message || 'Failed to load history.';
            return;
        }
        if (json.data.length === 0) {
            placeholder.textContent = 'No loans in your history yet.';
            return;
        }

        tbody.replaceChildren();
        json.data.forEach((loan) => {
            const row = document.createElement('tr');
            addCell(row, loan.book_title);
            addCell(row, formatDate(loan.borrow_date));
            addCell(row, formatDate(loan.due_date));
            addCell(row, formatDate(loan.return_date));

            const overdue = Number(loan.is_overdue) === 1;
            const status = loan.return_date ? 'Returned' : overdue ? 'Overdue' : 'Borrowed';
            const badge = document.createElement('span');
            badge.className = `status-badge ${overdue ? 'status-overdue' : 'status-on-time'}`;
            badge.textContent = status;
            addCell(row, '').appendChild(badge);

            const fine = loan.fine_amount === null ? '—'
                : '₦' + Number(loan.fine_amount).toLocaleString('en-NG', { minimumFractionDigits: 2 })
                    + ' (' + (loan.fine_status === 'paid' ? 'Paid' : 'Unpaid') + ')';
            addCell(row, fine);
            tbody.appendChild(row);
        });
        placeholder.hidden = true;
        table.hidden = false;
    } catch (err) {
        console.error('History failed:', err);
        placeholder.textContent = 'Something went wrong. Please try again.';
    }
}

loadHistory();
