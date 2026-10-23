/*
 * My Loans page: fetch active loans, render table, handle returns,
 * fetch fines (saved + accruing), render lists, handle payments via modal.
 */

const API = 'api';

/* ---------- element references ---------- */

const loansContainer = document.getElementById('loans-container');
const finesContainer = document.getElementById('fines-container');
const accruingContainer = document.getElementById('accruing-container');

const paymentModal = document.getElementById('payment-modal');
const payBookTitle = document.getElementById('pay-book-title');
const payAmount = document.getElementById('pay-amount');
const payChannel = document.getElementById('pay-channel');
const payCardNumber = document.getElementById('pay-card-number');
const cardNumberWrap = document.getElementById('card-number-wrap');
const payConfirm = document.getElementById('pay-confirm');
const payCancel = document.getElementById('pay-cancel');

let pendingFineId = null;

/* ---------- helpers ---------- */

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '\u2014';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatNaira(amount) {
    return '\u20a6' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 2 });
}

/* ---------- active loans ---------- */

async function loadLoans() {
    try {
        const res = await fetch(`${API}/loans/active.php`);
        const json = await res.json();

        if (!json.success) {
            loansContainer.innerHTML = `<p class="results-placeholder">${escapeHtml(json.message || 'Failed to load loans.')}</p>`;
            return;
        }

        renderLoans(json.data);
    } catch (err) {
        console.error('Load loans error:', err);
        loansContainer.innerHTML = '<p class="results-placeholder">Something went wrong. Please refresh.</p>';
    }
}

function renderLoans(loans) {
    if (!loans || loans.length === 0) {
        loansContainer.innerHTML = '<p class="results-placeholder">You have no active loans.</p>';
        return;
    }

    const rows = loans.map((loan) => {
        const isOverdue = loan.is_overdue === 1 || loan.is_overdue === true;
        const statusClass = isOverdue ? 'status-overdue' : 'status-on-time';
        const statusText = isOverdue ? 'Overdue' : 'On Time';
        const fineCell = isOverdue
            ? `<span class="fine-so-far">${escapeHtml(loan.fine_so_far)}</span>`
            : '\u2014';

        return `
            <tr>
                <td>${escapeHtml(loan.book_title)}</td>
                <td>${formatDate(loan.borrow_date)}</td>
                <td>${formatDate(loan.due_date)}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>${fineCell}</td>
                <td>
                    <button class="buttons return-btn" data-loan-id="${loan.loan_id}">Return</button>
                </td>
            </tr>`;
    }).join('');

    loansContainer.innerHTML = `
        <table class="loans-table">
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Borrowed</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Fine So Far</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>`;

    loansContainer.querySelectorAll('.return-btn').forEach((btn) => {
        btn.addEventListener('click', () => returnBook(Number(btn.dataset.loanId)));
    });
}

