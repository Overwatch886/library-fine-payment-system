/* A receipt describes one payment attempt; a failed attempt is not proof of payment. */
const API = '../api';

function addDetail(list, label, value) {
    const term = document.createElement('dt');
    term.textContent = label;
    const detail = document.createElement('dd');
    detail.textContent = value ?? '—';
    list.append(term, detail);
}

async function loadReceipt() {
    const container = document.getElementById('receipt-container');
    const reference = new URLSearchParams(window.location.search).get('reference');
    if (!reference) {
        container.textContent = 'No receipt reference provided. Open a receipt from My Loans.';
        return;
    }

    try {
        const res = await fetch(`${API}/payments/receipt.php?reference=${encodeURIComponent(reference)}`);
        if (res.status === 401) {
            window.location.assign('../login.php');
            return;
        }
        const json = await res.json();
        if (!json.success) {
            container.textContent = json.message || 'Receipt not found.';
            return;
        }

        const receipt = json.data;
        const successful = receipt.status === 'successful';
        const notice = document.createElement('p');
        notice.className = successful ? 'status-on-time receipt-notice' : 'status-overdue receipt-notice';
        notice.textContent = successful ? 'Payment successful — this fine was paid.'
            : 'Payment declined — this attempt did not pay the fine.';
        const list = document.createElement('dl');
        list.className = 'receipt-details';
        addDetail(list, 'Reference', receipt.reference);
        addDetail(list, 'Student', receipt.student_name);
        addDetail(list, 'Matric number', receipt.matric_number);
        addDetail(list, 'Book', receipt.book_title);
        addDetail(list, 'Loan ID', receipt.loan_id);
        addDetail(list, 'Amount', '₦' + Number(receipt.amount).toLocaleString('en-NG', { minimumFractionDigits: 2 }));
        const channels = { card: 'Card', bank_transfer: 'Bank Transfer', ussd: 'USSD' };
        addDetail(list, 'Payment channel', channels[receipt.payment_channel] || receipt.payment_channel);
        addDetail(list, 'Payment time (library time)', receipt.time_paid);
        addDetail(list, 'Borrowed', receipt.borrow_date);
        addDetail(list, 'Due', receipt.due_date);
        addDetail(list, 'Returned', receipt.return_date);
        addDetail(list, 'Days late', receipt.days_late);
        addDetail(list, 'Payment status', successful ? 'Successful' : 'Failed');
        addDetail(list, 'Current fine status', receipt.fine_status === 'paid' ? 'Paid' : 'Unpaid');
        if (!successful) addDetail(list, 'Reason', receipt.failure_reason);
        container.replaceChildren(notice, list);
        document.getElementById('print-receipt').hidden = false;
    } catch (err) {
        console.error('Receipt failed:', err);
        container.textContent = 'Something went wrong. Please try again.';
    }
}

document.getElementById('print-receipt').addEventListener('click', () => window.print());
loadReceipt();
