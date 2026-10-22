const API = 'api';

/** Fetch a JSON endpoint and return the parsed body. */
async function fetchJson(url, options = {}) {
    const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json' },
        ...options,
    });
    return res.json();
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/** Return { text, cls } for a loan's display status. */
function getLoanStatus(loan) {
    if (loan.is_overdue) return { text: 'Overdue', cls: 'status-overdue' };
    if (!loan.return_date) return { text: 'Active', cls: 'status-active' };
    return { text: 'Returned', cls: 'status-returned' };
}

/** Pick the right fine value: stored fine if returned late, else live calc. */
function getFineDisplay(loan) {
    return loan.fine_amount
        ? '₦' + loan.fine_amount
        : '₦' + loan.fine_so_far;
}

/** Build one <tr> for the loans table. */
function renderLoanRow(loan) {
    const { text: statusText, cls: statusClass } = getLoanStatus(loan);
    const fineDisplay = getFineDisplay(loan);
    return `
    <tr>
        <td>${loan.loan_id}</td>
        <td>${escapeHtml(loan.student_name)}</td>
        <td>${escapeHtml(loan.matric_number)}</td>
        <td>${escapeHtml(loan.book_title)}</td>
        <td>${escapeHtml(loan.borrow_date)}</td>
        <td>${escapeHtml(loan.due_date)}</td>
        <td>${escapeHtml(loan.return_date || '—')}</td>
        <td class="${statusClass}">${escapeHtml(statusText)}</td>
        <td>${escapeHtml(fineDisplay)}</td>
    </tr>`;
}

/** Populate the stat cards from /admin/stats.php */
async function loadStats() {
    try {
        const json = await fetchJson(`${API}/admin/stats.php`);
        if (!json.success) return;
        const d = json.data;
        document.getElementById('stat-total-books').textContent = d.total_books;
        document.getElementById('stat-total-copies').textContent = d.total_copies;
        document.getElementById('stat-copies-out').textContent = d.copies_out;
        document.getElementById('stat-overdue-loans').textContent = d.overdue_loans;
        document.getElementById('stat-unpaid-fines-count').textContent = d.unpaid_fines_count;
        document.getElementById('stat-unpaid-fines-total').textContent = '₦'+d.unpaid_fines_total;
        document.getElementById('stat-payments-collected').textContent = '₦'+d.payments_collected;
    } catch (err) {
        console.error('Stats failed:', err);
    }
}

/** Load and render the loans table. Reads filter inputs. */
async function loadLoans() {
    const status = document.getElementById('loan-status').value;
    const q = document.getElementById('loan-search').value.trim();

    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (q) params.set('q', q);

    try {
        const json = await fetchJson(`${API}/admin/loans.php?${params.toString()}`);
        if (!json.success) return;

        const tbody = document.getElementById('loans-tbody');
        const loans = json.data;
        if (!loans.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="results-placeholder">No loans found.</td></tr>';
            return;
        }

        tbody.innerHTML = loans.map(renderLoanRow).join('');
    } catch (err) {
        console.error('Loans failed:', err);
    }
}

// Wire up the filter button
document.getElementById('loan-filter-btn').addEventListener('click', loadLoans);

// Initial load
loadStats();
loadLoans();