async function returnBook(loanId) {
    if (!confirm('Return this book?')) return;

    try {
        const res = await fetch(`${API}/loans/return.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ loan_id: loanId }),
        });
        const json = await res.json();

        alert(json.message);

        if (json.success) {
            loadLoans();
            loadFines();
        }
    } catch (err) {
        console.error('Return error:', err);
        alert('Something went wrong. Please try again.');
    }
}

/* ---------- fines (saved + accruing) ---------- */

async function loadFines() {
    try {
        const res = await fetch(`${API}/fines/list.php`);
        const json = await res.json();

        if (!json.success) {
            finesContainer.innerHTML = `<p class="results-placeholder">${escapeHtml(json.message || 'Failed to load fines.')}</p>`;
            accruingContainer.innerHTML = '<p class="results-placeholder">\u2014</p>';
            return;
        }

        renderFines(json.data.fines);
        renderAccruing(json.data.accruing);
    } catch (err) {
        console.error('Load fines error:', err);
        finesContainer.innerHTML = '<p class="results-placeholder">Something went wrong. Please refresh.</p>';
        accruingContainer.innerHTML = '<p class="results-placeholder">\u2014</p>';
    }
}

function renderFines(fines) {
    if (!fines || fines.length === 0) {
        finesContainer.innerHTML = '<p class="results-placeholder">No saved fines.</p>';
        return;
    }

    const items = fines.map((fine) => {
        const isPaid = fine.status === 'paid';
        const statusClass = isPaid ? 'status-on-time' : 'status-overdue';
        const statusText = isPaid ? 'Paid' : 'Unpaid';

        const receiptLink = fine.payment_reference
            ? `<a href="payments/receipt.php?reference=${encodeURIComponent(fine.payment_reference)}">View Receipt</a>` : '';
        const actionCell = isPaid
            ? `<span class="paid-label">Paid on ${formatDate(fine.paid_at)}</span> ${receiptLink}`
            : `<button class="buttons pay-btn" data-fine-id="${fine.fine_id}" data-amount="${fine.amount}">Pay</button>`;

        return `
            <div class="fine-item">
                <div class="fine-info">
                    <span class="fine-book">${escapeHtml(fine.book_title)}</span>
                    <span class="fine-amount">${formatNaira(fine.amount)}</span>
                </div>
                <div class="fine-action">
                    <span class="status-badge ${statusClass}">${statusText}</span>
                    ${actionCell}
                </div>
            </div>`;
    }).join('');

    finesContainer.innerHTML = items;

    finesContainer.querySelectorAll('.pay-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            openPaymentModal(
                Number(btn.dataset.fineId),
                btn.closest('.fine-item').querySelector('.fine-book').textContent,
                Number(btn.dataset.amount)
            );
        });
    });
}

function renderAccruing(accruing) {
    if (!accruing || accruing.length === 0) {
        accruingContainer.innerHTML = '<p class="results-placeholder">No accruing fines. All books are within their due dates.</p>';
        return;
    }

    const items = accruing.map((item) => {
        return `
            <div class="fine-item accruing-item">
                <div class="fine-info">
                    <span class="fine-book">${escapeHtml(item.book_title)}</span>
                    <span class="fine-amount">
                        ${item.days_overdue} day(s) overdue \u2014 ${escapeHtml(item.fine_so_far)} so far
                    </span>
                </div>
                <div class="fine-action">
                    <span class="status-badge status-overdue">Accruing</span>
                    <span class="accruing-hint">Return the book to stop the fine</span>
                </div>
            </div>`;
    }).join('');

    accruingContainer.innerHTML = items;
}

/* ---------- payment modal ---------- */

function openPaymentModal(fineId, bookTitle, amount) {
    pendingFineId = fineId;
    payBookTitle.textContent = bookTitle;
    payAmount.textContent = formatNaira(amount);
    payCardNumber.value = '';
    payChannel.value = 'card';
    cardNumberWrap.style.display = 'block';
    paymentModal.style.display = 'flex';
}

function closePaymentModal() {
    paymentModal.style.display = 'none';
    pendingFineId = null;
}

async function confirmPayment() {
    if (pendingFineId === null) return;

    const channel = payChannel.value;
    const cardNumber = channel === 'card' ? payCardNumber.value.trim() : null;

    if (channel === 'card' && !cardNumber) {
        alert('Please enter a card number.');
        return;
    }

    const body = { fine_id: pendingFineId, channel };
    if (cardNumber) body.card_number = cardNumber;

    payConfirm.disabled = true;
    payCancel.disabled = true;
    try {
        const res = await fetch(`${API}/payments/pay.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const json = await res.json();

        closePaymentModal();
        const result = document.getElementById('payment-result');
        result.textContent = json.message + ' ';
        if (json.data?.reference) {
            const link = document.createElement('a');
            link.href = 'payments/receipt.php?reference=' + encodeURIComponent(json.data.reference);
            link.textContent = json.success ? 'View Receipt' : 'View Failed Attempt';
            result.appendChild(link);
        }
        if (json.success) loadFines();
    } catch (err) {
        console.error('Payment error:', err);
        closePaymentModal();
        alert('Something went wrong. Please try again.');
    } finally {
        payConfirm.disabled = false;
        payCancel.disabled = false;
        payCardNumber.value = '';
    }
}

/* ---------- events ---------- */

payChannel.addEventListener('change', () => {
    cardNumberWrap.style.display = payChannel.value === 'card' ? 'block' : 'none';
});

payConfirm.addEventListener('click', confirmPayment);
payCancel.addEventListener('click', closePaymentModal);

/* ---------- init ---------- */

loadLoans();
loadFines();
