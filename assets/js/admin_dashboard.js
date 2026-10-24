const API = 'api';
let returnPending = false;

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
        <td>${loan.copy_id}</td>
        <td>${escapeHtml(loan.student_name)}</td>
        <td>${escapeHtml(loan.matric_number)}</td>
        <td>${escapeHtml(loan.book_title)}</td>
        <td>${escapeHtml(loan.borrow_date)}</td>
        <td>${escapeHtml(loan.due_date)}</td>
        <td>${escapeHtml(loan.return_date || '—')}</td>
        <td class="${statusClass}">${escapeHtml(statusText)}</td>
        <td>${escapeHtml(fineDisplay)}</td>
        <td>${loan.return_date ? '—' : `<button type="button" class="buttons confirm-return-btn" data-loan-id="${Number(loan.loan_id)}" ${returnPending ? 'disabled' : ''}>Confirm return</button>`}</td>
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
            tbody.innerHTML = '<tr><td colspan="11" class="results-placeholder">No loans found.</td></tr>';
            return;
        }

        tbody.innerHTML = loans.map(renderLoanRow).join('');
    } catch (err) {
        console.error('Loans failed:', err);
    }
}

/** Confirm physical receipt before asking the backend to close a loan. */
async function confirmReturn(button) {
    if (returnPending || button.disabled) return;
    const cells = button.closest('tr').cells;
    const received = confirm(`Have you received copy ${cells[1].textContent} of "${cells[4].textContent}" from ${cells[2].textContent} (${cells[3].textContent})? Only confirm after checking the physical copy.`);
    if (!received) return;

    const result = document.getElementById('return-result');
    const filterButton = document.getElementById('loan-filter-btn');
    returnPending = true;
    filterButton.disabled = true;
    document.querySelectorAll('.confirm-return-btn').forEach((control) => { control.disabled = true; });
    result.textContent = 'Confirming return…';
    try {
        const res = await fetch(`${API}/loans/return.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ loan_id: Number(button.dataset.loanId) }),
        });
        if (res.status === 401) {
            window.location.assign('login.php');
            return;
        }
        const json = await res.json();
        result.textContent = json.message;
        if (json.success) {
            await Promise.all([loadLoans(), loadStats()]);
        }
    } catch (err) {
        console.error('Return confirmation failed:', err);
        result.textContent = 'Could not confirm the result. Refresh the loans before trying again; the return may already have been saved.';
    } finally {
        returnPending = false;
        filterButton.disabled = false;
        document.querySelectorAll('.confirm-return-btn').forEach((control) => { control.disabled = false; });
        if (!button.isConnected) filterButton.focus();
    }
}

// Delegate clicks because refreshing the table replaces its buttons.
document.getElementById('loans-tbody').addEventListener('click', (event) => {
    const button = event.target.closest('.confirm-return-btn');
    if (button) confirmReturn(button);
});

// Wire up the filter button
document.getElementById('loan-filter-btn').addEventListener('click', loadLoans);

// Initial load
loadStats();
loadLoans();
